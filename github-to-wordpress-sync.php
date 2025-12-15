<?php
/**
 * Plugin Name: Github to WordPress Sync
 * Plugin URI: https://sinanisler.com
 * Description: Sync WordPress themes and plugins directly from GitHub repositories. Easy development workflow from GitHub to WordPress.
 * Version: 1.0.0
 * Author: Sinan Isler
 * Author URI: https://sinanisler.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: snn
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GTWS_VERSION', '1.0.0');
define('GTWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GTWS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GTWS_PLUGIN_FILE', __FILE__);

/**
 * Main Plugin Class
 */
class Github_To_WordPress_Sync {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Register activation/deactivation hooks
        register_activation_hook(GTWS_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(GTWS_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Initialize admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        }
        
        // AJAX handlers
        add_action('wp_ajax_gtws_add_project', array($this, 'ajax_add_project'));
        add_action('wp_ajax_gtws_delete_project', array($this, 'ajax_delete_project'));
        add_action('wp_ajax_gtws_check_updates', array($this, 'ajax_check_updates'));
        add_action('wp_ajax_gtws_sync_project', array($this, 'ajax_sync_project'));
        add_action('wp_ajax_gtws_get_branches', array($this, 'ajax_get_branches'));
    }
    
    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once GTWS_PLUGIN_DIR . 'includes/class-github-api.php';
        require_once GTWS_PLUGIN_DIR . 'includes/class-sync-manager.php';
    }
    
    /**
     * Activate plugin
     */
    public function activate() {
        // Create options for storing projects
        if (!get_option('gtws_projects')) {
            add_option('gtws_projects', array());
        }
        
        // Set default options
        add_option('gtws_version', GTWS_VERSION);
    }
    
    /**
     * Deactivate plugin
     */
    public function deactivate() {
        // Clean up if needed
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Github to WordPress Sync', 'github-to-wordpress-sync'),
            __('Github Sync', 'github-to-wordpress-sync'),
            'manage_options',
            'github-to-wordpress-sync',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if ($hook !== 'settings_page_github-to-wordpress-sync') {
            return;
        }
        
        wp_enqueue_style(
            'gtws-admin-style',
            GTWS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GTWS_VERSION
        );
        
        wp_enqueue_script(
            'gtws-admin-script',
            GTWS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            GTWS_VERSION,
            true
        );
        
        wp_localize_script('gtws-admin-script', 'gtws_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gtws_nonce'),
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        require_once GTWS_PLUGIN_DIR . 'includes/admin-page.php';
    }
    
    /**
     * AJAX: Add new project
     */
    public function ajax_add_project() {
        check_ajax_referer('gtws_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $github_url = sanitize_text_field($_POST['github_url']);
        $project_type = sanitize_text_field($_POST['project_type']);
        $project_name = sanitize_text_field($_POST['project_name']);
        $branch = sanitize_text_field($_POST['branch']);
        
        // Validate GitHub URL
        if (!$this->validate_github_url($github_url)) {
            wp_send_json_error('Invalid GitHub URL');
        }
        
        // Add .git if not present
        $repo_url = rtrim($github_url, '/');
        if (!str_ends_with($repo_url, '.git')) {
            $repo_url .= '.git';
        }
        
        // Get existing projects
        $projects = get_option('gtws_projects', array());
        
        // Create new project
        $project = array(
            'id' => uniqid('gtws_'),
            'github_url' => $repo_url,
            'display_url' => $github_url,
            'project_type' => $project_type,
            'project_name' => $project_name,
            'branch' => $branch,
            'last_sync' => null,
            'last_commit' => null,
            'created_at' => current_time('mysql'),
        );
        
        // Check for latest commit
        $github_api = new GTWS_Github_API();
        $latest_commit = $github_api->get_latest_commit($repo_url, $branch);
        
        if ($latest_commit) {
            $project['last_commit'] = $latest_commit['sha'];
            $project['commit_message'] = $latest_commit['message'];
            $project['commit_date'] = $latest_commit['date'];
        }
        
        $projects[] = $project;
        update_option('gtws_projects', $projects);
        
        wp_send_json_success(array(
            'message' => 'Project added successfully',
            'project' => $project
        ));
    }
    
    /**
     * AJAX: Delete project
     */
    public function ajax_delete_project() {
        check_ajax_referer('gtws_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $project_id = sanitize_text_field($_POST['project_id']);
        
        $projects = get_option('gtws_projects', array());
        $projects = array_filter($projects, function($project) use ($project_id) {
            return $project['id'] !== $project_id;
        });
        
        update_option('gtws_projects', array_values($projects));
        
        wp_send_json_success('Project deleted successfully');
    }
    
    /**
     * AJAX: Check for updates
     */
    public function ajax_check_updates() {
        check_ajax_referer('gtws_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $project_id = sanitize_text_field($_POST['project_id']);
        
        $projects = get_option('gtws_projects', array());
        $project_index = array_search($project_id, array_column($projects, 'id'));
        
        if ($project_index === false) {
            wp_send_json_error('Project not found');
        }
        
        $project = $projects[$project_index];
        
        // Get latest commit from GitHub
        $github_api = new GTWS_Github_API();
        $latest_commit = $github_api->get_latest_commit($project['github_url'], $project['branch']);
        
        if (!$latest_commit) {
            wp_send_json_error('Failed to fetch commit information');
        }
        
        // Update project with latest commit info
        $projects[$project_index]['last_commit'] = $latest_commit['sha'];
        $projects[$project_index]['commit_message'] = $latest_commit['message'];
        $projects[$project_index]['commit_date'] = $latest_commit['date'];
        update_option('gtws_projects', $projects);
        
        // Check if update is available
        $has_update = (!isset($project['last_sync_commit']) || 
                       $project['last_sync_commit'] !== $latest_commit['sha']);
        
        wp_send_json_success(array(
            'has_update' => $has_update,
            'commit' => $latest_commit,
            'message' => $has_update ? 'Update available!' : 'Already up to date!'
        ));
    }
    
    /**
     * AJAX: Sync project
     */
    public function ajax_sync_project() {
        check_ajax_referer('gtws_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $project_id = sanitize_text_field($_POST['project_id']);
        
        $projects = get_option('gtws_projects', array());
        $project_index = array_search($project_id, array_column($projects, 'id'));
        
        if ($project_index === false) {
            wp_send_json_error('Project not found');
        }
        
        $project = $projects[$project_index];
        
        // Perform sync
        $sync_manager = new GTWS_Sync_Manager();
        $result = $sync_manager->sync_project($project);
        
        if ($result['success']) {
            $projects[$project_index]['last_sync'] = current_time('mysql');
            $projects[$project_index]['last_sync_commit'] = $project['last_commit'];
            update_option('gtws_projects', $projects);
            
            wp_send_json_success(array(
                'message' => 'Sync completed successfully!',
                'details' => $result
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: Get branches
     */
    public function ajax_get_branches() {
        check_ajax_referer('gtws_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $github_url = sanitize_text_field($_POST['github_url']);
        
        if (!$this->validate_github_url($github_url)) {
            wp_send_json_error('Invalid GitHub URL');
        }
        
        $repo_url = rtrim($github_url, '/');
        if (!str_ends_with($repo_url, '.git')) {
            $repo_url .= '.git';
        }
        
        $github_api = new GTWS_Github_API();
        $branches = $github_api->get_branches($repo_url);
        
        if ($branches) {
            wp_send_json_success($branches);
        } else {
            wp_send_json_error('Failed to fetch branches');
        }
    }
    
    /**
     * Validate GitHub URL
     */
    private function validate_github_url($url) {
        return preg_match('/^https?:\/\/github\.com\/[\w\-]+\/[\w\-\.]+\/?$/', $url);
    }
}

// Initialize plugin
Github_To_WordPress_Sync::get_instance();
