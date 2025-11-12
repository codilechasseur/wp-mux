(function( $ ) {
	'use strict';

	/**
	 * WP MUX Migrate Back JavaScript
	 */

	let videos = [];
	let currentIndex = 0;
	let isPaused = false;
	let completedCount = 0;
	let failedCount = 0;
	let failedVideos = [];
	let contentReferences = [];

	$(document).ready(function() {
		if (!$('.wp-mux-migration-page').length) {
			return;
		}

		// Get current tab from URL
		const urlParams = new URLSearchParams(window.location.search);
		const currentTab = urlParams.get('tab') || 'to-mux';
		
		// Only initialize on from-mux tab
		if (currentTab !== 'from-mux') {
			return;
		}

		initializeMigrateBack();
		runSystemCheck();
	});

	function initializeMigrateBack() {
		// Start scan button
		$('#btn-start-scan').on('click', function() {
			scanMuxVideos();
		});

		// Enable MP4 for all
		$('#btn-enable-mp4-all').on('click', function() {
			enableMP4ForAll();
		});

		// Step 3: Analyze content button
		$('#btn-analyze-content').on('click', function() {
			scanContent();
		});

		// Step 4: Start migration button
		$('#btn-start-migration').on('click', function() {
			startMigration();
		});

		// Step 5: Cleanup buttons
		$('#btn-delete-from-mux').on('click', function() {
			startCleanup();
		});
		
		$('#btn-cleanup').on('click', function() {
			startCleanup();
		});

		// Finish button
		$('#btn-finish').on('click', function() {
			window.location.href = '<?php echo admin_url("admin.php?page=wp-mux-videos"); ?>';
		});
	}

	function runSystemCheck() {
		$('#sidebar-requirements').html('<p class="description">Checking...</p>');

		$.ajax({
			url: wpMuxMigrateBack.ajaxUrl,
			type: 'POST',
			data: {
				action: 'migrate_back_system_check',
				nonce: wpMuxMigrateBack.nonce
			},
			success: function(response) {
				if (response.success) {
					displaySystemCheckInSidebar(response.data);
				} else {
					$('#sidebar-requirements').html('<p class="description" style="color: #d63638;">System check failed</p>');
				}
			},
			error: function(xhr, status, error) {
				$('#sidebar-requirements').html('<p class="description" style="color: #d63638;">System check failed</p>');
			}
		});
	}

	function displaySystemCheckInSidebar(data) {
		let html = '<ul style="margin: 0; padding: 0; list-style: none;">';
		let canMigrate = true;
		
		if (data.disk_space) {
			html += '<li style="padding: 4px 0; font-size: 13px;"><span style="color: #46b450;">✓</span> Disk: ' + data.disk_space + '</li>';
		} else {
			html += '<li style="padding: 4px 0; font-size: 13px;"><span style="color: #d63638;">✗</span> Insufficient disk space</li>';
			canMigrate = false;
		}

		if (data.writable) {
			html += '<li style="padding: 4px 0; font-size: 13px;"><span style="color: #46b450;">✓</span> Uploads writable</li>';
		} else {
			html += '<li style="padding: 4px 0; font-size: 13px;"><span style="color: #d63638;">✗</span> Uploads not writable</li>';
			canMigrate = false;
		}

		if (data.has_mp4_capability) {
			html += '<li style="padding: 4px 0; font-size: 13px;"><span style="color: #46b450;">✓</span> MP4 support enabled</li>';
		} else {
			html += '<li style="padding: 4px 0; font-size: 13px;"><span style="color: #d63638;">✗</span> MP4 not available</li>';
			canMigrate = false;
		}

		html += '</ul>';
		
		$('#sidebar-requirements').html(html);
		
		// Hide checking status
		$('#system-check-status').hide();
		
		// Show error if can't migrate
		if (!canMigrate && data.mp4_error_message) {
			$('#migration-error-message').text(data.mp4_error_message);
			$('#migration-not-available').show();
			$('#step-video-scan').hide();
		} else {
			$('#migration-not-available').hide();
			$('#step-video-scan').show();
			$('#btn-start-scan').prop('disabled', false);
		}
	}

	function scanMuxVideos() {
		$('#scan-progress').show();
		$('#scan-results').hide();
		$('#btn-start-scan').prop('disabled', true).text('Scanning...');

		$.ajax({
			url: wpMuxMigrateBack.ajaxUrl,
			type: 'POST',
			data: {
				action: 'migrate_back_scan',
				nonce: wpMuxMigrateBack.nonce
			},
			success: function(response) {
				if (response.success) {
					videos = response.data.videos || [];
					displayScanResults(response.data);
				} else {
					showError('Failed to scan videos: ' + response.data);
				}
			},
			error: function(xhr) {
				const msg = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : 'Failed to scan videos';
				showError(msg);
			},
			complete: function() {
				$('#scan-progress').hide();
				$('#btn-start-scan').prop('disabled', false).text('Rescan Videos');
			}
		});
	}

	function displayScanResults(data) {
		if (videos.length === 0) {
			$('#scan-results').html('<p>No Mux videos found.</p>').show();
			return;
		}

		// Update summary boxes
		const needsMP4 = videos.filter(v => !v.has_mp4).length;
		$('#migratable-count').text(videos.length - needsMP4);
		$('#not-migratable-count').text(needsMP4);
		
		// Show results
		$('#scan-results').show();
		$('#scan-actions').show();

		if (needsMP4 > 0) {
			$('#not-migratable-section').show();
			$('#btn-enable-mp4-all').show();
		} else {
			$('#not-migratable-section').hide();
			$('#btn-enable-mp4-all').hide();
		}
	}

	function enableMP4ForAll() {
		const videosNeedingMP4 = videos.filter(v => !v.has_mp4);
		if (videosNeedingMP4.length === 0) {
			$('#step-3').show();
			return;
		}

		$('#enable-mp4-all').prop('disabled', true);
		let completed = 0;

		function enableNext(index) {
			if (index >= videosNeedingMP4.length) {
				// Done
				$('#enable-mp4-all').prop('disabled', false);
				alert('MP4 support enabled for all videos!');
				scanMuxVideos(); // Rescan to update status
				return;
			}

			const video = videosNeedingMP4[index];
			
			$.ajax({
				url: wpMuxMigrateBack.ajaxUrl,
				type: 'POST',
				data: {
					action: 'migrate_back_enable_mp4',
					nonce: wpMuxMigrateBack.nonce,
					asset_id: video.asset_id
				},
				success: function(response) {
					completed++;
					video.has_mp4 = true;
					enableNext(index + 1);
				},
				error: function(xhr) {
					enableNext(index + 1);
				}
			});
		}

		enableNext(0);
	}

	function scanContent() {
		$('#content-scan-progress').show();
		$('#content-results').hide();
		$('#scan-content').prop('disabled', true);

		$.ajax({
			url: wpMuxMigrateBack.ajaxUrl,
			type: 'POST',
			data: {
				action: 'migrate_back_scan_content',
				nonce: wpMuxMigrateBack.nonce
			},
			success: function(response) {
				if (response.success) {
					contentReferences = response.data;
					displayContentResults(response.data);
				} else {
					showError('Failed to scan content');
				}
			},
			error: function() {
				showError('Failed to scan content');
			},
			complete: function() {
				$('#content-scan-progress').hide();
				$('#scan-content').prop('disabled', false);
			}
		});
	}

	function displayContentResults(data) {
		let html = '<h3>Content Analysis</h3>';
		
		if (data.total_posts === 0) {
			html += '<p>No posts found using Mux videos.</p>';
		} else {
			html += '<p>Found <strong>' + data.total_posts + ' posts</strong> using Mux videos.</p>';
			html += '<p>Total video references: <strong>' + data.total_references + '</strong></p>';
			
			if (data.posts && data.posts.length > 0) {
				html += '<ul class="post-list">';
				data.posts.forEach(function(post) {
					html += '<li>';
					html += '<strong>' + post.title + '</strong> ';
					html += '(' + post.references + ' reference' + (post.references > 1 ? 's' : '') + ')';
					html += '</li>';
				});
				html += '</ul>';
			}
		}

		$('#content-results').html(html).show();
		$('#step-3').show();
		$('#step-4').show();
	}

	function startMigration() {
		if (videos.length === 0) {
			alert('No videos to migrate');
			return;
		}

		currentIndex = 0;
		completedCount = 0;
		failedCount = 0;
		failedVideos = [];
		isPaused = false;

		$('#migration-progress').show();
		$('#start-migration').hide();
		$('#pause-migration').show();
		updateProgressBar();
		
		migrateNext();
	}

	function migrateNext() {
		if (isPaused) {
			return;
		}

		if (currentIndex >= videos.length) {
			migrationComplete();
			return;
		}

		const video = videos[currentIndex];
		updateProgressBar();
		
		$('#current-video-name').text('Migrating: ' + video.title);

		$.ajax({
			url: wpMuxMigrateBack.ajaxUrl,
			type: 'POST',
			data: {
				action: 'migrate_back_video',
				nonce: wpMuxMigrateBack.nonce,
				attachment_id: video.attachment_id
			},
			success: function(response) {
				if (response.success) {
					completedCount++;
					video.migrated = true;
					video.local_file = response.data.file;
				} else {
					failedCount++;
					failedVideos.push(video);
					video.error = response.data || 'Unknown error';
				}
			},
			error: function(xhr) {
				failedCount++;
				failedVideos.push(video);
				const msg = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : 'Unknown error';
				video.error = msg;
			},
			complete: function() {
				currentIndex++;
				migrateNext();
			}
		});
	}

	function updateProgressBar() {
		const percent = videos.length > 0 ? Math.round((currentIndex / videos.length) * 100) : 0;
		$('#migration-progress-bar').css('width', percent + '%');
		$('#migration-status').text(currentIndex + ' / ' + videos.length + ' videos');
	}

	function pauseMigration() {
		isPaused = true;
		$('#pause-migration').hide();
		$('#resume-migration').show();
	}

	function resumeMigration() {
		isPaused = false;
		$('#pause-migration').show();
		$('#resume-migration').hide();
		migrateNext();
	}

	function migrationComplete() {
		$('#migration-progress').hide();
		$('#pause-migration').hide();
		$('#resume-migration').hide();
		
		let html = '<h3>Migration Complete</h3>';
		html += '<p><strong>' + completedCount + '</strong> videos migrated successfully.</p>';
		
		if (failedCount > 0) {
			html += '<p><strong>' + failedCount + '</strong> videos failed.</p>';
			html += '<ul class="failed-videos">';
			failedVideos.forEach(function(video) {
				html += '<li>' + video.title + ': ' + video.error + '</li>';
			});
			html += '</ul>';
		}

		$('#migration-complete-summary').html(html).show();
		$('#step-5').show();
	}

	function updateContent() {
		if (!contentReferences || !contentReferences.posts || contentReferences.posts.length === 0) {
			$('#step-6').show();
			return;
		}

		$('#update-content').prop('disabled', true);
		$('#content-update-progress').show();

		const postIds = contentReferences.posts.map(p => p.id);

		$.ajax({
			url: wpMuxMigrateBack.ajaxUrl,
			type: 'POST',
			data: {
				action: 'migrate_back_update_content',
				nonce: wpMuxMigrateBack.nonce,
				post_ids: postIds
			},
			success: function(response) {
				if (response.success) {
					displayContentUpdateResults(response.data);
				} else {
					showError('Failed to update content');
				}
			},
			error: function() {
				showError('Failed to update content');
			},
			complete: function() {
				$('#update-content').prop('disabled', false);
				$('#content-update-progress').hide();
			}
		});
	}

	function displayContentUpdateResults(data) {
		let html = '<h3>Content Updated</h3>';
		html += '<p>Updated <strong>' + data.updated + '</strong> posts.</p>';
		
		if (data.failed > 0) {
			html += '<p><strong>' + data.failed + '</strong> posts failed to update.</p>';
		}

		$('#content-update-results').html(html).show();
		$('#step-6').show();
	}

	function startCleanup() {
		const confirmed = confirm('Are you sure you want to delete videos from Mux? This cannot be undone.');
		if (!confirmed) {
			return;
		}

		$('#start-cleanup').prop('disabled', true);
		$('#cleanup-progress').show();

		let cleaned = 0;
		let failed = 0;

		function cleanupNext(index) {
			if (index >= videos.length) {
				displayCleanupResults(cleaned, failed);
				return;
			}

			const video = videos[index];
			if (!video.migrated) {
				cleanupNext(index + 1);
				return;
			}

			$.ajax({
				url: wpMuxMigrateBack.ajaxUrl,
				type: 'POST',
				data: {
					action: 'migrate_back_cleanup',
					nonce: wpMuxMigrateBack.nonce,
					attachment_id: video.attachment_id
				},
				success: function() {
					cleaned++;
				},
				error: function() {
					failed++;
				},
				complete: function() {
					$('#cleanup-status').text((index + 1) + ' / ' + videos.length);
					cleanupNext(index + 1);
				}
			});
		}

		cleanupNext(0);
	}

	function displayCleanupResults(cleaned, failed) {
		let html = '<h3>Cleanup Complete</h3>';
		html += '<p>Deleted <strong>' + cleaned + '</strong> videos from Mux.</p>';
		
		if (failed > 0) {
			html += '<p><strong>' + failed + '</strong> deletions failed.</p>';
		}

		$('#cleanup-results').html(html).show();
		$('#cleanup-progress').hide();
		$('#start-cleanup').prop('disabled', false);
		
		showCompletion();
	}

	function showCompletion() {
		$('#step-7').show();
	}

	function showError(message) {
		alert('Error: ' + message);
	}

})( jQuery );
