<?php
/**
 * Mux settings meta box template for attachment edit page.
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.3.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wp-mux-attachment-settings">
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="mux_playback_policy"><?php _e( 'Playback Policy', 'wp-mux' ); ?></label>
			</th>
			<td>
				<select name="mux_playback_policy" id="mux_playback_policy">
					<option value="public" <?php selected( $playback_policy, 'public' ); ?>>
						<?php _e( 'Public (Anyone can view)', 'wp-mux' ); ?>
					</option>
					<option value="signed" <?php selected( $playback_policy, 'signed' ); ?> <?php echo ! $signing_keys_available ? 'disabled' : ''; ?>>
						<?php _e( 'Signed URLs (Requires token)', 'wp-mux' ); ?>
						<?php if ( ! $signing_keys_available ) : ?>
							<?php _e( ' - Upgrade Required', 'wp-mux' ); ?>
						<?php endif; ?>
					</option>
				</select>
				
				<?php if ( ! $signing_keys_available ) : ?>
					<p class="description" style="color: #d63638;">
						<span class="dashicons dashicons-lock" style="font-size: 16px; vertical-align: middle;"></span>
						<strong><?php _e( 'Private Videos Unavailable', 'wp-mux' ); ?></strong><br>
						<?php _e( 'Your Mux account does not have access to signed URLs. This feature requires:', 'wp-mux' ); ?>
						<ul style="list-style: disc; margin-left: 25px; margin-top: 5px; padding-left: 0;">
							<li><?php _e( 'A paid Mux plan, OR', 'wp-mux' ); ?></li>
							<li><?php _e( 'Signing keys API access enabled for your account', 'wp-mux' ); ?></li>
						</ul>
						<a href="https://mux.com/pricing" target="_blank"><?php _e( 'View Mux Pricing', 'wp-mux' ); ?> ↗</a>
					</p>
				<?php else : ?>
					<p class="description">
						<?php _e( 'Public videos can be played by anyone. Signed URLs automatically create secure tokens for each viewer.', 'wp-mux' ); ?>
						<br>
						<?php if ( $playback_policy === 'signed' ) : ?>
							<span style="color: #00a32a;">
								<span class="dashicons dashicons-yes-alt" style="font-size: 16px; vertical-align: middle;"></span>
								<?php _e( 'Signing keys are configured and ready.', 'wp-mux' ); ?>
							</span>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		
		<tr>
			<th scope="row">
				<label for="mux_mp4_support"><?php _e( 'MP4 Support', 'wp-mux' ); ?></label>
			</th>
			<td>
				<select name="mux_mp4_support" id="mux_mp4_support">
					<option value="none" <?php selected( $mp4_support, 'none' ); ?>>
						<?php _e( 'None', 'wp-mux' ); ?>
					</option>
					<option value="standard" <?php selected( $mp4_support, 'standard' ); ?>>
						<?php _e( 'Standard', 'wp-mux' ); ?>
					</option>
					<option value="audio-only" <?php selected( $mp4_support, 'audio-only' ); ?> <?php echo ! $has_audio ? 'disabled' : ''; ?>>
						<?php _e( 'Audio Only', 'wp-mux' ); ?><?php echo ! $has_audio ? ' ' . __( '(No audio track)', 'wp-mux' ) : ''; ?>
					</option>
				</select>
				<p class="description">
					<?php _e( 'Enable MP4 downloads. Note: May incur additional storage costs.', 'wp-mux' ); ?>
					<?php if ( ! $has_audio ) : ?>
						<br><strong><?php _e( 'Audio-only option is disabled because this video has no audio track.', 'wp-mux' ); ?></strong>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		
		<tr>
			<th scope="row">
				<?php _e( 'Encoding Tier', 'wp-mux' ); ?>
			</th>
			<td>
				<strong><?php echo esc_html( ucfirst( $encoding_tier ) ); ?></strong>
				<p class="description">
					<?php _e( 'Encoding tier cannot be changed after upload.', 'wp-mux' ); ?>
				</p>
			</td>
		</tr>
		
		<tr>
			<th scope="row">
				<?php _e( 'Captions', 'wp-mux' ); ?>
			</th>
			<td>
				<?php if ( $has_captions ) : ?>
					<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
					<?php _e( 'Captions available', 'wp-mux' ); ?>
					<button type="button" 
							class="button button-secondary" 
							id="mux-manage-captions"
							style="margin-left: 10px;">
						<?php _e( 'Manage Captions', 'wp-mux' ); ?>
					</button>
				<?php elseif ( ! $has_audio ) : ?>
					<!-- No UI elements, just error message -->
				<?php else : ?>
					<span class="dashicons dashicons-minus" style="color: #999;"></span>
					<?php _e( 'No captions', 'wp-mux' ); ?>
					<button type="button" 
							class="button button-secondary" 
							id="mux-generate-captions"
							data-asset-id="<?php echo esc_attr( $asset_id ); ?>"
							data-attachment-id="<?php echo esc_attr( $post->ID ); ?>"
							style="margin-left: 10px;"
							<?php echo ! $captions_available ? 'disabled' : ''; ?>>
						<?php _e( 'Generate Captions', 'wp-mux' ); ?>
					</button>
					
					<div id="mux-caption-options" style="display: none; margin-top: 10px;">
						<label for="caption_language"><?php _e( 'Language:', 'wp-mux' ); ?></label>
						<select name="caption_language" id="caption_language">
							<option value="en"><?php _e( 'English', 'wp-mux' ); ?></option>
							<option value="es"><?php _e( 'Spanish', 'wp-mux' ); ?></option>
							<option value="fr"><?php _e( 'French', 'wp-mux' ); ?></option>
							<option value="de"><?php _e( 'German', 'wp-mux' ); ?></option>
							<option value="it"><?php _e( 'Italian', 'wp-mux' ); ?></option>
							<option value="pt"><?php _e( 'Portuguese', 'wp-mux' ); ?></option>
						</select>
						<button type="button" class="button" id="mux-confirm-captions">
							<?php _e( 'Confirm', 'wp-mux' ); ?>
						</button>
					</div>
				<?php endif; ?>
				
				<p class="description">
					<?php if ( ! $has_audio ) : ?>
						<strong style="color: #d63638;">
							<?php _e( 'Cannot generate captions: This video has no audio track.', 'wp-mux' ); ?>
						</strong>
					<?php elseif ( ! $captions_available ) : ?>
						<strong style="color: #d63638;">
							<?php _e( 'Auto-caption generation is not available for your MUX account. This may be due to API key permissions or account restrictions. Please check your MUX dashboard settings or contact MUX support.', 'wp-mux' ); ?>
						</strong>
					<?php else : ?>
						<?php _e( 'Generate automatic captions for this video using AI speech recognition (free feature).', 'wp-mux' ); ?>
					<?php endif; ?>
				</p>
			</td>
		</tr>
	</table>
	
	<div style="margin-top: 20px;">
		<button type="submit" class="button button-primary" name="mux_save_settings">
			<?php _e( 'Update MUX Settings', 'wp-mux' ); ?>
		</button>
	</div>
</div>

<style>
.wp-mux-attachment-settings .form-table th {
	width: 200px;
}
</style>
