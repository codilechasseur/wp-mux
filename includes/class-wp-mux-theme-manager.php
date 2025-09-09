<?php

/**
 * The MUX Player theme management functionality.
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.0.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

/**
 * The MUX Player theme management functionality.
 *
 * Handles creating, saving, and applying themes for MUX players.
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 * @author     Codi Lechasseur <codilechasseur@gmail.com>
 */
class Wp_Mux_Theme_Manager {

	/**
	 * Default MUX player control options and their descriptions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $control_options    Available control options.
	 */
	private $control_options = array(
		'global' => array(
			'controls' => array(
				'label' => 'Show Controls',
				'description' => 'Master toggle for all player controls',
				'type' => 'toggle',
				'default' => true,
				'priority' => 1,
			),
			'control-bar' => array(
				'label' => 'Control Bar',
				'description' => 'Bottom control bar with play/pause, timeline, etc.',
				'type' => 'toggle',
				'default' => true,
				'depends_on' => 'controls',
				'priority' => 2,
			),
			'play-button' => array(
				'label' => 'Play Button',
				'description' => 'Central play/pause button overlay',
				'type' => 'toggle',
				'default' => true,
				'depends_on' => 'controls',
				'priority' => 3,
			),
			'primary-color' => array(
				'label' => 'Primary Color',
				'description' => 'Changes the color of the control icons (play button, volume, etc.)',
				'type' => 'color-only',
				'default' => true,
				'depends_on' => 'controls',
				'priority' => 4,
				'styling_options' => array(
					'color' => array(
						'label' => 'Color',
						'type' => 'color',
						'default' => '#007cba',
					),
				),
			),
			'secondary-color' => array(
				'label' => 'Secondary Color',
				'description' => 'Sets the background color of the control bar',
				'type' => 'color-only',
				'default' => true,
				'depends_on' => 'controls',
				'priority' => 5,
				'styling_options' => array(
					'color' => array(
						'label' => 'Color',
						'type' => 'color',
						'default' => '#666666',
					),
				),
			),
			'accent-color' => array(
				'label' => 'Accent Color',
				'description' => 'Changes the color used to accent the controls (progress bar, hover states)',
				'type' => 'color-only',
				'default' => true,
				'depends_on' => 'controls',
				'priority' => 6,
				'styling_options' => array(
					'color' => array(
						'label' => 'Color',
						'type' => 'color',
						'default' => '#007cba',
					),
				),
			),
		),
	);

	/**
	 * Get all available control options grouped by category.
	 *
	 * @since    1.0.0
	 * @return   array    The control options.
	 */
	public function get_control_options() {
		return $this->control_options;
	}

	/**
	 * Migrate theme data to fix ID issues.
	 * This method fixes themes that were incorrectly assigned the 'default' ID.
	 *
	 * @since    1.0.0
	 * @return   bool    True if migration was needed and performed, false otherwise.
	 */
	public function migrate_theme_data() {
		$themes = get_option( 'wp_mux_themes', array() );
		$migration_needed = false;

		// Check if we have a theme with ID 'default' that isn't actually the default theme
		if ( isset( $themes['default'] ) && $themes['default']['name'] !== 'Default' ) {
			error_log( 'WP MUX: Theme migration needed - found non-default theme with default ID' );

			$old_default_theme = $themes['default'];
			$new_theme_id = $this->create_slug_from_name( $old_default_theme['name'] );

			// Ensure we get a unique ID
			$counter = 1;
			$original_new_id = $new_theme_id;
			while ( isset( $themes[ $new_theme_id ] ) && $new_theme_id !== 'default' ) {
				$new_theme_id = $original_new_id . '-' . $counter;
				$counter++;
			}

			// Move the theme to the new ID
			$themes[ $new_theme_id ] = $old_default_theme;
			$themes[ $new_theme_id ]['is_default'] = false; // Remove default flag

			// Create a proper default theme
			$themes['default'] = array(
				'name' => 'Default',
				'description' => 'Default MUX player appearance with all controls enabled',
				'controls' => $this->get_default_theme_controls(),
				'styles' => array(),
				'created' => current_time( 'mysql' ),
				'modified' => current_time( 'mysql' ),
				'is_default' => true,
			);

			// Update the default theme setting if it was pointing to the old theme
			$current_default = get_option( 'wp_mux_default_theme', 'default' );
			if ( $current_default === 'default' ) {
				// Keep it pointing to the new proper default theme
				update_option( 'wp_mux_default_theme', 'default' );
			}

			update_option( 'wp_mux_themes', $themes );
			$migration_needed = true;

			error_log( 'WP MUX: Theme migration completed - moved "' . $old_default_theme['name'] . '" from ID "default" to "' . $new_theme_id . '"' );
		}

		return $migration_needed;
	}

	/**
	 * Get all saved themes.
	 *
	 * @since    1.0.0
	 * @return   array    Array of saved themes.
	 */
	public function get_themes() {
		// Run migration check first
		$this->migrate_theme_data();

		$themes = get_option( 'wp_mux_themes', array() );

		// Add default theme if no themes exist
		if ( empty( $themes ) ) {
			$themes = array(
				'default' => array(
					'name' => 'Default',
					'description' => 'Default MUX player appearance with all controls enabled',
					'controls' => $this->get_default_theme_controls(),
					'created' => current_time( 'mysql' ),
					'is_default' => true,
				),
			);
			update_option( 'wp_mux_themes', $themes );
		}

		return $themes;
	}

	/**
	 * Get control definition by key.
	 *
	 * @since    1.0.0
	 * @param    string    $control_key    The control key to look up.
	 * @return   array|null                Control definition or null if not found.
	 */
	private function get_control_definition( $control_key ) {
		foreach ( $this->control_options as $category => $controls ) {
			if ( isset( $controls[ $control_key ] ) ) {
				return $controls[ $control_key ];
			}
		}
		return null;
	}

	/**
	 * Get default theme control settings.
	 *
	 * @since    1.0.0
	 * @return   array    Default control settings.
	 */
	private function get_default_theme_controls() {
		$controls = array();
		foreach ( $this->control_options as $category => $options ) {
			foreach ( $options as $key => $option ) {
				$controls[ $key ] = $option['default'];
			}
		}
		return $controls;
	}

	/**
	 * Save a new theme or update existing theme.
	 *
	 * @since    1.0.0
	 * @param    string    $theme_id      Theme ID (slug).
	 * @param    string    $name          Theme name.
	 * @param    string    $description   Theme description.
	 * @param    array     $controls      Control settings.
	 * @param    array     $styles        Style settings.
	 * @return   bool|WP_Error            True on success, WP_Error on failure.
	 */
	public function save_theme( $theme_id, $name, $description, $controls, $styles = array() ) {
		// Validate inputs
		if ( empty( $theme_id ) || empty( $name ) ) {
			return new WP_Error( 'invalid_data', __( 'Theme ID and name are required.', 'wp-mux' ) );
		}

		// Sanitize theme ID
		$theme_id = sanitize_key( $theme_id );
		if ( empty( $theme_id ) ) {
			return new WP_Error( 'invalid_theme_id', __( 'Invalid theme ID.', 'wp-mux' ) );
		}

		// Get existing themes
		$themes = $this->get_themes();

		// Validate controls
		$validated_controls = $this->validate_controls( $controls );
		if ( is_wp_error( $validated_controls ) ) {
			return $validated_controls;
		}

		// Validate styles
		$validated_styles = $this->validate_styles( $styles );
		if ( is_wp_error( $validated_styles ) ) {
			return $validated_styles;
		}

		// Create theme data
		$theme_data = array(
			'name' => sanitize_text_field( $name ),
			'description' => sanitize_textarea_field( $description ),
			'controls' => $validated_controls,
			'styles' => $validated_styles,
			'created' => isset( $themes[ $theme_id ]['created'] ) ? $themes[ $theme_id ]['created'] : current_time( 'mysql' ),
			'modified' => current_time( 'mysql' ),
			'is_default' => false,
		);

		// Preserve default flag for default theme
		if ( $theme_id === 'default' || ( isset( $themes[ $theme_id ]['is_default'] ) && $themes[ $theme_id ]['is_default'] ) ) {
			$theme_data['is_default'] = true;
		}

		// Save theme
		$themes[ $theme_id ] = $theme_data;
		update_option( 'wp_mux_themes', $themes );

		return true;
	}

	/**
	 * Delete a theme.
	 *
	 * @since    1.0.0
	 * @param    string    $theme_id    Theme ID to delete.
	 * @return   bool|WP_Error          True on success, WP_Error on failure.
	 */
	public function delete_theme( $theme_id ) {
		if ( empty( $theme_id ) ) {
			return new WP_Error( 'invalid_theme_id', __( 'Theme ID is required.', 'wp-mux' ) );
		}

		$themes = $this->get_themes();

		// Don't allow deletion of default theme
		if ( $theme_id === 'default' || ( isset( $themes[ $theme_id ]['is_default'] ) && $themes[ $theme_id ]['is_default'] ) ) {
			return new WP_Error( 'cannot_delete_default', __( 'Cannot delete the default theme.', 'wp-mux' ) );
		}

		if ( ! isset( $themes[ $theme_id ] ) ) {
			return new WP_Error( 'theme_not_found', __( 'Theme not found.', 'wp-mux' ) );
		}

		unset( $themes[ $theme_id ] );
		update_option( 'wp_mux_themes', $themes );

		return true;
	}

	/**
	 * Get a specific theme.
	 *
	 * @since    1.0.0
	 * @param    string    $theme_id    Theme ID.
	 * @return   array|false            Theme data or false if not found.
	 */
	public function get_theme( $theme_id ) {
		$themes = $this->get_themes();
		return isset( $themes[ $theme_id ] ) ? $themes[ $theme_id ] : false;
	}

	/**
	 * Validate control settings.
	 *
	 * @since    1.0.0
	 * @param    array    $controls    Control settings to validate.
	 * @return   array|WP_Error       Validated controls or WP_Error.
	 */
	private function validate_controls( $controls ) {
		if ( ! is_array( $controls ) ) {
			$controls = array();
		}

		$validated = array();
		$all_options = array();

		// Flatten all control options for validation
		foreach ( $this->control_options as $category => $options ) {
			$all_options = array_merge( $all_options, array_keys( $options ) );
		}

		// Set all controls to false first (since unchecked checkboxes are not submitted)
		foreach ( $all_options as $control_key ) {
			$validated[ $control_key ] = false;
		}

		// Then set submitted (checked) controls to true
		foreach ( $controls as $key => $value ) {
			if ( in_array( $key, $all_options, true ) ) {
				$validated[ $key ] = (bool) $value;
			}
		}

		return $validated;
	}

	/**
	 * Validate style settings.
	 *
	 * @since    1.0.0
	 * @param    array    $styles    Style settings to validate.
	 * @return   array|WP_Error     Validated styles or WP_Error.
	 */
	private function validate_styles( $styles ) {
		if ( ! is_array( $styles ) ) {
			$styles = array();
		}

		$validated = array();

		foreach ( $this->control_options as $category => $controls ) {
			foreach ( $controls as $control_key => $control_data ) {
				if ( isset( $control_data['styling_options'] ) && is_array( $control_data['styling_options'] ) ) {
					foreach ( $control_data['styling_options'] as $style_key => $style_data ) {
						$input_key = $control_key . '_' . $style_key;

						if ( isset( $styles[ $input_key ] ) ) {
							$value = $styles[ $input_key ];

							// Validate based on type
							switch ( $style_data['type'] ) {
								case 'color':
									$value = sanitize_hex_color( $value );
									if ( empty( $value ) ) {
										$value = $style_data['default'];
									}
									break;
								case 'text':
									$value = sanitize_text_field( $value );
									if ( empty( $value ) ) {
										$value = $style_data['default'];
									}
									break;
								default:
									$value = sanitize_text_field( $value );
									break;
							}

							$validated[ $control_key ][ $style_key ] = $value;
						}
					}
				}
			}
		}

		return $validated;
	}

	/**
	 * Generate CSS variables for a theme.
	 *
	 * @since    1.0.0
	 * @param    string    $theme_id    Theme ID.
	 * @return   string                 CSS variables string.
	 */
	public function generate_theme_css( $theme_id ) {
		$theme = $this->get_theme( $theme_id );
		if ( ! $theme ) {
			return '';
		}

		$controls = $theme['controls'];
		$styles = isset( $theme['styles'] ) ? $theme['styles'] : array();

		// Check if main controls are enabled
		$main_controls_enabled = isset( $controls['controls'] ) ? $controls['controls'] : true;

		// If main controls are disabled, use the proper mux-player way to hide controls
		if ( ! $main_controls_enabled ) {
			return 'mux-player { --controls: none; }';
		}

		// Main controls are enabled - generate CSS for enabled individual controls
		$css_vars = array();

		// Handle individual control visibility
		$individual_controls = array( 'control-bar', 'play-button' );
		foreach ( $individual_controls as $control_key ) {
			$control_enabled = isset( $controls[ $control_key ] ) ? $controls[ $control_key ] : true;
			if ( ! $control_enabled ) {
				// Generate CSS to hide this specific control
				switch ( $control_key ) {
					case 'control-bar':
						$css_vars[] = 'mux-player::part(bottom) { display: none !important; }';
						break;
					case 'play-button':
						$css_vars[] = 'mux-player::part(center) { display: none !important; }';
						break;
				}
			}
		}

		// Handle primary, secondary, and accent colors only if they're enabled
		if ( isset( $controls['primary-color'] ) && $controls['primary-color'] && isset( $styles['primary-color']['color'] ) ) {
			$css_vars[] = '--media-primary-color: ' . $styles['primary-color']['color'] . ' !important';
		}

		if ( isset( $controls['secondary-color'] ) && $controls['secondary-color'] && isset( $styles['secondary-color']['color'] ) ) {
			$css_vars[] = '--media-secondary-color: ' . $styles['secondary-color']['color'] . ' !important';
		}

		if ( isset( $controls['accent-color'] ) && $controls['accent-color'] && isset( $styles['accent-color']['color'] ) ) {
			$css_vars[] = '--media-accent-color: ' . $styles['accent-color']['color'] . ' !important';
		}

		if ( empty( $css_vars ) ) {
			return '';
		}

		return 'mux-player { ' . implode( '; ', $css_vars ) . '; }';
	}

	/**
	 * Get inline style attributes for a theme.
	 *
	 * @since    1.0.0
	 * @param    string    $theme_id    Theme ID.
	 * @return   string                 Style attribute string.
	 */
	public function get_theme_style_attributes( $theme_id ) {
		$theme = $this->get_theme( $theme_id );
		if ( ! $theme ) {
			return '';
		}

		$controls = $theme['controls'];
		$styles = isset( $theme['styles'] ) ? $theme['styles'] : array();

		// Check if main controls are enabled
		$main_controls_enabled = isset( $controls['controls'] ) ? $controls['controls'] : true;

		// If main controls are disabled, return empty (CSS will handle hiding)
		if ( ! $main_controls_enabled ) {
			return '';
		}

		// Generate inline styles for colors only when controls are enabled
		$css_vars = array();

		if ( isset( $controls['primary-color'] ) && $controls['primary-color'] && isset( $styles['primary-color']['color'] ) ) {
			$css_vars[] = '--media-primary-color: ' . $styles['primary-color']['color'];
		}

		if ( isset( $controls['secondary-color'] ) && $controls['secondary-color'] && isset( $styles['secondary-color']['color'] ) ) {
			$css_vars[] = '--media-secondary-color: ' . $styles['secondary-color']['color'];
		}

		if ( isset( $controls['accent-color'] ) && $controls['accent-color'] && isset( $styles['accent-color']['color'] ) ) {
			$css_vars[] = '--media-accent-color: ' . $styles['accent-color']['color'];
		}

		if ( empty( $css_vars ) ) {
			return '';
		}

		return implode( '; ', $css_vars ) . ';"';
	}

	/**
	 * Get default theme ID.
	 *
	 * @since    1.0.0
	 * @return   string    Default theme ID.
	 */
	public function get_default_theme_id() {
		return get_option( 'wp_mux_default_theme', 'default' );
	}

	/**
	 * Set default theme ID.
	 *
	 * @since    1.0.0
	 * @param    string    $theme_id    Theme ID to set as default.
	 * @return   bool                   True on success, false on failure.
	 */
	public function set_default_theme( $theme_id ) {
		$themes = $this->get_themes();
		if ( ! isset( $themes[ $theme_id ] ) ) {
			return false;
		}

		return update_option( 'wp_mux_default_theme', $theme_id );
	}

	/**
	 * Generate a unique theme ID from a name.
	 *
	 * @since    1.0.0
	 * @param    string    $name    Theme name.
	 * @return   string             Unique theme ID.
	 */
	public function generate_theme_id( $name ) {
		// Special case for default theme
		if ( strtolower( trim( $name ) ) === 'default' ) {
			return 'default';
		}

		// Create a better base ID that preserves more of the original name
		$base_id = $this->create_slug_from_name( $name );
		$themes = $this->get_themes();

		// If this exact slug is available, use it
		if ( ! isset( $themes[ $base_id ] ) ) {
			return $base_id;
		}

		// Generate a unique ID with timestamp suffix to avoid collisions
		$counter = 1;
		$timestamp_suffix = substr( md5( $name . time() ), 0, 6 );

		while ( isset( $themes[ $base_id . '-' . $counter ] ) ) {
			$counter++;
		}

		// If we've tried many counters, use timestamp approach
		if ( $counter > 10 ) {
			return $base_id . '-' . $timestamp_suffix;
		}

		return $base_id . '-' . $counter;
	}

	/**
	 * Create a URL-friendly slug from a theme name.
	 *
	 * @since    1.0.0
	 * @param    string    $name    Theme name.
	 * @return   string             URL-friendly slug.
	 */
	private function create_slug_from_name( $name ) {
		// Convert to lowercase and trim
		$slug = strtolower( trim( $name ) );

		// Replace spaces and special characters with hyphens
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );

		// Remove leading and trailing hyphens
		$slug = trim( $slug, '-' );

		// Ensure we have something
		if ( empty( $slug ) ) {
			$slug = 'theme-' . substr( md5( $name ), 0, 8 );
		}

		return $slug;
	}

}
