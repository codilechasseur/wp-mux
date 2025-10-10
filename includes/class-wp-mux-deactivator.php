<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.0.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 * @author     Codi Lechasseur <codilechasseur@gmail.com>
 */
class Wp_Mux_Deactivator {

	/**
	 * Cleanup on plugin deactivation.
	 *
	 * Clears scheduled cron events for analytics refresh.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Clear the analytics refresh cron
		$timestamp = wp_next_scheduled( 'wp_mux_refresh_analytics_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wp_mux_refresh_analytics_event' );
		}
	}

}
