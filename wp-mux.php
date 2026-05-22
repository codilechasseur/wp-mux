<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://wpmux.com
 * @since             1.0.0
 * @package           Wp_Mux
 *
 * @wordpress-plugin
 * Plugin Name:       WP Mux
 * Plugin URI:        https://wpmux.com
 * Description:       Provides a media library integration for the MUX video hosting platform.
 * Version:           1.1.1
 * Author:            Codi Lechasseur
 * Author URI:        https://wpmux.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-mux
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// ---------------------------------------------------------------------------
// Freemius bootstrap
// ---------------------------------------------------------------------------
if ( ! function_exists( 'wp_mux_fs' ) ) {
	function wp_mux_fs() {
		global $wp_mux_fs;
		if ( ! isset( $wp_mux_fs ) ) {
			$sdk_path = plugin_dir_path( __FILE__ ) . 'vendor/freemius/wordpress-sdk/start.php';
			if ( ! file_exists( $sdk_path ) ) {
				return null;
			}
			require_once $sdk_path;
			$wp_mux_fs = fs_dynamic_init( array(
				'id'                  => '30325',
				'slug'                => 'wp-mux',
				'premium_slug'        => 'wp-mux-pro',
				'type'                => 'plugin',
				'public_key'          => 'pk_b2045781d1c2ebd1f82e8d84feb99',
				'is_premium'          => true,
				'premium_suffix'      => 'Pro',
				'has_premium_version' => true,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'is_org_compliant'    => true,
				// Automatically removed in the free version. If you're not using the
				// auto-generated free version, delete this line before uploading to wp.org.
				'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
				'menu'                => array(
					'slug'    => 'wp-mux-videos',
					'support' => false,
				),
			) );
		}
		return $wp_mux_fs;
	}
	wp_mux_fs();
	do_action( 'wp_mux_fs_loaded' );
}

/**
 * Returns true if the current site has an active WP Mux Pro license.
 *
 * Falls back gracefully to false when the Freemius SDK is not yet installed
 * (e.g. during local development before `composer install` has been run).
 *
 * @since  1.2.0
 * @return bool
 */
function wp_mux_is_pro(): bool {
	$fs = wp_mux_fs();
	if ( $fs === null ) {
		return false;
	}
	return $fs->can_use_premium_code();
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WP_MUX_VERSION', '1.1.1' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wp-mux-activator.php
 */
function activate_wp_mux() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-mux-activator.php';
	Wp_Mux_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wp-mux-deactivator.php
 */
function deactivate_wp_mux() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-mux-deactivator.php';
	Wp_Mux_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_mux' );
register_deactivation_hook( __FILE__, 'deactivate_wp_mux' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wp-mux.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wp_mux() {

	$plugin = new Wp_Mux();
	$plugin->run();

}
run_wp_mux();
