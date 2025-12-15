<?php
/**
 * Admin Settings Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$projects = get_option('gtws_projects', array());
?>

<div class="wrap gtws-admin-wrap">
    <h1>
        <span class="dashicons dashicons-update"></span>
        <?php _e('Github to WordPress Sync', 'snn'); ?>
    </h1>
    
    <div class="gtws-container">
        <!-- Add New Project Section -->
        <div class="gtws-card">
            <h2><?php _e('Add New Project', 'snn'); ?></h2>
            
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
                                        <?php echo esc_html(date('F j, Y g:i a', strtotime($project['commit_date']))); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($project['last_sync'])): ?>
                                    <div class="gtws-detail-item">
                                        <strong><?php _e('Last Sync:', 'snn'); ?></strong>
                                        <?php echo esc_html(date('F j, Y g:i a', strtotime($project['last_sync']))); ?>
                                        <?php if (!empty($project['last_sync_commit'])): ?>
                                            <code><?php echo esc_html(substr($project['last_sync_commit'], 0, 7)); ?></code>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="gtws-project-status"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Info Section -->
        <div class="gtws-card gtws-info-card">
            <h3><?php _e('How to Use', 'snn'); ?></h3>
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
