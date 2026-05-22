(function($) {
    'use strict';

    $(document).ready(function() {
        // ---------------------------------------------------------------
        // Caption review / inline editor
        // ---------------------------------------------------------------
        $('#mux-manage-captions').on('click', function() {
            var $btn        = $(this);
            var attachId    = $btn.data('attachment-id');
            var trackId     = $btn.data('track-id');
            var $editor     = $('#mux-caption-editor');
            var $textarea   = $('#mux-caption-vtt-content');

            if ($editor.is(':visible')) {
                $editor.slideUp();
                return;
            }

            $textarea.val('Loading captions\u2026');
            $editor.slideDown();

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'wp_mux_get_caption_vtt',
                    nonce: $('#wp_mux_settings_nonce').val(),
                    attachment_id: attachId,
                    track_id: trackId
                },
                success: function(response) {
                    if (response.success) {
                        $textarea.val(response.data.vtt_content);
                        // Store the resolved track ID (may differ from data attribute).
                        $btn.data('track-id', response.data.track_id);
                        $('#mux-save-captions').data('track-id', response.data.track_id);
                    } else {
                        $textarea.val('Error: ' + (response.data.message || 'Could not load captions.'));
                    }
                },
                error: function() {
                    $textarea.val('Network error \u2014 could not load captions.');
                }
            });
        });

        $('#mux-cancel-captions').on('click', function() {
            $('#mux-caption-editor').slideUp();
        });

        $('#mux-save-captions').on('click', function() {
            var $btn      = $(this);
            var $status   = $('#mux-caption-save-status');
            var attachId  = $('#mux-manage-captions').data('attachment-id');
            var trackId   = $btn.data('track-id') || $('#mux-manage-captions').data('track-id');
            var vttContent = $('#mux-caption-vtt-content').val();

            $btn.prop('disabled', true).text('Saving\u2026');
            $status.text('');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'wp_mux_save_caption_vtt',
                    nonce: $('#wp_mux_settings_nonce').val(),
                    attachment_id: attachId,
                    track_id: trackId,
                    vtt_content: vttContent
                },
                success: function(response) {
                    if (response.success) {
                        $status.css('color', '#46b450').text(response.data.message || 'Saved!');
                        // Update track ID in case Mux issued a new one.
                        if (response.data.new_track_id) {
                            $btn.data('track-id', response.data.new_track_id);
                            $('#mux-manage-captions').data('track-id', response.data.new_track_id);
                        }
                    } else {
                        $status.css('color', '#d63638').text('Error: ' + (response.data.message || 'Save failed.'));
                    }
                },
                error: function() {
                    $status.css('color', '#d63638').text('Network error \u2014 save failed.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save Captions');
                }
            });
        });

        // ---------------------------------------------------------------
        // Show caption language selector (existing generate flow)
        // ---------------------------------------------------------------
        $('#mux-generate-captions').on('click', function() {
            $('#mux-caption-options').slideToggle();
        });

        // Handle caption generation
        $('#mux-confirm-captions').on('click', function() {
            const attachmentId = $('#mux-generate-captions').data('attachment-id');
            const language = $('#caption_language').val();
            const $button = $(this);

            $button.prop('disabled', true).text('Generating...');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'wp_mux_generate_captions',
                    nonce: $('#wp_mux_settings_nonce').val(),
                    attachment_id: attachmentId,
                    language: language
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || 'Captions generated successfully.');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to generate captions'));
                        $button.prop('disabled', false).text('Confirm');
                    }
                },
                error: function() {
                    alert('Failed to generate captions. Please try again.');
                    $button.prop('disabled', false).text('Confirm');
                }
            });
        });

        // Handle playback policy change
        $('#mux_playback_policy').on('change', function() {
            const newPolicy = $(this).val();
            const attachmentId = $('#mux-generate-captions').data('attachment-id');
            const originalValue = $(this).data('original-value') || 'public';

            if (confirm('Update playback policy to ' + newPolicy + '?')) {
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'wp_mux_update_playback_policy',
                        nonce: $('#wp_mux_settings_nonce').val(),
                        attachment_id: attachmentId,
                        policy: newPolicy
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message || 'Policy updated successfully.');
                            $(this).data('original-value', newPolicy);
                        } else {
                            alert('Error: ' + (response.data.message || 'Failed to update policy'));
                            $('#mux_playback_policy').val(originalValue);
                        }
                    }.bind(this),
                    error: function() {
                        alert('Failed to update policy. Please try again.');
                        $('#mux_playback_policy').val(originalValue);
                    }
                });
            } else {
                $(this).val(originalValue);
            }
        });

        // Store original value on load
        $('#mux_playback_policy').data('original-value', $('#mux_playback_policy').val());
    });

})(jQuery);
