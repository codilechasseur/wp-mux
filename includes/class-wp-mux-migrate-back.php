<?php
/**
 * Handles migration from Mux back to local WordPress hosting
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

class Wp_Mux_Migrate_Back {

	/**
	 * Check if user's Mux account has MP4 support capability
	 *
	 * @return bool|WP_Error
	 */
	public function check_mp4_capability() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();
		
		// Try to get a video to test MP4 capability
		$videos = get_posts( array(
			'post_type' => 'attachment',
			'post_mime_type' => 'video/mux',
			'posts_per_page' => 1,
		) );
		
		if ( empty( $videos ) ) {
			return true; // No videos to check
		}
		
		$asset_id = get_post_meta( $videos[0]->ID, '_mux_asset_id', true );
		if ( empty( $asset_id ) ) {
			return true;
		}
		
		// Try to enable MP4 support to test capability
		$result = $api->update_mp4_support( $asset_id, 'standard' );
		
		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			// Check for common error messages indicating no MP4 support
			if ( strpos( $error_message, 'not available' ) !== false || 
			     strpos( $error_message, 'upgrade' ) !== false ||
			     strpos( $error_message, 'not enabled' ) !== false ||
			     strpos( $error_message, 'plan' ) !== false ) {
				return new WP_Error( 'no_mp4_capability', 
					__( 'Your Mux account does not support MP4 downloads. This feature requires a paid Mux plan with MP4 support enabled.', 'wp-mux' ) 
				);
			}
		} else {
			// Check if MP4 was actually enabled
			// On free plans, Mux silently ignores the request
			if ( isset( $result['data']['mp4_support'] ) && $result['data']['mp4_support'] === 'none' ) {
				return new WP_Error( 'no_mp4_capability', 
					__( 'Your Mux account does not support MP4 downloads. This feature requires a paid Mux plan with MP4 support enabled.', 'wp-mux' ) 
				);
			}
		}
		
		return true;
	}

	/**
	 * Get all Mux videos with their MP4 status
	 *
	 * @return array|WP_Error
	 */
	public function scan_mux_videos() {
		$videos = get_posts( array(
			'post_type' => 'attachment',
			'post_mime_type' => 'video/mux',
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
		) );
		
		if ( empty( $videos ) ) {
			return array(
				'videos' => array(),
				'total_count' => 0,
				'total_size' => 0,
				'total_size_formatted' => '0 MB',
			);
		}
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();
		
		$all_videos = array();
		$total_size = 0;
		
		foreach ( $videos as $video ) {
			$asset_id = get_post_meta( $video->ID, '_mux_asset_id', true );
			$playback_id = get_post_meta( $video->ID, '_mux_playback_id', true );
			
			if ( empty( $asset_id ) ) {
				continue;
			}
			
			$asset_data = $api->get_video( $asset_id );
			
			if ( is_wp_error( $asset_data ) ) {
				continue;
			}
			
			$mp4_support = $asset_data['data']['mp4_support'] ?? 'none';
			$duration = $asset_data['data']['duration'] ?? 0;
			
			// Estimate file size (rough calculation)
			$estimated_size = 0;
			if ( ! empty( $asset_data['data']['max_stored_resolution'] ) ) {
				$resolution = $asset_data['data']['max_stored_resolution'];
				// Rough estimate: HD video ~= 100MB per minute
				$mb_per_minute = ( $resolution === 'HD' || $resolution === '1080p' ) ? 100 : 50;
				$estimated_size = ( $duration / 60 ) * $mb_per_minute * 1024 * 1024;
			}
			
			$video_data = array(
				'attachment_id' => $video->ID,
				'title' => $video->post_title,
				'asset_id' => $asset_id,
				'playback_id' => $playback_id,
				'has_mp4' => $mp4_support === 'standard',
				'mp4_support' => $mp4_support,
				'duration' => $duration,
				'estimated_size' => $estimated_size,
				'formatted_size' => size_format( $estimated_size ),
			);
			
			$all_videos[] = $video_data;
			
			if ( $mp4_support === 'standard' ) {
				$total_size += $estimated_size;
			}
		}
		
		return array(
			'videos' => $all_videos,
			'total_count' => count( $all_videos ),
			'total_size' => $total_size,
			'total_size_formatted' => size_format( $total_size ),
		);
	}

	/**
	 * Enable MP4 support for a video asset
	 *
	 * @param string $asset_id
	 * @return bool|WP_Error
	 */
	public function enable_mp4_support( $asset_id ) {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();
		
		$result = $api->update_mp4_support( $asset_id, 'standard' );
		
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		
		return true;
	}

	/**
	 * Download video from Mux and update attachment
	 *
	 * @param int $attachment_id
	 * @return array|WP_Error
	 */
	public function migrate_video_to_local( $attachment_id ) {
		$playback_id = get_post_meta( $attachment_id, '_mux_playback_id', true );
		$asset_id = get_post_meta( $attachment_id, '_mux_asset_id', true );
		
		if ( empty( $playback_id ) ) {
			return new WP_Error( 'no_playback_id', __( 'Video has no playback ID', 'wp-mux' ) );
		}
		
		// Step 1: Download MP4 from Mux
		$mp4_url = "https://stream.mux.com/{$playback_id}.mp4";
		$upload_dir = wp_upload_dir();
		$filename = sanitize_file_name( $playback_id . '.mp4' );
		$filepath = $upload_dir['path'] . '/' . $filename;
		
		// Download file
		$response = wp_remote_get( $mp4_url, array(
			'timeout' => 600, // 10 minutes for large files
			'stream' => true,
			'filename' => $filepath,
		) );
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new WP_Error( 'download_failed', __( 'Failed to download video from Mux', 'wp-mux' ) );
		}
		
		// Verify file was downloaded
		if ( ! file_exists( $filepath ) || filesize( $filepath ) === 0 ) {
			return new WP_Error( 'download_failed', __( 'Downloaded file is empty or missing', 'wp-mux' ) );
		}
		
		// Step 2: Update attachment to point to local file
		update_attached_file( $attachment_id, $filepath );
		
		// Step 3: Update mime type
		wp_update_post( array(
			'ID' => $attachment_id,
			'post_mime_type' => 'video/mp4',
		) );
		
		// Step 4: Generate WordPress video metadata
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		$metadata = wp_generate_attachment_metadata( $attachment_id, $filepath );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		
		// Step 5: Store migration data (keep Mux IDs for optional deletion later)
		update_post_meta( $attachment_id, '_wp_mux_migrate_back', array(
			'downloaded_at' => current_time( 'mysql' ),
			'original_playback_id' => $playback_id,
			'original_asset_id' => $asset_id,
			'mux_deleted' => false,
			'local_file_size' => filesize( $filepath ),
		) );
		
		// Step 6: Keep essential Mux metadata for now (don't delete until user confirms)
		// We'll clean this up in the cleanup step
		
		return array(
			'success' => true,
			'attachment_id' => $attachment_id,
			'filepath' => $filepath,
			'filesize' => filesize( $filepath ),
			'formatted_size' => size_format( filesize( $filepath ) ),
		);
	}

	/**
	 * Validate system requirements for migration
	 *
	 * @return array
	 */
	public function validate_system() {
		$upload_dir = wp_upload_dir();
		$available_space = @disk_free_space( $upload_dir['basedir'] );
		$max_execution_time = ini_get( 'max_execution_time' );
		$memory_limit = ini_get( 'memory_limit' );
		
		// Check MP4 capability
		$mp4_capability = $this->check_mp4_capability();
		$has_mp4_capability = ! is_wp_error( $mp4_capability );
		$mp4_error_message = is_wp_error( $mp4_capability ) ? $mp4_capability->get_error_message() : null;
		
		return array(
			'disk_space' => $available_space !== false ? size_format( $available_space ) : null,
			'writable' => wp_is_writable( $upload_dir['path'] ),
			'php_version' => PHP_VERSION,
			'max_execution_time' => $max_execution_time == 0 ? 'Unlimited' : $max_execution_time . 's',
			'memory_limit' => $memory_limit,
			'has_mp4_capability' => $has_mp4_capability,
			'mp4_error_message' => $mp4_error_message,
		);
	}

	/**
	 * Delete video from Mux after successful migration
	 *
	 * @param int $attachment_id
	 * @return bool|WP_Error
	 */
	public function cleanup_mux_video( $attachment_id ) {
		$migrate_data = get_post_meta( $attachment_id, '_wp_mux_migrate_back', true );
		
		if ( empty( $migrate_data['original_asset_id'] ) ) {
			return new WP_Error( 'no_mux_data', __( 'No Mux data found for this video', 'wp-mux' ) );
		}
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();
		
		$result = $api->delete_video( $migrate_data['original_asset_id'] );
		
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		
		// Update migration data
		$migrate_data['mux_deleted'] = true;
		$migrate_data['deleted_at'] = current_time( 'mysql' );
		update_post_meta( $attachment_id, '_wp_mux_migrate_back', $migrate_data );
		
		// Now safe to delete Mux metadata
		$mux_meta_keys = array(
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
		);
		
		foreach ( $mux_meta_keys as $key ) {
			delete_post_meta( $attachment_id, $key );
		}
		
		return true;
	}
}
