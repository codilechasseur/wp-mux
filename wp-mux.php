<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://codilechasseur@gmail.com
 * @since             1.0.0
 * @package           Wp_Mux
 *
 * @wordpress-plugin
 * Plugin Name:       WP Mux
 * Plugin URI:        https://wpmux.com
 * Description:       Provides a media library integration for the MUX video hosting platform.
 * Version:           1.0.1
 * Author:            Codi Lechasseur
 * Author URI:        https://codilechasseur@gmail.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-mux
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WP_MUX_VERSION', '1.0.0' );

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
