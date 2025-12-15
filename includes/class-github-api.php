<?php
/**
 * GitHub API Integration Class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

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
}
