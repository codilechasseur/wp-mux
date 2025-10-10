/**
 * WP MUX Media Library Stats Display
 *
 * Displays video analytics in the media library attachment details sidepanel.
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/admin/js
 */

(function($) {
	'use strict';

	// Track if we've already added stats to avoid duplicates
	var statsAddedForAttachment = {};

	/**
	 * Add stats section to attachment details
	 */
	function addStatsToAttachmentDetails(attachment) {
		if (!attachment || !attachment.id) {
			return;
		}

		// Only add stats for MUX videos that are complete
		if (attachment.subtype !== 'mux') {
			return;
		}

		if (!attachment.mux_has_analytics) {
			return;
		}

		// Avoid adding stats multiple times
		if (statsAddedForAttachment[attachment.id]) {
			return;
		}

		// Wait for the DOM to be ready
		setTimeout(function() {
			var $details = $('.attachment-details[data-id="' + attachment.id + '"]');

			if ($details.length === 0) {
				// Try alternate selectors
				$details = $('.attachment-details').first();
			}

			if ($details.length === 0) {
				return;
			}

			// Check if stats section already exists
			if ($details.find('.mux-video-stats').length > 0) {
				return;
			}

			// Build stats HTML
			var statsHtml = buildStatsHtml(attachment);

			// Find the best insertion point (after settings or at the end)
			var $insertionPoint = $details.find('.attachment-info');

			if ($insertionPoint.length === 0) {
				$insertionPoint = $details.find('.details');
			}

			if ($insertionPoint.length === 0) {
				// Last resort - just append to the details panel directly
				$insertionPoint = $details;
			}

			if ($insertionPoint.length > 0) {
				$insertionPoint.append(statsHtml);
				statsAddedForAttachment[attachment.id] = true;

				// Attach event handler for refresh button
				attachRefreshHandler(attachment.id);
			}
		}, 500);
	}

	/**
	 * Build stats HTML section
	 */
	function buildStatsHtml(attachment) {
		var analytics = attachment.mux_analytics || {};

		var html = '<div class="mux-video-stats">';

		html += '<div class="mux-stats-grid">';

		// Primary metrics
		html += '<div class="mux-stat-card mux-stat-primary">';
		html += '<div class="mux-stat-value">' + (analytics.total_views || '0') + '</div>';
		html += '<div class="mux-stat-label">Total Views</div>';
		html += '</div>';

		html += '<div class="mux-stat-card mux-stat-primary">';
		html += '<div class="mux-stat-value">' + (analytics.unique_viewers || '0') + '</div>';
		html += '<div class="mux-stat-label">Unique Viewers</div>';
		html += '</div>';

		html += '<div class="mux-stat-card">';
		html += '<div class="mux-stat-value">' + (analytics.total_watch_time || '0 min') + '</div>';
		html += '<div class="mux-stat-label">Total Watch Time</div>';
		html += '</div>';

		html += '<div class="mux-stat-card">';
		html += '<div class="mux-stat-value">' + (analytics.average_view_duration || '0 min') + '</div>';
		html += '<div class="mux-stat-label">Avg. View Duration</div>';
		html += '</div>';

		// Quality metrics
		if (analytics.video_quality_score && analytics.video_quality_score !== 'N/A') {
			html += '<div class="mux-stat-card mux-stat-quality">';
			html += '<div class="mux-stat-value">' + analytics.video_quality_score + '</div>';
			html += '<div class="mux-stat-label">Quality Score</div>';
			html += '</div>';
		}

		html += '</div>'; // End stats-grid

		// Footer with last updated timestamp and refresh button
		html += '<div class="mux-stats-footer">';
		html += '<span class="mux-stats-updated">Updated: ' + (analytics.last_updated || 'Never') + '</span>';
		html += '<button type="button" class="button button-small mux-refresh-stats" data-attachment-id="' + attachment.id + '" title="Refresh Analytics">';
		html += '<span class="dashicons dashicons-update"></span> Refresh';
		html += '</button>';
		html += '</div>';

		html += '</div>'; // End mux-video-stats

		return html;
	}

	/**
	 * Attach click handler to refresh button
	 */
	function attachRefreshHandler(attachmentId) {
		$(document).on('click', '.mux-refresh-stats[data-attachment-id="' + attachmentId + '"]', function(e) {
			e.preventDefault();
			refreshStats(attachmentId, $(this));
		});
	}

	/**
	 * Refresh stats via AJAX
	 */
	function refreshStats(attachmentId) {
		var $button = $('.mux-stats-refresh[data-attachment-id="' + attachmentId + '"]');

		// Disable button and show loading state
		$button.prop('disabled', true).addClass('mux-stats-loading');

		$.ajax({
			url: wpMuxAnalytics.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wp_mux_refresh_video_analytics',
				nonce: wpMuxAnalytics.nonce,
				attachment_id: attachmentId
			},
			success: function(response) {
				if (response.success && response.data.analytics) {
					// Update the stats display
					updateStatsDisplay(attachmentId, response.data.analytics);

					// Show success message briefly
					showRefreshMessage('success', 'Stats refreshed!');
				} else {
					showRefreshMessage('error', response.data.message || 'Failed to refresh stats');
				}
			},
			error: function(xhr, status, error) {
				showRefreshMessage('error', 'Failed to refresh stats');
			},
			complete: function() {
				// Re-enable button
				$button.prop('disabled', false).removeClass('mux-stats-loading');
			}
		});
	}

	/**
	 * Update stats display with new data
	 */
	function updateStatsDisplay(attachmentId, analytics) {
		var $stats = $('.attachment-details[data-id="' + attachmentId + '"] .mux-video-stats');

		if ($stats.length === 0) {
			return;
		}

		// Update each stat value
		var statMapping = {
			'Total Views': analytics.total_views,
			'Unique Viewers': analytics.unique_viewers,
			'Total Watch Time': analytics.total_watch_time,
			'Avg. View Duration': analytics.average_view_duration,
			'Quality Score': analytics.video_quality_score
		};

		$stats.find('.mux-stat-card').each(function() {
			var $card = $(this);
			var label = $card.find('.mux-stat-label').text().trim();

			if (statMapping[label] !== undefined) {
				$card.find('.mux-stat-value').text(statMapping[label]);
			}
		});

		// Update timestamp - always show "Just now" after a manual refresh
		$stats.find('.mux-stats-updated').text('Updated: Just now');
	}

	/**
	 * Show refresh message
	 */
	function showRefreshMessage(type, message) {
		var $message = $('<div class="mux-stats-message mux-stats-message-' + type + '">' + message + '</div>');
		$('.mux-video-stats').prepend($message);

		setTimeout(function() {
			$message.fadeOut(300, function() {
				$(this).remove();
			});
		}, 3000);
	}

	/**
	 * Hook into WordPress media library events
	 */
	if (typeof wp !== 'undefined' && wp.media) {
		// Add stats when attachment details are rendered
		var originalRender = wp.media.view.Attachment.Details.prototype.render;
		wp.media.view.Attachment.Details.prototype.render = function() {
			var result = originalRender.apply(this, arguments);

			var self = this;
			setTimeout(function() {
				if (self.model) {
					var attachment = self.model.toJSON();
					addStatsToAttachmentDetails(attachment);
				}
			}, 100);

			return result;
		};

		// Also hook into ready event (safer approach)
		var originalReady = wp.media.view.Attachment.Details.prototype.ready;
		if (originalReady) {
			wp.media.view.Attachment.Details.prototype.ready = function() {
				if (originalReady) {
					originalReady.apply(this, arguments);
				}

				if (this.model) {
					var attachment = this.model.toJSON();
					addStatsToAttachmentDetails(attachment);
				}
			};
		}
	}

	// Also watch for DOM changes to catch dynamically loaded attachment details
	if (typeof MutationObserver !== 'undefined') {
		var observer = new MutationObserver(function(mutations) {
			mutations.forEach(function(mutation) {
				if (mutation.addedNodes.length > 0) {
					$(mutation.addedNodes).find('.attachment-details').each(function() {
						var $details = $(this);
						var attachmentId = $details.data('id');

						if (attachmentId && !statsAddedForAttachment[attachmentId]) {
							// Try to get attachment data from wp.media if available
							if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
								var attachment = wp.media.frame.state().get('selection').first();
								if (attachment && attachment.id === attachmentId) {
									addStatsToAttachmentDetails(attachment.toJSON());
								}
							}
						}
					});
				}
			});
		});

		// Start observing when document is ready
		$(document).ready(function() {
			var target = document.querySelector('.media-modal') || document.body;
			observer.observe(target, {
				childList: true,
				subtree: true
			});
		});
	}

})(jQuery);
