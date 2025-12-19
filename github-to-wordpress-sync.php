<?php
/**     
 * Plugin Name: Github to WordPress Sync
 * Plugin URI: https://github.com/sinanisler/github-to-wordpress-sync
 * Description: GitHub to WordPress Sync: Streamline theme & plugin updates directly from GitHub. Easy, secure, and developer-friendly.
 * Version: 0.7
 * Author: sinanisler
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
define('GTWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GTWS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GTWS_PLUGIN_FILE', __FILE__);

/**
 * GitHub API Integration Class
 */
class GTWS_Github_API {
    
    /**
     * Get repository owner and name from URL
     */
    private function parse_github_url($url) {
        // Remove .git extension if present
        $url = str_replace('.git', '', $url);
        $url = rtrim($url, '/');
        
        // Extract owner and repo name
        if (preg_match('/github\.com\/([^\/]+)\/([^\/]+)/', $url, $matches)) {
            return array(
                'owner' => $matches[1],
                'repo' => $matches[2]
            );
        }
        
        return false;
    }
    
    /**
     * Get latest commit from a branch
     */
    public function get_latest_commit($repo_url, $branch = 'main') {
        $repo_info = $this->parse_github_url($repo_url);
        
        if (!$repo_info) {
            return false;
        }
        
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/commits/%s',
            $repo_info['owner'],
            $repo_info['repo'],
            $branch
        );
        
        $response = $this->make_api_request($api_url);
        
        if (!$response) {
            return false;
        }
        
        return array(
            'sha' => $response['sha'],
            'message' => isset($response['commit']['message']) ? $response['commit']['message'] : '',
            'date' => isset($response['commit']['committer']['date']) ? $response['commit']['committer']['date'] : '',
            'author' => isset($response['commit']['author']['name']) ? $response['commit']['author']['name'] : '',
        );
    }
    
    /**
     * Get all branches for a repository
     */
    public function get_branches($repo_url) {
        $repo_info = $this->parse_github_url($repo_url);
        
        if (!$repo_info) {
            return false;
        }
        
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/branches',
            $repo_info['owner'],
            $repo_info['repo']
        );
        
        $response = $this->make_api_request($api_url);
        
        if (!$response || !is_array($response)) {
            return false;
        }
        
        $branches = array();
        foreach ($response as $branch) {
            if (isset($branch['name'])) {
                $branches[] = $branch['name'];
            }
        }
        
        return $branches;
    }
    
    /**
     * Download repository as ZIP
     */
    public function download_repository($repo_url, $branch = 'main') {
        $repo_info = $this->parse_github_url($repo_url);
        
        if (!$repo_info) {
            return false;
        }
        
        // GitHub archive URL
        $download_url = sprintf(
            'https://github.com/%s/%s/archive/refs/heads/%s.zip',
            $repo_info['owner'],
            $repo_info['repo'],
            $branch
        );
        
        // Create temporary file
        $temp_file = wp_tempnam('gtws-repo-');
        
        // Download the file
        $response = wp_remote_get($download_url, array(
            'timeout' => 300,
            'stream' => true,
            'filename' => $temp_file
        ));
        
        if (is_wp_error($response)) {
            @unlink($temp_file);
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            @unlink($temp_file);
            return false;
        }
        
        return $temp_file;
    }
    
    /**
     * Get repository info
     */
    public function get_repository_info($repo_url) {
        $repo_info = $this->parse_github_url($repo_url);
        
        if (!$repo_info) {
            return false;
        }
        
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s',
            $repo_info['owner'],
            $repo_info['repo']
        );
        
        $response = $this->make_api_request($api_url);
        
        if (!$response) {
            return false;
        }
        
        return array(
            'name' => isset($response['name']) ? $response['name'] : '',
            'description' => isset($response['description']) ? $response['description'] : '',
            'default_branch' => isset($response['default_branch']) ? $response['default_branch'] : 'main',
            'updated_at' => isset($response['updated_at']) ? $response['updated_at'] : '',
            'private' => isset($response['private']) ? $response['private'] : false,
        );
    }
    
    /**
     * Make API request to GitHub
     */
    private function make_api_request($url) {
        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress-Github-Sync-Plugin'
            )
        );
        
        // Add GitHub token if available (for private repos or higher rate limits)
        $github_token = get_option('gtws_github_token');
        if ($github_token) {
            $args['headers']['Authorization'] = 'token ' . $github_token;
        }
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data;
    }
    
    /**
     * Get commits between two commits
     */
    public function get_commits_between($repo_url, $branch, $since_commit = null) {
        $repo_info = $this->parse_github_url($repo_url);

        if (!$repo_info) {
            return false;
        }

        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/commits?sha=%s',
            $repo_info['owner'],
            $repo_info['repo'],
            $branch
        );

        if ($since_commit) {
            $api_url .= '&since=' . urlencode($since_commit);
        }

        $response = $this->make_api_request($api_url);

        if (!$response || !is_array($response)) {
            return false;
        }

        $commits = array();
        foreach ($response as $commit) {
            if (isset($commit['sha'])) {
                $commits[] = array(
                    'sha' => $commit['sha'],
                    'message' => isset($commit['commit']['message']) ? $commit['commit']['message'] : '',
                    'date' => isset($commit['commit']['committer']['date']) ? $commit['commit']['committer']['date'] : '',
                    'author' => isset($commit['commit']['author']['name']) ? $commit['commit']['author']['name'] : '',
                );
            }
        }

        return $commits;
    }

    /**
     * Get commit history for a branch (limited to recent commits)
     */
    public function get_commit_history($repo_url, $branch = 'main', $per_page = 20) {
        $repo_info = $this->parse_github_url($repo_url);

        if (!$repo_info) {
            return false;
        }

        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/commits?sha=%s&per_page=%d',
            $repo_info['owner'],
            $repo_info['repo'],
            $branch,
            $per_page
        );

        $response = $this->make_api_request($api_url);

        if (!$response || !is_array($response)) {
            return false;
        }

        $commits = array();
        foreach ($response as $commit) {
            if (isset($commit['sha'])) {
                $commits[] = array(
                    'sha' => $commit['sha'],
                    'message' => isset($commit['commit']['message']) ? $commit['commit']['message'] : '',
                    'date' => isset($commit['commit']['committer']['date']) ? $commit['commit']['committer']['date'] : '',
                    'author' => isset($commit['commit']['author']['name']) ? $commit['commit']['author']['name'] : '',
                );
            }
        }

        return $commits;
    }

    /**
     * Download repository at specific commit
     */
    public function download_repository_at_commit($repo_url, $commit_sha) {
        $repo_info = $this->parse_github_url($repo_url);

        if (!$repo_info) {
            return false;
        }

        // GitHub archive URL for specific commit
        $download_url = sprintf(
            'https://github.com/%s/%s/archive/%s.zip',
            $repo_info['owner'],
            $repo_info['repo'],
            $commit_sha
        );

        // Create temporary file
        $temp_file = wp_tempnam('gtws-repo-');

        // Download the file
        $response = wp_remote_get($download_url, array(
            'timeout' => 300,
            'stream' => true,
            'filename' => $temp_file
        ));

        if (is_wp_error($response)) {
            @unlink($temp_file);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            @unlink($temp_file);
            return false;
        }

        return $temp_file;
    }
}

/**
 * Sync Manager Class
 * Handles downloading and syncing GitHub repositories to WordPress
 */
class GTWS_Sync_Manager {
    
    private $github_api;
    
    public function __construct() {
        $this->github_api = new GTWS_Github_API();
    }
    
    /**
     * Sync a project from GitHub (to latest commit or specific commit)
     */
    public function sync_project($project, $commit_sha = null) {
        try {
            // Download repository
            if ($commit_sha) {
                $zip_file = $this->github_api->download_repository_at_commit(
                    $project['github_url'],
                    $commit_sha
                );
            } else {
                $zip_file = $this->github_api->download_repository(
                    $project['github_url'],
                    $project['branch']
                );
            }

            if (!$zip_file) {
                return array(
                    'success' => false,
                    'message' => 'Failed to download repository from GitHub'
                );
            }

            // Determine target directory
            $target_dir = $this->get_target_directory($project['project_type'], $project['project_name']);

            if (!$target_dir) {
                @unlink($zip_file);
                return array(
                    'success' => false,
                    'message' => 'Invalid project type'
                );
            }

            // Extract and sync
            $result = $this->extract_and_sync($zip_file, $target_dir, $project, $commit_sha);

            // Clean up temp file
            @unlink($zip_file);

            return $result;

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Get target directory based on project type
     */
    private function get_target_directory($type, $name) {
        switch ($type) {
            case 'theme':
                return WP_CONTENT_DIR . '/themes/' . $name;
            case 'plugin':
                return WP_CONTENT_DIR . '/plugins/' . $name;
            default:
                return false;
        }
    }
    
    /**
     * Extract ZIP and sync to target directory
     */
    private function extract_and_sync($zip_file, $target_dir, $project, $commit_sha = null) {
        // Load WordPress filesystem
        WP_Filesystem();
        global $wp_filesystem;

        // Create temporary extraction directory
        $temp_dir = wp_tempnam('gtws-extract-');
        @unlink($temp_dir);
        wp_mkdir_p($temp_dir);

        // Extract ZIP file
        $unzip_result = unzip_file($zip_file, $temp_dir);

        if (is_wp_error($unzip_result)) {
            $this->cleanup_directory($temp_dir);
            return array(
                'success' => false,
                'message' => 'Failed to extract ZIP file: ' . $unzip_result->get_error_message()
            );
        }

        // Find the extracted folder (GitHub creates a folder with repo-branch name or repo-commit)
        $extracted_folders = glob($temp_dir . '/*', GLOB_ONLYDIR);

        if (empty($extracted_folders)) {
            $this->cleanup_directory($temp_dir);
            return array(
                'success' => false,
                'message' => 'No folder found in extracted archive'
            );
        }

        $source_dir = $extracted_folders[0];

        // Remove existing directory completely (no backup - we have Git history!)
        if (file_exists($target_dir)) {
            $this->cleanup_directory($target_dir);
        }

        // Create target directory
        wp_mkdir_p($target_dir);

        // Sync files from source to target
        $sync_result = $this->sync_directories($source_dir, $target_dir);

        // Clean up temp directory
        $this->cleanup_directory($temp_dir);

        if ($sync_result) {
            return array(
                'success' => true,
                'message' => 'Project synced successfully!',
                'target_dir' => $target_dir,
                'commit_sha' => $commit_sha
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to sync files'
            );
        }
    }
    
    /**
     * Sync files from source to target directory
     */
    private function sync_directories($source, $target) {
        if (!is_dir($source)) {
            return false;
        }
        
        // Create target directory if it doesn't exist
        if (!file_exists($target)) {
            wp_mkdir_p($target);
        }
        
        // Get all files and directories
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($items as $item) {
            $target_path = $target . DIRECTORY_SEPARATOR . $items->getSubPathName();
            
            if ($item->isDir()) {
                // Create directory
                if (!file_exists($target_path)) {
                    wp_mkdir_p($target_path);
                }
            } else {
                // Copy file
                if (!copy($item, $target_path)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get sync history for a project (stored in project data)
     */
    public function get_sync_history($project_id) {
        $projects = get_option('gtws_projects', array());
        $project_index = array_search($project_id, array_column($projects, 'id'));

        if ($project_index === false) {
            return false;
        }

        $project = $projects[$project_index];

        // Get sync history from project data
        return isset($project['sync_history']) ? $project['sync_history'] : array();
    }

    /**
     * Add sync record to project history
     */
    public function add_sync_record($project_id, $commit_sha, $commit_message, $commit_date) {
        $projects = get_option('gtws_projects', array());
        $project_index = array_search($project_id, array_column($projects, 'id'));

        if ($project_index === false) {
            return false;
        }

        // Initialize sync history if not exists
        if (!isset($projects[$project_index]['sync_history'])) {
            $projects[$project_index]['sync_history'] = array();
        }

        // Add new sync record at the beginning (most recent first)
        array_unshift($projects[$project_index]['sync_history'], array(
            'commit_sha' => $commit_sha,
            'commit_message' => $commit_message,
            'commit_date' => $commit_date,
            'synced_at' => current_time('mysql')
        ));

        // Keep only last 20 sync records
        $projects[$project_index]['sync_history'] = array_slice(
            $projects[$project_index]['sync_history'],
            0,
            20
        );

        update_option('gtws_projects', $projects);

        return true;
    }
    
    /**
     * Clean up directory recursively
     */
    private function cleanup_directory($dir) {
        if (!file_exists($dir)) {
            return;
        }
        
        if (is_dir($dir)) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($items as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getRealPath());
                } else {
                    @unlink($item->getRealPath());
                }
            }
            
            @rmdir($dir);
        }
    }
    
    /**
     * Get list of files in directory
     */
    public function get_directory_files($dir) {
        if (!is_dir($dir)) {
            return array();
        }
        
        $files = array();
        
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($items as $item) {
            $files[] = $items->getSubPathName();
        }
        
        return $files;
    }
    
    /**
     * Check if directory exists and is writable
     */
    public function check_directory_permissions($type, $name) {
        $dir = $this->get_target_directory($type, $name);
        
        if (!$dir) {
            return array(
                'exists' => false,
                'writable' => false,
                'path' => ''
            );
        }
        
        $parent_dir = dirname($dir);
        
        return array(
            'exists' => file_exists($dir),
            'writable' => is_writable($parent_dir) && (!file_exists($dir) || is_writable($dir)),
            'path' => $dir
        );
    }
    
}

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
        add_action('wp_ajax_gtws_get_commit_history', array($this, 'ajax_get_commit_history'));
        add_action('wp_ajax_gtws_restore_commit', array($this, 'ajax_restore_commit'));
    }
    
    /**
     * Activate plugin
     */
    public function activate() {
        // Create options for storing projects
        if (!get_option('gtws_projects')) {
            add_option('gtws_projects', array());
        }
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

        // Get version from plugin header
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_data = get_plugin_data(GTWS_PLUGIN_FILE);
        $version = $plugin_data['Version'];

        wp_enqueue_style(
            'gtws-admin-style',
            GTWS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $version
        );

        wp_enqueue_script(
            'gtws-admin-script',
            GTWS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            $version,
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
        $projects = get_option('gtws_projects', array());
        ?>
        <div class="wrap gtws-admin-wrap">
            <h1>
                <span class="dashicons dashicons-update"></span>
                <?php _e('Github to WordPress Sync', 'snn'); ?>
            </h1>
            
            <div class="gtws-container">
                <!-- Add New Project Section -->
                <div class="gtws-card gtws-collapsible-card">
                    <h2 class="gtws-card-header" data-toggle="collapse">
                        <span class="gtws-toggle-icon dashicons dashicons-arrow-right"></span>
                        <?php _e('Add New Project', 'snn'); ?>
                    </h2>
                    <div class="gtws-card-content" style="display: none;">
                    <form id="gtws-add-project-form" class="gtws-form">
                        <div class="gtws-form-group">
                            <label for="github_url">
                                <?php _e('GitHub Repository URL', 'snn'); ?>
                                <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="github_url" 
                                name="github_url" 
                                placeholder="https://github.com/username/repository-name"
                                required
                            >
                            <p class="description">
                                <?php _e('Enter the GitHub repository URL (without .git extension)', 'snn'); ?>
                            </p>
                        </div>
                        
                        <div class="gtws-form-row">
                            <div class="gtws-form-group">
                                <label for="project_type">
                                    <?php _e('Project Type', 'snn'); ?>
                                    <span class="required">*</span>
                                </label>
                                <select id="project_type" name="project_type" required>
                                    <option value=""><?php _e('Select Type', 'snn'); ?></option>
                                    <option value="theme"><?php _e('Theme', 'snn'); ?></option>
                                    <option value="plugin"><?php _e('Plugin', 'snn'); ?></option>
                                </select>
                            </div>
                            
                            <div class="gtws-form-group">
                                <label for="project_name">
                                    <?php _e('Project Name (Folder Name)', 'snn'); ?>
                                    <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="project_name" 
                                    name="project_name" 
                                    placeholder="my-theme or my-plugin"
                                    required
                                >
                                <p class="description">
                                    <?php _e('The folder name in wp-content/themes or wp-content/plugins', 'snn'); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="gtws-form-group">
                            <label for="branch">
                                <?php _e('Branch', 'snn'); ?>
                                <span class="required">*</span>
                            </label>
                            <div class="gtws-branch-selector">
                                <input 
                                    type="text" 
                                    id="branch" 
                                    name="branch" 
                                    value="main"
                                    required
                                >
                                <button type="button" id="fetch-branches" class="button">
                                    <?php _e('Fetch Branches', 'snn'); ?>
                                </button>
                            </div>
                            <div id="branch-list" class="gtws-branch-list"></div>
                        </div>
                        
                        <button type="submit" class="button button-primary button-large">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php _e('Add Project', 'snn'); ?>
                        </button>
                    </form>
                    </div>
                </div>
                
                <!-- Projects List -->
                <div class="gtws-card">
                    <h2><?php _e('Synced Projects', 'snn'); ?></h2>
                    
                    <?php if (empty($projects)): ?>
                        <div class="gtws-empty-state">
                            <span class="dashicons dashicons-admin-plugins"></span>
                            <p><?php _e('No projects added yet. Add your first project above!', 'snn'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="gtws-projects-list">
                            <?php foreach ($projects as $project): ?>
                                <div class="gtws-project-item" data-project-id="<?php echo esc_attr($project['id']); ?>">
                                    <div class="gtws-project-header">
                                        <div class="gtws-project-info">
                                            <h3>
                                                <span class="gtws-project-type gtws-type-<?php echo esc_attr($project['project_type']); ?>">
                                                    <?php echo esc_html(ucfirst($project['project_type'])); ?>
                                                </span>
                                                <?php echo esc_html($project['project_name']); ?>
                                            </h3>
                                            <p class="gtws-project-url">
                                                <span class="dashicons dashicons-admin-links"></span>
                                                <a href="<?php echo esc_url($project['display_url']); ?>" target="_blank">
                                                    <?php echo esc_html($project['display_url']); ?>
                                                </a>
                                            </p>
                                        </div>
                                        
                                        <div class="gtws-project-actions">
                                            <button
                                                class="button gtws-view-history"
                                                data-project-id="<?php echo esc_attr($project['id']); ?>"
                                            >
                                                <span class="dashicons dashicons-backup"></span>
                                                <?php _e('History', 'snn'); ?>
                                            </button>

                                            <button
                                                class="button gtws-check-update"
                                                data-project-id="<?php echo esc_attr($project['id']); ?>"
                                            >
                                                <span class="dashicons dashicons-update"></span>
                                                <?php _e('Check Update', 'snn'); ?>
                                            </button>

                                            <button
                                                class="button button-primary gtws-sync-project"
                                                data-project-id="<?php echo esc_attr($project['id']); ?>"
                                            >
                                                <span class="dashicons dashicons-download"></span>
                                                <?php _e('Sync Now', 'snn'); ?>
                                            </button>

                                            <button
                                                class="button button-link-delete gtws-delete-project"
                                                data-project-id="<?php echo esc_attr($project['id']); ?>"
                                            >
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="gtws-project-details">
                                        <div class="gtws-detail-item">
                                            <strong><?php _e('Branch:', 'snn'); ?></strong>
                                            <span class="gtws-branch-badge"><?php echo esc_html($project['branch']); ?></span>
                                        </div>
                                        
                                        <?php if (!empty($project['last_commit'])): ?>
                                            <div class="gtws-detail-item">
                                                <strong><?php _e('Latest Commit:', 'snn'); ?></strong>
                                                <code><?php echo esc_html(substr($project['last_commit'], 0, 7)); ?></code>
                                                <?php if (!empty($project['commit_message'])): ?>
                                                    <span class="gtws-commit-message">
                                                        - <?php echo esc_html($project['commit_message']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($project['commit_date'])): ?>
                                            <div class="gtws-detail-item">
                                                <strong><?php _e('Commit Date:', 'snn'); ?></strong>
                                                <?php echo esc_html(wp_date('F j, Y g:i a', strtotime($project['commit_date']))); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($project['last_sync'])): ?>
                                            <div class="gtws-detail-item">
                                                <strong><?php _e('Last Sync:', 'snn'); ?></strong>
                                                <?php echo esc_html(wp_date('F j, Y g:i a', strtotime($project['last_sync']))); ?>
                                                <?php if (!empty($project['last_sync_commit'])): ?>
                                                    <code><?php echo esc_html(substr($project['last_sync_commit'], 0, 7)); ?></code>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="gtws-project-status"></div>

                                    <!-- Commit History Section (Hidden by default) -->
                                    <div class="gtws-commit-history" style="display: none;">
                                        <h4><?php _e('Commit History', 'snn'); ?></h4>
                                        <div class="gtws-history-timeline">
                                            <!-- Timeline will be populated via JavaScript -->
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Info Section -->
                <div class="gtws-card gtws-info-card gtws-collapsible-card">
                    <h3 class="gtws-card-header" data-toggle="collapse">
                        <span class="gtws-toggle-icon dashicons dashicons-arrow-right"></span>
                        <?php _e('How to Use', 'snn'); ?>
                    </h3>
                    <div class="gtws-card-content" style="display: none;">
                    <ol>
                        <li><?php _e('Add your GitHub repository URL (e.g., https://github.com/username/repo)', 'snn'); ?></li>
                        <li><?php _e('Select whether it\'s a theme or plugin', 'snn'); ?></li>
                        <li><?php _e('Enter the exact folder name as it should appear in wp-content/themes or wp-content/plugins', 'snn'); ?></li>
                        <li><?php _e('Choose the branch you want to sync (usually "main" or "master")', 'snn'); ?></li>
                        <li><?php _e('Click "Add Project" to save', 'snn'); ?></li>
                        <li><?php _e('Use "Check Update" to see if there are new commits', 'snn'); ?></li>
                        <li><?php _e('Click "Sync Now" to download and update your theme/plugin', 'snn'); ?></li>
                    </ol>
                    
                    <div class="gtws-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <strong><?php _e('Important:', 'snn'); ?></strong>
                        <?php _e('Syncing will overwrite local changes. Make sure to commit your work to GitHub first!', 'snn'); ?>
                    </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
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

            // Add to sync history
            $sync_manager->add_sync_record(
                $project_id,
                $project['last_commit'],
                $project['commit_message'],
                $project['commit_date']
            );

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
     * AJAX: Get commit history
     */
    public function ajax_get_commit_history() {
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

        // Get commit history from GitHub
        $github_api = new GTWS_Github_API();
        $commit_history = $github_api->get_commit_history($project['github_url'], $project['branch'], 50);

        if ($commit_history === false) {
            wp_send_json_error('Failed to fetch commit history');
        }

        // Get sync history
        $sync_manager = new GTWS_Sync_Manager();
        $sync_history = $sync_manager->get_sync_history($project_id);

        wp_send_json_success(array(
            'commit_history' => $commit_history,
            'sync_history' => $sync_history,
            'current_commit' => isset($project['last_sync_commit']) ? $project['last_sync_commit'] : null
        ));
    }

    /**
     * AJAX: Restore to specific commit
     */
    public function ajax_restore_commit() {
        check_ajax_referer('gtws_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $project_id = sanitize_text_field($_POST['project_id']);
        $commit_sha = sanitize_text_field($_POST['commit_sha']);

        $projects = get_option('gtws_projects', array());
        $project_index = array_search($project_id, array_column($projects, 'id'));

        if ($project_index === false) {
            wp_send_json_error('Project not found');
        }

        $project = $projects[$project_index];

        // Get commit info from GitHub
        $github_api = new GTWS_Github_API();
        $commit_history = $github_api->get_commit_history($project['github_url'], $project['branch'], 50);

        $commit_info = null;
        foreach ($commit_history as $commit) {
            if ($commit['sha'] === $commit_sha) {
                $commit_info = $commit;
                break;
            }
        }

        if (!$commit_info) {
            wp_send_json_error('Commit not found');
        }

        // Perform sync to specific commit
        $sync_manager = new GTWS_Sync_Manager();
        $result = $sync_manager->sync_project($project, $commit_sha);

        if ($result['success']) {
            $projects[$project_index]['last_sync'] = current_time('mysql');
            $projects[$project_index]['last_sync_commit'] = $commit_sha;
            update_option('gtws_projects', $projects);

            // Add to sync history
            $sync_manager->add_sync_record(
                $project_id,
                $commit_sha,
                $commit_info['message'],
                $commit_info['date']
            );

            wp_send_json_success(array(
                'message' => 'Restored to commit successfully!',
                'details' => $result
            ));
        } else {
            wp_send_json_error($result['message']);
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
