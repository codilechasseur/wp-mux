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

                    // Extend edit function to add theme selector
                    settings.edit = function(props) {
                        const { InspectorControls } = wp.blockEditor;
                        const { PanelBody, SelectControl } = wp.components;
                        const { Fragment } = wp.element;

                        // Get original edit component
                        const originalComponent = originalEdit(props);

                        // Check if this is a MUX video (by checking src or other indicators)
                        const isMuxVideo = props.attributes.src &&
                            (props.attributes.src.includes('mux.com') ||
                             props.attributes.src.includes('muxed.s3.amazonaws.com'));

                        if (!isMuxVideo) {
                            return originalComponent;
                        }

                        // Debug themes

                        // Log individual themes
                        if (window.wpMuxThemes) {
                            window.wpMuxThemes.forEach(theme => {
                            });
                        }

                        // Create theme options
                        const themeOptions = [
                            // Add all themes including default, but show proper names
                            ...(window.wpMuxThemes || []).map(theme => ({
                                label: theme.id === 'default' ? `${theme.name} (Default)` : theme.name,
                                value: theme.id
                            }))
                        ];

                        // Theme selector component
                        const themeSelector = wp.element.createElement(
                            InspectorControls,
                            {},
                            wp.element.createElement(
                                PanelBody,
                                {
                                    title: 'MUX Player Theme',
                                    initialOpen: false
                                },
                                wp.element.createElement(
                                    SelectControl,
                                    {
                                        label: 'Player Theme',
                                        value: props.attributes.mux_theme || 'default',
                                        options: themeOptions,
                                        onChange: function(value) {
                                            props.setAttributes({ mux_theme: value });
                                        }
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

                        // Try creating mux-player first (might be more reliable)
                        let muxElement;
                        if (customElements.get('mux-player')) {
                            muxElement = doc.createElement('mux-player');
                            muxElement.setAttribute('playback-id', playbackId);
                            muxElement.setAttribute('controls', '');
                            muxElement.setAttribute('stream-type', 'on-demand');
                            muxElement.setAttribute('poster', thumbnailUrl);
                        } else if (customElements.get('mux-video')) {
                            muxElement = doc.createElement('mux-video');
                            muxElement.setAttribute('playback-id', playbackId);
                            muxElement.setAttribute('controls', '');
                            muxElement.setAttribute('stream-type', 'on-demand');
                            muxElement.setAttribute('poster', thumbnailUrl);
                        } else {
                            // Fall back to regular video element but with some enhancements
                            muxElement = doc.createElement('video');
                            muxElement.setAttribute('controls', '');
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
