/**
 * WP MUX Theme Management JavaScript
 *
 * Handles theme management functionality in the admin area.
 *
 * @since    1.0.0
 */

(function($) {
    'use strict';

    /**
     * Theme management functionality
     */
    var WpMuxThemes = {

        /**
         * Initialize theme management
         */
        init: function() {
            this.bindEvents();
            this.initPreview();
            this.initControlsVisibility();
        },

        /**
         * Initialize controls visibility based on current state
         */
        initControlsVisibility: function() {
            // Check if the main controls toggle exists and is unchecked
            var $mainControlsToggle = $('.wp-mux-control-item[data-control="controls"] .control-toggle');
            if ($mainControlsToggle.length > 0) {
                this.handleMainControlsToggle($mainControlsToggle.is(':checked'));
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Theme form submission (form-based, not AJAX)
            $(document).on('submit', '.wp-mux-theme-form form', this.handleThemeSubmit);

            // Delete theme buttons
            $(document).on('click', '.wp-mux-delete-theme', this.handleThemeDelete);

            // Set default theme buttons
            $(document).on('click', '.wp-mux-set-default-theme', this.handleSetDefaultTheme);

            // Control checkboxes change
            $(document).on('change', '.control-toggle', this.handleControlToggle);

            // Style input changes
            $(document).on('change input', '.style-option input', this.updatePreview);

            // Theme name changes
            $(document).on('input', '#theme_name', this.updatePreview);
        },

        /**
         * Handle control toggle (show/hide styling options)
         */
        handleControlToggle: function() {
            var $checkbox = $(this);
            var $controlItem = $checkbox.closest('.wp-mux-control-item');
            var $styleOptions = $controlItem.find('.control-styles');
            var controlKey = $controlItem.data('control');

            if ($checkbox.is(':checked')) {
                $controlItem.addClass('control-enabled');
                $styleOptions.slideDown(200);
            } else {
                $controlItem.removeClass('control-enabled');
                $styleOptions.slideUp(200);
            }

            // Special handling for the main "controls" toggle
            if (controlKey === 'controls') {
                WpMuxThemes.handleMainControlsToggle($checkbox.is(':checked'));
            }

            WpMuxThemes.updatePreview();
        },

        /**
         * Handle the main "Show Controls" toggle
         */
        handleMainControlsToggle: function(enabled) {
            var $globalSection = $('.wp-mux-control-section[data-category="global"]');
            var $controlsToggle = $globalSection.find('[data-control="controls"]');
            var $dependentControls = $globalSection.find('[data-control="control-bar"], [data-control="play-button"], [data-control="primary-color"], [data-control="secondary-color"], [data-control="accent-color"]');

            if (enabled) {
                // Show dependent controls
                $dependentControls.slideDown(300);
                $globalSection.find('.wp-mux-controls-notice').remove();
            } else {
                // Hide dependent controls
                $dependentControls.slideUp(300);

                // Add a notice if it doesn't exist
                if ($globalSection.find('.wp-mux-controls-notice').length === 0) {
                    var $notice = $('<div class="wp-mux-controls-notice" style="margin-top: 15px; padding: 10px; background: #f0f6fc; border: 1px solid #c3dbf2; border-radius: 4px;">' +
                        '<p style="margin: 0; color: #0073aa;">' +
                        '<span class="dashicons dashicons-info" style="margin-right: 5px;"></span>' +
                        'All controls are disabled. Individual control settings are hidden since they won\'t be visible.' +
                        '</p></div>');
                    $globalSection.append($notice);
                }
            }
        },

        /**
         * Handle theme form submission
         */
        handleThemeSubmit: function(e) {
            // Let the form submit normally since we're using server-side processing
            // Just show a loading state
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"], button[type="submit"]');

            // Show loading state
            $submitButton.prop('disabled', true);
            var originalText = $submitButton.val() || $submitButton.text();
            $submitButton.val('Saving...').text('Saving...');

            // Re-enable after a delay (form will redirect anyway)
            setTimeout(function() {
                $submitButton.prop('disabled', false).val(originalText).text(originalText);
            }, 3000);
        },

        /**
         * Handle theme deletion
         */
        handleThemeDelete: function(e) {
            e.preventDefault();

            if (!confirm(wpMuxData.strings.deleteThemeConfirm)) {
                return;
            }

            var $button = $(this);
            var themeId = $button.data('theme-id');

            $button.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: wpMuxData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_mux_delete_theme',
                    theme_id: themeId,
                    nonce: wpMuxData.themesNonce
                },
                success: function(response) {
                    if (response.success) {
                        WpMuxThemes.showNotice('success', wpMuxData.strings.themeDeleted);

                        // Remove theme card
                        $button.closest('.wp-mux-theme-card').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        WpMuxThemes.showNotice('error', response.data || 'Error deleting theme');
                        $button.prop('disabled', false).text('Delete');
                    }
                },
                error: function() {
                    WpMuxThemes.showNotice('error', 'Network error occurred');
                    $button.prop('disabled', false).text('Delete');
                }
            });
        },

        /**
         * Handle setting default theme
         */
        handleSetDefaultTheme: function(e) {
            e.preventDefault();

            var $button = $(this);
            var themeId = $button.data('theme-id');

            $button.prop('disabled', true).text('Setting...');

            $.ajax({
                url: wpMuxData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_mux_set_default_theme',
                    theme_id: themeId,
                    nonce: wpMuxData.themesNonce
                },
                success: function(response) {
                    if (response.success) {
                        WpMuxThemes.showNotice('success', wpMuxData.strings.defaultThemeSet);

                        // Reload page to update default indicators
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        WpMuxThemes.showNotice('error', response.data || 'Error setting default theme');
                        $button.prop('disabled', false).text('Set Default');
                    }
                },
                error: function() {
                    WpMuxThemes.showNotice('error', 'Network error occurred');
                    $button.prop('disabled', false).text('Set Default');
                }
            });
        },

        /**
         * Initialize preview functionality
         */
        initPreview: function() {
            this.updatePreview();

            // Initialize control toggles
            $('.control-toggle').each(function() {
                WpMuxThemes.handleControlToggle.call(this);
            });
        },

        /**
         * Update theme preview
         */
        updatePreview: function() {
            // Only update preview if we're in edit mode (editing a theme)
            // Check if we have control toggles present (means we're editing)
            if ($('.control-toggle').length === 0) return;

            // Only update previews that are NOT in theme cards (i.e., only the editing form preview)
            // Theme cards should maintain their static counts based on saved data
            var $preview = $('.wp-mux-theme-preview').not('.wp-mux-theme-card .wp-mux-theme-preview');
            if ($preview.length === 0) return;

            // Check if main controls toggle is disabled
            var mainControlsEnabled = $('[name="controls[controls]"]').is(':checked');
            var themeName = $('#theme_name').val() || 'Unnamed Theme';

            // Update preview info based on main controls state
            if (!mainControlsEnabled) {
                // When main controls are disabled, show "Controls Disabled"
                $preview.find('.preview-controls').text('Controls Disabled');
            } else {
                // Count controls excluding the global "Show All Controls" toggle and color controls
                var enabledControls = $('.control-toggle:checked')
                    .not('[name="controls[controls]"]')
                    .not('[name="controls[primary-color]"]')
                    .not('[name="controls[secondary-color]"]').length;
                var totalControls = $('.control-toggle')
                    .not('[name="controls[controls]"]')
                    .not('[name="controls[primary-color]"]')
                    .not('[name="controls[secondary-color]"]').length;

                $preview.find('.preview-controls').text(
                    enabledControls + ' of ' + totalControls + ' controls enabled'
                );
            }

            // Generate CSS variables for preview
            var cssVars = [];

            // Handle disabled controls
            $('.control-toggle').each(function() {
                if (!$(this).is(':checked')) {
                    var controlName = $(this).attr('name').replace('controls[', '').replace(']', '');
                    cssVars.push('--' + controlName + ': none');
                }
            });

            // Handle styling options for enabled controls
            $('.control-toggle:checked').each(function() {
                var $controlItem = $(this).closest('.wp-mux-control-item');
                var controlName = $(this).attr('name').replace('controls[', '').replace(']', '');

                $controlItem.find('.style-option input').each(function() {
                    var $input = $(this);
                    var inputName = $input.attr('name');
                    var value = $input.val();

                    if (value && inputName) {
                        // Extract style property from input name
                        // e.g., "styles[play-button_background-color]" -> "play-button-background-color"
                        var match = inputName.match(/styles\[([^\]]+)\]/);
                        if (match) {
                            var styleVar = '--' + match[1].replace('_', '-');
                            cssVars.push(styleVar + ': ' + value);
                        }
                    }
                });
            });

            // Apply styles to preview
            var previewStyle = cssVars.length > 0 ? cssVars.join('; ') : '';
            $preview.find('.preview-player').attr('style', previewStyle);

            // Update preview with visual feedback
            var $previewPlayer = $preview.find('.preview-player');
            $previewPlayer.removeClass('preview-updating');
            setTimeout(function() {
                $previewPlayer.addClass('preview-updating');
            }, 50);
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

            // Remove existing notices
            $('.wp-mux-themes-page .notice').remove();

            // Add new notice
            $('.wp-mux-themes-page h1').after($notice);

            // Auto-dismiss success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 3000);
            }

            // Scroll to top
            $('html, body').animate({ scrollTop: 0 }, 300);
        },

        /**
         * Generate CSS for theme
         */
        generateThemeCSS: function(controls) {
            var cssVars = [];

            $.each(controls, function(controlName, enabled) {
                if (!enabled) {
                    cssVars.push('--' + controlName + ': none');
                }
            });

            return cssVars.length > 0 ? 'mux-player { ' + cssVars.join('; ') + '; }' : '';
        }
    };

    /**
     * Theme selector functionality for media upload
     */
    var WpMuxThemeSelector = {

        /**
         * Initialize theme selector
         */
        init: function() {
            this.addThemeSelector();
        },

        /**
         * Add theme selector to media upload
         */
        addThemeSelector: function() {
            // This would be called when uploading MUX videos
            // to allow users to select a theme for the video
            if (typeof wp !== 'undefined' && wp.media) {
                // Extend media library functionality
                this.extendMediaLibrary();
            }
        },

        /**
         * Extend media library with theme selection
         */
        extendMediaLibrary: function() {
            // Add theme selection to MUX video attachments
            // This could be implemented as a custom field in the media library
        }
    };

    /**
     * Document ready
     */
    $(document).ready(function() {
        // Initialize theme management if on themes page
        if ($('.wp-mux-themes-page').length > 0) {
            WpMuxThemes.init();
        }

        // Initialize theme selector if needed
        WpMuxThemeSelector.init();
    });

})(jQuery);
