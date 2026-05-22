<?php

declare(strict_types=1);

/**
 * Mux webhook receiver.
 *
 * Handles real-time events from Mux, eliminating the need for polling.
 * Registers a REST API endpoint that Mux calls when asset status changes.
 *
 * Configure the webhook in the Mux dashboard:
 *   URL: <site>/wp-json/wp-mux/v1/webhook
 *   Events: video.asset.ready, video.asset.deleted,
 *           video.upload.asset.created, video.upload.error,
 *           video.asset.track.ready, video.asset.master.ready
 *
 * @link       https://wpmux.com
 * @since      1.2.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

/**
 * Mux webhook handler class.
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 * @author     Codi Lechasseur
 */
class Wp_Mux_Webhook {

	/**
	 * Timestamp tolerance in seconds for signature validation.
	 *
	 * @var int
	 */
	private const TIMESTAMP_TOLERANCE = 300;

	/**
	 * Register the REST route.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the webhook REST route.
	 *
	 * @since 1.2.0
	 */
	public function register_routes(): void {
		register_rest_route(
			'wp-mux/v1',
			'/webhook',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_webhook' ],
				// Mux signs all requests; we verify below instead of using WP caps.
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Handle an incoming Mux webhook request.
	 *
	 * @since  1.2.0
	 * @param  WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$body             = $request->get_body();
		$signing_secret   = get_option( 'wp_mux_webhook_secret', '' );

		// Only validate signature when a secret is configured.
		if ( ! empty( $signing_secret ) ) {
			$sig_header = $request->get_header( 'mux-signature' );
			if ( ! $this->verify_signature( $body, $sig_header, $signing_secret ) ) {
				return new WP_REST_Response( [ 'error' => 'Invalid signature' ], 401 );
			}
		}

		$payload    = $request->get_json_params();
		$event_type = $payload['type'] ?? '';
		$data       = $payload['data'] ?? [];

		switch ( $event_type ) {
			case 'video.asset.ready':
				$this->handle_asset_ready( $data );
				break;
			case 'video.asset.deleted':
				$this->handle_asset_deleted( $data );
				break;
			case 'video.upload.asset.created':
				$this->handle_upload_asset_created( $data );
				break;
			case 'video.upload.error':
				$this->handle_upload_error( $data );
				break;
			case 'video.asset.track.ready':
				$this->handle_track_ready( $data );
				break;
			case 'video.asset.master.ready':
				$this->handle_master_ready( $data );
				break;
		}

		return new WP_REST_Response( [ 'received' => true ], 200 );
	}

	// -------------------------------------------------------------------------
	// Signature verification
	// -------------------------------------------------------------------------

	/**
	 * Verify the Mux webhook signature.
	 *
	 * Mux signs requests with:
	 *   mux-signature: t=<unix_ts>,v1=<hmac_hex>
	 * where the signed payload is "<t>.<raw_body>".
	 *
	 * @since  1.2.0
	 * @param  string      $body    Raw request body.
	 * @param  string|null $header  Value of the mux-signature header.
	 * @param  string      $secret  Webhook signing secret from settings.
	 * @return bool
	 */
	private function verify_signature( string $body, ?string $header, string $secret ): bool {
		if ( empty( $header ) ) {
			return false;
		}

		$parts = [];
		foreach ( explode( ',', $header ) as $part ) {
			[ $key, $value ] = array_pad( explode( '=', $part, 2 ), 2, '' );
			$parts[ trim( $key ) ] = trim( $value );
		}

		if ( empty( $parts['t'] ) || empty( $parts['v1'] ) ) {
			return false;
		}

		// Reject stale timestamps.
		if ( abs( time() - (int) $parts['t'] ) > self::TIMESTAMP_TOLERANCE ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $parts['t'] . '.' . $body, $secret );
		return hash_equals( $expected, $parts['v1'] );
	}

	// -------------------------------------------------------------------------
	// Event handlers
	// -------------------------------------------------------------------------

	/**
	 * Asset is ready — populate WP attachment metadata.
	 *
	 * @since 1.2.0
	 * @param array $data Mux event data payload.
	 */
	private function handle_asset_ready( array $data ): void {
		$asset_id = $data['id'] ?? '';
		if ( empty( $asset_id ) ) {
			return;
		}

		$attach_id = $this->find_attachment_by_asset( $asset_id );

		if ( ! $attach_id ) {
			// Asset arrived before admin_init sync — create the attachment now.
			require_once plugin_dir_path( __FILE__ ) . 'class-wp-mux-media-sync.php';
			require_once plugin_dir_path( __FILE__ ) . 'class-wp-mux-api.php';
			$api  = new Wp_Mux_Api();
			$sync = new Wp_Mux_Media_Sync();
			$res  = $api->get_video( $asset_id );
			if ( ! is_wp_error( $res ) && ! empty( $res['data'] ) ) {
				$sync->maybe_create_mux_attachment( $res['data'] );
			}
			return;
		}

		$playback_id     = $data['playback_ids'][0]['id'] ?? '';
		$playback_policy = $data['playback_ids'][0]['policy'] ?? 'public';

		update_post_meta( $attach_id, '_mux_playback_id', $playback_id );
		update_post_meta( $attach_id, '_mux_duration', $data['duration'] ?? '' );
		update_post_meta( $attach_id, '_mux_playback_policy', $playback_policy );
		update_post_meta( $attach_id, '_mux_mp4_support', $data['mp4_support'] ?? 'none' );
		update_post_meta( $attach_id, '_mux_encoding_tier', $data['encoding_tier'] ?? 'baseline' );
		update_post_meta( $attach_id, '_wp_mux_status', 'complete' );
		update_post_meta( $attach_id, '_mux_requires_signed_url', $playback_policy === 'signed' );

		if ( ! empty( $playback_id ) ) {
			update_post_meta(
				$attach_id,
				'_mux_thumbnail_url',
				"https://image.mux.com/{$playback_id}/thumbnail.jpg?width=240&height=160"
			);
		}

		// Remove processing flags so the queue manager / polling stops.
		delete_post_meta( $attach_id, '_mux_processing' );
		delete_post_meta( $attach_id, '_mux_upload_id' );
		delete_post_meta( $attach_id, '_mux_from_upload' );

		// Update attachment post type.
		wp_update_post( [ 'ID' => $attach_id, 'post_mime_type' => 'video/mux' ] );

		// Auto-generate captions if enabled.
		if ( get_option( 'wp_mux_enable_auto_captions', false ) && ! empty( $asset_id ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-wp-mux-api.php';
			$api      = new Wp_Mux_Api();
			$language = get_option( 'wp_mux_default_caption_language', 'en' );
			$api->generate_captions( $asset_id, $language );
		}

		delete_transient( 'wp_mux_videos_cache' );
	}

	/**
	 * Asset was deleted in Mux — mark the WP attachment.
	 *
	 * @since 1.2.0
	 * @param array $data Mux event data payload.
	 */
	private function handle_asset_deleted( array $data ): void {
		$asset_id = $data['id'] ?? '';
		if ( empty( $asset_id ) ) {
			return;
		}

		$attach_id = $this->find_attachment_by_asset( $asset_id );
		if ( $attach_id ) {
			update_post_meta( $attach_id, '_wp_mux_status', 'deleted' );
		}

		delete_transient( 'wp_mux_videos_cache' );
	}

	/**
	 * A direct upload created an asset — link asset_id to the WP attachment.
	 *
	 * @since 1.2.0
	 * @param array $data Mux event data payload.
	 */
	private function handle_upload_asset_created( array $data ): void {
		$upload_id = $data['id'] ?? '';
		$asset_id  = $data['asset_id'] ?? '';
		if ( empty( $upload_id ) || empty( $asset_id ) ) {
			return;
		}

		$attachments = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => [ [ 'key' => '_mux_upload_id', 'value' => $upload_id ] ],
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		foreach ( $attachments as $attach_id ) {
			update_post_meta( $attach_id, '_mux_asset_id', $asset_id );
		}
	}

	/**
	 * Upload failed — mark the WP attachment as failed.
	 *
	 * @since 1.2.0
	 * @param array $data Mux event data payload.
	 */
	private function handle_upload_error( array $data ): void {
		$upload_id = $data['id'] ?? '';
		if ( empty( $upload_id ) ) {
			return;
		}

		$attachments = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => [ [ 'key' => '_mux_upload_id', 'value' => $upload_id ] ],
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		foreach ( $attachments as $attach_id ) {
			update_post_meta( $attach_id, '_wp_mux_status', 'failed' );
			delete_post_meta( $attach_id, '_mux_processing' );
		}
	}

	/**
	 * A text (caption) track is ready — store track ID on the attachment.
	 *
	 * @since 1.2.0
	 * @param array $data Mux event data payload.
	 */
	private function handle_track_ready( array $data ): void {
		$asset_id = $data['asset_id'] ?? '';
		$track    = $data['track'] ?? [];
		if ( empty( $asset_id ) || ( $track['type'] ?? '' ) !== 'text' ) {
			return;
		}

		$attach_id = $this->find_attachment_by_asset( $asset_id );
		if ( ! $attach_id ) {
			return;
		}

		update_post_meta( $attach_id, '_mux_has_captions', true );
		$existing = (array) get_post_meta( $attach_id, '_mux_caption_track_ids', true );
		if ( ! in_array( $track['id'], $existing, true ) ) {
			$existing[] = $track['id'];
			update_post_meta( $attach_id, '_mux_caption_track_ids', $existing );
		}
	}

	/**
	 * MP4 static rendition (master) is ready — update mp4_support meta.
	 *
	 * @since 1.2.0
	 * @param array $data Mux event data payload.
	 */
	private function handle_master_ready( array $data ): void {
		$asset_id = $data['asset_id'] ?? '';
		if ( empty( $asset_id ) ) {
			return;
		}

		$attach_id = $this->find_attachment_by_asset( $asset_id );
		if ( $attach_id ) {
			// Asset now has an MP4 rendition available.
			$current = get_post_meta( $attach_id, '_mux_mp4_support', true );
			if ( empty( $current ) || $current === 'none' ) {
				update_post_meta( $attach_id, '_mux_mp4_support', 'standard' );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Find a WP attachment post ID by Mux asset ID.
	 *
	 * @since  1.2.0
	 * @param  string $asset_id Mux asset ID.
	 * @return int|null Attachment post ID, or null if not found.
	 */
	private function find_attachment_by_asset( string $asset_id ): ?int {
		$results = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => [ [ 'key' => '_mux_asset_id', 'value' => $asset_id ] ],
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		return ! empty( $results ) ? (int) $results[0] : null;
	}
}
