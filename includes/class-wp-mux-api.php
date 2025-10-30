<?php

/**
 * MUX API client for handling video operations.
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.0.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

/**
 * MUX API client class.
 *
 * Handles communication with the MUX API for video upload, listing, and management.
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 * @author     Codi Lechasseur <codilechasseur@gmail.com>
 */
class Wp_Mux_Api {

	/**
	 * MUX API base URL.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $api_base_url    The MUX API base URL.
	 */
	private $api_base_url = 'https://api.mux.com';

	/**
	 * MUX access token.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $access_token    The MUX access token.
	 */
	private $access_token;

	/**
	 * MUX secret key.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $secret_key    The MUX secret key.
	 */
	private $secret_key;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->access_token = get_option( 'wp_mux_access_token', '' );
		$this->secret_key = get_option( 'wp_mux_secret_key', '' );
	}

	/**
	 * Check if API credentials are configured.
	 *
	 * @since    1.0.0
	 * @return   bool    True if credentials are set, false otherwise.
	 */
	public function is_configured() {
		return ! empty( $this->access_token ) && ! empty( $this->secret_key );
	}

	/**
	 * Make an authenticated request to the MUX API.
	 *
	 * @since    1.0.0
	 * @param    string    $endpoint    The API endpoint (without base URL).
	 * @param    string    $method      The HTTP method (GET, POST, DELETE, etc.).
	 * @param    array     $data        The request data.
	 * @return   array|WP_Error         The API response or WP_Error on failure.
	 */
	private function make_request( $endpoint, $method = 'GET', $data = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'missing_credentials', __( 'MUX API credentials are not configured.', 'wp-mux' ) );
		}

		$url = $this->api_base_url . $endpoint;
		$auth = base64_encode( $this->access_token . ':' . $this->secret_key );

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Basic ' . $auth,
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'WP-Mux/1.0.0',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code >= 400 ) {
			$error_data = json_decode( $response_body, true );
			$error_message = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : __( 'Unknown API error', 'wp-mux' );
			return new WP_Error( 'api_error', $error_message, array( 'status' => $response_code ) );
		}

		return json_decode( $response_body, true );
	}

	/**
	 * Get a list of videos from MUX.
	 *
	 * @since    1.0.0
	 * @param    int    $limit    Number of videos to retrieve (max 100).
	 * @param    int    $page     Page number for pagination.
	 * @return   array|WP_Error   The videos list or WP_Error on failure.
	 */
	public function get_videos( $limit = 25, $page = 1 ) {
		$endpoint = '/video/v1/assets';
		$params = array(
			'limit' => min( $limit, 100 ),
			'page'  => max( $page, 1 ),
		);

		$endpoint .= '?' . http_build_query( $params );

		$response = $this->make_request( $endpoint );

		// If successful, enhance the response with computed fields
		if ( ! is_wp_error( $response ) && isset( $response['data'] ) ) {
			foreach ( $response['data'] as &$video ) {
				$video = $this->enhance_video_data( $video );
			}
		}

		return $response;
	}

	/**
	 * Get details for a specific video.
	 *
	 * @since    1.0.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @return   array|WP_Error         The video details or WP_Error on failure.
	 */
	public function get_video( $asset_id ) {
		$endpoint = '/video/v1/assets/' . sanitize_text_field( $asset_id );
		return $this->make_request( $endpoint );
	}

	/**
	 * Create a direct upload URL for video files.
	 *
	 * @since    1.0.0
	 * @param    array    $options    Upload options (cors_origin, new_asset_settings, etc.).
	 * @return   array|WP_Error      The upload details or WP_Error on failure.
	 */
	public function create_direct_upload( $options = array() ) {
		$endpoint = '/video/v1/uploads';

		$default_options = array(
			'cors_origin' => home_url(),
			'new_asset_settings' => array(
				'playback_policy' => array( 'public' ),
			),
		);

		$data = wp_parse_args( $options, $default_options );

		return $this->make_request( $endpoint, 'POST', $data );
	}

	/**
	 * Get upload details.
	 *
	 * @since    1.0.0
	 * @param    string    $upload_id    The upload ID.
	 * @return   array|WP_Error          The upload details or WP_Error on failure.
	 */
	public function get_upload( $upload_id ) {
		$endpoint = '/video/v1/uploads/' . sanitize_text_field( $upload_id );
		return $this->make_request( $endpoint );
	}

	/**
	 * Delete a video asset.
	 *
	 * @since    1.0.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @return   array|WP_Error         The API response or WP_Error on failure.
	 */
	public function delete_video( $asset_id ) {
		$endpoint = '/video/v1/assets/' . sanitize_text_field( $asset_id );
		return $this->make_request( $endpoint, 'DELETE' );
	}

	/**
	 * Get playback ID for a video asset.
	 *
	 * @since    1.0.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @return   string|false            The playback ID or false on failure.
	 */
	public function get_playback_id( $asset_id ) {
		$video = $this->get_video( $asset_id );

		if ( is_wp_error( $video ) || ! isset( $video['data']['playback_ids'][0]['id'] ) ) {
			return false;
		}

		return $video['data']['playback_ids'][0]['id'];
	}

	/**
	 * Get thumbnail URL for a video asset.
	 *
	 * @since    1.0.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @param    int       $width       Thumbnail width (default: 120).
	 * @param    int       $height      Thumbnail height (default: 80).
	 * @return   string|false            The thumbnail URL or false on failure.
	 */
	public function get_thumbnail_url( $asset_id, $width = 120, $height = 80 ) {
		$playback_id = $this->get_playback_id( $asset_id );

		if ( ! $playback_id ) {
			return false;
		}

		return "https://image.mux.com/{$playback_id}/thumbnail.jpg?width={$width}&height={$height}";
	}

	/**
	 * Format file size for display.
	 *
	 * @since    1.0.0
	 * @param    int    $bytes    Size in bytes.
	 * @return   string           Formatted file size.
	 */
	public static function format_file_size( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

		for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
			$bytes /= 1024;
		}

		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}

	/**
	 * Format duration for display.
	 *
	 * @since    1.0.0
	 * @param    float    $seconds    Duration in seconds.
	 * @return   string              Formatted duration.
	 */
	public static function format_duration( $seconds ) {
		if ( empty( $seconds ) ) {
			return '--';
		}

		$hours = floor( $seconds / 3600 );
		$minutes = floor( ( $seconds % 3600 ) / 60 );
		$seconds = $seconds % 60;

		if ( $hours > 0 ) {
			return sprintf( '%02d:%02d:%02d', $hours, $minutes, $seconds );
		} else {
			return sprintf( '%02d:%02d', $minutes, $seconds );
		}
	}

	/**
	 * Enhance video data with computed fields.
	 *
	 * @since    1.0.0
	 * @param    array    $video    Raw video data from MUX API.
	 * @return   array              Enhanced video data.
	 */
	private function enhance_video_data( $video ) {
		// Add thumbnail URL if playback ID exists
		if ( isset( $video['playback_ids'][0]['id'] ) ) {
			$playback_id = $video['playback_ids'][0]['id'];
			$video['thumbnail_url'] = "https://image.mux.com/{$playback_id}/thumbnail.jpg?width=240&height=160";
			$video['thumbnail_url_large'] = "https://image.mux.com/{$playback_id}/thumbnail.jpg?width=640&height=360";
		}

		// Extract and normalize resolution information
		$video['resolution'] = $this->extract_video_resolution( $video );

		// Estimate file size if not available
		if ( empty( $video['file_size'] ) && ! empty( $video['duration'] ) ) {
			// Rough estimate: 1 Mbps for standard quality
			$estimated_bitrate = 1000000; // 1 Mbps in bits per second
			$video['estimated_file_size'] = ( $video['duration'] * $estimated_bitrate ) / 8; // Convert to bytes
		}

		// Ensure created_at is properly formatted
		if ( isset( $video['created_at'] ) ) {
			// MUX returns ISO 8601 format, ensure it's parseable
			$video['created_at_formatted'] = $video['created_at'];
		} elseif ( isset( $video['created'] ) ) {
			// Some APIs use 'created' instead of 'created_at'
			$video['created_at'] = $video['created'];
			$video['created_at_formatted'] = $video['created'];
		} elseif ( isset( $video['date_created'] ) ) {
			// Try another common field name
			$video['created_at'] = $video['date_created'];
			$video['created_at_formatted'] = $video['date_created'];
		}

		return $video;
	}

	/**
	 * Extract video resolution from MUX video data.
	 *
	 * @since    1.0.0
	 * @param    array    $video    Video data from MUX API.
	 * @return   string             Resolution string (e.g., "1920x1080") or "--".
	 */
	private function extract_video_resolution( $video ) {
		// Check tracks for video resolution
		if ( isset( $video['tracks'] ) && is_array( $video['tracks'] ) ) {
			foreach ( $video['tracks'] as $track ) {
				if ( isset( $track['type'] ) && $track['type'] === 'video' &&
					 isset( $track['width'] ) && isset( $track['height'] ) ) {
					return $track['width'] . 'x' . $track['height'];
				}
				// Sometimes video track doesn't have type specified but has dimensions
				if ( isset( $track['width'] ) && isset( $track['height'] ) ) {
					return $track['width'] . 'x' . $track['height'];
				}
			}
		}

		// Check input_info for resolution
		if ( isset( $video['input_info']['settings'] ) ) {
			$settings = $video['input_info']['settings'];
			if ( isset( $settings['width'] ) && isset( $settings['height'] ) ) {
				return $settings['width'] . 'x' . $settings['height'];
			}
		}

		// Check master access for resolution
		if ( isset( $video['master'] ) && isset( $video['master']['width'] ) && isset( $video['master']['height'] ) ) {
			return $video['master']['width'] . 'x' . $video['master']['height'];
		}

		// Check max_resolution_tier
		if ( isset( $video['max_resolution_tier'] ) ) {
			switch ( $video['max_resolution_tier'] ) {
				case '1080p':
					return '1920x1080';
				case '720p':
					return '1280x720';
				case '480p':
					return '854x480';
				case '360p':
					return '640x360';
				default:
					if ( strpos( $video['max_resolution_tier'], 'x' ) !== false ) {
						return $video['max_resolution_tier'];
					}
			}
		}

		return '--';
	}

	/**
	 * Get video view statistics from MUX Data API.
	 *
	 * @since    1.1.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @param    array     $timeframe   Array with 'start' and 'end' timestamps (optional).
	 * @return   array|WP_Error         The view statistics or WP_Error on failure.
	 */
	public function get_video_views( $asset_id, $timeframe = array() ) {
		// MUX Data API endpoint for overall metrics
		$endpoint = '/data/v1/metrics/video-views';

		$params = array(
			'filters' => array( 'asset_id:' . sanitize_text_field( $asset_id ) ),
		);

		if ( ! empty( $timeframe['start'] ) && ! empty( $timeframe['end'] ) ) {
			$params['timeframe'] = array( $timeframe['start'], $timeframe['end'] );
		}

		$endpoint .= '?' . http_build_query( $params );

		$response = $this->make_request( $endpoint );

		if ( is_wp_error( $response ) ) {
			// If it's a 404, the asset might not have any views yet
			if ( isset( $response->errors['api_error'][0] ) ) {
				return array(
					'total_views' => 0,
					'data' => array(),
				);
			}
		}

		return $response;
	}

	/**
	 * Get overall video metrics including engagement data.
	 *
	 * @since    1.1.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @return   array|WP_Error         The overall metrics or WP_Error on failure.
	 */
	public function get_video_overall_metrics( $asset_id ) {
		$asset_id = sanitize_text_field( $asset_id );

		// Get overall metrics from MUX Data API
		$endpoint = '/data/v1/metrics/overall';

		$params = array(
			'filters' => array( 'asset_id:' . $asset_id ),
		);

		$endpoint .= '?' . http_build_query( $params );

		$response = $this->make_request( $endpoint );

		// If the request fails or returns no data, return default values
		if ( is_wp_error( $response ) ) {
			return array(
				'total_views' => 0,
				'total_watch_time' => 0,
				'average_view_duration' => 0,
				'unique_viewers' => 0,
			);
		}

		// Parse and format the response
		$metrics = array(
			'total_views' => isset( $response['data']['total_views'] ) ? $response['data']['total_views'] : 0,
			'total_watch_time' => isset( $response['data']['total_watch_time'] ) ? $response['data']['total_watch_time'] : 0,
			'average_view_duration' => isset( $response['data']['average_view_duration'] ) ? $response['data']['average_view_duration'] : 0,
			'unique_viewers' => isset( $response['data']['unique_viewers'] ) ? $response['data']['unique_viewers'] : 0,
		);

		return $metrics;
	}

	/**
	 * Get engagement metrics for a video.
	 *
	 * @since    1.1.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @return   array|WP_Error         The engagement metrics or WP_Error on failure.
	 */
	public function get_video_engagement_metrics( $asset_id ) {
		$asset_id = sanitize_text_field( $asset_id );

		// Get video quality score and engagement metrics
		$endpoint = '/data/v1/metrics/video-quality-score';

		$params = array(
			'filters' => array( 'asset_id:' . $asset_id ),
			'measurement' => '95th',
		);

		$endpoint .= '?' . http_build_query( $params );

		$response = $this->make_request( $endpoint );

		// If the request fails, return default values
		if ( is_wp_error( $response ) ) {
			return array(
				'video_quality_score' => 0,
				'rebuffer_percentage' => 0,
				'video_startup_time' => 0,
			);
		}

		// Parse metrics
		$metrics = array(
			'video_quality_score' => isset( $response['data']['value'] ) ? round( $response['data']['value'], 2 ) : 0,
			'rebuffer_percentage' => isset( $response['data']['rebuffer_percentage'] ) ? round( $response['data']['rebuffer_percentage'], 2 ) : 0,
			'video_startup_time' => isset( $response['data']['video_startup_time'] ) ? round( $response['data']['video_startup_time'] ) : 0,
		);

		return $metrics;
	}

	/**
	 * Get comprehensive analytics for a video asset.
	 *
	 * @since    1.1.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @return   array                  Combined analytics data.
	 */
	public function get_video_analytics( $asset_id ) {
		$asset_id = sanitize_text_field( $asset_id );

		// Try to get views from the simpler endpoint first
		$views_response = $this->get_video_views( $asset_id );
		$overall_metrics = $this->get_video_overall_metrics( $asset_id );
		$engagement_metrics = $this->get_video_engagement_metrics( $asset_id );

		// Combine all metrics
		$analytics = array(
			'total_views' => 0,
			'unique_viewers' => 0,
			'total_watch_time' => 0,
			'average_view_duration' => 0,
			'video_quality_score' => 0,
			'rebuffer_percentage' => 0,
			'video_startup_time' => 0,
			'last_updated' => current_time( 'timestamp' ),
		);

		// Extract view count from views response
		if ( ! is_wp_error( $views_response ) ) {
			if ( isset( $views_response['total_row_count'] ) ) {
				$analytics['total_views'] = intval( $views_response['total_row_count'] );
			} elseif ( isset( $views_response['data']['total_views'] ) ) {
				$analytics['total_views'] = intval( $views_response['data']['total_views'] );
			}
		}

		// Merge overall metrics
		if ( ! is_wp_error( $overall_metrics ) && is_array( $overall_metrics ) ) {
			$analytics = array_merge( $analytics, $overall_metrics );
		}

		// Merge engagement metrics
		if ( ! is_wp_error( $engagement_metrics ) && is_array( $engagement_metrics ) ) {
			$analytics = array_merge( $analytics, $engagement_metrics );
		}

		return $analytics;
	}

	/**
	 * Format watch time for display.
	 *
	 * @since    1.1.0
	 * @param    int    $seconds    Watch time in seconds.
	 * @return   string            Formatted watch time.
	 */
	public static function format_watch_time( $seconds ) {
		if ( empty( $seconds ) || $seconds < 0 ) {
			return '0 min';
		}

		$hours = floor( $seconds / 3600 );
		$minutes = floor( ( $seconds % 3600 ) / 60 );

		if ( $hours > 0 ) {
			return sprintf( '%d hr %d min', $hours, $minutes );
		} else {
			return sprintf( '%d min', $minutes );
		}
	}

	/**
	 * Get account-wide statistics from MUX.
	 *
	 * @since    1.2.0
	 * @return   array|WP_Error    Account statistics or error on failure.
	 */
	public function get_account_stats() {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'MUX API is not configured.', 'wp-mux' ) );
		}

		// Try to get from cache first (cache for 5 minutes)
		$cache_key = 'wp_mux_account_stats';
		$cached_stats = get_transient( $cache_key );
		if ( $cached_stats !== false ) {
			return $cached_stats;
		}

		// Get all videos to calculate total storage and count
		$videos_response = $this->make_request( '/video/v1/assets?limit=100' );

		if ( is_wp_error( $videos_response ) ) {
			return $videos_response;
		}

		$stats = array(
			'total_videos' => 0,
			'total_storage_bytes' => 0,
			'total_duration_seconds' => 0,
			'videos_ready' => 0,
			'videos_processing' => 0,
			'videos_errored' => 0,
		);

		if ( isset( $videos_response['data'] ) && is_array( $videos_response['data'] ) ) {
			$stats['total_videos'] = count( $videos_response['data'] );

			foreach ( $videos_response['data'] as $video ) {
				// Count by status
				if ( isset( $video['status'] ) ) {
					switch ( $video['status'] ) {
						case 'ready':
							$stats['videos_ready']++;
							break;
						case 'preparing':
						case 'waiting':
							$stats['videos_processing']++;
							break;
						case 'errored':
							$stats['videos_errored']++;
							break;
					}
				}

				// Sum up storage
				if ( isset( $video['max_stored_resolution'] ) && isset( $video['duration'] ) ) {
					// Estimate storage based on resolution and duration
					// This is approximate - MUX doesn't provide exact storage per video
					$resolution = $video['max_stored_resolution'];
					$duration = floatval( $video['duration'] );

					// Rough estimate: bitrate in Mbps based on resolution
					$bitrate_mbps = 5; // default
					if ( strpos( $resolution, '4K' ) !== false || strpos( $resolution, '2160' ) !== false ) {
						$bitrate_mbps = 25;
					} elseif ( strpos( $resolution, '1080' ) !== false ) {
						$bitrate_mbps = 8;
					} elseif ( strpos( $resolution, '720' ) !== false ) {
						$bitrate_mbps = 5;
					} elseif ( strpos( $resolution, '480' ) !== false ) {
						$bitrate_mbps = 2.5;
					}

					$estimated_bytes = ( $bitrate_mbps * 1024 * 1024 / 8 ) * $duration;
					$stats['total_storage_bytes'] += $estimated_bytes;
				}

				// Sum up duration
				if ( isset( $video['duration'] ) ) {
					$stats['total_duration_seconds'] += floatval( $video['duration'] );
				}
			}
		}

		// Get overall viewing metrics using Data API
		// This requires making a request to the metrics endpoint
		$timeframe = array(
			array( 'gte', strtotime( '-30 days' ) * 1000 ), // Last 30 days in milliseconds
			array( 'lt', time() * 1000 ),
		);

		$metrics_params = array(
			'measurement' => array( 'aggregate_startup_time', 'views' ),
			'filters' => array(),
			'group_by' => array(),
			'timeframe' => $timeframe,
		);

		$metrics_response = $this->make_request(
			'/data/v1/metrics/overall',
			'GET',
			array(),
			$metrics_params
		);

		$stats['total_views_30d'] = 0;
		$stats['total_minutes_viewed_30d'] = 0;

		if ( ! is_wp_error( $metrics_response ) && isset( $metrics_response['data'] ) ) {
			foreach ( $metrics_response['data'] as $metric ) {
				if ( isset( $metric['value'] ) ) {
					if ( isset( $metric['field'] ) && $metric['field'] === 'views' ) {
						$stats['total_views_30d'] = intval( $metric['value'] );
					}
				}
			}
		}

		// Cache the results
		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}
}
