<?php

/**
 * Provide a admin area view for the themes management
 *
 * This file is used to markup the admin-facing aspects of theme management.
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.0.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/admin/partials
 */

// Load theme manager
require_once plugin_dir_path( dirname( __FILE__ ) ) . '../includes/class-wp-mux-theme-manager.php';
$theme_manager = new Wp_Mux_Theme_Manager();
$themes = $theme_manager->get_themes();
$default_theme_id = $theme_manager->get_default_theme_id();
$control_options = $theme_manager->get_control_options();

// Get current theme for editing
$current_theme = null;
$edit_mode = false;
if ( isset( $_GET['edit'] ) && isset( $themes[ $_GET['edit'] ] ) ) {
	$current_theme = $themes[ $_GET['edit'] ];
	$edit_mode = true;
}

// Show success message if redirected after update
if ( isset( $_GET['updated'] ) && $_GET['updated'] == '1' ) {
	echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Theme updated successfully!', 'wp-mux' ) . '</p></div>';
}

// Show success/error messages from other actions (delete, set default)
if ( isset( $_GET['action_completed'] ) && $_GET['action_completed'] == '1' ) {
	$success_message = get_transient( 'wp_mux_theme_success_' . get_current_user_id() );
	if ( $success_message ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $success_message ) . '</p></div>';
		delete_transient( 'wp_mux_theme_success_' . get_current_user_id() );
	}
}

// Show error message if there was an error during save
$error_message = get_transient( 'wp_mux_theme_error_' . get_current_user_id() );
if ( $error_message ) {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_message ) . '</p></div>';
	delete_transient( 'wp_mux_theme_error_' . get_current_user_id() );
}

?>

<div class="wrap wp-mux-themes-page">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="wp-mux-themes-container">
		<div class="wp-mux-themes-sidebar">
			<div class="wp-mux-theme-form">
				<h2><?php echo $edit_mode ? __( 'Edit Theme', 'wp-mux' ) : __( 'Create New Theme', 'wp-mux' ); ?></h2>

				<form method="post" action="">
					<?php wp_nonce_field( 'wp_mux_themes_action', 'wp_mux_themes_nonce' ); ?>
					<input type="hidden" name="action" value="save_theme">
					<?php if ( $edit_mode ): ?>
						<input type="hidden" name="theme_id" value="<?php echo esc_attr( $_GET['edit'] ); ?>">
					<?php endif; ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="theme_name"><?php _e( 'Theme Name', 'wp-mux' ); ?></label>
							</th>
							<td>
								<input type="text"
									   id="theme_name"
									   name="theme_name"
									   value="<?php echo $current_theme ? esc_attr( $current_theme['name'] ) : ''; ?>"
									   class="regular-text"
									   required>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="theme_description"><?php _e( 'Description', 'wp-mux' ); ?></label>
							</th>
							<td>
								<textarea id="theme_description"
										  name="theme_description"
										  rows="3"
										  class="regular-text"><?php echo $current_theme ? esc_textarea( $current_theme['description'] ) : ''; ?></textarea>
							</td>
						</tr>
					</table>

					<h3><?php _e( 'Player Controls', 'wp-mux' ); ?></h3>
					<p class="description"><?php _e( 'Select which controls and elements should be visible in the player, and customize their appearance.', 'wp-mux' ); ?></p>

					<div class="wp-mux-controls-sections">
						<?php foreach ( $control_options as $section_key => $section_options ): ?>
							<div class="wp-mux-control-section" data-category="<?php echo esc_attr( $section_key ); ?>">
								<h4><?php echo esc_html( ucwords( str_replace( '_', ' ', $section_key ) ) ); ?></h4>

								<?php foreach ( $section_options as $control_key => $control_data ): ?>
									<?php
									$default_value = isset( $control_data['default'] ) ? $control_data['default'] : true;
									$is_checked = $default_value; // Use default value first
									if ( $current_theme && array_key_exists( $control_key, $current_theme['controls'] ) ) {
										$is_checked = (bool) $current_theme['controls'][ $control_key ];
									}
									?>
									<div class="wp-mux-control-item" data-control="<?php echo esc_attr( $control_key ); ?>">
										<div class="control-header">
											<label>
												<input type="checkbox"
													   name="controls[<?php echo esc_attr( $control_key ); ?>]"
													   value="1"
													   <?php checked( $is_checked ); ?>
													   class="control-toggle">
												<strong><?php echo esc_html( $control_data['label'] ); ?></strong>
											</label>
											<?php if ( ! empty( $control_data['description'] ) ): ?>
												<span class="description"><?php echo esc_html( $control_data['description'] ); ?></span>
											<?php endif; ?>
										</div>

										<?php if ( isset( $control_data['styling_options'] ) && ! empty( $control_data['styling_options'] ) ): ?>
											<div class="control-styles" style="<?php echo $is_checked ? '' : 'display: none;'; ?>">
												<div class="style-options">
													<?php foreach ( $control_data['styling_options'] as $style_key => $style_data ): ?>
														<?php
														$input_name = 'styles[' . $control_key . '_' . $style_key . ']';
														$current_value = '';
														if ( $current_theme && isset( $current_theme['styles'][ $control_key ][ $style_key ] ) ) {
															$current_value = $current_theme['styles'][ $control_key ][ $style_key ];
														} else {
															$current_value = $style_data['default'];
														}
														?>
														<div class="style-option">
															<label for="<?php echo esc_attr( $control_key . '_' . $style_key ); ?>">
																<?php echo esc_html( $style_data['label'] ); ?>
															</label>
															<?php if ( $style_data['type'] === 'color' ): ?>
																<input type="color"
																	   id="<?php echo esc_attr( $control_key . '_' . $style_key ); ?>"
																	   name="<?php echo esc_attr( $input_name ); ?>"
																	   value="<?php echo esc_attr( $current_value ); ?>"
																	   class="color-picker">
															<?php else: ?>
																<input type="text"
																	   id="<?php echo esc_attr( $control_key . '_' . $style_key ); ?>"
																	   name="<?php echo esc_attr( $input_name ); ?>"
																	   value="<?php echo esc_attr( $current_value ); ?>"
																	   placeholder="<?php echo isset( $style_data['placeholder'] ) ? esc_attr( $style_data['placeholder'] ) : ''; ?>"
																	   class="small-text">
															<?php endif; ?>
														</div>
													<?php endforeach; ?>
												</div>
											</div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
					</div>

					<p class="submit">
						<?php submit_button( $edit_mode ? __( 'Update Theme', 'wp-mux' ) : __( 'Create Theme', 'wp-mux' ), 'primary', 'submit', false ); ?>
						<?php if ( $edit_mode ): ?>
							<a href="<?php echo admin_url( 'admin.php?page=wp-mux-themes' ); ?>" class="button"><?php _e( 'Cancel', 'wp-mux' ); ?></a>
						<?php endif; ?>
					</p>
				</form>
			</div>
		</div>

		<div class="wp-mux-themes-main">
			<h2><?php _e( 'Existing Themes', 'wp-mux' ); ?></h2>

			<?php if ( empty( $themes ) ): ?>
				<p><?php _e( 'No themes created yet. Create your first theme using the form on the left.', 'wp-mux' ); ?></p>
			<?php else: ?>
				<div class="wp-mux-themes-grid">
					<?php foreach ( $themes as $theme_id => $theme ): ?>
						<?php
						$is_default = $theme_id === $default_theme_id;
						$is_editing = $edit_mode && isset( $_GET['edit'] ) && $_GET['edit'] === $theme_id;
						$card_classes = array( 'wp-mux-theme-card' );
						if ( $is_default ) {
							$card_classes[] = 'is-default';
						}
						if ( $is_editing ) {
							$card_classes[] = 'is-editing';
						}
						?>
						<div class="<?php echo implode( ' ', $card_classes ); ?>">
							<div class="wp-mux-theme-header">
								<h3><?php echo esc_html( $theme['name'] ); ?></h3>
								<?php if ( $is_default ): ?>
									<span class="wp-mux-default-badge"><?php _e( 'Default', 'wp-mux' ); ?></span>
								<?php endif; ?>
							</div>

							<?php if ( ! empty( $theme['description'] ) ): ?>
								<p class="wp-mux-theme-description"><?php echo esc_html( $theme['description'] ); ?></p>
							<?php endif; ?>

							<div class="wp-mux-theme-preview">
								<div class="wp-mux-player-preview" style="<?php echo esc_attr( $theme_manager->get_theme_style_attributes( $theme_id ) ); ?>">
									<div class="preview-player">
										<div class="preview-controls">
											<?php
											// Count enabled controls excluding the global "Show All Controls" toggle
											$enabled_controls = array_filter( $theme['controls'] );
											if ( isset( $enabled_controls['controls'] ) ) {
												unset( $enabled_controls['controls'] );
											}

											// Check if main controls toggle is disabled
											$main_controls_disabled = isset( $theme['controls']['controls'] ) && ! $theme['controls']['controls'];

											// If main controls are disabled, also exclude primary and secondary colors from the count
											if ( $main_controls_disabled ) {
												if ( isset( $enabled_controls['primary-color'] ) ) {
													unset( $enabled_controls['primary-color'] );
												}
												if ( isset( $enabled_controls['secondary-color'] ) ) {
													unset( $enabled_controls['secondary-color'] );
												}
											}

											$enabled_count = count( $enabled_controls );

											if ( $main_controls_disabled ) {
												echo __( 'All controls hidden', 'wp-mux' );
											} elseif ( $enabled_count === 0 ) {
												echo __( 'No individual controls enabled', 'wp-mux' );
											} else {
												echo sprintf( _n( '%d control enabled', '%d controls enabled', $enabled_count, 'wp-mux' ), $enabled_count );
											}
											?>
										</div>
									</div>
								</div>
							</div>

							<div class="wp-mux-theme-meta">
								<small>
									<?php if ( isset( $theme['modified'] ) ): ?>
										<?php printf( __( 'Modified: %s', 'wp-mux' ), date_i18n( get_option( 'date_format' ), strtotime( $theme['modified'] ) ) ); ?>
									<?php elseif ( isset( $theme['created'] ) ): ?>
										<?php printf( __( 'Created: %s', 'wp-mux' ), date_i18n( get_option( 'date_format' ), strtotime( $theme['created'] ) ) ); ?>
									<?php endif; ?>
								</small>
							</div>

							<div class="wp-mux-theme-actions">
								<a href="<?php echo admin_url( 'admin.php?page=wp-mux-themes&edit=' . urlencode( $theme_id ) ); ?>"
								   class="button"><?php _e( 'Edit', 'wp-mux' ); ?></a>

								<?php if ( $theme_id !== $default_theme_id ): ?>
									<form method="post" action="" style="display: inline;">
										<?php wp_nonce_field( 'wp_mux_themes_action', 'wp_mux_themes_nonce' ); ?>
										<input type="hidden" name="action" value="set_default">
										<input type="hidden" name="theme_id" value="<?php echo esc_attr( $theme_id ); ?>">
										<button type="submit" class="button"><?php _e( 'Set Default', 'wp-mux' ); ?></button>
									</form>
								<?php endif; ?>

								<?php if ( ! isset( $theme['is_default'] ) || ! $theme['is_default'] ): ?>
									<form method="post" action="" style="display: inline;"
										  onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this theme?', 'wp-mux' ) ); ?>');">
										<?php wp_nonce_field( 'wp_mux_themes_action', 'wp_mux_themes_nonce' ); ?>
										<input type="hidden" name="action" value="delete_theme">
										<input type="hidden" name="theme_id" value="<?php echo esc_attr( $theme_id ); ?>">
										<button type="submit" class="button button-link-delete"><?php _e( 'Delete', 'wp-mux' ); ?></button>
									</form>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="wp-mux-theme-usage">
				<h3><?php _e( 'Using Themes', 'wp-mux' ); ?></h3>
				<p><?php _e( 'Themes control the appearance and available controls of MUX players. The default theme is automatically applied to all new players.', 'wp-mux' ); ?></p>
				<p><?php _e( 'To apply a specific theme to a player, you can specify the theme ID when creating mux-player elements:', 'wp-mux' ); ?></p>
				<code>&lt;mux-player playback-id="your-id" class="wp-mux-theme-<?php echo esc_attr( $default_theme_id ); ?>"&gt;&lt;/mux-player&gt;</code>
			</div>
		</div>
	</div>
</div>

<style>
.wp-mux-themes-container {
	display: flex;
	gap: 20px;
	margin-top: 20px;
}

.wp-mux-themes-sidebar {
	flex: 0 0 350px;
}

.wp-mux-themes-main {
	flex: 1;
}

.wp-mux-theme-form {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 20px;
	border-radius: 4px;
}

.wp-mux-controls-sections {
	margin: 20px 0;
}

.wp-mux-control-section {
	margin-bottom: 25px;
	padding-bottom: 15px;
	border-bottom: 1px solid #f0f0f1;
}

.wp-mux-control-section:last-child {
	border-bottom: none;
}

.wp-mux-control-section h4 {
	margin: 0 0 10px 0;
	color: #1d2327;
	text-transform: capitalize;
}

.wp-mux-control-item {
	margin-bottom: 10px;
}

.wp-mux-control-item label {
	display: block;
	padding: 8px 0;
}

.wp-mux-themes-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}

.wp-mux-theme-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	position: relative;
}

.wp-mux-theme-card.is-default {
	border-color: #00a32a;
	box-shadow: 0 0 0 1px #00a32a;
}

.wp-mux-theme-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 10px;
}

.wp-mux-theme-header h3 {
	margin: 0;
	font-size: 18px;
}

.wp-mux-default-badge {
	background: #00a32a;
	color: white;
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
}

.wp-mux-theme-description {
	color: #646970;
	margin-bottom: 15px;
}

.wp-mux-theme-preview {
	margin: 15px 0;
}

.preview-player {
	background: #000;
	border-radius: 4px;
	padding: 40px 20px;
	text-align: center;
	position: relative;
	min-height: 80px;
}

.preview-controls {
	color: #fff;
	font-size: 14px;
}

.wp-mux-theme-meta {
	margin: 15px 0;
	color: #646970;
}

.wp-mux-theme-actions {
	margin-top: 15px;
	padding-top: 15px;
	border-top: 1px solid #f0f0f1;
}

.wp-mux-theme-actions .button {
	margin-right: 8px;
}

.wp-mux-theme-usage {
	background: #f6f7f7;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px;
	margin-top: 30px;
}

.wp-mux-theme-usage code {
	display: block;
	background: #2c3338;
	color: #fff;
	padding: 10px;
	border-radius: 3px;
	margin-top: 10px;
	font-family: Consolas, Monaco, monospace;
	font-size: 13px;
}
</style>
