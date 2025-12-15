/**
 * Admin JavaScript for Github to WordPress Sync Plugin
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {

        // Collapsible Card Toggle
        $('.gtws-card-header[data-toggle="collapse"]').on('click', function() {
            const $header = $(this);
            const $content = $header.next('.gtws-card-content');

            // Toggle active class on header
            $header.toggleClass('active');

            // Slide toggle content
            $content.slideToggle(300);
        });

        // Add Project Form Submit
        $('#gtws-add-project-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.html();
            
            // Get form data
            const formData = {
                action: 'gtws_add_project',
                nonce: gtws_ajax.nonce,
                github_url: $('#github_url').val(),
                project_type: $('#project_type').val(),
                project_name: $('#project_name').val(),
                branch: $('#branch').val()
            };
            
            // Disable submit button
            $submitBtn.prop('disabled', true).html('<span class="gtws-loading"></span> Adding...');
            
            // Send AJAX request
            $.post(gtws_ajax.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        showNotice('Project added successfully!', 'success');
                        $form[0].reset();
                        $('#branch').val('main');
                        $('#branch-list').empty();

                        // Collapse the "Add New Project" section before reload
                        const $addProjectCard = $form.closest('.gtws-collapsible-card');
                        const $header = $addProjectCard.find('.gtws-card-header');
                        const $content = $addProjectCard.find('.gtws-card-content');

                        $header.removeClass('active');
                        $content.slideUp(300);

                        // Reload page after short delay to show new project
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotice('Error: ' + response.data, 'error');
                    }
                })
                .fail(function() {
                    showNotice('Failed to add project. Please try again.', 'error');
                })
                .always(function() {
                    $submitBtn.prop('disabled', false).html(originalText);
                });
        });
        
        // Fetch Branches
        $('#fetch-branches').on('click', function() {
            const $btn = $(this);
            const githubUrl = $('#github_url').val();
            
            if (!githubUrl) {
                showNotice('Please enter a GitHub URL first', 'warning');
                return;
            }
            
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="gtws-loading"></span> Fetching...');
            
            $.post(gtws_ajax.ajax_url, {
                action: 'gtws_get_branches',
                nonce: gtws_ajax.nonce,
                github_url: githubUrl
            })
            .done(function(response) {
                if (response.success) {
                    displayBranches(response.data);
                    showNotice('✓ Branches fetched successfully!', 'success');
                } else {
                    showNotice('❌ Error: ' + response.data, 'error', true);
                }
            })
            .fail(function() {
                showNotice('❌ Failed to fetch branches', 'error', true);
            })
            .always(function() {
                $btn.prop('disabled', false).html(originalText);
            });
        });
        
        // Display branches
        function displayBranches(branches) {
            const $branchList = $('#branch-list');
            $branchList.empty();
            
            if (branches && branches.length > 0) {
                branches.forEach(function(branch) {
                    const $branchItem = $('<div>')
                        .addClass('gtws-branch-item')
                        .text(branch)
                        .on('click', function() {
                            $('#branch').val(branch);
                            $('.gtws-branch-item').removeClass('active');
                            $(this).addClass('active');
                        });
                    
                    $branchList.append($branchItem);
                });
            } else {
                $branchList.html('<p>No branches found</p>');
            }
        }
        
        // Check for Updates
        $(document).on('click', '.gtws-check-update', function() {
            const $btn = $(this);
            const projectId = $btn.data('project-id');
            const $projectItem = $btn.closest('.gtws-project-item');
            const $status = $projectItem.find('.gtws-project-status');
            
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="gtws-loading"></span> Checking...');
            
            $status.removeClass('success error warning').addClass('loading show')
                .html('Checking for updates...');
            
            $.post(gtws_ajax.ajax_url, {
                action: 'gtws_check_updates',
                nonce: gtws_ajax.nonce,
                project_id: projectId
            })
            .done(function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    if (data.has_update) {
                        $status.removeClass('loading').addClass('warning')
                            .html(`
                                <strong>Update Available!</strong><br>
                                Latest commit: <code>${data.commit.sha.substring(0, 7)}</code><br>
                                Message: ${data.commit.message}<br>
                                Date: ${formatDate(data.commit.date)}
                            `);
                        showNotice('🎉 Update Available! New commits are ready to sync.', 'warning', true);
                        
                        // Update commit info without page reload
                        updateProjectCommitInfo($projectItem, data.commit);
                    } else {
                        $status.removeClass('loading').addClass('success')
                            .html('<strong>✓ Already up to date!</strong>');
                        showNotice('✓ Already up to date! You have the latest version.', 'success');
                        
                        // Hide status after 5 seconds
                        setTimeout(function() {
                            $status.removeClass('show');
                        }, 5000);
                    }
                } else {
                    $status.removeClass('loading').addClass('error')
                        .html('<strong>Error:</strong> ' + response.data);
                    showNotice('❌ Error: ' + response.data, 'error', true);
                }
            })
            .fail(function() {
                $status.removeClass('loading').addClass('error')
                    .html('<strong>Error:</strong> Failed to check for updates');
                showNotice('❌ Failed to check for updates. Please try again.', 'error', true);
            })
            .always(function() {
                $btn.prop('disabled', false).html(originalText);
            });
        });
        
        // Sync Project
        $(document).on('click', '.gtws-sync-project', function() {
            const $btn = $(this);
            const projectId = $btn.data('project-id');
            const $projectItem = $btn.closest('.gtws-project-item');
            const $status = $projectItem.find('.gtws-project-status');
            
            if (!confirm('Are you sure you want to sync this project? This will overwrite local changes.')) {
                return;
            }
            
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="gtws-loading"></span> Syncing...');
            
            // Disable other buttons
            $projectItem.find('button').prop('disabled', true);
            
            $status.removeClass('success error warning').addClass('loading show')
                .html('Syncing project from GitHub... This may take a moment.');
            
            $.post(gtws_ajax.ajax_url, {
                action: 'gtws_sync_project',
                nonce: gtws_ajax.nonce,
                project_id: projectId
            })
            .done(function(response) {
                if (response.success) {
                    $status.removeClass('loading').addClass('success')
                        .html(`
                            <strong>✓ Sync Completed Successfully!</strong><br>
                            ${response.data.message}<br>
                            Target: ${response.data.details.target_dir}
                        `);
                    showNotice('Project synced successfully!', 'success');
                    
                    // Reload page after short delay
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $status.removeClass('loading').addClass('error')
                        .html('<strong>Sync Failed:</strong> ' + response.data);
                    showNotice('❌ Sync failed: ' + response.data, 'error', true);
                }
            })
            .fail(function() {
                $status.removeClass('loading').addClass('error')
                    .html('<strong>Error:</strong> Failed to sync project');
                showNotice('❌ Failed to sync project. Please check your connection and try again.', 'error', true);
            })
            .always(function() {
                $btn.prop('disabled', false).html(originalText);
                $projectItem.find('button').prop('disabled', false);
            });
        });
        
        // Delete Project
        $(document).on('click', '.gtws-delete-project', function() {
            const $btn = $(this);
            const projectId = $btn.data('project-id');
            const $projectItem = $btn.closest('.gtws-project-item');
            
            if (!confirm('Are you sure you want to delete this project configuration? This will not delete the actual files.')) {
                return;
            }
            
            $btn.prop('disabled', true);
            $projectItem.css('opacity', '0.5');
            
            $.post(gtws_ajax.ajax_url, {
                action: 'gtws_delete_project',
                nonce: gtws_ajax.nonce,
                project_id: projectId
            })
            .done(function(response) {
                if (response.success) {
                    $projectItem.slideUp(300, function() {
                        $(this).remove();
                        
                        // Check if no projects left
                        if ($('.gtws-project-item').length === 0) {
                            location.reload();
                        }
                    });
                    showNotice('Project deleted successfully', 'success');
                } else {
                    showNotice('❌ Error: ' + response.data, 'error', true);
                    $projectItem.css('opacity', '1');
                    $btn.prop('disabled', false);
                }
            })
            .fail(function() {
                showNotice('❌ Failed to delete project', 'error', true);
                $projectItem.css('opacity', '1');
                $btn.prop('disabled', false);
            });
        });
        
        // Show notification
        function showNotice(message, type, persistent) {
            // Remove existing notices of the same type to avoid clutter
            $('.gtws-notice.' + type).remove();
            
            const $closeBtn = $('<button>')
                .addClass('gtws-notice-close')
                .html('×')
                .attr('type', 'button')
                .attr('aria-label', 'Close notification');
            
            const $notice = $('<div>')
                .addClass('gtws-notice')
                .addClass(type || 'success')
                .html('<div class="gtws-notice-content">' + message + '</div>')
                .append($closeBtn);
            
            $('body').append($notice);
            
            // Close button handler
            $closeBtn.on('click', function() {
                $notice.addClass('hiding');
                setTimeout(function() {
                    $notice.remove();
                }, 300);
            });
            
            // Auto-dismiss only if not persistent
            if (!persistent) {
                setTimeout(function() {
                    if ($notice.length) {
                        $notice.addClass('hiding');
                        setTimeout(function() {
                            $notice.remove();
                        }, 300);
                    }
                }, 8000); // Increased from 4000 to 8000ms
            }
        }
        
        // Update project commit info without reload
        function updateProjectCommitInfo($projectItem, commit) {
            // This updates the DOM with new commit info
            // Could be expanded to update specific elements if needed
        }
        
        // Format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }
        
        // Auto-fill project name from GitHub URL
        $('#github_url').on('blur', function() {
            const url = $(this).val();
            const $projectName = $('#project_name');

            // Only auto-fill if project name is empty
            if (!$projectName.val() && url) {
                const match = url.match(/github\.com\/[^\/]+\/([^\/]+)/);
                if (match && match[1]) {
                    let repoName = match[1].replace('.git', '');
                    $projectName.val(repoName);
                }
            }
        });

        // View Commit History
        $(document).on('click', '.gtws-view-history', function() {
            const $btn = $(this);
            const projectId = $btn.data('project-id');
            const $projectItem = $btn.closest('.gtws-project-item');
            const $historySection = $projectItem.find('.gtws-commit-history');
            const $timeline = $historySection.find('.gtws-history-timeline');

            // Toggle visibility
            if ($historySection.is(':visible')) {
                $historySection.slideUp(300);
                $btn.removeClass('active');
                return;
            }

            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="gtws-loading"></span> Loading...');

            $.post(gtws_ajax.ajax_url, {
                action: 'gtws_get_commit_history',
                nonce: gtws_ajax.nonce,
                project_id: projectId
            })
            .done(function(response) {
                if (response.success) {
                    displayCommitHistory($timeline, response.data, projectId);
                    $historySection.slideDown(300);
                    $btn.addClass('active');
                } else {
                    showNotice('❌ Error: ' + response.data, 'error', true);
                }
            })
            .fail(function() {
                showNotice('❌ Failed to fetch commit history', 'error', true);
            })
            .always(function() {
                $btn.prop('disabled', false).html(originalText);
            });
        });

        // Display commit history timeline
        function displayCommitHistory($timeline, data, projectId) {
            $timeline.empty();

            const commits = data.commit_history || [];
            const syncHistory = data.sync_history || [];
            const currentCommit = data.current_commit;

            if (commits.length === 0) {
                $timeline.html('<p class="gtws-no-history">No commit history available</p>');
                return;
            }

            // Create sync history map for quick lookup
            const syncMap = {};
            syncHistory.forEach(function(sync) {
                syncMap[sync.commit_sha] = sync.synced_at;
            });

            commits.forEach(function(commit, index) {
                const isCurrent = commit.sha === currentCommit;
                const wasSynced = syncMap[commit.sha];

                const $item = $('<div>').addClass('gtws-timeline-item');

                if (isCurrent) {
                    $item.addClass('current');
                }

                // Timeline marker
                const $marker = $('<div>').addClass('gtws-timeline-marker');
                if (isCurrent) {
                    $marker.append('<span class="dashicons dashicons-yes-alt"></span>');
                } else if (wasSynced) {
                    $marker.append('<span class="dashicons dashicons-backup"></span>');
                } else {
                    $marker.append('<span class="dashicons dashicons-marker"></span>');
                }

                // Timeline content
                const $content = $('<div>').addClass('gtws-timeline-content');

                const $header = $('<div>').addClass('gtws-commit-header');
                $header.append(
                    $('<code>').addClass('gtws-commit-sha').text(commit.sha.substring(0, 7))
                );
                $header.append(
                    $('<span>').addClass('gtws-commit-author').text('by ' + commit.author)
                );

                if (isCurrent) {
                    $header.append(
                        $('<span>').addClass('gtws-current-badge').text('Current')
                    );
                }

                const $message = $('<div>')
                    .addClass('gtws-commit-message')
                    .text(commit.message);

                const $date = $('<div>')
                    .addClass('gtws-commit-date')
                    .text(formatDate(commit.date));

                if (wasSynced) {
                    const $syncInfo = $('<div>')
                        .addClass('gtws-sync-info')
                        .html('<span class="dashicons dashicons-backup"></span> Synced on ' + formatDate(wasSynced));
                    $date.append($syncInfo);
                }

                $content.append($header, $message, $date);

                // Restore button (only if not current)
                if (!isCurrent) {
                    const $restoreBtn = $('<button>')
                        .addClass('button gtws-restore-commit')
                        .attr('data-project-id', projectId)
                        .attr('data-commit-sha', commit.sha)
                        .attr('title', 'Restore to this commit - This will replace your current files!')
                        .html('<span class="dashicons dashicons-backup"></span> Restore');

                    $content.append($restoreBtn);
                }

                $item.append($marker, $content);
                $timeline.append($item);
            });
        }

        // Restore to specific commit
        $(document).on('click', '.gtws-restore-commit', function() {
            const $btn = $(this);
            const projectId = $btn.data('project-id');
            const commitSha = $btn.data('commit-sha');
            const $projectItem = $btn.closest('.gtws-project-item');
            const $status = $projectItem.find('.gtws-project-status');

            const confirmMsg = 'Are you sure you want to restore to this commit?\n\n' +
                               'Commit: ' + commitSha.substring(0, 7) + '\n\n' +
                               'WARNING: This will REPLACE your current files with this commit!\n' +
                               'Make sure you have committed any local changes to GitHub first.';

            if (!confirm(confirmMsg)) {
                return;
            }

            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="gtws-loading"></span> Restoring...');

            // Disable all buttons in project
            $projectItem.find('button').prop('disabled', true);

            $status.removeClass('success error warning').addClass('loading show')
                .html('Restoring to commit ' + commitSha.substring(0, 7) + '... This may take a moment.');

            $.post(gtws_ajax.ajax_url, {
                action: 'gtws_restore_commit',
                nonce: gtws_ajax.nonce,
                project_id: projectId,
                commit_sha: commitSha
            })
            .done(function(response) {
                if (response.success) {
                    $status.removeClass('loading').addClass('success')
                        .html(`
                            <strong>✓ Restore Completed Successfully!</strong><br>
                            ${response.data.message}<br>
                            Restored to commit: <code>${commitSha.substring(0, 7)}</code>
                        `);
                    showNotice('Project restored to commit ' + commitSha.substring(0, 7) + ' successfully!', 'success');

                    // Reload page after short delay
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $status.removeClass('loading').addClass('error')
                        .html('<strong>Restore Failed:</strong> ' + response.data);
                    showNotice('❌ Restore failed: ' + response.data, 'error', true);
                    $btn.prop('disabled', false).html(originalText);
                    $projectItem.find('button').prop('disabled', false);
                }
            })
            .fail(function() {
                $status.removeClass('loading').addClass('error')
                    .html('<strong>Error:</strong> Failed to restore commit');
                showNotice('❌ Failed to restore commit. Please check your connection and try again.', 'error', true);
                $btn.prop('disabled', false).html(originalText);
                $projectItem.find('button').prop('disabled', false);
            });
        });

    });

})(jQuery);
