jQuery(document).ready(function($) {
    'use strict';

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

    if (!isMediaLibrary) {
        // Don't exit - media modals might be opened from other pages
    }

    var statusCheckInterval;
    var processedAttachments = new Set();
    var activeProcessingVideos = new Set(); // Track videos currently processing

    /**
     * Add MUX status badge to attachment thumbnail
     */
    function addStatusBadge(attachmentId, status) {
        var $thumbnail = findAttachmentThumbnails(attachmentId);

        if ($thumbnail.length === 0) {
            return;
        }

        // Remove existing badge if any
        $thumbnail.find('.wp-mux-status-badge').remove();

        var badgeClass = 'wp-mux-status-badge';
        var badgeText = '';

        switch (status) {
            case 'queued':
                badgeClass += ' wp-mux-queued';
                badgeText = 'Queued for MUX';
                break;
            case 'processing':
                badgeClass += ' wp-mux-processing';
                badgeText = 'Processing Video...';
                break;
            case 'complete':
                badgeClass += ' wp-mux-complete';
                badgeText = 'Ready to Use';
                break;
            case 'failed':
                badgeClass += ' wp-mux-failed';
                badgeText = 'Processing Failed';
                break;
            default:
                return; // Don't add badge for unknown status
        }

        var $badge = $('<div>')
            .addClass(badgeClass)
            .attr('title', badgeText);

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
    }

    /**
     * Update thumbnail with MUX generated thumbnail
     */
    function updateThumbnailWithMuxImage(attachmentId, $thumbnail) {
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
                            $thumbnail.removeClass('wp-mux-thumbnail-updating');
                        };
                        testImg.src = thumbnailUrl;
                    } else {
                        $thumbnail.removeClass('wp-mux-thumbnail-updating');
                    }
                } else {
                    $thumbnail.removeClass('wp-mux-thumbnail-updating');
                }
            },
            error: function() {
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

        if (typeof wpMuxMedia === 'undefined') {
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
                if (response.success && response.data.status) {
                    var status = response.data.status;
                    var $thumbnails = findAttachmentThumbnails(attachmentId);

                    // Only show badges for queued, processing, or failed
                    // Don't show badge for "complete" - that's the normal state
                    if (status === 'queued' || status === 'processing' || status === 'failed') {
                        $thumbnails.each(function() {
                            addMuxStatusBadge(attachmentId, status, $(this));
                        });
                    } else if (status === 'complete') {
                        // Remove any existing badges for completed videos
                        $thumbnails.find('.wp-mux-status-badge').fadeOut(300, function() {
                            $(this).remove();
                        });
                    }

                    // Track active processing videos
                    if (status === 'queued' || status === 'processing') {
                        activeProcessingVideos.add(attachmentId);
                    } else {
                        activeProcessingVideos.delete(attachmentId);
                    }

                    // If status is complete or failed, mark as processed
                    if (status === 'complete' || status === 'failed') {
                        processedAttachments.add(attachmentId);
                    }
                }
            },
            error: function(xhr, status, error) {
                // Silent error handling
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

        return $thumbnails;
    }

    /**
     * Scan for video attachments and check their MUX status
     */
    function scanForVideoAttachments() {
        // Early exit if no actively processing videos and we've done initial scan
        if (activeProcessingVideos.size === 0 && processedAttachments.size > 0) {
            return;
        }

        var videoAttachments = new Set();

        // Grid view - look for MUX video attachments
        $('.attachment').each(function() {
            var $this = $(this);
            var attachmentId = $this.data('id');

            // Skip already processed attachments
            if (attachmentId && processedAttachments.has(attachmentId)) {
                return;
            }

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

                // Skip already processed attachments
                if (processedAttachments.has(attachmentId)) {
                    return;
                }

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

        // Check MUX status for each potential video attachment
        videoAttachments.forEach(function(attachmentId) {
            checkMuxStatus(attachmentId);
        });

        // If no videos found and no active processing, stop the interval
        if (videoAttachments.size === 0 && activeProcessingVideos.size === 0) {
            stopStatusChecking();
        }
    }

    /**
     * Initialize status checking
     */
    function initStatusChecking() {
        // Initial scan
        scanForVideoAttachments();

        // Periodic check for processing videos
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }

        statusCheckInterval = setInterval(function() {
            scanForVideoAttachments();

            // Only trigger queue processing if we have active videos
            if (activeProcessingVideos.size > 0) {
                triggerQueueProcessing();
            }
        }, 10000); // Check every 10 seconds (reduced from 5s for better performance)
    }

    /**
     * Stop status checking when no videos need monitoring
     */
    function stopStatusChecking() {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
            statusCheckInterval = null;
        }
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
                // Silent success
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
        // Watch for new uploads in the media library
        if (typeof wp !== 'undefined' && wp.Uploader) {
            wp.Uploader.queue.on('add', function(attachment) {
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
                }
            });
        }
    }

    // Force initialization after a short delay to ensure DOM is ready
    setTimeout(function() {
        initStatusChecking();
        handleNewUploads();
    }, 1000);

    // Re-initialize when media library view changes
    $(document).on('click', '.view-switch a, .media-toolbar-secondary .media-button', function() {
        setTimeout(function() {
            processedAttachments.clear(); // Reset processed list
            initStatusChecking();
        }, 1000);
    });

    // Initialize when media modal opens
    $(document).on('click', '.add_media, .insert-media', function() {
        setTimeout(function() {
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
});
