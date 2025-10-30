(function() {
    'use strict';


    // Register mux-video as a valid element for Gutenberg
    if (typeof wp !== 'undefined' && wp.blocks) {
        // Add mux-video to the list of valid HTML elements
        wp.hooks.addFilter(
            'blocks.getSaveContent.extraProps',
            'wp-mux/allow-mux-video',
            function(props, blockType, attributes) {
                return props;
            }
        );

        // Allow mux-video element in content
        wp.hooks.addFilter(
            'blocks.registerBlockType',
            'wp-mux/register-mux-video',
            function(settings, name) {
                if (name === 'core/video') {
                    // Extend video block to allow mux-video elements
                    if (settings.supports) {
                        settings.supports.html = true;
                    }
                }
                return settings;
            }
        );

        // Add theme selector to video block
        wp.hooks.addFilter(
            'blocks.registerBlockType',
            'wp-mux/add-theme-selector',
            function(settings, name) {
                if (name === 'core/video') {
                    // Add mux_theme attribute
                    if (!settings.attributes) {
                        settings.attributes = {};
                    }
                    settings.attributes.mux_theme = {
                        type: 'string',
                        default: ''
                    };

                    // Store original edit function
                    const originalEdit = settings.edit;

                    // Extend edit function to add theme selector and MUX status
                    settings.edit = function(props) {
                        const { InspectorControls } = wp.blockEditor;
                        const { PanelBody, SelectControl, Notice } = wp.components;
                        const { Fragment, useState, useEffect } = wp.element;

                        // State for MUX video detection - MUST be at the top before any returns
                        const [isMuxVideo, setIsMuxVideo] = useState(false);
                        const [muxStatus, setMuxStatus] = useState(null);
                        const [playbackId, setPlaybackId] = useState(null);
                        const [isChecking, setIsChecking] = useState(false);
                        const [wasMonitoring, setWasMonitoring] = useState(false); // Track if we were actively monitoring

                        // Check if this is a MUX video by attachment ID or URL
                        useEffect(() => {
                            let interval = null;

                            const checkMuxStatus = async () => {
                                if (isChecking) return; // Prevent concurrent checks

                                setIsChecking(true);

                                // Check by attachment ID first
                                if (props.attributes.id) {
                                    try {
                                        const response = await wp.apiFetch({
                                            path: `/wp/v2/media/${props.attributes.id}`
                                        });


                                        if (response.mime_type === 'video/mux' || response.subtype === 'mux') {
                                            console.log('MUX Video detected:', response);
                                            setIsMuxVideo(true);
                                            setMuxStatus(response.mux_status || 'complete');
                                            setPlaybackId(response.mux_playback_id || response.playback_id);
                                            console.log('Set state:', {
                                                isMuxVideo: true,
                                                muxStatus: response.mux_status || 'complete',
                                                playbackId: response.mux_playback_id || response.playback_id
                                            });

                                            // Update block attributes to use MUX URL if it changed
                                            if (response.url && response.url !== props.attributes.src) {
                                                props.setAttributes({ src: response.url });
                                            }

                                            // Stop polling if complete
                                            if (response.mux_status === 'complete') {
                                                if (interval) {
                                                    clearInterval(interval);
                                                    interval = null;
                                                }
                                                // Mark that we were actively monitoring, show completion message briefly
                                                if (wasMonitoring) {
                                                    // After 3 seconds, hide the status message
                                                    setTimeout(() => {
                                                        setWasMonitoring(false);
                                                    }, 3000);
                                                }
                                            }
                                            setIsChecking(false);
                                            return;
                                        } else if (response.mime_type && response.mime_type.startsWith('video/')) {
                                            // It's a video but not MUX yet - keep checking
                                            setIsMuxVideo(false);
                                            setMuxStatus('checking');
                                            setWasMonitoring(true); // Mark that we're actively monitoring
                                        }
                                    } catch (error) {
                                    }
                                }

                                // Check by URL
                                if (props.attributes.src) {
                                    const isMuxUrl = props.attributes.src.includes('mux.com') ||
                                                   props.attributes.src.includes('muxed.s3.amazonaws.com');
                                    if (isMuxUrl) {
                                        setIsMuxVideo(true);
                                    }
                                }

                                setIsChecking(false);
                            };

                            // Initial check
                            checkMuxStatus();

                            // Always poll for video attachments to detect when they become MUX videos
                            // Poll more frequently at first, then slow down
                            if (props.attributes.id && props.attributes.src) {
                                // Poll every 3 seconds for video attachments
                                interval = setInterval(checkMuxStatus, 3000);

                                // After 2 minutes, slow down to every 10 seconds
                                setTimeout(() => {
                                    if (interval) {
                                        clearInterval(interval);
                                        interval = setInterval(checkMuxStatus, 10000);
                                    }
                                }, 120000);

                                // Stop polling after 5 minutes
                                setTimeout(() => {
                                    if (interval) {
                                        clearInterval(interval);
                                    }
                                }, 300000);
                            }

                            return () => {
                                if (interval) {
                                    clearInterval(interval);
                                }
                            };
                        }, [props.attributes.id, props.attributes.src]);

                        // Trigger conversion when MUX processing is complete
                        // Let React re-render the video block with the new MUX URL first,
                        // then let the existing convertMuxVideos() function handle the conversion
                        useEffect(() => {
                            console.log('MUX conversion trigger:', { isMuxVideo, playbackId, muxStatus });

                            if (!isMuxVideo || !playbackId || muxStatus !== 'complete') {
                                return;
                            }

                            // Give React time to re-render the video block with the new src,
                            // then trigger the existing conversion function with increasing delays
                            // to handle various React render timing scenarios
                            console.log('MUX video ready, triggering convertMuxVideos()...');

                            const delays = [100, 500, 1000, 2000];
                            delays.forEach(delay => {
                                setTimeout(() => {
                                    if (typeof window.convertMuxVideos === 'function') {
                                        window.convertMuxVideos();
                                    }
                                }, delay);
                            });
                        }, [isMuxVideo, playbackId, muxStatus]);

                        // Get original edit component AFTER all hooks
                        const originalComponent = originalEdit(props);

                        // Show MUX controls for MUX videos OR videos being checked for MUX conversion
                        const showMuxControls = isMuxVideo || muxStatus === 'checking';

                        if (!showMuxControls) {
                            return originalComponent;
                        }

                        // Create theme options
                        const themeOptions = [
                            { label: 'Default Theme', value: 'default' },
                            ...(window.wpMuxThemes || []).map(theme => ({
                                label: theme.id === 'default' ? `${theme.name} (Default)` : theme.name,
                                value: theme.id
                            }))
                        ];

                        // Status notice - only show if actively monitoring
                        let statusNotice = null;
                        if (muxStatus === 'checking') {
                            statusNotice = wp.element.createElement(
                                'div',
                                {
                                    style: {
                                        backgroundColor: '#e6f2ff',
                                        padding: '12px 16px',
                                        borderRadius: '4px',
                                        borderLeft: '4px solid #0073aa'
                                    }
                                },
                                wp.element.createElement(
                                    Notice,
                                    {
                                        status: 'info',
                                        isDismissible: false
                                    },
                                    'Video uploaded. Monitoring for MUX processing status...'
                                )
                            );
                        } else if (muxStatus === 'queued') {
                            statusNotice = wp.element.createElement(
                                'div',
                                {
                                    style: {
                                        backgroundColor: '#e6f2ff',
                                        padding: '12px 16px',
                                        borderRadius: '4px',
                                        borderLeft: '4px solid #0073aa'
                                    }
                                },
                                wp.element.createElement(
                                    Notice,
                                    {
                                        status: 'info',
                                        isDismissible: false
                                    },
                                    'MUX: Queued for processing. This usually takes a few moments.'
                                )
                            );
                        } else if (muxStatus === 'processing') {
                            statusNotice = wp.element.createElement(
                                'div',
                                {
                                    style: {
                                        backgroundColor: '#e6f2ff',
                                        padding: '12px 16px',
                                        borderRadius: '4px',
                                        borderLeft: '4px solid #0073aa'
                                    }
                                },
                                wp.element.createElement(
                                    Notice,
                                    {
                                        status: 'warning',
                                        isDismissible: false
                                    },
                                    'MUX: Processing video. Quality and optimized streaming will be available shortly.'
                                )
                            );
                        } else if (muxStatus === 'complete' && playbackId && wasMonitoring) {
                            // Only show completion message if we were actively monitoring this upload
                            // Wrap in a div with fade-out animation
                            statusNotice = wp.element.createElement(
                                'div',
                                {
                                    style: {
                                        animation: 'fadeOut 0.5s ease-in-out 2.5s forwards'
                                    }
                                },
                                wp.element.createElement(
                                    Notice,
                                    {
                                        status: 'success',
                                        isDismissible: false
                                    },
                                    'MUX video ready and optimized for streaming!'
                                )
                            );
                        }

                        // Theme selector component
                        const themeSelector = wp.element.createElement(
                            InspectorControls,
                            {},
                            wp.element.createElement(
                                PanelBody,
                                {
                                    title: 'MUX Player Settings',
                                    initialOpen: true,
                                    style: { marginBottom: '16px' }
                                },
                                statusNotice ? wp.element.createElement(
                                    'div',
                                    { style: { marginBottom: '16px' } },
                                    statusNotice
                                ) : null,
                                wp.element.createElement(
                                    SelectControl,
                                    {
                                        label: 'Player Theme',
                                        value: props.attributes.mux_theme || 'default',
                                        options: themeOptions,
                                        onChange: function(value) {
                                            props.setAttributes({ mux_theme: value });
                                        },
                                        help: 'Choose a custom theme for the MUX player'
                                    }
                                )
                            )
                        );

                        return wp.element.createElement(
                            Fragment,
                            {},
                            originalComponent,
                            themeSelector
                        );
                    };
                }
                return settings;
            }
        );
    }

    // Function to convert MUX videos
    function convertMuxVideos() {

        // Look in multiple contexts - main document, iframes, etc.
        const contexts = [document];

        // Check for editor iframes
        const iframes = document.querySelectorAll('iframe');
        iframes.forEach(function(iframe) {
            try {
                if (iframe.contentDocument) {
                    contexts.push(iframe.contentDocument);
                }
            } catch (e) {
                // Cross-origin iframe, skip
            }
        });

        let totalVideos = 0;

        contexts.forEach(function(doc) {
            const videos = doc.querySelectorAll('video[src*="stream.mux.com"]');
            totalVideos += videos.length;

            videos.forEach(function(video) {
                const src = video.getAttribute('src');
                if (src && (src.includes('.mp4') || src.includes('.m3u8') || src.includes('mux=1'))) {

                    try {
                        // Extract playback ID
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

                        // Check if original video has controls attribute
                        const hasControls = video.hasAttribute('controls');

                        // Try creating mux-player first (might be more reliable)
                        let muxElement;
                        if (customElements.get('mux-player')) {
                            muxElement = doc.createElement('mux-player');
                            muxElement.setAttribute('playback-id', playbackId);
                            // Only add controls if original video had them
                            if (hasControls) {
                                muxElement.setAttribute('controls', '');
                            }
                            muxElement.setAttribute('stream-type', 'on-demand');
                            muxElement.setAttribute('poster', thumbnailUrl);
                        } else if (customElements.get('mux-video')) {
                            muxElement = doc.createElement('mux-video');
                            muxElement.setAttribute('playback-id', playbackId);
                            // Only add controls if original video had them
                            if (hasControls) {
                                muxElement.setAttribute('controls', '');
                            }
                            muxElement.setAttribute('stream-type', 'on-demand');
                            muxElement.setAttribute('poster', thumbnailUrl);
                        } else {
                            // Fall back to regular video element but with some enhancements
                            muxElement = doc.createElement('video');
                            // Only add controls if original video had them
                            if (hasControls) {
                                muxElement.setAttribute('controls', '');
                            }
                            muxElement.setAttribute('src', src);
                            muxElement.setAttribute('poster', thumbnailUrl);
                            muxElement.style.width = '100%';
                            muxElement.style.height = 'auto';
                        }

                        // Copy over other attributes
                        const attributes = ['width', 'height', 'poster', 'style', 'class'];
                        attributes.forEach(function(attr) {
                            const value = video.getAttribute(attr);
                            if (value) {
                                muxElement.setAttribute(attr, value);
                            }
                        });

                        // Ensure proper styling
                        muxElement.style.width = '100%';
                        muxElement.style.height = 'auto';
                        muxElement.style.minHeight = '200px';
                        muxElement.style.backgroundColor = '#000';

                        // Replace the video element
                        video.parentNode.replaceChild(muxElement, video);


                        // Force the element to initialize
                        setTimeout(function() {

                            // If it's a MUX element and has no children, try to force initialization
                            if ((muxElement.tagName === 'MUX-VIDEO' || muxElement.tagName === 'MUX-PLAYER') && muxElement.children.length === 0) {

                                // Try different initialization methods
                                if (muxElement.connectedCallback) {
                                    try {
                                        muxElement.connectedCallback();
                                    } catch (e) {
                                    }
                                }

                                // Try re-setting the playback-id
                                muxElement.setAttribute('playback-id', playbackId);

                                // Force a reflow
                                muxElement.style.display = 'none';
                                muxElement.offsetHeight; // trigger reflow
                                muxElement.style.display = 'block';

                                // Try dispatching events
                                try {
                                    const event = new CustomEvent('loadstart');
                                    muxElement.dispatchEvent(event);
                                } catch (e) {
                                }
                            }
                        }, 500);

                        // Final check after 2 seconds
                        setTimeout(function() {
                            if ((muxElement.tagName === 'MUX-VIDEO' || muxElement.tagName === 'MUX-PLAYER') && muxElement.children.length === 0) {

                                // Create thumbnail URL from playback ID
                                const thumbnailUrl = `https://image.mux.com/${playbackId}/thumbnail.jpg?width=640&height=360`;

                                // Add a thumbnail preview div inside the mux element
                                const placeholder = doc.createElement('div');
                                placeholder.style.cssText = `
                                    width: 100%;
                                    height: 200px;
                                    background-image: url('${thumbnailUrl}');
                                    background-size: cover;
                                    background-position: center;
                                    background-repeat: no-repeat;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    color: white;
                                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                                    font-size: 14px;
                                    border-radius: 4px;
                                    position: relative;
                                    cursor: pointer;
                                `;

                                // Add overlay with play button and info
                                placeholder.innerHTML = `
                                    <div style="
                                        position: absolute;
                                        top: 0;
                                        left: 0;
                                        right: 0;
                                        bottom: 0;
                                        background: rgba(0, 0, 0, 0.4);
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        border-radius: 4px;
                                    ">
                                        <div style="text-align: center;">
                                            <div style="
                                                font-size: 48px;
                                                margin-bottom: 8px;
                                                text-shadow: 0 2px 4px rgba(0,0,0,0.5);
                                            ">▶</div>
                                            <div style="
                                                font-weight: 500;
                                                text-shadow: 0 1px 2px rgba(0,0,0,0.7);
                                                margin-bottom: 4px;
                                            ">MUX Video Preview</div>
                                            <div style="
                                                font-size: 11px;
                                                opacity: 0.9;
                                                text-shadow: 0 1px 2px rgba(0,0,0,0.7);
                                            ">Will play on frontend</div>
                                        </div>
                                    </div>
                                `;

                                muxElement.appendChild(placeholder);
                            } else {
                            }
                        }, 2000);
                    } catch (error) {
                    }
                }
            });
        });

    }

    // Expose convertMuxVideos globally so it can be called from React components
    window.convertMuxVideos = convertMuxVideos;

    // Function to set up observers for each context
    function setupObservers() {
        const contexts = [document];

        // Check for editor iframes
        const iframes = document.querySelectorAll('iframe');
        iframes.forEach(function(iframe) {
            try {
                if (iframe.contentDocument) {
                    contexts.push(iframe.contentDocument);
                }
            } catch (e) {
                // Cross-origin iframe, skip
            }
        });

        contexts.forEach(function(doc) {
            // Make sure we have a valid body or documentElement to observe
            const target = doc.body || doc.documentElement;
            if (!target) return;

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

            observer.observe(target, {
                childList: true,
                subtree: true
            });
        });
    }

    // Wait for WordPress to be ready
    if (typeof wp !== 'undefined' && wp.domReady) {
        wp.domReady(function() {

            // Add CSS to make mux-video elements display properly in editor
            const style = document.createElement('style');
            style.textContent = `
                mux-video {
                    display: block;
                    width: 100%;
                    max-width: 100%;
                    height: auto;
                }
                .wp-block-video mux-video {
                    width: 100%;
                    height: auto;
                }
                @keyframes fadeOut {
                    0% {
                        opacity: 1;
                    }
                    100% {
                        opacity: 0;
                        display: none;
                        height: 0;
                        padding: 0;
                        margin: 0;
                        overflow: hidden;
                    }
                }
            `;
            document.head.appendChild(style);

            convertMuxVideos();
            setupObservers();
        });
    }

    // Also try with regular DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(convertMuxVideos, 500);
        setTimeout(setupObservers, 500);
    });

    // Try multiple times with delays to catch dynamically loaded content
    setTimeout(function() {
        convertMuxVideos();
        setupObservers();
    }, 1000);

    setTimeout(function() {
        convertMuxVideos();
    }, 3000);

    // Periodic check every few seconds
    setInterval(function() {
        const videos = document.querySelectorAll('video[src*="stream.mux.com"]');
        if (videos.length > 0) {
            convertMuxVideos();
        }
    }, 3000);

})();
