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
            // Initialize color options visibility based on checkbox state
            $('.control-toggle').each(function() {
                var $checkbox = $(this);
                var $colorOptions = $checkbox.closest('fieldset').find('.wp-mux-color-options');
                if ($colorOptions.length > 0) {
                    if (!$checkbox.is(':checked')) {
                        $colorOptions.hide();
                    }
                }
            });
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

            // Theme name changes
            $(document).on('input', '#theme_name', this.updatePreview);
        },

        /**
         * Handle control toggle (show/hide color options)
         */
        handleControlToggle: function() {
            var $checkbox = $(this);
            var $fieldset = $checkbox.closest('fieldset');
            var $colorOptions = $fieldset.find('.wp-mux-color-options');
            var controlKey = $checkbox.data('control');

            if ($checkbox.is(':checked')) {
                $colorOptions.slideDown(200);
            } else {
                $colorOptions.slideUp(200);
            }

            WpMuxThemes.updatePreview();
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
            // Initialize control state on page load
            $('.control-toggle').each(function() {
                var $checkbox = $(this);
                var $colorOptions = $checkbox.closest('fieldset').find('.wp-mux-color-options');
                if ($colorOptions.length > 0 && !$checkbox.is(':checked')) {
                    $colorOptions.hide();
                }
            });
        },

        /**
         * Update theme preview
         */
        updatePreview: function() {
            // Preview functionality removed since we're using a simplified card-based view
            // Preview is now just a static indicator in theme cards
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
