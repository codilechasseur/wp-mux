(function( $ ) {
	'use strict';

	/**
	 * Load the Mux Player script once, if the server didn't already enqueue it
	 * (it only does for players it rendered itself).
	 */
	function ensureMuxPlayer() {
		if (window.customElements && window.customElements.get('mux-player')) {
			return;
		}

		if (typeof wpMuxPublic === 'undefined' || !wpMuxPublic.playerScriptUrl) {
			return;
		}

		if (document.querySelector('script[data-wp-mux-player], script#mux-player-js')) {
			return;
		}

		const script = document.createElement('script');
		script.src = wpMuxPublic.playerScriptUrl;
		script.async = true;
		script.setAttribute('data-wp-mux-player', '');
		document.head.appendChild(script);
	}

	/**
	 * Convert MUX videos from regular video elements to mux-player elements
	 */
	function convertMuxVideos() {
		// Find all video elements with MUX URLs
		const videos = document.querySelectorAll('video[src*="stream.mux.com"]');

		if (videos.length) {
			ensureMuxPlayer();
		}

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

				// Create thumbnail URL for poster attribute (default settings)
				const thumbnailUrl = `https://image.mux.com/${playbackId}/thumbnail.jpg?time=0&width=640&height=360&fit_mode=smartcrop`;

				// Check if original video has controls attribute
				const hasControls = video.hasAttribute('controls');

				// Create mux-player element (preferred over mux-video)
				const muxPlayer = document.createElement('mux-player');
				muxPlayer.setAttribute('playback-id', playbackId);
				// Only add controls if original video had them
				if (hasControls) {
					muxPlayer.setAttribute('controls', '');
				}
				muxPlayer.setAttribute('stream-type', 'on-demand');

				// Copy over other attributes including custom poster if set
				const attributes = ['width', 'height', 'poster', 'style', 'class'];
				attributes.forEach(function(attr) {
					const value = video.getAttribute(attr);
					if (value) {
						muxPlayer.setAttribute(attr, value);
					}
				});

				// Add thumbnail as poster if not already set by user
				if (!muxPlayer.getAttribute('poster')) {
					muxPlayer.setAttribute('poster', thumbnailUrl);
				}

				// Apply privacy options (cookieless / no analytics) from settings.
				if (typeof wpMuxPublic !== 'undefined') {
					if (wpMuxPublic.disableCookies) {
						muxPlayer.setAttribute('disable-cookies', '');
					}
					if (wpMuxPublic.disableTracking) {
						muxPlayer.setAttribute('disable-tracking', '');
					}
				}
					// Replace the video element
					video.parentNode.replaceChild(muxPlayer, video);
				} catch (error) {
					// Silent error handling
				}
			}
		});
	}

	// Convert videos when DOM is ready; load the player for any <mux-player>
	// printed without it (e.g. by a theme).
	$(document).ready(function() {
		convertMuxVideos();

		if (document.querySelector('mux-player')) {
			ensureMuxPlayer();
		}
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

	// ---------------------------------------------------------------
	// Clip end-time: mux-player has no native end-time attribute.
	// We use data-end-time and pause via timeupdate.
	// ---------------------------------------------------------------
	function initVideoClipping() {
		document.querySelectorAll('mux-player[data-end-time]').forEach(function(player) {
			var endTime = parseFloat(player.getAttribute('data-end-time'));
			if (!endTime || endTime <= 0) return;
			// Avoid attaching multiple listeners.
			if (player._muxEndTimeInit) return;
			player._muxEndTimeInit = true;
			player.addEventListener('timeupdate', function() {
				if (player.currentTime >= endTime) {
					player.pause();
					// Clamp to exact end so scrubber lands correctly.
					if (player.currentTime > endTime) {
						player.currentTime = endTime;
					}
				}
			});
		});
	}

	$(document).ready(function() {
		initVideoClipping();
	});
	$(window).on('load', function() {
		setTimeout(initVideoClipping, 300);
	});

})( jQuery );
