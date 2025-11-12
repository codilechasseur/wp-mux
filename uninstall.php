<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * This file handles cleanup when WP Mux is uninstalled.
 * 
 * IMPORTANT: By default, this performs MINIMAL cleanup to prevent accidental data loss.
 * - Transients are deleted (temporary data)
 * - Cron jobs are cleared
 * - Processing queue is cleared
 * 
 * NOT deleted by default:
 * - WordPress attachments (video/mux posts)
 * - Post meta data
 * - Plugin settings/options
 * - Videos on Mux.com (these must be manually deleted from Mux dashboard)
 *
 * Users can enable full cleanup by checking the option in Settings before uninstalling.
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.0.0
 *
 * @package    Wp_Mux
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Check if user has opted into full data deletion
 */
$delete_all_data = get_option( 'wp_mux_delete_on_uninstall', false );

/**
 * ALWAYS delete temporary data (safe cleanup)
 */

// Delete all transients
$transients = array(
	'wp_mux_upload_success_',    // Per-user, will have user ID appended
	'wp_mux_settings_updated_',  // Per-user
	'wp_mux_theme_error_',       // Per-user
	'wp_mux_theme_success_',     // Per-user
	'wp_mux_video_details_',     // Per-video
	'wp_mux_account_stats',
	'wp_mux_has_local_videos',
	'wp_mux_has_caption_generation',
	'wp_mux_has_signing_keys',
	'wp_mux_needs_api_config',
	'wp_mux_needs_key_creation',
	'wp_mux_signing_key_success',
	'wp_mux_signing_key_error',
	'wp_mux_signing_keys_info',
);

// Delete simple transients
foreach ( $transients as $transient ) {
	delete_transient( $transient );
}

// Delete user-specific transients (search for all users)
global $wpdb;
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_wp_mux_%'" );
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_wp_mux_%'" );

// Clear scheduled cron jobs
$cron_jobs = array(
	'wp_mux_process_queue_event',
	'wp_mux_refresh_analytics_event',
);

foreach ( $cron_jobs as $hook ) {
	$timestamp = wp_next_scheduled( $hook );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $hook );
	}
	// Also clear any other instances
	wp_clear_scheduled_hook( $hook );
}

// Clear processing queue (safe to delete as videos are already on Mux)
delete_option( 'wp_mux_processing_queue' );

/**
 * Full cleanup (only if user opted in)
 */
if ( $delete_all_data ) {
	
	// Delete all plugin options
	$options = array(
		'wp_mux_access_token',
		'wp_mux_secret_key',
		'wp_mux_signing_key_id',
		'wp_mux_signing_key_private',
		'wp_mux_override_video_upload',
		'wp_mux_default_playback_policy',
		'wp_mux_default_mp4_support',
		'wp_mux_default_encoding_tier',
		'wp_mux_enable_auto_captions',
		'wp_mux_default_caption_language',
		'wp_mux_player_prefer_playback',
		'wp_mux_player_max_resolution',
		'wp_mux_player_min_resolution',
		'wp_mux_player_preload',
		'wp_mux_player_debug',
		'wp_mux_themes',
		'wp_mux_default_theme',
		'wp_mux_delete_local_after_migration',
		'wp_mux_delete_on_uninstall',
	);
	
	foreach ( $options as $option ) {
		delete_option( $option );
	}
	
	// Get all MUX video attachments
	$mux_videos = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'post_mime_type' => 'video/mux',
	) );
	
	// Convert MUX attachments to broken placeholders instead of deleting
	// This preserves attachment IDs so Gutenberg blocks don't break completely
	foreach ( $mux_videos as $video ) {
		// Store essential data before cleanup (for potential recovery)
		$playback_id = get_post_meta( $video->ID, '_mux_playback_id', true );
		$asset_id = get_post_meta( $video->ID, '_mux_asset_id', true );
		
		// Save recovery data in a single meta field
		if ( $playback_id || $asset_id ) {
			update_post_meta( $video->ID, '_wp_mux_uninstall_recovery', array(
				'playback_id' => $playback_id,
				'asset_id' => $asset_id,
				'uninstalled_at' => current_time( 'mysql' ),
			) );
		}
		
		// Delete all MUX-specific post meta
		$meta_keys = array(
			'_mux_asset_id',
			'_mux_playback_id',
			'_mux_upload_id',
			'_mux_thumbnail_url',
			'_mux_duration',
			'_mux_created_at',
			'_mux_playback_policy',
			'_mux_requires_signed_url',
			'_mux_mp4_support',
			'_mux_encoding_tier',
			'_mux_has_captions',
			'_mux_caption_track_ids',
			'_mux_processing',
			'_mux_from_upload',
			'_mux_migrated',
			'_wp_mux_status',
			'_wp_mux_queued_at',
			'_wp_mux_processing_started',
			'_wp_mux_completed_at',
			'_wp_mux_failed_at',
			'_wp_mux_error',
			'_mux_analytics_cache',
			'_mux_analytics_timestamp',
		);
		
		foreach ( $meta_keys as $meta_key ) {
			delete_post_meta( $video->ID, $meta_key );
		}
		
		// Change mime type to broken/orphaned marker
		// This keeps the attachment alive but marks it as disconnected
		wp_update_post( array(
			'ID' => $video->ID,
			'post_mime_type' => 'video/mux-disconnected',
		) );
		
		// Update title to indicate it's disconnected
		$current_title = get_the_title( $video->ID );
		if ( ! strpos( $current_title, '[Disconnected]' ) ) {
			wp_update_post( array(
				'ID' => $video->ID,
				'post_title' => '[Disconnected] ' . $current_title,
			) );
		}
	}
	
	// Clean up any remaining orphaned meta from deleted attachments
	$wpdb->query( "DELETE pm FROM $wpdb->postmeta pm LEFT JOIN $wpdb->posts p ON pm.post_id = p.ID WHERE p.ID IS NULL AND (pm.meta_key LIKE '_mux_%' OR pm.meta_key LIKE '_wp_mux_%')" );
}

// For multisite, clean up site options
if ( is_multisite() ) {
	delete_site_option( 'wp_mux_network_settings' );
}
