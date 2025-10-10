(function( $ ) {
	'use strict';

	/**
	 * Convert MUX videos from regular video elements to mux-player elements
	 */
	function convertMuxVideos() {
		// Find all video elements with MUX URLs
		const videos = document.querySelectorAll('video[src*="stream.mux.com"]');

		videos.forEach(function(video) {
			const src = video.getAttribute('src');

			if (src && (src.includes('.mp4') || src.includes('.m3u8') || src.includes('mux=1'))) {
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
				} catch (error) {
					// Silent error handling
				}
			}
		});
	}

	// Convert videos when DOM is ready
	$(document).ready(function() {
		convertMuxVideos();
	});

	// Also convert videos after a short delay (for dynamically loaded content)
	$(window).on('load', function() {
		setTimeout(function() {
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
				setTimeout(convertMuxVideos, 100);
			}
		});

		// Start observing once DOM is ready
		$(document).ready(function() {
			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
		});
	}

	// Fallback for older browsers - check periodically
	setInterval(function() {
		const videos = document.querySelectorAll('video[src*="stream.mux.com"]');
		if (videos.length > 0) {
			convertMuxVideos();
		}
	}, 2000);

})( jQuery );
