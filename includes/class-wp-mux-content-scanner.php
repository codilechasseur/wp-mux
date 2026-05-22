<?php

declare(strict_types=1);
/**
 * Scans and updates post content for Mux video references
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

class Wp_Mux_Content_Scanner {

	/**
	 * Scan all posts for Mux video references
	 *
	 * @return array
	 */
	public function scan_content_references() {
		global $wpdb;

		// Get all Mux videos
		$videos = get_posts( array(
			'post_type' => 'attachment',
			'post_mime_type' => 'video/mux',
			'posts_per_page' => -1,
		) );

		$references = array();
		$total_references = 0;

		foreach ( $videos as $video ) {
			$playback_id = get_post_meta( $video->ID, '_mux_playback_id', true );
			$asset_id = get_post_meta( $video->ID, '_mux_asset_id', true );

			if ( empty( $playback_id ) ) {
				continue;
			}

			// Search for mux-player elements with this playback ID
			$posts_with_player = $wpdb->get_results( $wpdb->prepare(
				"SELECT ID, post_title, post_type, post_status
				 FROM $wpdb->posts
				 WHERE post_content LIKE %s
				 AND post_status IN ('publish', 'draft', 'pending')
				 AND post_type IN ('post', 'page')",
				'%' . $wpdb->esc_like( $playback_id ) . '%'
			) );

			// Search for Gutenberg blocks with this attachment ID
			$posts_with_block = $wpdb->get_results( $wpdb->prepare(
				"SELECT ID, post_title, post_type, post_status
				 FROM $wpdb->posts
				 WHERE post_content LIKE %s
				 AND post_status IN ('publish', 'draft', 'pending')
				 AND post_type IN ('post', 'page')",
				'%"id":' . intval( $video->ID ) . '%'
			) );

			// Merge and deduplicate
			$all_posts = array_merge( $posts_with_player, $posts_with_block );
			$unique_posts = array();
			$seen_ids = array();

			foreach ( $all_posts as $post ) {
				if ( ! in_array( $post->ID, $seen_ids ) ) {
					$unique_posts[] = $post;
					$seen_ids[] = $post->ID;
				}
			}

			if ( ! empty( $unique_posts ) ) {
				$references[ $video->ID ] = array(
					'video_id' => $video->ID,
					'video_title' => $video->post_title,
					'playback_id' => $playback_id,
					'asset_id' => $asset_id,
					'posts' => $unique_posts,
					'count' => count( $unique_posts ),
				);
				$total_references += count( $unique_posts );
			}
		}

		return array(
			'references' => $references,
			'total_posts' => $total_references,
			'total_videos' => count( $references ),
		);
	}

	/**
	 * Preview content changes for a specific post
	 *
	 * @param int $post_id
	 * @return array
	 */
	public function preview_content_changes( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$content = $post->post_content;
		$changes = array();

		// Find all mux-player elements
		preg_match_all( '/<mux-player[^>]*playback-id="([^"]+)"[^>]*>.*?<\/mux-player>/is', $content, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$full_element = $match[0];
			$playback_id = $match[1];

			// Find the attachment with this playback ID
			$attachments = get_posts( array(
				'post_type' => 'attachment',
				'meta_key' => '_mux_playback_id',
				'meta_value' => $playback_id,
				'posts_per_page' => 1,
			) );

			if ( empty( $attachments ) ) {
				continue;
			}

			$attachment = $attachments[0];
			$attachment_url = wp_get_attachment_url( $attachment->ID );

			// Generate replacement video element
			$replacement = $this->generate_video_element( $attachment->ID, $attachment_url, $full_element );

			$changes[] = array(
				'from' => $full_element,
				'to' => $replacement,
				'playback_id' => $playback_id,
				'attachment_id' => $attachment->ID,
			);
		}

		return $changes;
	}

	/**
	 * Update content for a single post
	 *
	 * @param int $post_id
	 * @param bool $create_backup
	 * @return array|WP_Error
	 */
	public function update_post_content( $post_id, $create_backup = true ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found', 'wp-mux' ) );
		}

		// Create backup if requested
		if ( $create_backup ) {
			update_post_meta( $post_id, '_wp_mux_content_backup', $post->post_content );
			update_post_meta( $post_id, '_wp_mux_content_backup_date', current_time( 'mysql' ) );
		}

		$content = $post->post_content;
		$original_content = $content;
		$changes_made = 0;

		// Replace mux-player elements
		preg_match_all( '/<mux-player[^>]*playback-id="([^"]+)"[^>]*>.*?<\/mux-player>/is', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

		// Process in reverse order to maintain offsets
		$matches = array_reverse( $matches );

		foreach ( $matches as $match ) {
			$full_element = $match[0][0];
			$playback_id = $match[1][0];
			$offset = $match[0][1];

			// Find the attachment
			$attachments = get_posts( array(
				'post_type' => 'attachment',
				'meta_key' => '_mux_playback_id',
				'meta_value' => $playback_id,
				'posts_per_page' => 1,
			) );

			if ( empty( $attachments ) ) {
				continue;
			}

			$attachment = $attachments[0];
			$attachment_url = wp_get_attachment_url( $attachment->ID );

			// Generate replacement
			$replacement = $this->generate_video_element( $attachment->ID, $attachment_url, $full_element );

			// Replace in content
			$content = substr_replace( $content, $replacement, $offset, strlen( $full_element ) );
			$changes_made++;
		}

		// Only update if changes were made
		if ( $changes_made > 0 ) {
			$result = wp_update_post( array(
				'ID' => $post_id,
				'post_content' => $content,
			), true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Log the update
			$updates_log = get_post_meta( $post_id, '_wp_mux_content_updates', true );
			if ( ! is_array( $updates_log ) ) {
				$updates_log = array();
			}

			$updates_log[] = array(
				'updated_at' => current_time( 'mysql' ),
				'changes' => $changes_made,
			);

			update_post_meta( $post_id, '_wp_mux_content_updates', $updates_log );
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'changes' => $changes_made,
			'has_backup' => $create_backup,
		);
	}

	/**
	 * Generate HTML5 video element from mux-player
	 *
	 * @param int $attachment_id
	 * @param string $video_url
	 * @param string $original_element
	 * @return string
	 */
	private function generate_video_element( $attachment_id, $video_url, $original_element ) {
		// Extract attributes from original mux-player
		$has_controls = ( strpos( $original_element, 'controls' ) !== false );
		$has_autoplay = ( strpos( $original_element, 'autoplay' ) !== false );
		$has_loop = ( strpos( $original_element, 'loop' ) !== false );
		$has_muted = ( strpos( $original_element, 'muted' ) !== false );

		// Extract poster if present
		$poster = '';
		if ( preg_match( '/poster="([^"]+)"/', $original_element, $poster_match ) ) {
			$poster = ' poster="' . esc_attr( $poster_match[1] ) . '"';
		}

		// Check if this is in a Gutenberg block
		$is_gutenberg = ( strpos( $original_element, 'wp-block' ) !== false ||
		                 strpos( $original_element, '<!-- wp:' ) !== false );

		if ( $is_gutenberg ) {
			// Generate Gutenberg-compatible video block
			$block_attrs = wp_json_encode( array( 'id' => $attachment_id ) );

			return '<!-- wp:video ' . $block_attrs . ' -->' . "\n" .
			       '<figure class="wp-block-video">' .
			       '<video controls src="' . esc_url( $video_url ) . '"' . $poster . '></video>' .
			       '</figure>' . "\n" .
			       '<!-- /wp:video -->';
		} else {
			// Generate standard HTML5 video element
			$attributes = array();
			if ( $has_controls ) $attributes[] = 'controls';
			if ( $has_autoplay ) $attributes[] = 'autoplay';
			if ( $has_loop ) $attributes[] = 'loop';
			if ( $has_muted ) $attributes[] = 'muted';

			$attrs_string = implode( ' ', $attributes );

			return '<video src="' . esc_url( $video_url ) . '"' . $poster . ' ' . $attrs_string . '>' .
			       '<p>' . __( 'Your browser does not support HTML5 video.', 'wp-mux' ) . '</p>' .
			       '</video>';
		}
	}

	/**
	 * Restore content from backup
	 *
	 * @param int $post_id
	 * @return bool|WP_Error
	 */
	public function restore_from_backup( $post_id ) {
		$backup_content = get_post_meta( $post_id, '_wp_mux_content_backup', true );

		if ( empty( $backup_content ) ) {
			return new WP_Error( 'no_backup', __( 'No backup found for this post', 'wp-mux' ) );
		}

		$result = wp_update_post( array(
			'ID' => $post_id,
			'post_content' => $backup_content,
		), true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Delete backup after restore
		delete_post_meta( $post_id, '_wp_mux_content_backup' );
		delete_post_meta( $post_id, '_wp_mux_content_backup_date' );

		return true;
	}

	/**
	 * Batch update multiple posts
	 *
	 * @param array $post_ids
	 * @param bool $create_backup
	 * @return array
	 */
	public function batch_update_content( $post_ids, $create_backup = true ) {
		$results = array(
			'success' => array(),
			'failed' => array(),
			'total_changes' => 0,
		);

		foreach ( $post_ids as $post_id ) {
			$result = $this->update_post_content( $post_id, $create_backup );

			if ( is_wp_error( $result ) ) {
				$results['failed'][] = array(
					'post_id' => $post_id,
					'error' => $result->get_error_message(),
				);
			} else {
				$results['success'][] = $post_id;
				$results['total_changes'] += $result['changes'];
			}
		}

		return $results;
	}
}
