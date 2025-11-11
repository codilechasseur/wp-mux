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
if ( isset( $_GET['edit'] ) ) {
	if ( $_GET['edit'] === 'new' ) {
		// Creating a new theme - use defaults
		$edit_mode = true;
		$current_theme = array(
			'name' => '',
			'description' => '',
			'controls' => $theme_manager->get_default_theme_controls(),
			'styles' => array(),
		);
	} elseif ( isset( $themes[ $_GET['edit'] ] ) ) {
		// Editing an existing theme
		$current_theme = $themes[ $_GET['edit'] ];
		$edit_mode = true;
	}
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
		<div class="wp-mux-themes-main">
			<?php if ( $edit_mode ): ?>
				<div class="wp-mux-theme-form">
					<form method="post" action="">
						<?php wp_nonce_field( 'wp_mux_themes_action', 'wp_mux_themes_nonce' ); ?>
						<input type="hidden" name="action" value="save_theme">
						<?php if ( isset( $_GET['edit'] ) && $_GET['edit'] !== 'new' ): ?>
							<input type="hidden" name="theme_id" value="<?php echo esc_attr( $_GET['edit'] ); ?>">
						<?php endif; ?>

						<h2><?php echo ( isset( $_GET['edit'] ) && $_GET['edit'] !== 'new' ) ? __( 'Edit Theme', 'wp-mux' ) : __( 'Create New Theme', 'wp-mux' ); ?></h2>

						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row">
										<label for="theme_name"><?php _e( 'Theme Name', 'wp-mux' ); ?></label>
									</th>
									<td>
										<input type="text"
											   id="theme_name"
											   name="theme_name"
											   value="<?php echo esc_attr( $current_theme['name'] ); ?>"
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
												  rows="2"
												  class="regular-text"><?php echo esc_textarea( $current_theme['description'] ); ?></textarea>
										<p class="description"><?php _e( 'Optional description for this theme.', 'wp-mux' ); ?></p>
									</td>
								</tr>
							</tbody>
						</table>

						<h2><?php _e( 'Player Controls', 'wp-mux' ); ?></h2>

						<table class="form-table" role="presentation">
							<tbody>
								<?php foreach ( $control_options as $section_key => $section_options ): ?>
									<?php foreach ( $section_options as $control_key => $control_data ): ?>
										<?php
										$default_value = isset( $control_data['default'] ) ? $control_data['default'] : true;
										$is_checked = $default_value;
										if ( $current_theme && array_key_exists( $control_key, $current_theme['controls'] ) ) {
											$is_checked = (bool) $current_theme['controls'][ $control_key ];
										}
										?>
										<tr>
											<th scope="row">
												<?php echo esc_html( $control_data['label'] ); ?>
											</th>
											<td>
												<fieldset>
													<label>
														<input type="checkbox"
															   name="controls[<?php echo esc_attr( $control_key ); ?>]"
															   value="1"
															   <?php checked( $is_checked ); ?>
															   class="control-toggle"
															   data-control="<?php echo esc_attr( $control_key ); ?>">
														<?php _e( 'Enable', 'wp-mux' ); ?>
													</label>
													<?php if ( ! empty( $control_data['description'] ) ): ?>
														<p class="description"><?php echo esc_html( $control_data['description'] ); ?></p>
													<?php endif; ?>

													<?php if ( isset( $control_data['styling_options'] ) && ! empty( $control_data['styling_options'] ) ): ?>
														<div class="wp-mux-color-options" style="<?php echo $is_checked ? 'margin-top: 10px;' : 'display: none;'; ?>">
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
																<label style="display: inline-flex; align-items: center; gap: 8px; margin-right: 15px;">
																	<span><?php echo esc_html( $style_data['label'] ); ?>:</span>
																	<input type="color"
																		   id="<?php echo esc_attr( $control_key . '_' . $style_key ); ?>"
																		   name="<?php echo esc_attr( $input_name ); ?>"
																		   value="<?php echo esc_attr( $current_value ); ?>"
																		   style="height: 28px; padding: 2px;">
																</label>
															<?php endforeach; ?>
														</div>
													<?php endif; ?>
												</fieldset>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endforeach; ?>
							</tbody>
						</table>

						<?php submit_button( __( 'Save Changes', 'wp-mux' ) ); ?>

						<p>
							<a href="<?php echo admin_url( 'admin.php?page=wp-mux-themes' ); ?>" class="button"><?php _e( '← Back to Themes', 'wp-mux' ); ?></a>
							<?php if ( ! isset( $current_theme['is_default'] ) || ! $current_theme['is_default'] ): ?>
								<button type="button" class="button button-link-delete" onclick="if(confirm('<?php echo esc_js( __( 'Are you sure you want to delete this theme?', 'wp-mux' ) ); ?>')) { var form = document.createElement('form'); form.method = 'post'; form.innerHTML = '<input type=\'hidden\' name=\'action\' value=\'delete_theme\'><input type=\'hidden\' name=\'theme_id\' value=\'<?php echo esc_js( $_GET['edit'] ); ?>\'><input type=\'hidden\' name=\'wp_mux_themes_nonce\' value=\'<?php echo wp_create_nonce( 'wp_mux_themes_action' ); ?>\'>'; document.body.appendChild(form); form.submit(); }"><?php _e( 'Delete Theme', 'wp-mux' ); ?></button>
							<?php endif; ?>
						</p>
					</form>
				</div>
			<?php else: ?>
				<div class="wp-mux-themes-list">
					<?php if ( empty( $themes ) ): ?>
						<div class="wp-mux-empty-state">
							<p><?php _e( 'No themes created yet.', 'wp-mux' ); ?></p>
						</div>
					<?php else: ?>
						<div class="wp-mux-themes-grid">
							<?php foreach ( $themes as $theme_id => $theme ): ?>
								<?php
								$is_default = $theme_id === $default_theme_id;
								$card_classes = array( 'wp-mux-theme-card' );
								if ( $is_default ) {
									$card_classes[] = 'is-default';
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
										<div class="preview-player">
											<div class="preview-controls">
												<?php
												$enabled_controls = array_filter( $theme['controls'] );
												if ( isset( $enabled_controls['controls'] ) ) {
													unset( $enabled_controls['controls'] );
												}

												$main_controls_disabled = isset( $theme['controls']['controls'] ) && ! $theme['controls']['controls'];

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
													echo __( 'No controls', 'wp-mux' );
												} else {
													echo sprintf( _n( '%d control', '%d controls', $enabled_count, 'wp-mux' ), $enabled_count );
												}
												?>
											</div>
										</div>
									</div>

									<div class="wp-mux-theme-actions">
										<a href="<?php echo admin_url( 'admin.php?page=wp-mux-themes&edit=' . urlencode( $theme_id ) ); ?>"
										   class="button button-primary"><?php _e( 'Edit', 'wp-mux' ); ?></a>

										<?php if ( $theme_id !== $default_theme_id ): ?>
											<form method="post" action="" style="display: inline;">
												<?php wp_nonce_field( 'wp_mux_themes_action', 'wp_mux_themes_nonce' ); ?>
												<input type="hidden" name="action" value="set_default">
												<input type="hidden" name="theme_id" value="<?php echo esc_attr( $theme_id ); ?>">
												<button type="submit" class="button"><?php _e( 'Set as Default', 'wp-mux' ); ?></button>
											</form>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<p class="wp-mux-add-theme">
							<a href="<?php echo admin_url( 'admin.php?page=wp-mux-themes&edit=new' ); ?>" class="button button-secondary">
								<?php _e( '+ Create New Theme', 'wp-mux' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="wp-mux-themes-sidebar">
			<div class="wp-mux-sidebar-panel">
				<h3><?php _e( 'About Themes', 'wp-mux' ); ?></h3>
				<p><?php _e( 'Themes control which player controls are visible and their appearance.', 'wp-mux' ); ?></p>
				<p><?php _e( 'The default theme is automatically applied to all video players.', 'wp-mux' ); ?></p>
			</div>

			<?php if ( $edit_mode ): ?>
				<div class="wp-mux-sidebar-panel">
					<h3><?php _e( 'Theme Info', 'wp-mux' ); ?></h3>
					<?php if ( isset( $_GET['edit'] ) && $_GET['edit'] !== 'new' ): ?>
						<p><strong><?php _e( 'Theme ID:', 'wp-mux' ); ?></strong> <code><?php echo esc_html( $_GET['edit'] ); ?></code></p>
						<?php if ( isset( $current_theme['created'] ) ): ?>
							<p><strong><?php _e( 'Created:', 'wp-mux' ); ?></strong> <?php echo date_i18n( get_option( 'date_format' ), strtotime( $current_theme['created'] ) ); ?></p>
						<?php endif; ?>
						<?php if ( isset( $current_theme['modified'] ) ): ?>
							<p><strong><?php _e( 'Modified:', 'wp-mux' ); ?></strong> <?php echo date_i18n( get_option( 'date_format' ), strtotime( $current_theme['modified'] ) ); ?></p>
						<?php endif; ?>
					<?php else: ?>
						<p><?php _e( 'A unique ID will be generated when you save this theme.', 'wp-mux' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="wp-mux-sidebar-panel">
				<h3><?php _e( 'Using Themes', 'wp-mux' ); ?></h3>
				<p><?php _e( 'To use a theme in your shortcode:', 'wp-mux' ); ?></p>
				<code>[mux_video id="123" theme="<?php echo esc_attr( $default_theme_id ); ?>"]</code>
			</div>
		</div>
	</div>
</div>


