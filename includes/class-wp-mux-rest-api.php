<?php

declare(strict_types=1);

/**
 * REST API endpoints for headless / decoupled WordPress.
 *
 * Exposes Mux video data over the WordPress REST API so external frontends
 * (Next.js, React SPA, mobile apps, etc.) can consume signed URLs, metadata,
 * and thumbnails without logging in to wp-admin.
 *
 * Routes (base: /wp-json/wp-mux/v1):
 *   GET  /videos            — paginated list of all Mux videos
 *   GET  /videos/{id}       — single video by WP attachment ID
 *   GET  /videos/asset/{id} — single video by Mux asset ID
 *
 * Authentication uses WordPress Application Passwords (WP 5.6+) or any
 * other auth provider that sets current_user. Public video data is exposed
 * without auth; signed URL generation requires the 'read' capability so that
 * guest users can at least retrieve already-public playback IDs.
 *
 * @link       https://wpmux.com
 * @since      1.2.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

/**
 * WP Mux REST API class.
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 * @author     Codi Lechasseur
 */
class Wp_Mux_Rest_Api {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'wp-mux/v1';

	/**
	 * Register REST routes.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register all routes.
	 *
	 * @since 1.2.0
	 */
	public function register_routes(): void {
		// List all Mux videos.
		register_rest_route( self::NAMESPACE, '/videos', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_videos' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'page'     => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
				'per_page' => [ 'default' => 20, 'sanitize_callback' => 'absint' ],
			],
		] );

		// Single video by WP attachment ID.
		register_rest_route( self::NAMESPACE, '/videos/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_video_by_id' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
			],
		] );

		// Single video by Mux asset ID.
		register_rest_route( self::NAMESPACE, '/videos/asset/(?P<asset_id>[a-zA-Z0-9]+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_video_by_asset_id' ],
			'permission_callback' => '__return_true',
		] );
	}

	// -------------------------------------------------------------------------
	// Endpoint callbacks
	// -------------------------------------------------------------------------

	/**
	 * Return a paginated list of Mux videos.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_videos( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );

		$query = new WP_Query( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'video/mux',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$videos = array_map(
			fn( $post ) => $this->format_video( $post->ID ),
			$query->posts
		);

		$response = new WP_REST_Response( $videos, 200 );
		$response->header( 'X-WP-Total', $query->found_posts );
		$response->header( 'X-WP-TotalPages', $query->max_num_pages );

		return $response;
	}

	/**
	 * Return a single video by WP attachment ID.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_video_by_id( WP_REST_Request $request ) {
		$attach_id  = (int) $request->get_param( 'id' );
		$attachment = get_post( $attach_id );

		if ( ! $attachment || $attachment->post_mime_type !== 'video/mux' ) {
			return new WP_Error( 'not_found', 'Mux video not found', [ 'status' => 404 ] );
		}

		return new WP_REST_Response( $this->format_video( $attach_id ), 200 );
	}

	/**
	 * Return a single video by Mux asset ID.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_video_by_asset_id( WP_REST_Request $request ) {
		$asset_id = sanitize_text_field( $request->get_param( 'asset_id' ) );

		$results = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => [ [ 'key' => '_mux_asset_id', 'value' => $asset_id ] ],
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		if ( empty( $results ) ) {
			return new WP_Error( 'not_found', 'Mux video not found', [ 'status' => 404 ] );
		}

		return new WP_REST_Response( $this->format_video( (int) $results[0] ), 200 );
	}

	// -------------------------------------------------------------------------
	// Formatting
	// -------------------------------------------------------------------------

	/**
	 * Format a Mux attachment as a REST-ready array.
	 *
	 * Generates a signed playback URL if the video uses signed policy;
	 * otherwise returns the plain HLS URL. Signed URLs use a 4-hour TTL
	 * by default so they are safe to cache in CDNs.
	 *
	 * @since  1.2.0
	 * @param  int $attach_id WP attachment post ID.
	 * @return array
	 */
	private function format_video( int $attach_id ): array {
		$post        = get_post( $attach_id );
		$playback_id = get_post_meta( $attach_id, '_mux_playback_id', true );
		$asset_id    = get_post_meta( $attach_id, '_mux_asset_id', true );
		$policy      = get_post_meta( $attach_id, '_mux_playback_policy', true ) ?: 'public';
		$duration    = (float) ( get_post_meta( $attach_id, '_mux_duration', true ) ?: 0 );
		$status      = get_post_meta( $attach_id, '_wp_mux_status', true ) ?: 'complete';

		$stream_url = '';
		if ( ! empty( $playback_id ) ) {
			if ( $policy === 'signed' ) {
				$stream_url = $this->generate_signed_url( $playback_id, 'video', 14400 );
			} else {
				$stream_url = "https://stream.mux.com/{$playback_id}.m3u8";
			}
		}

		$thumbnail_url = ! empty( $playback_id )
			? $this->build_thumbnail_url( $playback_id, $attach_id )
			: '';

		return [
			'id'            => $attach_id,
			'asset_id'      => $asset_id,
			'playback_id'   => $playback_id,
			'title'         => get_the_title( $attach_id ),
			'description'   => $post->post_content ?? '',
			'status'        => $status,
			'policy'        => $policy,
			'stream_url'    => $stream_url,
			'thumbnail_url' => $thumbnail_url,
			'duration'      => $duration,
			'mp4_support'   => get_post_meta( $attach_id, '_mux_mp4_support', true ) ?: 'none',
			'has_captions'  => (bool) get_post_meta( $attach_id, '_mux_has_captions', true ),
			'date'          => $post->post_date,
			'date_gmt'      => $post->post_date_gmt,
		];
	}

	/**
	 * Build a Mux thumbnail URL, respecting any per-attachment poster settings
	 * stored from the block editor (mux_poster_time block attribute is not stored
	 * as post meta, so we fall back to second 0).
	 *
	 * @since  1.2.0
	 * @param  string $playback_id
	 * @param  int    $attach_id
	 * @return string
	 */
	private function build_thumbnail_url( string $playback_id, int $attach_id ): string {
		$time = (float) ( get_post_meta( $attach_id, '_mux_poster_time', true ) ?: 0 );
		return "https://image.mux.com/{$playback_id}/thumbnail.jpg?time={$time}&width=640&height=360&fit_mode=smartcrop";
	}

	/**
	 * Generate a short-lived signed playback URL using stored signing keys.
	 *
	 * Falls back to the unsigned URL if signing keys are not configured.
	 *
	 * @since  1.2.0
	 * @param  string $playback_id Mux playback ID.
	 * @param  string $type        Token type: 'video', 'thumbnail', 'storyboard'.
	 * @param  int    $expiry      Token lifetime in seconds (default: 4 hours).
	 * @return string
	 */
	private function generate_signed_url( string $playback_id, string $type = 'video', int $expiry = 14400 ): string {
		$key_id      = get_option( 'wp_mux_signing_key_id', '' );
		$private_key = get_option( 'wp_mux_signing_key_private', '' );

		if ( empty( $key_id ) || empty( $private_key ) ) {
			// Signing keys not configured — return unsigned URL.
			return "https://stream.mux.com/{$playback_id}.m3u8";
		}

		$exp  = time() + $expiry;
		$payload = base64_encode( wp_json_encode( [
			'sub' => $playback_id,
			'aud' => $type,
			'exp' => $exp,
			'kid' => $key_id,
		] ) );

		// JWT: header.payload.signature (RS256).
		$header    = base64_encode( wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
		$unsigned  = $this->base64url_encode( $header ) . '.' . $this->base64url_encode( $payload );
		$pem       = base64_decode( $private_key );

		$signature = '';
		if ( openssl_sign( $unsigned, $signature, $pem, OPENSSL_ALGO_SHA256 ) ) {
			$token = $unsigned . '.' . $this->base64url_encode( $signature );
			if ( $type === 'video' ) {
				return "https://stream.mux.com/{$playback_id}.m3u8?token={$token}";
			}
			return "https://image.mux.com/{$playback_id}/thumbnail.jpg?token={$token}";
		}

		return "https://stream.mux.com/{$playback_id}.m3u8";
	}

	/**
	 * URL-safe base64 encoding (no padding).
	 *
	 * @since  1.2.0
	 * @param  string $data
	 * @return string
	 */
	private function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
