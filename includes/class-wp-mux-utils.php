<?php
/**
 * Utility functions for WP Mux plugin.
 *
 * Provides common functionality used across the plugin to reduce code duplication.
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.2.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

/**
 * WP Mux utility class.
 *
 * Static utility methods for common operations.
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 * @author     Codi Lechasseur <codilechasseur@gmail.com>
 */
class Wp_Mux_Utils {

	/**
	 * Verify nonce and user capability for AJAX requests.
	 *
	 * Sends JSON error and dies if verification fails.
	 *
	 * @since    1.2.0
	 * @param    string    $nonce_key    The nonce key to verify.
	 * @param    string    $capability   Required user capability (default: 'manage_options').
	 */
	public static function verify_ajax_request( $nonce_key, $capability = 'manage_options' ) {
		// Verify nonce
		if ( ! check_ajax_referer( $nonce_key, 'nonce', false ) ) {
			wp_send_json_error(
				self::error_response(
					__( 'Security verification failed. Please refresh the page and try again.', 'wp-mux' ),
					403
				)
			);
		}

		// Verify user capability
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error(
				self::error_response(
					__( 'You do not have permission to perform this action.', 'wp-mux' ),
					403
				)
			);
		}
	}

	/**
	 * Create standardized error response.
	 *
	 * @since    1.2.0
	 * @param    string    $message    Error message.
	 * @param    int       $code       HTTP status code.
	 * @param    array     $data       Additional error data.
	 * @return   array                 Formatted error response.
	 */
	public static function error_response( $message, $code = 400, $data = array() ) {
		return array(
			'success' => false,
			'message' => $message,
			'code'    => $code,
			'data'    => $data,
		);
	}

	/**
	 * Create standardized success response.
	 *
	 * @since    1.2.0
	 * @param    mixed     $data       Response data.
	 * @param    string    $message    Success message.
	 * @return   array                 Formatted success response.
	 */
	public static function success_response( $data = array(), $message = '' ) {
		$response = array(
			'success' => true,
			'data'    => $data,
		);

		if ( ! empty( $message ) ) {
			$response['message'] = $message;
		}

		return $response;
	}

	/**
	 * Get a cached value or fetch and cache it.
	 *
	 * @since    1.2.0
	 * @param    string    $cache_key    Cache key.
	 * @param    callable  $callback     Callback to fetch data if not cached.
	 * @param    int       $expiration   Cache expiration in seconds.
	 * @return   mixed                   Cached or freshly fetched data.
	 */
	public static function get_cached_or_fetch( $cache_key, $callback, $expiration = HOUR_IN_SECONDS ) {
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$result = call_user_func( $callback );

		// Only cache non-error results
		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, $expiration );
		}

		return $result;
	}

	/**
	 * Invalidate cached data by pattern.
	 *
	 * @since    1.2.0
	 * @param    string    $pattern    Pattern to match (with wildcard *).
	 */
	public static function invalidate_cache( $pattern = 'wp_mux_*' ) {
		global $wpdb;

		$pattern = str_replace( '*', '%', $pattern );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND (option_name LIKE '_transient_" . $wpdb->esc_like( $pattern ) . "'
				OR option_name LIKE '_transient_timeout_" . $wpdb->esc_like( $pattern ) . "')",
				$pattern
			)
		);
	}

	/**
	 * Sanitize and validate theme name.
	 *
	 * @since    1.2.0
	 * @param    string    $name    Theme name.
	 * @return   string             Sanitized theme name.
	 */
	public static function sanitize_theme_name( $name ) {
		return sanitize_text_field( trim( $name ) );
	}

	/**
	 * Create a slug from a name.
	 *
	 * @since    1.2.0
	 * @param    string    $name    Name to slugify.
	 * @return   string             Slug.
	 */
	public static function create_slug( $name ) {
		return sanitize_title( $name );
	}

	/**
	 * Log debug message if WP_DEBUG is enabled.
	 *
	 * @since    1.2.0
	 * @param    string    $message    Message to log.
	 * @param    string    $level      Log level (info, warning, error).
	 */
	public static function log( $message, $level = 'info' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$prefix = '[WP MUX ' . strtoupper( $level ) . ']';
			error_log( $prefix . ' ' . $message );
		}
	}

	/**
	 * Check if current request is an AJAX request.
	 *
	 * @since    1.2.0
	 * @return   bool    True if AJAX request.
	 */
	public static function is_ajax() {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	/**
	 * Check if current request is from admin area.
	 *
	 * @since    1.2.0
	 * @return   bool    True if admin request.
	 */
	public static function is_admin() {
		return is_admin() && ! self::is_ajax();
	}

	/**
	 * Format bytes to human-readable size.
	 *
	 * @since    1.2.0
	 * @param    int       $bytes      Bytes to format.
	 * @param    int       $precision  Decimal precision.
	 * @return   string                Formatted size string.
	 */
	public static function format_bytes( $bytes, $precision = 2 ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

		for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
			$bytes /= 1024;
		}

		return round( $bytes, $precision ) . ' ' . $units[ $i ];
	}

	/**
	 * Format seconds to human-readable duration.
	 *
	 * @since    1.2.0
	 * @param    float     $seconds    Duration in seconds.
	 * @return   string                Formatted duration string.
	 */
	public static function format_duration( $seconds ) {
		$hours = floor( $seconds / 3600 );
		$minutes = floor( ( $seconds % 3600 ) / 60 );
		$seconds = $seconds % 60;

		$parts = array();

		if ( $hours > 0 ) {
			$parts[] = sprintf( '%d:%02d:%02d', $hours, $minutes, $seconds );
		} elseif ( $minutes > 0 ) {
			$parts[] = sprintf( '%d:%02d', $minutes, $seconds );
		} else {
			$parts[] = sprintf( '0:%02d', $seconds );
		}

		return implode( '', $parts );
	}

	/**
	 * Batch update post meta for multiple posts.
	 *
	 * More efficient than multiple update_post_meta() calls.
	 *
	 * @since    1.2.0
	 * @param    array     $updates    Array of [post_id => [meta_key => meta_value]].
	 */
	public static function batch_update_post_meta( $updates ) {
		foreach ( $updates as $post_id => $meta_data ) {
			foreach ( $meta_data as $meta_key => $meta_value ) {
				update_post_meta( $post_id, $meta_key, $meta_value );
			}
		}

		// Prime meta cache for all updated posts
		update_meta_cache( 'post', array_keys( $updates ) );
	}

	/**
	 * Get multiple post meta values in a single query.
	 *
	 * @since    1.2.0
	 * @param    array     $post_ids     Post IDs.
	 * @param    string    $meta_key     Meta key to fetch.
	 * @param    bool      $single       Return single value per post.
	 * @return   array                   Array of [post_id => meta_value].
	 */
	public static function get_multiple_post_meta( $post_ids, $meta_key, $single = true ) {
		if ( empty( $post_ids ) ) {
			return array();
		}

		// Prime the cache
		update_meta_cache( 'post', $post_ids );

		$results = array();
		foreach ( $post_ids as $post_id ) {
			$results[ $post_id ] = get_post_meta( $post_id, $meta_key, $single );
		}

		return $results;
	}
}
