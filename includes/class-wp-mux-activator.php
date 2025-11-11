<?php

/**
 * Fired during plugin activation
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.0.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 * @author     Codi Lechasseur <codilechasseur@gmail.com>
 */
class Wp_Mux_Activator {

	/**
	 * Run during plugin activation.
	 * 
	 * Sets up default options and automatically configures signing keys
	 * for secure video playback.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Set default options with secure defaults
		self::set_default_options();
		
		// Attempt to auto-configure signing keys
		self::auto_setup_signing_keys();
	}

	/**
	 * Set default plugin options.
	 *
	 * @since    1.3.0
	 */
	private static function set_default_options() {
		// Set conservative defaults
		// Note: We default to PUBLIC now since signing keys may not be available on all Mux accounts
		add_option( 'wp_mux_default_playback_policy', 'public' );
		add_option( 'wp_mux_default_mp4_support', 'none' );
		add_option( 'wp_mux_default_encoding_tier', 'baseline' );
		add_option( 'wp_mux_enable_auto_captions', false );
		add_option( 'wp_mux_default_caption_language', 'en' );
	}

	/**
	 * Automatically configure signing keys on plugin activation.
	 *
	 * This is OPTIONAL - if it fails, the plugin will work fine with public videos.
	 * Users can manually configure signing keys later if they want private videos.
	 *
	 * @since    1.3.0
	 */
	private static function auto_setup_signing_keys() {
		// Load the API class
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		
		$api = new Wp_Mux_Api();
		
		// Check if API is configured
		if ( ! $api->is_configured() ) {
			// Can't create keys without API credentials
			set_transient( 'wp_mux_needs_api_config', true, DAY_IN_SECONDS );
			error_log( '[WP MUX] Plugin activated but API credentials not configured.' );
			return;
		}
		
		// Check if keys already exist (from previous installation)
		$existing_key_id = get_option( 'wp_mux_signing_key_id', '' );
		if ( ! empty( $existing_key_id ) ) {
			error_log( '[WP MUX] Signing keys already exist: ' . $existing_key_id );
			// If keys exist, set default to signed
			update_option( 'wp_mux_default_playback_policy', 'signed' );
			return;
		}
		
		// Try to auto-configure signing keys (this is optional)
		$result = $api->auto_configure_signing_keys();
		
		if ( is_wp_error( $result ) ) {
			// Log but don't show error - this is expected for many Mux accounts
			$error_msg = $result->get_error_message();
			error_log( '[WP MUX] Automatic signing key creation not available: ' . $error_msg );
			
			// Set a transient with helpful info (not an error, just informational)
			set_transient( 'wp_mux_signing_keys_info', $error_msg, WEEK_IN_SECONDS );
			
			// Default to public (safe default)
			update_option( 'wp_mux_default_playback_policy', 'public' );
		} else {
			// Success! Log it and set to signed
			error_log( '[WP MUX] Signing keys auto-configured on activation: ' . $result['key_id'] );
			set_transient( 'wp_mux_signing_key_success', $result['key_id'], WEEK_IN_SECONDS );
			
			// Now we can default to signed URLs
			update_option( 'wp_mux_default_playback_policy', 'signed' );
		}
	}

}
