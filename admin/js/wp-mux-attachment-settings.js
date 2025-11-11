(function($) {
    'use strict';

    $(document).ready(function() {
        // Show caption language selector
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
