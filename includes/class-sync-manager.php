<?php
/**
 * Sync Manager Class
 * Handles downloading and syncing GitHub repositories to WordPress
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GTWS_Sync_Manager {
    
    private $github_api;
    
    public function __construct() {
        $this->github_api = new GTWS_Github_API();
    }
    
    /**
     * Sync a project from GitHub
     */
    public function sync_project($project) {
        try {
            // Download repository
            $zip_file = $this->github_api->download_repository(
                $project['github_url'],
                $project['branch']
            );
            
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
            $result = $this->extract_and_sync($zip_file, $target_dir, $project);
            
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
    private function extract_and_sync($zip_file, $target_dir, $project) {
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
        
        // Find the extracted folder (GitHub creates a folder with repo-branch name)
        $extracted_folders = glob($temp_dir . '/*', GLOB_ONLYDIR);
        
        if (empty($extracted_folders)) {
            $this->cleanup_directory($temp_dir);
            return array(
                'success' => false,
                'message' => 'No folder found in extracted archive'
            );
        }
        
        $source_dir = $extracted_folders[0];
        
        // Backup existing directory if it exists
        $backup_result = $this->backup_existing_directory($target_dir);
        
        // Create target directory if it doesn't exist
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        // Sync files from source to target
        $sync_result = $this->sync_directories($source_dir, $target_dir);
        
        // Clean up temp directory
        $this->cleanup_directory($temp_dir);
        
        if ($sync_result) {
            return array(
                'success' => true,
                'message' => 'Project synced successfully!',
                'backup' => $backup_result,
                'target_dir' => $target_dir
            );
        } else {
            // Restore backup if sync failed
            if ($backup_result) {
                $this->restore_backup($target_dir, $backup_result);
            }
            
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
     * Backup existing directory
     */
    private function backup_existing_directory($dir) {
        if (!file_exists($dir)) {
            return false;
        }
        
        $backup_dir = $dir . '-backup-' . date('YmdHis');
        
        // Use rename for quick backup
        if (@rename($dir, $backup_dir)) {
            return $backup_dir;
        }
        
        return false;
    }
    
    /**
     * Restore backup
     */
    private function restore_backup($target_dir, $backup_dir) {
        if (!file_exists($backup_dir)) {
            return false;
        }
        
        // Remove failed target
        if (file_exists($target_dir)) {
            $this->cleanup_directory($target_dir);
        }
        
        // Restore backup
        return @rename($backup_dir, $target_dir);
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
    
    /**
     * Get backup list for a project
     */
    public function get_backups($type, $name) {
        $target_dir = $this->get_target_directory($type, $name);
        
        if (!$target_dir) {
            return array();
        }
        
        $parent_dir = dirname($target_dir);
        $project_name = basename($target_dir);
        
        $backups = glob($parent_dir . '/' . $project_name . '-backup-*');
        
        $backup_list = array();
        foreach ($backups as $backup) {
            $backup_list[] = array(
                'path' => $backup,
                'name' => basename($backup),
                'date' => date('Y-m-d H:i:s', filemtime($backup)),
                'size' => $this->get_directory_size($backup)
            );
        }
        
        // Sort by date, newest first
        usort($backup_list, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $backup_list;
    }
    
    /**
     * Get directory size
     */
    private function get_directory_size($dir) {
        $size = 0;
        
        if (!is_dir($dir)) {
            return 0;
        }
        
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($items as $item) {
            $size += $item->getSize();
        }
        
        return $this->format_bytes($size);
    }
    
    /**
     * Format bytes to human readable
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
