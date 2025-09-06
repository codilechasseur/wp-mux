(function( $ ) {
	'use strict';

	console.log('WP MUX: Public script loading...');

	/**
	 * Convert MUX videos from regular video elements to mux-player elements
	 */
	function convertMuxVideos() {
		console.log('WP MUX: Looking for videos to convert...');

		// Find all video elements with MUX URLs
		const videos = document.querySelectorAll('video[src*="stream.mux.com"]');
		console.log('WP MUX: Found', videos.length, 'potential MUX videos');

		videos.forEach(function(video) {
			const src = video.getAttribute('src');
			console.log('WP MUX: Checking video with src:', src);

			if (src && (src.includes('.mp4') || src.includes('.m3u8') || src.includes('mux=1'))) {
				console.log('WP MUX: Converting video with src:', src);

				// Extract playback ID
				try {
					const url = new URL(src);
					const pathname = url.pathname;
					let playbackId = pathname.substring(1); // Remove leading slash

					// Remove file extension
					if (playbackId.endsWith('.mp4')) {
						playbackId = playbackId.slice(0, -4);
					} else if (playbackId.endsWith('.m3u8')) {
						playbackId = playbackId.slice(0, -5);
					}

					console.log('WP MUX: Extracted playback ID:', playbackId);

					// Create thumbnail URL for poster attribute
					const thumbnailUrl = `https://image.mux.com/${playbackId}/thumbnail.jpg?width=640&height=360`;

					// Create mux-player element (preferred over mux-video)
					const muxPlayer = document.createElement('mux-player');
					muxPlayer.setAttribute('playback-id', playbackId);
					muxPlayer.setAttribute('controls', '');
					muxPlayer.setAttribute('stream-type', 'on-demand');

					// Copy over other attributes
					const attributes = ['width', 'height', 'poster', 'style', 'class'];
					attributes.forEach(function(attr) {
						const value = video.getAttribute(attr);
						if (value) {
							muxPlayer.setAttribute(attr, value);
						}
					});

					// Add thumbnail as poster if not already set
					if (!muxPlayer.getAttribute('poster')) {
						muxPlayer.setAttribute('poster', thumbnailUrl);
					}

					// Replace the video element
					video.parentNode.replaceChild(muxPlayer, video);

					console.log('WP MUX: Successfully replaced video element with mux-player');
				} catch (error) {
					console.error('WP MUX: Error converting video:', error);
				}
			}
		});
	}

	// Convert videos when DOM is ready
	$(document).ready(function() {
		console.log('WP MUX: DOM ready, converting videos');
		convertMuxVideos();
	});

	// Also convert videos after a short delay (for dynamically loaded content)
	$(window).on('load', function() {
		setTimeout(function() {
			console.log('WP MUX: Window loaded, converting videos again');
			convertMuxVideos();
		}, 500);
	});

	// Convert videos when new content is added (using modern approach)
	if (typeof MutationObserver !== 'undefined') {
		const observer = new MutationObserver(function(mutations) {
			let shouldConvert = false;

			mutations.forEach(function(mutation) {
				if (mutation.type === 'childList') {
					mutation.addedNodes.forEach(function(node) {
						if (node.nodeType === Node.ELEMENT_NODE) {
							if (node.tagName === 'VIDEO' && node.src && node.src.includes('stream.mux.com')) {
								shouldConvert = true;
							} else if (node.querySelector && node.querySelector('video[src*="stream.mux.com"]')) {
								shouldConvert = true;
							}
						}
					});
				}
			});

			if (shouldConvert) {
				console.log('WP MUX: New video detected via observer, converting...');
				setTimeout(convertMuxVideos, 100);
			}
		});

		// Start observing once DOM is ready
		$(document).ready(function() {
			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
			console.log('WP MUX: MutationObserver set up');
		});
	}

	// Fallback for older browsers - check periodically
	setInterval(function() {
		const videos = document.querySelectorAll('video[src*="stream.mux.com"]');
		if (videos.length > 0) {
			console.log('WP MUX: Periodic check found videos, converting...');
			convertMuxVideos();
		}
	}, 2000);

})( jQuery );
