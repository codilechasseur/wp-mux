<?php

declare(strict_types=1);

/**
 * MUX API client for handling video operations.
 *
 * @link       https://wpmux.com
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
 * @author     Codi Lechasseur
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

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ) ) ) {
			// For POST/PUT/PATCH, always send a body (even if empty object)
			$args['body'] = ! empty( $data ) ? wp_json_encode( $data ) : '{}';
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code >= 400 ) {
			$error_data = json_decode( $response_body, true );

			// Try to get the most specific error message
			$error_message = __( 'Unknown API error', 'wp-mux' );

			if ( isset( $error_data['error']['message'] ) ) {
				$error_message = $error_data['error']['message'];
			} elseif ( isset( $error_data['error']['messages'] ) && is_array( $error_data['error']['messages'] ) ) {
				$error_message = implode( ', ', $error_data['error']['messages'] );
			} elseif ( isset( $error_data['message'] ) ) {
				$error_message = $error_data['message'];
			}

			// Log detailed error for debugging

			return new WP_Error( 'api_error', $error_message, array( 'status' => $response_code, 'response' => $error_data ) );
		}

		return json_decode( $response_body, true );
	}

	/**
	 * Get a list of videos from MUX.
	 *
	 * Implements caching with 15-minute expiration to reduce API calls.
	 *
	 * @since    1.0.0
	 * @param    int    $limit         Number of videos to retrieve (max 100).
	 * @param    int    $page          Page number for pagination.
	 * @param    bool   $bypass_cache  Whether to bypass cache and fetch fresh data.
	 * @return   array|WP_Error        The videos list or WP_Error on failure.
	 */
	public function get_videos( $limit = 25, $page = 1, $bypass_cache = false ) {
		$cache_key = "wp_mux_videos_list_{$limit}_{$page}";

		// Return cached result if available and not bypassing cache
		if ( ! $bypass_cache ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

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

			// Cache successful response for 15 minutes
			set_transient( $cache_key, $response, 15 * MINUTE_IN_SECONDS );
		}

		return $response;
	}

	/**
	 * Get details for a specific video.
	 *
	 * Implements caching with 1-hour expiration to reduce API calls.
	 *
	 * @since    1.0.0
	 * @param    string    $asset_id       The MUX asset ID.
	 * @param    bool      $bypass_cache   Whether to bypass cache and fetch fresh data.
	 * @return   array|WP_Error            The video details or WP_Error on failure.
	 */
	public function get_video( $asset_id, $bypass_cache = false ) {
		$cache_key = "wp_mux_video_details_{$asset_id}";

		// Return cached result if available and not bypassing cache
		if ( ! $bypass_cache ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$endpoint = '/video/v1/assets/' . sanitize_text_field( $asset_id );
		$response = $this->make_request( $endpoint );

		// Cache successful response for 1 hour
		if ( ! is_wp_error( $response ) ) {
			set_transient( $cache_key, $response, HOUR_IN_SECONDS );
		}

		return $response;
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

		// Get global defaults
		$playback_policy = get_option( 'wp_mux_default_playback_policy', 'public' );
		$mp4_support = get_option( 'wp_mux_default_mp4_support', 'none' );
		$encoding_tier = get_option( 'wp_mux_default_encoding_tier', 'baseline' );

		$default_options = array(
			'cors_origin' => home_url(),
			'new_asset_settings' => array(
				'playback_policy' => array( $playback_policy ),
				'mp4_support' => $mp4_support,
				'encoding_tier' => $encoding_tier,
			),
		);

		$data = wp_parse_args( $options, $default_options );

		return $this->make_request( $endpoint, 'POST', $data );
	}

	/**
	 * Upload a local file to Mux.
	 *
	 * Creates a direct upload, streams the file via cURL (avoiding loading the
	 * entire file into memory), then polls for the resulting asset ID.
	 *
	 * @since    1.3.0
	 *
	 * @param string $filePath   Absolute path to the local video file.
	 * @param string $mimeType   MIME type of the file (e.g. "video/mp4").
	 * @param array  $options    Optional settings passed to create_direct_upload().
	 *
	 * @return array{upload_id: string, asset_id: string|null, playback_id: string|null}|WP_Error
	 */
	public function upload_local_file( string $file_path, string $mime_type, array $options = array() ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', "Source file not found: {$file_path}" );
		}

		$upload = $this->create_direct_upload( $options );
		if ( is_wp_error( $upload ) ) {
			return $upload;
		}

		if ( empty( $upload['data']['url'] ) || empty( $upload['data']['id'] ) ) {
			return new WP_Error( 'upload_create_failed', 'Failed to create Mux direct upload' );
		}

		$upload_url = $upload['data']['url'];
		$upload_id  = $upload['data']['id'];

		$http_code = $this->stream_put_file( $upload_url, $file_path, $mime_type );
		if ( $http_code < 200 || $http_code >= 300 ) {
			return new WP_Error( 'upload_failed', "File upload to Mux failed with HTTP {$http_code}" );
		}

		$asset_id    = null;
		$playback_id = null;

		for ( $i = 0; $i < 10; $i++ ) {
			sleep( 3 );
			$status = $this->get_upload( $upload_id );
			if ( ! is_wp_error( $status ) && ! empty( $status['data']['asset_id'] ) ) {
				$asset_id = $status['data']['asset_id'];
				break;
			}
		}

		if ( $asset_id ) {
			$details = $this->get_video( $asset_id );
			if ( ! is_wp_error( $details ) ) {
				$playback_id = $details['data']['playback_ids'][0]['id'] ?? null;
			}
		}

		return array(
			'upload_id'   => $upload_id,
			'asset_id'    => $asset_id,
			'playback_id' => $playback_id,
		);
	}

	/**
	 * Stream a local file to a URL using cURL PUT to avoid loading it into memory.
	 *
	 * @since    1.3.0
	 *
	 * @param string $url      Target URL.
	 * @param string $filePath Absolute path to the file.
	 * @param string $mimeType MIME type for the Content-Type header.
	 *
	 * @return int HTTP response code.
	 */
	private function stream_put_file( string $url, string $file_path, string $mime_type ): int {
		$file_handle = fopen( $file_path, 'rb' );
		if ( ! $file_handle ) {
			return 0;
		}

		$ch = curl_init( $url );
		curl_setopt_array( $ch, array(
			CURLOPT_PUT            => true,
			CURLOPT_INFILE         => $file_handle,
			CURLOPT_INFILESIZE     => filesize( $file_path ),
			CURLOPT_HTTPHEADER     => array( "Content-Type: {$mime_type}" ),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT        => 600,
		) );

		curl_exec( $ch );
		$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		fclose( $file_handle );

		return $http_code;
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
		$units      = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$unit_count = count( $units );

		for ( $i = 0; $bytes > 1024 && $i < $unit_count - 1; $i++ ) {
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

	/**
	 * Request automatic caption generation for an asset.
	 *
	 * @since    1.3.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @param    string    $language    Language code (ISO 639-1).
	 * @return   array|WP_Error         The text track data or WP_Error on failure.
	 */
	public function generate_captions( $asset_id, $language = 'en' ) {
		// Get the audio track ID
		$audio_track_id = $this->get_audio_track_id( $asset_id );

		if ( empty( $audio_track_id ) ) {
			return new WP_Error(
				'no_audio_track',
				__( 'No audio track found for this video. Cannot generate captions.', 'wp-mux' )
			);
		}

		// Use the correct endpoint for generating subtitles
		$endpoint = "/video/v1/assets/{$asset_id}/tracks/{$audio_track_id}/generate-subtitles";

		$data = array(
			'generated_subtitles' => array(
				array(
					'name' => sprintf( __( '%s (Auto)', 'wp-mux' ), strtoupper( $language ) ),
					'language_code' => $language,
					'passthrough' => 'auto-generated',
				),
			),
		);

		return $this->make_request( $endpoint, 'POST', $data );
	}

	/**
	 * Get caption tracks for an asset.
	 *
	 * @since    1.3.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @return   array|WP_Error         Array of text tracks or WP_Error.
	 */
	public function get_caption_tracks( $asset_id ) {
		$endpoint = "/video/v1/assets/{$asset_id}/tracks";
		return $this->make_request( $endpoint );
	}

	/**
	 * Delete a caption track.
	 *
	 * @since    1.3.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @param    string    $track_id    The track ID to delete.
	 * @return   array|WP_Error         The API response or WP_Error.
	 */
	public function delete_caption_track( $asset_id, $track_id ) {
		$endpoint = "/video/v1/assets/{$asset_id}/tracks/{$track_id}";
		return $this->make_request( $endpoint, 'DELETE' );
	}

	/**
	 * Update playback policy for an asset.
	 *
	 * @since    1.3.0
	 * @param    string    $asset_id         The MUX asset ID.
	 * @param    string    $policy           'public' or 'signed'.
	 * @return   array|WP_Error              The updated asset or WP_Error.
	 */
	public function update_playback_policy( $asset_id, $policy ) {
		$endpoint = "/video/v1/assets/{$asset_id}";

		$data = array(
			'playback_policy' => array( $policy ),
		);

		$response = $this->make_request( $endpoint, 'PATCH', $data );

		if ( ! is_wp_error( $response ) ) {
			delete_transient( "wp_mux_video_details_{$asset_id}" );
		}

		return $response;
	}

	/**
	 * Update MP4 support for an asset.
	 *
	 * @since    1.3.0
	 * @param    string    $asset_id         The MUX asset ID.
	 * @param    string    $support          'none', 'standard', or 'audio-only'.
	 * @return   array|WP_Error              The updated asset or WP_Error.
	 */
	public function update_mp4_support( $asset_id, $support ) {
		$endpoint = "/video/v1/assets/{$asset_id}";

		$data = array(
			'mp4_support' => $support,
		);

		$response = $this->make_request( $endpoint, 'PATCH', $data );

		if ( ! is_wp_error( $response ) ) {
			delete_transient( "wp_mux_video_details_{$asset_id}" );
		}

		return $response;
	}

	/**
	 * Create a Mux asset from a remote URL.
	 *
	 * Mux will fetch and encode the file at the provided URL directly.
	 * No local download needed.
	 *
	 * @since    1.2.0
	 * @param    string    $url             Publicly reachable URL of the video file.
	 * @param    array     $options         Optional settings: playback_policy, mp4_support, encoding_tier.
	 * @return   array|WP_Error             Asset data array or WP_Error on failure.
	 */
	public function create_asset_from_url( string $url, array $options = array() ): array|WP_Error {
		$playback_policy = $options['playback_policy'] ?? get_option( 'wp_mux_default_playback_policy', 'public' );
		$mp4_support     = $options['mp4_support']     ?? get_option( 'wp_mux_default_mp4_support', 'none' );
		$encoding_tier   = $options['encoding_tier']   ?? get_option( 'wp_mux_default_encoding_tier', 'baseline' );

		return $this->make_request( '/video/v1/assets', 'POST', [
			'input'           => [ [ 'url' => $url ] ],
			'playback_policy' => [ $playback_policy ],
			'mp4_support'     => $mp4_support,
			'encoding_tier'   => $encoding_tier,
		] );
	}

	/**
	 * Add a text track (subtitles/captions) to an existing asset.
	 *
	 * @since    1.2.0
	 * @param    string    $asset_id    Mux asset ID.
	 * @param    string    $vtt_url     Publicly reachable URL of the VTT file.
	 * @param    string    $language    BCP-47 language code (e.g. "en").
	 * @param    string    $name        Human-readable track name.
	 * @return   array|WP_Error         Track data or WP_Error on failure.
	 */
	public function add_caption_track( string $asset_id, string $vtt_url, string $language = 'en', string $name = 'Captions' ): array|WP_Error {
		return $this->make_request( '/video/v1/assets/' . sanitize_text_field( $asset_id ) . '/tracks', 'POST', [
			'type'          => 'text',
			'text_type'     => 'subtitles',
			'language_code' => $language,
			'url'           => $vtt_url,
			'name'          => $name,
		] );
	}

	/**
	 * Create a new signing key pair in Mux.
	 *
	 * @since    1.3.0
	 * @return   array|WP_Error    The signing key data or WP_Error on failure.
	 */
	public function create_signing_key() {
		$endpoint = '/system/v1/signing-keys';

		// Mux signing keys endpoint doesn't require data, but may need an empty JSON object
		$response = $this->make_request( $endpoint, 'POST', null );

		// Additional error logging
		if ( is_wp_error( $response ) ) {
		} else {
		}

		return $response;
	}

	/**
	 * List all signing keys.
	 *
	 * @since    1.3.0
	 * @return   array|WP_Error    Array of signing keys or WP_Error.
	 */
	public function list_signing_keys() {
		$endpoint = '/system/v1/signing-keys';
		return $this->make_request( $endpoint, 'GET' );
	}

	/**
	 * Get details of a specific signing key.
	 *
	 * @since    1.3.0
	 * @param    string    $key_id    The signing key ID.
	 * @return   array|WP_Error       The signing key details or WP_Error.
	 */
	public function get_signing_key( $key_id ) {
		$endpoint = '/system/v1/signing-keys/' . sanitize_text_field( $key_id );
		return $this->make_request( $endpoint, 'GET' );
	}

	/**
	 * Delete a signing key.
	 *
	 * @since    1.3.0
	 * @param    string    $key_id    The signing key ID to delete.
	 * @return   array|WP_Error       The API response or WP_Error.
	 */
	public function delete_signing_key( $key_id ) {
		$endpoint = '/system/v1/signing-keys/' . sanitize_text_field( $key_id );
		return $this->make_request( $endpoint, 'DELETE' );
	}

	/**
	 * Auto-configure signing keys for the plugin.
	 * Creates a new signing key if none exists or uses existing one.
	 *
	 * @since    1.3.0
	 * @return   array|WP_Error    Success data or WP_Error on failure.
	 */
	public function auto_configure_signing_keys() {
		// Check if we already have keys configured
		$existing_key_id = get_option( 'wp_mux_signing_key_id', '' );
		$existing_private_key = get_option( 'wp_mux_signing_key_private', '' );

		if ( ! empty( $existing_key_id ) && ! empty( $existing_private_key ) ) {
			// Verify the existing key is still valid
			$key_check = $this->get_signing_key( $existing_key_id );

			if ( ! is_wp_error( $key_check ) ) {
				return array(
					'status' => 'existing',
					'message' => __( 'Signing keys already configured and valid.', 'wp-mux' ),
					'key_id' => $existing_key_id,
				);
			}
		}

		// Create a new signing key
		$response = $this->create_signing_key();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['data']['id'] ) || empty( $response['data']['private_key'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response from Mux API when creating signing key.', 'wp-mux' )
			);
		}

		// Store the new keys
		$key_id = $response['data']['id'];
		$private_key = $response['data']['private_key'];

		update_option( 'wp_mux_signing_key_id', $key_id );
		update_option( 'wp_mux_signing_key_private', base64_encode( $private_key ) );

		// Log the creation

		return array(
			'status' => 'created',
			'message' => __( 'New signing key created and configured successfully.', 'wp-mux' ),
			'key_id' => $key_id,
			'created_at' => $response['data']['created_at'] ?? '',
		);
	}

	/**
	 * Check if the Mux account has signing keys capability.
	 *
	 * This tests whether the account can access the signing keys API.
	 * Results are cached for 1 hour to avoid repeated API calls.
	 *
	 * @since    1.3.0
	 * @return   bool    True if signing keys are available, false otherwise.
	 */
	public function has_signing_keys_capability() {
		// Check cache first
		$cached = get_transient( 'wp_mux_has_signing_keys' );
		if ( $cached !== false ) {
			return (bool) $cached;
		}

		// Check if API is configured
		if ( ! $this->is_configured() ) {
			return false;
		}

		// Try to list signing keys (lightweight test)
		$result = $this->list_signing_keys();

		$has_capability = ! is_wp_error( $result );

		// Cache for 1 hour
		set_transient( 'wp_mux_has_signing_keys', $has_capability ? 1 : 0, HOUR_IN_SECONDS );

		return $has_capability;
	}

	/**
	 * Check if the Mux account has caption generation capability.
	 *
	 * Tests whether the account can generate auto-captions.
	 * Results are cached for 1 hour to avoid repeated API calls.
	 *
	 * @since    1.3.0
	 * @return   bool    True if caption generation is available, false otherwise.
	 */
	public function has_caption_generation_capability() {
		// Check cache first
		$cached = get_transient( 'wp_mux_has_caption_generation' );
		if ( $cached !== false ) {
			return (bool) $cached;
		}

		// Check if API is configured
		if ( ! $this->is_configured() ) {
			return false;
		}

		// Get a sample video to test with
		$videos = $this->get_videos( 1, 1 );

		if ( is_wp_error( $videos ) || empty( $videos['data'] ) ) {
			// No videos to test with, assume capability exists
			// This prevents blocking the feature when account is empty
			set_transient( 'wp_mux_has_caption_generation', 1, HOUR_IN_SECONDS );
			return true;
		}

		$test_asset_id = $videos['data'][0]['id'];

		// Get the asset to check for audio track
		$video = $this->get_video( $test_asset_id );

		if ( is_wp_error( $video ) ) {
			// Can't determine, assume capability exists
			set_transient( 'wp_mux_has_caption_generation', 1, HOUR_IN_SECONDS );
			return true;
		}

		// Find audio track
		$audio_track_id = null;
		if ( isset( $video['data']['tracks'] ) && is_array( $video['data']['tracks'] ) ) {
			foreach ( $video['data']['tracks'] as $track ) {
				if ( isset( $track['type'] ) && $track['type'] === 'audio' ) {
					$audio_track_id = $track['id'];
					break;
				}
			}
		}

		// If no audio track, we can't definitively test, assume yes
		if ( empty( $audio_track_id ) ) {
			set_transient( 'wp_mux_has_caption_generation', 1, HOUR_IN_SECONDS );
			return true;
		}

		// Test the endpoint with a HEAD-like approach - check if endpoint exists
		// We'll check the error code if we get one
		$endpoint = "/video/v1/assets/{$test_asset_id}/tracks/{$audio_track_id}/generate-subtitles";

		// Make a test request - the API will fail but tell us if it's permissions or just bad data
		$test_result = $this->make_request( $endpoint, 'POST', array(
			'generated_subtitles' => array(
				array(
					'name' => 'Test',
					'language_code' => 'en',
				),
			),
		) );

		// If we get specific permission/auth errors, caption generation is not available
		if ( is_wp_error( $test_result ) ) {
			$error_data = $test_result->get_error_data();
			$status_code = isset( $error_data['status'] ) ? $error_data['status'] : 0;

			// 401/403 = authentication/permission issue
			if ( in_array( $status_code, array( 401, 403 ) ) ) {
				set_transient( 'wp_mux_has_caption_generation', 0, HOUR_IN_SECONDS );
				return false;
			}

			// Any other error (like 400, 422) means the endpoint exists but our test data was bad
			// This is actually a good sign - the feature is available
		}

		// Either succeeded or got a non-auth error, so capability exists
		set_transient( 'wp_mux_has_caption_generation', 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Check if a video asset has an audio track.
	 *
	 * @since    1.3.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @return   bool                   True if audio track exists, false otherwise.
	 */
	public function has_audio_track( $asset_id ) {
		$video = $this->get_video( $asset_id );

		if ( is_wp_error( $video ) || ! isset( $video['data']['tracks'] ) ) {
			return false;
		}

		foreach ( $video['data']['tracks'] as $track ) {
			if ( isset( $track['type'] ) && $track['type'] === 'audio' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the audio track ID for a video asset.
	 *
	 * @since    1.3.0
	 * @param    string    $asset_id    The MUX asset ID.
	 * @return   string|false            Audio track ID or false if not found.
	 */
	public function get_audio_track_id( $asset_id ) {
		$video = $this->get_video( $asset_id );

		if ( is_wp_error( $video ) || ! isset( $video['data']['tracks'] ) ) {
			return false;
		}

		foreach ( $video['data']['tracks'] as $track ) {
			if ( isset( $track['type'] ) && $track['type'] === 'audio' ) {
				return $track['id'];
			}
		}

		return false;
	}
}
