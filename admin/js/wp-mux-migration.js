(function( $ ) {
	'use strict';

	/**
	 * WP MUX Bulk Migration JavaScript
	 */

	let videos = [];
	let currentIndex = 0;
	let isPaused = false;
	let completedCount = 0;
	let failedCount = 0;
	let failedVideos = [];

	$(document).ready(function() {
		// Only run on migration page
		if (!$('.wp-mux-migration-page').length) {
			return;
		}

		initializeMigration();

		// Automatically scan for videos on page load
		scanForLocalVideos();
	});

	function initializeMigration() {
		// Start migration button
		$('#start-migration').on('click', function() {
			startMigration();
		});

		// Pause button
		$('#pause-migration').on('click', function() {
			pauseMigration();
		});

		// Resume button
		$('#resume-migration').on('click', function() {
			resumeMigration();
		});

		// Start new migration button
		$('#start-new-migration').on('click', function() {
			resetMigration();
			scanForLocalVideos();
		});
	}

	function scanForLocalVideos() {

		// Show progress
		$('#scan-progress').show();
		$('#scan-results').hide();
		$('#migration-progress').hide();
		$('#migration-complete').hide();
		$('.migration-controls button').prop('disabled', true);

		$.ajax({
			url: wpMuxMigration.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wp_mux_scan_local_videos',
				nonce: wpMuxMigration.nonce
			},
			success: function(response) {
				if (response.success) {
					videos = response.data.videos;
					displayScanResults(response.data);
				} else {
					showError(response.data.message || 'Failed to scan videos');
				}
			},
			error: function(xhr, status, error) {
				showError('Failed to scan videos: ' + error);
			},
			complete: function() {
				$('#scan-progress').hide();
				$('.migration-controls button').prop('disabled', false);
			}
		});
	}

	function displayScanResults(data) {

		if (data.total === 0) {
			$('#scan-progress').hide();
			$('#scan-results').html(
				'<div class="no-videos-found">' +
				'<div class="no-videos-icon"><span class="dashicons dashicons-video-alt2"></span></div>' +
				'<h2>No Local Videos Found</h2>' +
				'<p>' + wpMuxMigration.strings.noVideosFound + '</p>' +
				'</div>'
			).show();
			$('.migration-controls button').prop('disabled', false);
			return;
		}

		// Update summary
		$('#total-videos').text(data.total);
		$('#total-size').text(data.total_size_formatted);

		// Estimate time (rough: 30 seconds per video)
		const estimatedMinutes = Math.ceil((data.total * 30) / 60);
		$('#estimated-time').text('~' + estimatedMinutes + ' min');

		// Display videos list
		const $videosList = $('#videos-list');
		$videosList.empty();

		videos.forEach(function(video, index) {
			const $videoItem = $('<div class="video-item" data-index="' + index + '">');
			$videoItem.html(
				'<div class="video-info">' +
					'<div class="video-title">' + escapeHtml(video.title) + '</div>' +
					'<div class="video-meta">' +
						'<span class="video-filename">' + escapeHtml(video.filename) + '</span> | ' +
						'<span class="video-size">' + escapeHtml(video.size_formatted) + '</span> | ' +
						'<span class="video-date">' + escapeHtml(video.date_formatted) + '</span>' +
					'</div>' +
				'</div>' +
				'<div class="video-status">' +
					'<span class="status-badge status-pending">' + wpMuxMigration.strings.pending + '</span>' +
				'</div>'
			);
			$videosList.append($videoItem);
		});

		$('#scan-results').show();
		$('#start-migration').prop('disabled', false).show();
	}

	function startMigration() {

		// Reset counters
		currentIndex = 0;
		completedCount = 0;
		failedCount = 0;
		failedVideos = [];
		isPaused = false;

		// Save the delete option setting
		const deleteLocal = $('#delete_local_after_migration').is(':checked');
		$.ajax({
			url: wpMuxMigration.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wp_mux_save_delete_option',
				nonce: wpMuxMigration.nonce,
				delete_local: deleteLocal ? '1' : '0'
			}
		});

		// Hide scan results, show migration progress
		$('#scan-results').hide();
		$('#migration-progress').show();
		$('#migration-complete').removeClass('show').hide();
		$('#start-migration').hide();
		$('#pause-migration').show();
		$('#resume-migration').hide();

		// Update progress display
		$('#progress-total').text(videos.length);
		$('#progress-current').text(0);
		$('#progress-percentage').text(0);
		$('#overall-progress-fill').css('width', '0%');

		// Clear log
		$('#migration-log').empty();

		// Start processing
		processNextVideo();
	}

	function pauseMigration() {
		isPaused = true;
		$('#pause-migration').hide();
		$('#resume-migration').show();
		addLogEntry('Migration paused by user', 'info');
	}

	function resumeMigration() {
		isPaused = false;
		$('#pause-migration').show();
		$('#resume-migration').hide();
		addLogEntry('Migration resumed', 'info');
		processNextVideo();
	}

	function processNextVideo() {
		// Check if paused
		if (isPaused) {
			return;
		}

		// Check if completed
		if (currentIndex >= videos.length) {
			completeMigration();
			return;
		}

		const video = videos[currentIndex];

		// Update UI
		updateVideoStatus(currentIndex, 'processing');
		$('#current-video-info').html(
			'<strong>' + escapeHtml(video.title) + '</strong><br>' +
			'<span class="video-filename">' + escapeHtml(video.filename) + '</span>'
		);
		$('#current-video-progress').css('width', '0%');

		addLogEntry('Starting migration: ' + video.title, 'info');

		// Migrate the video
		$.ajax({
			url: wpMuxMigration.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wp_mux_migrate_single_video',
				nonce: wpMuxMigration.nonce,
				attachment_id: video.id
			},
			success: function(response) {
				if (response.success) {
					completedCount++;
					updateVideoStatus(currentIndex, 'success');
					addLogEntry('✓ Successfully migrated: ' + video.title, 'success');

					if (response.data.file_deleted) {
						addLogEntry('  → Local file deleted', 'info');
					}
				} else {
					failedCount++;
					failedVideos.push({
						video: video,
						error: response.data.message
					});
					updateVideoStatus(currentIndex, 'failed');
					addLogEntry('✗ Failed to migrate: ' + video.title + ' - ' + response.data.message, 'error');
				}
			},
			error: function(xhr, status, error) {
				failedCount++;
				failedVideos.push({
					video: video,
					error: error
				});
				updateVideoStatus(currentIndex, 'failed');
				addLogEntry('✗ Error migrating: ' + video.title + ' - ' + error, 'error');
			},
			complete: function() {
				// Update progress
				currentIndex++;
				const percentage = Math.round((currentIndex / videos.length) * 100);
				$('#progress-current').text(currentIndex);
				$('#progress-percentage').text(percentage);
				$('#overall-progress-fill').css('width', percentage + '%');
				$('#current-video-progress').css('width', '100%');

				// Process next video after a short delay
				setTimeout(function() {
					processNextVideo();
				}, 500);
			}
		});
	}

	function updateVideoStatus(index, status) {
		const $videoItem = $('.video-item[data-index="' + index + '"]');
		const $statusBadge = $videoItem.find('.status-badge');

		$statusBadge.removeClass('status-pending status-processing status-success status-failed');

		switch (status) {
			case 'processing':
				$statusBadge.addClass('status-processing').text(wpMuxMigration.strings.processing);
				break;
			case 'success':
				$statusBadge.addClass('status-success').text(wpMuxMigration.strings.success);
				break;
			case 'failed':
				$statusBadge.addClass('status-failed').text(wpMuxMigration.strings.failed);
				break;
		}

		// Scroll to current video
		$videoItem[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	function addLogEntry(message, type) {
		const $log = $('#migration-log');
		const timestamp = new Date().toLocaleTimeString();
		const $entry = $('<div class="log-entry log-' + type + '">');
		$entry.html('<span class="log-time">[' + timestamp + ']</span> ' + escapeHtml(message));
		$log.append($entry);

		// Auto-scroll to bottom
		$log.scrollTop($log[0].scrollHeight);
	}

	function completeMigration() {

		// Hide migration progress
		$('#migration-progress').hide();
		$('#pause-migration').hide();
		$('#resume-migration').hide();

		// Show completion summary
		$('#completed-count').text(completedCount);
		$('#migration-complete').addClass('show').show();

		if (failedCount > 0) {
			$('#failed-count').text(failedCount);
			$('#failed-count-container').show();

			// Display failed videos
			const $failedList = $('#failed-videos');
			$failedList.empty();

			failedVideos.forEach(function(item) {
				const $failedItem = $('<div class="failed-video-item">');
				$failedItem.html(
					'<div class="failed-video-title">' + escapeHtml(item.video.title) + '</div>' +
					'<div class="failed-video-error">' + escapeHtml(item.error) + '</div>'
				);
				$failedList.append($failedItem);
			});

			$('#failed-videos-list').show();
		}

		addLogEntry('Migration completed! ' + completedCount + ' succeeded, ' + failedCount + ' failed.', 'info');
	}

	function resetMigration() {
		videos = [];
		currentIndex = 0;
		completedCount = 0;
		failedCount = 0;
		failedVideos = [];
		isPaused = false;

		$('#scan-results').hide();
		$('#migration-progress').hide();
		$('#migration-complete').removeClass('show').hide();
		$('#start-migration').hide().prop('disabled', true);
		$('#pause-migration').hide();
		$('#resume-migration').hide();
		$('.migration-controls button').prop('disabled', false);
		$('#scan-videos').show();
	}

	function showError(message) {
		const $error = $('<div class="notice notice-error is-dismissible"><p>' + escapeHtml(message) + '</p></div>');
		$('.wp-mux-migration-page h1').after($error);

		// Auto-dismiss after 5 seconds
		setTimeout(function() {
			$error.fadeOut(function() {
				$(this).remove();
			});
		}, 5000);
	}

	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
	}

})( jQuery );
