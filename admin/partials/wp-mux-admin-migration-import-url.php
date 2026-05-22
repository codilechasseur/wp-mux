<?php
/**
 * "Import from URL" tab — allows importing a publicly accessible video
 * file directly into Mux by passing the URL to the Mux Assets API.
 *
 * No local download or temporary storage is required; Mux fetches the
 * file from the provided URL at encoding time.
 *
 * @link       https://wpmux.com
 * @since      1.2.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<div class="wp-mux-import-url-wrapper" style="max-width: 720px; margin-top: 24px;">

	<p class="description" style="font-size: 14px; margin-bottom: 20px;">
		<?php esc_html_e( 'Enter the URL of a publicly accessible video file. Mux will fetch and encode it automatically. A WordPress Media Library attachment is created immediately so you can track progress.', 'wp-mux' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="mux-import-url"><?php esc_html_e( 'Video URL', 'wp-mux' ); ?></label>
				</th>
				<td>
					<input type="url"
						   id="mux-import-url"
						   name="mux_import_url"
						   class="regular-text"
						   placeholder="https://example.com/video.mp4"
						   style="width: 100%; max-width: 540px;"
					/>
					<p class="description">
						<?php esc_html_e( 'Must be publicly reachable by Mux (http or https). Direct video file links (MP4, MOV, MKV, etc.) work best.', 'wp-mux' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="mux-import-title"><?php esc_html_e( 'Title (optional)', 'wp-mux' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="mux-import-title"
						   name="mux_import_title"
						   class="regular-text"
						   placeholder="<?php esc_attr_e( 'Auto-generated from filename if left blank', 'wp-mux' ); ?>"
						   style="width: 100%; max-width: 540px;"
					/>
				</td>
			</tr>
		</tbody>
	</table>

	<p style="margin-top: 8px;">
		<button type="button"
				id="mux-start-url-import"
				class="button button-primary"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_mux_migration_nonce' ) ); ?>">
			<?php esc_html_e( 'Import Video', 'wp-mux' ); ?>
		</button>
	</p>

	<!-- Progress / results area -->
	<div id="mux-import-url-results" style="margin-top: 20px; display: none;">
		<div id="mux-import-url-notice" class="notice" style="padding: 12px 16px; margin: 0;"></div>

		<div id="mux-import-url-table-wrapper" style="margin-top: 16px; display: none;">
			<h3><?php esc_html_e( 'Imported Videos', 'wp-mux' ); ?></h3>
			<table class="wp-list-table widefat fixed striped" id="mux-import-url-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'wp-mux' ); ?></th>
						<th><?php esc_html_e( 'Mux Asset ID', 'wp-mux' ); ?></th>
						<th><?php esc_html_e( 'Attachment ID', 'wp-mux' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-mux' ); ?></th>
					</tr>
				</thead>
				<tbody id="mux-import-url-table-body"></tbody>
			</table>
		</div>
	</div>

</div>

<script type="text/javascript">
(function($) {
    'use strict';

    $('#mux-start-url-import').on('click', function() {
        var $btn        = $(this);
        var videoUrl    = $('#mux-import-url').val().trim();
        var videoTitle  = $('#mux-import-title').val().trim();
        var nonce       = $btn.data('nonce');
        var $results    = $('#mux-import-url-results');
        var $notice     = $('#mux-import-url-notice');
        var $tableWrap  = $('#mux-import-url-table-wrapper');
        var $tbody      = $('#mux-import-url-table-body');

        if (!videoUrl) {
            alert('<?php echo esc_js( __( 'Please enter a video URL.', 'wp-mux' ) ); ?>');
            return;
        }

        $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Importing\u2026', 'wp-mux' ) ); ?>');
        $notice.removeClass('notice-success notice-error').addClass('notice-info').html('<p><?php echo esc_js( __( 'Sending request to Mux\u2026', 'wp-mux' ) ); ?></p>');
        $results.show();

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wp_mux_import_from_url',
                nonce: nonce,
                url: videoUrl,
                title: videoTitle
            },
            success: function(response) {
                if (response.success) {
                    var d = response.data;
                    $notice.removeClass('notice-info notice-error').addClass('notice-success')
                           .html('<p><strong>' + d.message + '</strong></p>' +
                                 '<p>' + '<?php echo esc_js( __( 'Attachment ID: ', 'wp-mux' ) ); ?>' + d.attachment_id + ' &mdash; ' +
                                 '<?php echo esc_js( __( 'Asset ID: ', 'wp-mux' ) ); ?>' + d.asset_id + '</p>');

                    $tbody.append(
                        '<tr>' +
                        '<td>' + $('<span>').text(d.title).html() + '</td>' +
                        '<td><code>' + $('<span>').text(d.asset_id).html() + '</code></td>' +
                        '<td>' + d.attachment_id + '</td>' +
                        '<td><em><?php echo esc_js( __( 'Processing', 'wp-mux' ) ); ?></em></td>' +
                        '</tr>'
                    );
                    $tableWrap.show();

                    // Reset form for next import.
                    $('#mux-import-url').val('');
                    $('#mux-import-title').val('');
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : '<?php echo esc_js( __( 'Import failed.', 'wp-mux' ) ); ?>';
                    $notice.removeClass('notice-info notice-success').addClass('notice-error')
                           .html('<p>' + msg + '</p>');
                }
            },
            error: function() {
                $notice.removeClass('notice-info notice-success').addClass('notice-error')
                       .html('<p><?php echo esc_js( __( 'Network error. Please try again.', 'wp-mux' ) ); ?></p>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Import Video', 'wp-mux' ) ); ?>');
            }
        });
    });
})(jQuery);
</script>
