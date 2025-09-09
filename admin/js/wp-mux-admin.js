(function( $ ) {
	'use strict';

	/**
	 * WP Mux Admin JavaScript
	 */

	let currentPage = 1;
	let isLoading = false;

	$(function() {
		// Only run on MUX videos page
		if (!$('.wp-mux-videos-page').length) {
			return;
		}

		// Check if MUX is configured
		if (!window.wpMuxData || !window.wpMuxData.isConfigured) {
			return;
		}

		initializeVideoManagement();
		initializeModal();
		loadVideos();
	});

	function initializeModal() {
		// Close modal when clicking overlay or close button
		$('#video-preview-modal .wp-mux-modal-overlay, .wp-mux-modal-close, #modal-close-btn').on('click', function() {
			closeVideoPreview();
		});

		// Copy playback URL
		$('#copy-playback-url').on('click', function() {
			const playbackId = $('#modal-playback-id').text();
			if (playbackId && playbackId !== '-') {
				const url = `https://stream.mux.com/${playbackId}.m3u8`;
				navigator.clipboard.writeText(url).then(function() {
					const $button = $('#copy-playback-url');
					const originalText = $button.text();
					$button.text('Copied!').prop('disabled', true);
					setTimeout(function() {
						$button.text(originalText).prop('disabled', false);
					}, 2000);
				});
			}
		});

		// Delete from modal
		$('#modal-delete-btn').on('click', function() {
			const assetId = $('#modal-asset-id').text();
			if (assetId && assetId !== '-') {
				deleteVideo(assetId);
				closeVideoPreview();
			}
		});

		// Prevent modal from closing when clicking inside content
		$('.wp-mux-modal-content').on('click', function(e) {
			e.stopPropagation();
		});

		// Close modal with Escape key
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('#video-preview-modal').is(':visible')) {
				closeVideoPreview();
			}
		});
	}

	function initializeVideoManagement() {
		// Upload button click
		$('#upload-new-video').on('click', function(e) {
			e.preventDefault();
			toggleUploadSection();
		});

		// Dropzone click
		$('#upload-dropzone').on('click', function() {
			$('#video-file-input').click();
		});

		// File input change
		$('#video-file-input').on('change', function() {
			const file = this.files[0];
			if (file) {
				handleFileUpload(file);
			}
		});

		// Drag and drop
		$('#upload-dropzone').on('dragover dragenter', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).addClass('dragover');
		});

		$('#upload-dropzone').on('dragleave', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).removeClass('dragover');
		});

		$('#upload-dropzone').on('drop', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).removeClass('dragover');

			const files = e.originalEvent.dataTransfer.files;
			if (files.length > 0) {
				handleFileUpload(files[0]);
			}
		});

		// Pagination
		$('#prev-page').on('click', function() {
			if (currentPage > 1) {
				currentPage--;
				loadVideos();
			}
		});

		$('#next-page').on('click', function() {
			currentPage++;
			loadVideos();
		});

		$('#current-page').on('change', function() {
			const page = parseInt($(this).val());
			if (page > 0) {
				currentPage = page;
				loadVideos();
			}
		});
	}

	function toggleUploadSection() {
		const $section = $('#upload-section');
		if ($section.is(':visible')) {
			$section.slideUp();
		} else {
			$section.slideDown();
		}
	}

	function handleFileUpload(file) {
		// Validate file type
		if (!file.type.startsWith('video/')) {
			showError('Please select a valid video file.');
			return;
		}

		// Hide previous messages
		$('#upload-success, #upload-error').hide();

		// Create direct upload
		createDirectUpload(file);
	}

	function createDirectUpload(file) {
		$.ajax({
			url: window.wpMuxData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wp_mux_create_upload',
				nonce: window.wpMuxData.uploadNonce
			},
			beforeSend: function() {
				showUploadProgress(0);
			},
			success: function(response) {
				if (response.success) {
					uploadFileToMux(file, response.data.data);
				} else {
					showUploadError(response.data || window.wpMuxData.strings.uploadError);
				}
			},
			error: function() {
				showUploadError(window.wpMuxData.strings.uploadError);
			}
		});
	}

	function uploadFileToMux(file, uploadData) {
		const formData = new FormData();
		formData.append('file', file);

		$.ajax({
			url: uploadData.url,
			type: 'PUT',
			data: file,
			processData: false,
			contentType: false,
			xhr: function() {
				const xhr = new window.XMLHttpRequest();
				xhr.upload.addEventListener('progress', function(e) {
					if (e.lengthComputable) {
						const percentComplete = Math.round((e.loaded / e.total) * 100);
						showUploadProgress(percentComplete);
					}
				}, false);
				return xhr;
			},
			success: function() {
				showUploadProgress(100);
				showUploadSuccess();
				// Reload videos after successful upload
				setTimeout(function() {
					loadVideos();
					hideUploadSection();
				}, 2000);
			},
			error: function() {
				showUploadError(window.wpMuxData.strings.uploadError);
			}
		});
	}

	function showUploadProgress(percent) {
		$('#upload-progress').show();
		$('#upload-progress .progress-fill').css('width', percent + '%');
		$('#upload-progress .progress-text').text(percent + '%');
	}

	function showUploadSuccess() {
		$('#upload-progress').hide();
		$('#upload-success').show();
	}

	function showUploadError(message) {
		$('#upload-progress').hide();
		$('#upload-error p').text(message);
		$('#upload-error').show();
	}

	function hideUploadSection() {
		setTimeout(function() {
			$('#upload-section').slideUp();
			$('#upload-progress, #upload-success, #upload-error').hide();
			$('#video-file-input').val('');
		}, 3000);
	}

	function loadVideos() {
		if (isLoading) {
			return;
		}

		isLoading = true;

		$.ajax({
			url: window.wpMuxData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wp_mux_load_videos',
				nonce: window.wpMuxData.videosNonce,
				page: currentPage
			},
			beforeSend: function() {
				$('#videos-loading').show();
				$('#videos-list, #videos-error, #no-videos').hide();
			},
			success: function(response) {
				if (response.success) {
					displayVideos(response.data);
				} else {
					showVideosError(response.data || window.wpMuxData.strings.loadingError);
				}
			},
			error: function() {
				showVideosError(window.wpMuxData.strings.loadingError);
			},
			complete: function() {
				isLoading = false;
				$('#videos-loading').hide();
			}
		});
	}

	function displayVideos(data) {
		const videos = data.data || [];
		const $tbody = $('#videos-table-body');

		// Debug: Log the first video to see the structure
		if (videos.length > 0) {
			console.log('MUX Video Data Structure:', videos[0]);
			console.log('Available date fields:', {
				created_at: videos[0].created_at,
				created: videos[0].created,
				date_created: videos[0].date_created,
				upload_time: videos[0].upload_time,
				timestamp: videos[0].timestamp
			});
		}

		if (videos.length === 0) {
			$('#no-videos').show();
			return;
		}

		$tbody.empty();

		videos.forEach(function(video) {
			const row = createVideoRow(video);
			$tbody.append(row);
		});

		updatePagination(data);
		$('#videos-list').show();
	}

	function createVideoRow(video) {
		// MUX provides thumbnails via their image API using playback ID
		const playbackId = video.playback_ids && video.playback_ids.length > 0 ? video.playback_ids[0].id : null;

		// Use enhanced thumbnail URL if available, otherwise generate it
		const thumbnail = video.thumbnail_url
			? `<img src="${video.thumbnail_url}" alt="Video thumbnail" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
			   <span class="dashicons dashicons-format-video" style="display:none;"></span>`
			: (playbackId
				? `<img src="https://image.mux.com/${playbackId}/thumbnail.jpg?width=240&height=160" alt="Video thumbnail" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
				   <span class="dashicons dashicons-format-video" style="display:none;"></span>`
				: '<span class="dashicons dashicons-format-video"></span>');

		const status = getStatusBadge(video.status);
		const duration = formatDuration(video.duration);

		// Use estimated file size if available, otherwise try to compute
		const fileSize = video.estimated_file_size || getVideoFileSize(video);
		const size = formatFileSize(fileSize);

		// Try to get the created date from various possible fields
		const createdDateValue = getVideoCreatedDate(video);
		const created = formatDate(createdDateValue);

		return `
			<tr data-asset-id="${video.id}">
				<td class="column-thumbnail">
					<div class="wp-mux-video-thumbnail" ${playbackId ? `onclick="openVideoPreview('${video.id}', '${playbackId}')"` : ''}>
						${thumbnail}
					</div>
				</td>
				<td class="column-title">
					<strong>${video.id}</strong>
				</td>
				<td class="column-status">${status}</td>
				<td class="column-duration">${duration}</td>
				<td class="column-size">${size}</td>
				<td class="column-created">${created}</td>
				<td class="column-actions">
					<div class="wp-mux-video-actions">
						${playbackId ?
							`<button class="button button-small" onclick="copyPlaybackId('${playbackId}')">Copy ID</button>` : ''
						}
						${playbackId ?
							`<button class="button button-small" onclick="openVideoPreview('${video.id}', '${playbackId}')">Preview</button>` : ''
						}
						<button class="button button-small button-link-delete" onclick="deleteVideo('${video.id}')">Delete</button>
					</div>
				</td>
			</tr>
		`;
	}

	function getStatusBadge(status) {
		const statusClass = status === 'ready' ? 'ready' : status === 'preparing' ? 'processing' : 'error';
		const statusText = status === 'ready' ? window.wpMuxData.strings.ready :
			status === 'preparing' ? window.wpMuxData.strings.processing :
			window.wpMuxData.strings.error;

		return `<span class="wp-mux-status ${statusClass}">${statusText}</span>`;
	}

	function formatDuration(seconds) {
		if (!seconds) return '--';

		const hours = Math.floor(seconds / 3600);
		const minutes = Math.floor((seconds % 3600) / 60);
		const secs = Math.floor(seconds % 60);

		if (hours > 0) {
			return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
		} else {
			return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
		}
	}

	function formatFileSize(bytes) {
		if (!bytes) return '--';

		const units = ['B', 'KB', 'MB', 'GB'];
		let i = 0;

		while (bytes >= 1024 && i < units.length - 1) {
			bytes /= 1024;
			i++;
		}

		return Math.round(bytes * 100) / 100 + ' ' + units[i];
	}

	function formatDate(dateString) {
		// Try multiple possible date values
		const possibleDates = [
			dateString,
			arguments[0] // In case video object is passed instead of string
		];

		for (let dateValue of possibleDates) {
			if (!dateValue) continue;

			try {
				// Handle if it's a video object, extract date fields
				if (typeof dateValue === 'object') {
					dateValue = dateValue.created_at || dateValue.created || dateValue.date_created || dateValue.upload_time;
				}

				if (!dateValue) continue;

				const date = new Date(dateValue);
				// Check if date is valid
				if (!isNaN(date.getTime())) {
					return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
				}

				// Try parsing Unix timestamp if it's a number
				if (typeof dateValue === 'number' || (typeof dateValue === 'string' && /^\d+$/.test(dateValue))) {
					const timestampDate = new Date(parseInt(dateValue) * 1000); // Convert seconds to milliseconds
					if (!isNaN(timestampDate.getTime())) {
						return timestampDate.toLocaleDateString() + ' ' + timestampDate.toLocaleTimeString();
					}
				}

			} catch (e) {
				console.log('Date parsing error:', e, 'for value:', dateValue);
				continue;
			}
		}

		return '--';
	}

	function getVideoCreatedDate(video) {
		// Try different possible date field names that MUX might use
		const dateFields = [
			'created_at',
			'created',
			'date_created',
			'upload_time',
			'timestamp',
			'created_date',
			'date'
		];

		for (let field of dateFields) {
			if (video[field]) {
				return video[field];
			}
		}

		return null;
	}

	function getVideoFileSize(video) {
		// Check if we already have an estimated file size from the server
		if (video.estimated_file_size) {
			return video.estimated_file_size;
		}

		// Check if we have input info with file size
		if (video.input_info && video.input_info.file_size) {
			return video.input_info.file_size;
		}

		// Check if we have it in the input settings
		if (video.input && video.input.file_size) {
			return video.input.file_size;
		}

		// Try to estimate based on duration and resolution
		if (video.duration) {
			let estimatedBitrate = 1000000; // 1 Mbps default

			// Adjust bitrate based on resolution if available
			if (video.aspect_ratio) {
				if (video.aspect_ratio.includes('1920') || video.aspect_ratio.includes('1080')) {
					estimatedBitrate = 3000000; // 3 Mbps for HD
				} else if (video.aspect_ratio.includes('1280') || video.aspect_ratio.includes('720')) {
					estimatedBitrate = 2000000; // 2 Mbps for 720p
				}
			}

			// Calculate estimated size in bytes
			return (video.duration * estimatedBitrate) / 8;
		}

		// Return 0 if no file size information is available
		return 0;
	}

	function updatePagination(data) {
		// Update page info (this is simplified, you might want to get actual pagination data from API)
		$('#current-page').val(currentPage);
		$('#videos-count').text(`${data.data.length} items`);

		// Enable/disable pagination buttons
		$('#prev-page').prop('disabled', currentPage <= 1);
		$('#next-page').prop('disabled', data.data.length < 25);
	}

	function showVideosError(message) {
		$('#videos-error p').text(message);
		$('#videos-error').show();
	}

	// Global functions for button actions
	window.copyPlaybackId = function(playbackId) {
		navigator.clipboard.writeText(playbackId).then(function() {
			// Show success message
			const $button = $(`button[onclick="copyPlaybackId('${playbackId}')"]`);
			const originalText = $button.text();
			$button.text('Copied!').prop('disabled', true);
			setTimeout(function() {
				$button.text(originalText).prop('disabled', false);
			}, 2000);
		});
	};

	window.deleteVideo = function(assetId) {
		if (!confirm(window.wpMuxData.strings.deleteConfirm)) {
			return;
		}

		$.ajax({
			url: window.wpMuxData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wp_mux_delete_video',
				nonce: window.wpMuxData.videosNonce,
				asset_id: assetId
			},
			beforeSend: function() {
				$(`tr[data-asset-id="${assetId}"]`).css('opacity', 0.5);
			},
			success: function(response) {
				if (response.success) {
					$(`tr[data-asset-id="${assetId}"]`).fadeOut(function() {
						$(this).remove();

						// Check if we need to reload the page
						if ($('#videos-table-body tr').length === 0) {
							loadVideos();
						}
					});
				} else {
					$(`tr[data-asset-id="${assetId}"]`).css('opacity', 1);
					alert(response.data || window.wpMuxData.strings.deleteError);
				}
			},
			error: function() {
				$(`tr[data-asset-id="${assetId}"]`).css('opacity', 1);
				alert(window.wpMuxData.strings.deleteError);
			}
		});
	};

	function showError(message) {
		alert(message);
	}

	// Global functions for video preview modal
	window.openVideoPreview = function(assetId, playbackId) {
		// Get video data from the table row
		const $row = $(`tr[data-asset-id="${assetId}"]`);
		const status = $row.find('.wp-mux-status').text();
		const duration = $row.find('.column-duration').text();
		const created = $row.find('.column-created').text();

		console.log('Opening preview for asset:', assetId, 'with created date:', created);

		// Populate modal with video data
		$('#modal-video-title').text(`Video Preview - ${assetId}`);
		$('#modal-asset-id').text(assetId);
		$('#modal-playback-id').text(playbackId);
		$('#modal-status').text(status);
		$('#modal-duration').text(duration);
		$('#modal-created').text(created);

		// Get additional video details if needed
		loadVideoDetails(assetId);

		// Create video player
		createVideoPlayer(playbackId);

		// Show modal
		$('#video-preview-modal').fadeIn(300);
		$('body').addClass('modal-open');
	};

	window.closeVideoPreview = function() {
		$('#video-preview-modal').fadeOut(300);
		$('body').removeClass('modal-open');

		// Clean up video player
		$('#video-player-container').empty();
	};

	function createVideoPlayer(playbackId) {
		const $container = $('#video-player-container');
		$container.html('<div class="wp-mux-video-loading">Loading video...</div>');

		// Check if we can load MUX player script
		if (typeof window.MuxPlayerElement === 'undefined') {
			// Load MUX player script dynamically
			const script = document.createElement('script');
			script.src = 'https://unpkg.com/@mux/mux-player';
			script.onload = function() {
				insertMuxPlayer(playbackId);
			};
			script.onerror = function() {
				// Fallback to HTML5 video
				insertHtml5Player(playbackId);
			};
			document.head.appendChild(script);
		} else {
			insertMuxPlayer(playbackId);
		}
	}

	function insertMuxPlayer(playbackId) {
		const $container = $('#video-player-container');
		$container.html(`
			<mux-player
				playback-id="${playbackId}"
				metadata-video-title="MUX Video"
				controls
				style="width: 100%; height: auto;"
			></mux-player>
		`);
	}

	function insertHtml5Player(playbackId) {
		const $container = $('#video-player-container');
		$container.html(`
			<video
				controls
				style="width: 100%; height: auto;"
				poster="https://image.mux.com/${playbackId}/thumbnail.jpg?width=640&height=360"
			>
				<source src="https://stream.mux.com/${playbackId}.m3u8" type="application/x-mpegURL">
				<source src="https://stream.mux.com/${playbackId}/medium.mp4" type="video/mp4">
				Your browser does not support the video tag.
			</video>
		`);
	}

	function loadVideoDetails(assetId) {
		// Make AJAX call to get detailed video info
		$.ajax({
			url: window.wpMuxData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wp_mux_get_video_details',
				nonce: window.wpMuxData.videosNonce,
				asset_id: assetId
			},
			success: function(response) {
				if (response.success && response.data.data) {
					const video = response.data.data;
					console.log('Detailed video data:', video);

					// Update any other details from the full video object if needed
					if (video.aspect_ratio) {
						console.log('Aspect ratio:', video.aspect_ratio);
					}
				}
			},
			error: function() {
				// Silently fail
				console.log('Failed to load detailed video info');
			}
		});
	}

})( jQuery );
