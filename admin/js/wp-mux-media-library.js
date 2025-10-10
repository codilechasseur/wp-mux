jQuery(document).ready(function($) {
    'use strict';

    console.log('WP MUX Media Library: Script loaded, checking environment...');

    // Debug environment info
    console.log('WP MUX Media Library: pagenow =', window.pagenow);
    console.log('WP MUX Media Library: body classes =', $('body').attr('class'));
    console.log('WP MUX Media Library: wpMuxMedia available =', typeof wpMuxMedia !== 'undefined');

    // Check if we're on a media library page with more flexible detection
    var isMediaLibrary = window.pagenow === 'upload' ||
                        window.pagenow === 'media' ||
                        $('body').hasClass('upload-php') ||
                        $('body').hasClass('wp-admin') && (
                            window.location.href.indexOf('upload.php') !== -1 ||
                            window.location.href.indexOf('media-upload.php') !== -1 ||
                            $('#media-items').length > 0 ||
                            $('.media-frame').length > 0 ||
                            $('.wp-list-table.media').length > 0
                        );

    console.log('WP MUX Media Library: isMediaLibrary =', isMediaLibrary);

    if (!isMediaLibrary) {
        console.log('WP MUX Media Library: Not on media library page, but will initialize anyway for modal support');
        // Don't exit - media modals might be opened from other pages
    }

    var statusCheckInterval;
    var processedAttachments = new Set();

    /**
     * Add MUX status badge to attachment thumbnail
     */
    function addMuxStatusBadge(attachmentId, status, $thumbnail) {
        console.log('WP MUX: Adding status badge', {attachmentId, status, thumbnailCount: $thumbnail.length});

        if ($thumbnail.length === 0) {
            console.log('WP MUX: No thumbnail found for attachment', attachmentId);
            return;
        }

        // Remove existing badge if any
        $thumbnail.find('.wp-mux-status-badge').remove();

        var badgeClass = 'wp-mux-status-badge';
        var badgeText = '';
        var badgeIcon = '';

        switch (status) {
            case 'queued':
                badgeClass += ' wp-mux-queued';
                badgeIcon = '<span class="badge-icon"></span>'; // Dot
                badgeText = 'Queued for MUX';
                break;
            case 'processing':
                badgeClass += ' wp-mux-processing';
                badgeIcon = '<span class="badge-icon"></span>'; // Spinner
                badgeText = 'Processing...';
                break;
            case 'complete':
                badgeClass += ' wp-mux-complete';
                badgeIcon = '<span class="badge-icon"></span>'; // Checkmark
                badgeText = 'Ready';
                break;
            case 'failed':
                badgeClass += ' wp-mux-failed';
                badgeIcon = '<span class="badge-icon"></span>'; // Exclamation
                badgeText = 'Failed';
                break;
            default:
                console.log('WP MUX: Unknown status:', status);
                return; // Don't add badge for unknown status
        }

        var $badge = $('<div>')
            .addClass(badgeClass)
            .attr('title', badgeText)
            .html(badgeIcon);

        // Ensure parent has relative positioning
        $thumbnail.css('position', 'relative');
        $thumbnail.append($badge);

        // If status is complete, fade out the badge after 3 seconds
        if (status === 'complete') {
            setTimeout(function() {
                $badge.fadeOut(1000, function() {
                    $badge.remove();
                });
            }, 3000);

            // Also update the thumbnail with MUX thumbnail
            updateThumbnailWithMuxImage(attachmentId, $thumbnail);
        }

        console.log('WP MUX: Badge added successfully for attachment', attachmentId);
    }

    /**
     * Update thumbnail with MUX generated thumbnail
     */
    function updateThumbnailWithMuxImage(attachmentId, $thumbnail) {
        console.log('WP MUX: Updating thumbnail for attachment', attachmentId);

        if (typeof wpMuxMedia === 'undefined') {
            return;
        }

        // Add loading state
        $thumbnail.addClass('wp-mux-thumbnail-updating');

        // Get MUX asset info via AJAX
        $.ajax({
            url: wpMuxMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_mux_get_attachment_info',
                attachment_id: attachmentId,
                nonce: wpMuxMedia.nonce || ''
            },
            success: function(response) {
                if (response.success && response.data.thumbnail_url) {
                    var thumbnailUrl = response.data.thumbnail_url;

                    // Find the image element in the thumbnail
                    var $img = $thumbnail.find('img');
                    if ($img.length > 0) {
                        // Create a new image to test if it loads
                        var testImg = new Image();
                        testImg.onload = function() {
                            // Successfully loaded, update the src with a fade effect
                            $img.fadeOut(300, function() {
                                $img.attr('src', thumbnailUrl);
                                $img.fadeIn(300, function() {
                                    // Remove loading state after transition
                                    $thumbnail.removeClass('wp-mux-thumbnail-updating');
                                });
                            });
                        };
                        testImg.onerror = function() {
                            console.log('WP MUX: Failed to load MUX thumbnail:', thumbnailUrl);
                            $thumbnail.removeClass('wp-mux-thumbnail-updating');
                        };
                        testImg.src = thumbnailUrl;
                    } else {
                        $thumbnail.removeClass('wp-mux-thumbnail-updating');
                    }
                } else {
                    console.log('WP MUX: No thumbnail URL in response for attachment', attachmentId);
                    $thumbnail.removeClass('wp-mux-thumbnail-updating');
                }
            },
            error: function() {
                console.log('WP MUX: Failed to get attachment info for', attachmentId);
                $thumbnail.removeClass('wp-mux-thumbnail-updating');
            }
        });
    }

    /**
     * Check MUX status for an attachment
     */
    function checkMuxStatus(attachmentId) {
        if (processedAttachments.has(attachmentId)) {
            return;
        }

        console.log('WP MUX: Checking status for attachment', attachmentId);

        if (typeof wpMuxMedia === 'undefined') {
            console.log('WP MUX: wpMuxMedia not available, cannot check status');
            return;
        }

        $.ajax({
            url: wpMuxMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_mux_get_queue_status',
                attachment_id: attachmentId,
                nonce: wpMuxMedia.nonce || ''
            },
            success: function(response) {
                console.log('WP MUX: AJAX response for attachment', attachmentId, response);

                if (response.success && response.data.status) {
                    var status = response.data.status;
                    var $thumbnails = findAttachmentThumbnails(attachmentId);

                    console.log('WP MUX: Found', $thumbnails.length, 'thumbnails for attachment', attachmentId);

                    $thumbnails.each(function() {
                        addMuxStatusBadge(attachmentId, status, $(this));
                    });

                    // If status is complete or failed, mark as processed
                    if (status === 'complete' || status === 'failed') {
                        processedAttachments.add(attachmentId);
                    }
                } else {
                    console.log('WP MUX: No valid status returned for attachment', attachmentId);
                }
            },
            error: function(xhr, status, error) {
                console.log('WP MUX: AJAX error for attachment', attachmentId, {xhr, status, error});
            }
        });
    }

    /**
     * Find thumbnail elements for an attachment ID
     */
    function findAttachmentThumbnails(attachmentId) {
        var $thumbnails = $();

        // Grid view - WordPress media library
        $thumbnails = $thumbnails.add($('.attachment[data-id="' + attachmentId + '"] .thumbnail'));
        $thumbnails = $thumbnails.add($('.attachment[data-id="' + attachmentId + '"] .attachment-preview'));

        // List view
        $thumbnails = $thumbnails.add($('#post-' + attachmentId + ' .column-title .media-icon'));
        $thumbnails = $thumbnails.add($('#post-' + attachmentId + ' .media-icon'));

        // Media modal/library view
        $thumbnails = $thumbnails.add($('.attachment[data-id="' + attachmentId + '"] .thumbnail img').parent());

        // Legacy selectors
        $thumbnails = $thumbnails.add($('.media-item[id*="media-item-' + attachmentId + '"] .thumbnail'));

        // More specific selectors for different views
        $thumbnails = $thumbnails.add($('[data-attachment-id="' + attachmentId + '"] .thumbnail'));
        $thumbnails = $thumbnails.add($('#attachment-' + attachmentId + ' .thumbnail'));

        console.log('WP MUX: Found', $thumbnails.length, 'thumbnail elements for attachment', attachmentId);

        return $thumbnails;
    }

    /**
     * Scan for video attachments and check their MUX status
     */
    function scanForVideoAttachments() {
        var videoAttachments = new Set();

        // Grid view - look for MUX video attachments
        $('.attachment').each(function() {
            var $this = $(this);
            var attachmentId = $this.data('id');

            // Check if it's a MUX video by looking for MUX-specific attributes
            if (attachmentId && (
                $this.attr('data-subtype') === 'mux' ||
                $this.find('.mux-badge, .wp-mux-status-badge').length > 0 ||
                $this.find('.mux-player').length > 0 ||
                $this.hasClass('subtype-mux') ||
                $this.find('.subtype-mux').length > 0
            )) {
                videoAttachments.add(attachmentId);
            }
        });

        // List view - look for MUX videos
        $('.wp-list-table tr[id*="post-"]').each(function() {
            var $this = $(this);
            var id = $this.attr('id');
            if (id && id.indexOf('post-') === 0) {
                var attachmentId = parseInt(id.replace('post-', ''));
                var $mimeCell = $this.find('.column-mime_type');
                var mimeText = $mimeCell.text().toLowerCase();

                // Check if it's a MUX video (video/mux mime type) or has MUX badge
                if (mimeText.indexOf('video/mux') !== -1 ||
                    mimeText === 'mux' ||
                    $this.find('.mux-badge, .wp-mux-status-badge').length > 0) {
                    videoAttachments.add(attachmentId);
                }
            }
        });

        if (videoAttachments.size > 0) {
            console.log('WP MUX: Checking status for', videoAttachments.size, 'MUX video(s)');
        }

        // Check MUX status for each potential video attachment
        videoAttachments.forEach(function(attachmentId) {
            checkMuxStatus(attachmentId);
        });
    }

    /**
     * Initialize status checking
     */
    function initStatusChecking() {
        console.log('WP MUX: Initializing status checking...');

        // Initial scan
        scanForVideoAttachments();

        // Periodic check for processing videos
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }

        statusCheckInterval = setInterval(function() {
            scanForVideoAttachments();

            // Also trigger queue processing in background if needed
            triggerQueueProcessing();
        }, 5000); // Check every 5 seconds

        console.log('WP MUX: Status checking initialized with 5s interval');
    }

    /**
     * Trigger queue processing via AJAX
     */
    function triggerQueueProcessing() {
        if (typeof wpMuxMedia === 'undefined') {
            return;
        }

        $.ajax({
            url: wpMuxMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_mux_process_queue',
                background: '1'
            },
            timeout: 100, // Very short timeout since it's meant to be non-blocking
            success: function(response) {
                if (response.success) {
                    console.log('WP MUX: Queue processing triggered');
                }
            },
            error: function() {
                // Silently handle errors - this is background processing
            }
        });
    }

    /**
     * Handle new uploads
     */
    function handleNewUploads() {
        console.log('WP MUX: Setting up upload handlers...');

        // Watch for new uploads in the media library
        if (typeof wp !== 'undefined' && wp.Uploader) {
            wp.Uploader.queue.on('add', function(attachment) {
                console.log('WP MUX: New upload detected:', attachment);
                if (attachment.get('type') === 'video') {
                    // Wait a bit for the attachment to be processed, then check status
                    setTimeout(function() {
                        checkMuxStatus(attachment.get('id'));
                    }, 2000);
                }
            });
        }

        // Watch for DOM changes (new attachments loaded)
        if (window.MutationObserver) {
            var observer = new MutationObserver(function(mutations) {
                var shouldRescan = false;
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === Node.ELEMENT_NODE &&
                                (node.classList.contains('attachment') ||
                                 node.querySelector && node.querySelector('.attachment'))) {
                                shouldRescan = true;
                            }
                        });
                    }
                });

                if (shouldRescan) {
                    console.log('WP MUX: DOM changes detected, rescanning...');
                    setTimeout(scanForVideoAttachments, 500);
                }
            });

            // Observe multiple possible containers
            var selectors = ['.attachments-browser', '.wp-list-table', '#media-items', '.media-frame-content', 'body'];
            selectors.forEach(function(selector) {
                var container = document.querySelector(selector);
                if (container) {
                    observer.observe(container, {
                        childList: true,
                        subtree: true
                    });
                    console.log('WP MUX: Observing DOM changes in:', selector);
                }
            });
        }
    }

    // Force initialization after a short delay to ensure DOM is ready
    setTimeout(function() {
        console.log('WP MUX: Starting initialization...');
        initStatusChecking();
        handleNewUploads();
    }, 1000);

    // Re-initialize when media library view changes
    $(document).on('click', '.view-switch a, .media-toolbar-secondary .media-button', function() {
        console.log('WP MUX: View switch detected, reinitializing...');
        setTimeout(function() {
            processedAttachments.clear(); // Reset processed list
            initStatusChecking();
        }, 1000);
    });

    // Initialize when media modal opens
    $(document).on('click', '.add_media, .insert-media', function() {
        setTimeout(function() {
            console.log('WP MUX: Media modal opened, initializing...');
            initStatusChecking();
        }, 1500);
    });

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }
    });

    /**
     * Add View link to actions menu for MUX videos if missing
     * This ensures MUX videos have a "View media file" link in the attachment details
     */
    function addViewLinkForMuxVideos(attachment) {
        if (!attachment || attachment.subtype !== 'mux') {
            return;
        }

        setTimeout(function() {
            var $actions = $('.attachment-details[data-id="' + attachment.id + '"] .actions');

            if ($actions.length === 0) {
                $actions = $('.attachment-details .actions');
            }

            if ($actions.length === 0) {
                return;
            }

            // Check if View link already exists
            if ($actions.find('a:contains("View")').length > 0) {
                return;
            }

            // Use the link property (attachment page) or fallback to url (stream URL)
            var viewUrl = attachment.link || attachment.url;

            if (!viewUrl) {
                return;
            }

            // Create View link without separator - WordPress template adds it
            var viewHtml = '<a href="' + viewUrl + '" target="_blank" rel="noopener noreferrer">View media file</a>';

            // Prepend to actions (so it appears first)
            $actions.prepend(viewHtml);
        }, 200);
    }

    /**
     * Hook into WordPress media library events to add View link
     */
    if (typeof wp !== 'undefined' && wp.media) {
        // Hook into attachment details render
        var originalRender = wp.media.view.Attachment.Details.prototype.render;
        wp.media.view.Attachment.Details.prototype.render = function() {
            var result = originalRender.apply(this, arguments);

            var self = this;
            setTimeout(function() {
                if (self.model) {
                    var attachment = self.model.toJSON();
                    addViewLinkForMuxVideos(attachment);
                }
            }, 100);

            return result;
        };

        // Also hook into ready event
        var originalReady = wp.media.view.Attachment.Details.prototype.ready;
        if (originalReady) {
            wp.media.view.Attachment.Details.prototype.ready = function() {
                if (originalReady) {
                    originalReady.apply(this, arguments);
                }

                if (this.model) {
                    var attachment = this.model.toJSON();
                    addViewLinkForMuxVideos(attachment);
                }
            };
        }
    }

    console.log('WP MUX Media Library: Setup complete');
});
