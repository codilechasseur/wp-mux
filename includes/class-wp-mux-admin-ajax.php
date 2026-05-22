<?php

declare(strict_types=1);

/**
 * AJAX request handlers for WP Mux admin functionality.
 *
 * Extracted from Wp_Mux_Admin to improve code organization and maintainability.
 * Handles all AJAX requests from the admin interface.
 *
 * @link       https://wpmux.com
 * @since      1.3.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

/**
 * WP Mux AJAX handler class.
 *
 * Provides all AJAX endpoint handlers for admin functionality including
 * video management, theme management, analytics, and migration operations.
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 * @author     Codi Lechasseur
 */
class Wp_Mux_Admin_Ajax {
	/**
	 * API instance.
	 *
	 * @since 1.3.0
	 *
	 * @var Wp_Mux_Api
	 */
	private Wp_Mux_Api $api;

	/**
	 * Theme manager instance.
	 *
	 * @since 1.3.0
	 *
	 * @var Wp_Mux_Theme_Manager
	 */
	private Wp_Mux_Theme_Manager $theme_manager;

	/**
	 * Initialize the AJAX handler.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		require_once plugin_dir_path(__FILE__) . 'class-wp-mux-api.php';
		require_once plugin_dir_path(__FILE__) . 'class-wp-mux-theme-manager.php';
		require_once plugin_dir_path(__FILE__) . 'class-wp-mux-utils.php';

		$this->api = new Wp_Mux_Api();
		$this->themeManager = new Wp_Mux_Theme_Manager();
	}

	/**
	 * Register all AJAX handlers.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Video management
		add_action('wp_ajax_wp_mux_create_upload', [$this, 'create_upload']);
		add_action('wp_ajax_wp_mux_load_videos', [$this, 'load_videos']);
		add_action('wp_ajax_wp_mux_delete_video', [$this, 'delete_video']);
		add_action('wp_ajax_wp_mux_get_video_details', [$this, 'get_video_details']);

		// Theme management
		add_action('wp_ajax_wp_mux_save_theme', [$this, 'save_theme']);
		add_action('wp_ajax_wp_mux_delete_theme', [$this, 'delete_theme']);
		add_action('wp_ajax_wp_mux_set_default_theme', [$this, 'set_default_theme']);

		// Analytics
		add_action('wp_ajax_wp_mux_refresh_video_analytics', [$this, 'refresh_video_analytics']);

		// Migration
		add_action('wp_ajax_wp_mux_scan_local_videos', [$this, 'scan_local_videos']);
		add_action('wp_ajax_wp_mux_migrate_single_video', [$this, 'migrate_single_video']);
		add_action('wp_ajax_wp_mux_get_migration_status', [$this, 'get_migration_status']);

		// Settings
		add_action('wp_ajax_wp_mux_save_delete_option', [$this, 'save_delete_option']);

		// Caption and policy management
		add_action('wp_ajax_wp_mux_generate_captions', [$this, 'generate_captions']);
		add_action('wp_ajax_wp_mux_update_playback_policy', [$this, 'update_playback_policy']);

		// Auto-configure signing keys
		add_action('wp_ajax_wp_mux_auto_configure_keys', [$this, 'auto_configure_keys']);

		// Check signing keys
		add_action('wp_ajax_wp_mux_check_signing_keys', [$this, 'check_signing_keys']);

		// Test API connection
		add_action('wp_ajax_wp_mux_test_api_connection', [$this, 'test_api_connection']);

		// Migrate-back
		add_action('wp_ajax_migrate_back_system_check', [$this, 'migrate_back_system_check']);
		add_action('wp_ajax_migrate_back_scan', [$this, 'migrate_back_scan']);
		add_action('wp_ajax_migrate_back_enable_mp4', [$this, 'migrate_back_enable_mp4']);
		add_action('wp_ajax_migrate_back_scan_content', [$this, 'migrate_back_scan_content']);
		add_action('wp_ajax_migrate_back_video', [$this, 'migrate_back_video']);
		add_action('wp_ajax_migrate_back_update_content', [$this, 'migrate_back_update_content']);
		add_action('wp_ajax_migrate_back_cleanup', [$this, 'migrate_back_cleanup']);

		// Caption review
		add_action('wp_ajax_wp_mux_get_caption_vtt', [$this, 'get_caption_vtt']);
		add_action('wp_ajax_wp_mux_save_caption_vtt', [$this, 'save_caption_vtt']);

		// URL import
		add_action('wp_ajax_wp_mux_import_from_url', [$this, 'import_from_url']);
	}

	/**
	 * Handle AJAX request for creating a direct upload.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function create_upload(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_upload_nonce');

		$upload = $this->api->create_direct_upload();

		if (is_wp_error($upload)) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response($upload->get_error_message())
			);
		}

		wp_send_json_success(
			Wp_Mux_Utils::success_response($upload)
		);
	}

	/**
	 * Handle AJAX request for loading videos.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function load_videos(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_videos_nonce');

		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		// Use caching for video list
		$cache_key = "wp_mux_videos_page_{$page}";
		$videos = Wp_Mux_Utils::get_cached_or_fetch(
			$cache_key,
			fn() => $this->api->get_videos(25, $page),
			15 * MINUTE_IN_SECONDS
		);

		if (is_wp_error($videos)) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response($videos->get_error_message())
			);
		}

		wp_send_json_success(
			Wp_Mux_Utils::success_response($videos)
		);
	}

	/**
	 * Handle AJAX request for deleting a video.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function delete_video(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_videos_nonce');

		$asset_id = sanitize_text_field( wp_unslash( $_POST['asset_id'] ?? '' ) );

		if ( empty( $asset_id ) ) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response( __( 'Invalid asset ID', 'wp-mux' ) )
			);
		}

		$result = $this->api->delete_video( $asset_id );

		if (is_wp_error($result)) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response($result->get_error_message())
			);
		}

		// Invalidate caches
		Wp_Mux_Utils::invalidate_cache('wp_mux_videos_*');
		Wp_Mux_Utils::invalidate_cache('wp_mux_account_*');

		wp_send_json_success(
			Wp_Mux_Utils::success_response(
				['deleted' => true],
				__('Video deleted successfully', 'wp-mux')
			)
		);
	}

	/**
	 * Handle AJAX request for getting video details.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function get_video_details(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_videos_nonce');

		$asset_id = sanitize_text_field( wp_unslash( $_POST['asset_id'] ?? '' ) );

		if ( empty( $asset_id ) ) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response( __( 'Invalid asset ID', 'wp-mux' ) )
			);
		}

		// Use caching for video details
		$cache_key = "wp_mux_video_{$asset_id}";
		$video = Wp_Mux_Utils::get_cached_or_fetch(
			$cache_key,
			fn() => $this->api->get_video($asset_id),
			HOUR_IN_SECONDS
		);

		if (is_wp_error($video)) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response($video->get_error_message())
			);
		}

		wp_send_json_success(
			Wp_Mux_Utils::success_response($video)
		);
	}

	/**
	 * Handle AJAX request for saving a theme.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function save_theme(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_themes_nonce');

		$theme_id     = sanitize_text_field( wp_unslash( $_POST['theme_id'] ?? '' ) );
		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$controls    = wp_unslash( $_POST['controls'] ?? [] );
		$styles      = wp_unslash( $_POST['styles'] ?? [] );

		// Validate required fields
		$missing_fields = Wp_Mux_Utils::validate_required_fields(
			['name' => $name],
			['name']
		);

		if (!empty($missing_fields)) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(__('Theme name is required', 'wp-mux'))
			);
		}

		// Generate theme ID if creating new theme
		if (empty($theme_id)) {
			$theme_id = $this->themeManager->generate_theme_id($name);
		}

		$result = $this->themeManager->save_theme(
			$theme_id,
			$name,
			$description,
			$controls,
			$styles
		);

		if (is_wp_error($result)) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response($result->get_error_message())
			);
		}

		wp_send_json_success(
			Wp_Mux_Utils::success_response(
				['theme_id' => $theme_id],
				__('Theme saved successfully', 'wp-mux')
			)
		);
	}

	/**
	 * Handle AJAX request for deleting a theme.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function delete_theme(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_themes_nonce');

		$theme_id = sanitize_text_field( wp_unslash( $_POST['theme_id'] ?? '' ) );

		if ( empty( $theme_id ) ) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response( __( 'Invalid theme ID', 'wp-mux' ) )
			);
		}

		$result = $this->themeManager->delete_theme( $theme_id );

		if (is_wp_error($result)) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response($result->get_error_message())
			);
		}

		if ($result === true) {
			wp_send_json_success(
				Wp_Mux_Utils::success_response(
					['deleted' => true],
					__('Theme deleted successfully', 'wp-mux')
				)
			);
		}

		wp_send_json_error(
			Wp_Mux_Utils::error_response(__('Failed to delete theme', 'wp-mux'))
		);
	}

	/**
	 * Handle AJAX request for setting default theme.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function set_default_theme(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_themes_nonce');

		$theme_id = sanitize_text_field( wp_unslash( $_POST['theme_id'] ?? '' ) );

		if ( empty( $theme_id ) ) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response( __( 'Invalid theme ID', 'wp-mux' ) )
			);
		}

		$result = $this->themeManager->set_default_theme( $theme_id );

		if ($result === false) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(__('Theme not found', 'wp-mux'))
			);
		}

		wp_send_json_success(
			Wp_Mux_Utils::success_response(
				['default_theme' => $theme_id],
				__('Default theme set successfully', 'wp-mux')
			)
		);
	}

	/**
	 * Handle AJAX request for refreshing video analytics.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function refresh_video_analytics(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_videos_nonce');

		$asset_id = sanitize_text_field( wp_unslash( $_POST['asset_id'] ?? '' ) );

		if ( empty( $asset_id ) ) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response( __( 'Invalid asset ID', 'wp-mux' ) )
			);
		}

		// Invalidate cache for this video's analytics
		Wp_Mux_Utils::invalidate_cache("wp_mux_analytics_{$asset_id}*");

		// Fetch fresh analytics (returns array, not WP_Error)
		$analytics = $this->api->get_video_analytics($asset_id);

		wp_send_json_success(
			Wp_Mux_Utils::success_response(
				$analytics,
				__('Analytics refreshed successfully', 'wp-mux')
			)
		);
	}

	/**
	 * Handle AJAX request for scanning local videos.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function scan_local_videos(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_migration_nonce');

		// Query for video attachments not yet in MUX
		$args = [
			'post_type'      => 'attachment',
			'post_mime_type' => 'video',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'meta_query'     => [
				'relation' => 'OR',
				[
					'key'     => '_mux_asset_id',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_mux_asset_id',
					'value'   => '',
					'compare' => '=',
				],
			],
		];

		$videos = get_posts($args);

		$total_size = 0;
		$results = array_map(function ($video) use (&$total_size) {
			$filesize = filesize(get_attached_file($video->ID));
			$total_size += $filesize;

			return [
				'id'               => $video->ID,
				'title'            => $video->post_title,
				'url'              => wp_get_attachment_url($video->ID),
				'filename'         => basename(get_attached_file($video->ID)),
				'size_formatted'   => size_format($filesize),
				'filesize'         => $filesize,
				'date'             => $video->post_date,
				'date_formatted'   => date_i18n(get_option('date_format'), strtotime($video->post_date)),
			];
		}, $videos);

		wp_send_json_success(
			Wp_Mux_Utils::success_response(
				[
					'videos'               => $results,
					'total'                => count($results),
					'total_size'           => $total_size,
					'total_size_formatted' => size_format($total_size),
				],
				sprintf(
					_n('%d video found', '%d videos found', count($results), 'wp-mux'),
					count($results)
				)
			)
		);
	}

	/**
	 * Handle AJAX request for migrating a single video.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function migrate_single_video(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_migration_nonce');

		$attachment_id = absint($_POST['attachment_id'] ?? 0);

		if (empty($attachment_id)) {
			wp_send_json_error(Wp_Mux_Utils::error_response(__('Invalid attachment ID', 'wp-mux')));
		}

		$attachment = get_post($attachment_id);
		if (!$attachment || $attachment->post_type !== 'attachment') {
			wp_send_json_error(Wp_Mux_Utils::error_response(__('Attachment not found', 'wp-mux')));
		}

		$mimeType = (string) get_post_mime_type($attachment_id);
		if (!str_starts_with($mimeType, 'video/')) {
			wp_send_json_error(Wp_Mux_Utils::error_response(__('Not a video attachment', 'wp-mux')));
		}

		if (!empty(get_post_meta($attachment_id, '_mux_asset_id', true))) {
			wp_send_json_error(Wp_Mux_Utils::error_response(__('Video already migrated to Mux', 'wp-mux')));
		}

		$filePath = get_attached_file($attachment_id);
		if (!file_exists($filePath)) {
			wp_send_json_error(Wp_Mux_Utils::error_response(__('Video file not found', 'wp-mux')));
		}

		$result = $this->api->upload_local_file($filePath, $mimeType);
		if (is_wp_error($result)) {
			wp_send_json_error(Wp_Mux_Utils::error_response($result->get_error_message()));
		}

		update_post_meta($attachment_id, '_mux_upload_id', $result['upload_id']);
		update_post_meta($attachment_id, '_wp_mux_status', $result['asset_id'] ? 'complete' : 'processing');
		update_post_meta($attachment_id, '_mux_migrated', '1');

		if ($result['asset_id']) {
			update_post_meta($attachment_id, '_mux_asset_id', $result['asset_id']);
		}
		if ($result['playback_id']) {
			update_post_meta($attachment_id, '_mux_playback_id', $result['playback_id']);
		}

		wp_send_json_success(
			Wp_Mux_Utils::success_response(
				[
					'upload_id'     => $result['upload_id'],
					'attachment_id' => $attachment_id,
				],
				__('Video migration started successfully', 'wp-mux')
			)
		);
	}

	/**
	 * Handle AJAX request for getting migration status.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function get_migration_status(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_migration_nonce');

		$status = get_option('wp_mux_migration_status', [
			'total'       => 0,
			'completed'   => 0,
			'failed'      => 0,
			'in_progress' => false,
		]);

		wp_send_json_success(
			Wp_Mux_Utils::success_response($status)
		);
	}

	/**
	 * Handle AJAX request for saving delete option.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function save_delete_option(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_settings_nonce');

		$delete_on_uninstall = isset( $_POST['delete_on_uninstall'] ) && wp_unslash( $_POST['delete_on_uninstall'] ) === 'true';

		update_option('wp_mux_delete_on_uninstall', $delete_on_uninstall);

		wp_send_json_success(
			Wp_Mux_Utils::success_response(
				['delete_on_uninstall' => $delete_on_uninstall],
				__('Setting saved successfully', 'wp-mux')
			)
		);
	}

	/**
	 * Handle caption generation request.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function generate_captions(): void {

		// Verify nonce - action is 'wp_mux_save_settings', field is 'wp_mux_settings_nonce'
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_mux_save_settings' ) ) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(__('Security verification failed. Please refresh the page and try again.', 'wp-mux'), 'nonce_failed')
			);
		}

		if (!current_user_can('upload_files')) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(__('Insufficient permissions.', 'wp-mux'), 'permission_denied')
			);
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$language     = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'en';


		if (!$attachment_id) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(__('Invalid attachment ID.', 'wp-mux'), 'invalid_attachment')
			);
		}

		$asset_id = get_post_meta($attachment_id, '_mux_asset_id', true);

		if (!$asset_id) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(__('MUX asset ID not found.', 'wp-mux'), 'no_asset_id')
			);
		}

		$response = $this->api->generate_captions($asset_id, $language);

		if (is_wp_error($response)) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response($response->get_error_message())
			);
		}


		// Update metadata
		update_post_meta($attachment_id, '_mux_has_captions', true);
		$trackIds = get_post_meta($attachment_id, '_mux_caption_track_ids', true) ?: [];
		if (isset($response['data'])) {
			$trackIds = array_merge($trackIds, array_column($response['data'], 'id'));
		}
		update_post_meta($attachment_id, '_mux_caption_track_ids', $trackIds);


		wp_send_json_success(
			Wp_Mux_Utils::success_response(
				[
					'track_ids' => $trackIds,
				],
				__('Captions generated successfully.', 'wp-mux')
			)
		);
	}

	/**
	 * Handle playback policy update.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function update_playback_policy(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_settings_nonce');

		if (!current_user_can('upload_files')) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(__('Insufficient permissions.', 'wp-mux'), 'permission_denied')
			);
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$policy       = isset( $_POST['policy'] ) ? sanitize_text_field( wp_unslash( $_POST['policy'] ) ) : 'public';

		if (!in_array($policy, ['public', 'signed'], true)) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(__('Invalid playback policy.', 'wp-mux'), 'invalid_policy')
			);
		}

		$asset_id = get_post_meta($attachment_id, '_mux_asset_id', true);

		if (!$asset_id) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(__('MUX asset ID not found.', 'wp-mux'), 'no_asset_id')
			);
		}

		$response = $this->api->update_playback_policy($asset_id, $policy);

		if (is_wp_error($response)) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response($response->get_error_message(), 'api_error')
			);
		}

		// Update metadata
		update_post_meta($attachment_id, '_mux_playback_policy', $policy);
		update_post_meta($attachment_id, '_mux_requires_signed_url', $policy === 'signed');

		wp_send_json_success(
			Wp_Mux_Utils::success_response(
				[],
				__('Playback policy updated successfully.', 'wp-mux')
			)
		);
	}

	/**
	 * Handle automatic signing key configuration.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function auto_configure_keys(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_auto_configure_keys');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(__('Insufficient permissions.', 'wp-mux'), 'permission_denied')
			);
		}

		$result = $this->api->auto_configure_signing_keys();

		if (is_wp_error($result)) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response($result->get_error_message(), 'configuration_failed')
			);
		}

		wp_send_json_success(
			Wp_Mux_Utils::success_response(
				[
					'status' => $result['status'],
					'key_id' => $result['key_id'],
					'created_at' => $result['created_at'] ?? null,
				],
				$result['message']
			)
		);
	}

	/**
	 * Check if signing keys exist.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function check_signing_keys(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_check_keys');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(__('Insufficient permissions.', 'wp-mux'), 'permission_denied')
			);
		}

		$key_id = get_option('wp_mux_signing_key_id', '');
		$has_keys = !empty($key_id);

		wp_send_json_success(
			Wp_Mux_Utils::success_response(
				[
					'has_keys' => $has_keys,
					'key_id' => $has_keys ? $key_id : null,
				],
				$has_keys ? __('Signing keys already configured.', 'wp-mux') : __('No signing keys configured.', 'wp-mux')
			)
		);
	}

	/**
	 * Test API connection.
	 *
	 * @since 1.3.0
	 *
	 * @return void Sends JSON response and dies.
	 */
	public function test_api_connection(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_test_api');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(__('Insufficient permissions.', 'wp-mux'), 'permission_denied')
			);
		}

		// Test 1: Check if API is configured
		if (!$this->api->is_configured()) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(
					__('API credentials are not configured. Please enter your Mux Access Token and Secret Key in the settings.', 'wp-mux'),
					'not_configured'
				)
			);
		}

		// Test 2: Try to list assets (simple API call)
		$test_result = $this->api->get_videos(1, 1);

		if (is_wp_error($test_result)) {
			$error_message = $test_result->get_error_message();
			$error_data = $test_result->get_error_data();

			wp_send_json_error(
				Wp_Mux_Utils::error_response(
					sprintf(
						__('API connection failed: %s (Status: %d)', 'wp-mux'),
						$error_message,
						$error_data['status'] ?? 'unknown'
					),
					'api_error'
				)
			);
		}

		// Test 3: Try to check signing keys endpoint
		$keys_result = $this->api->list_signing_keys();

		if (is_wp_error($keys_result)) {
			wp_send_json_error(
				Wp_Mux_Utils::error_response(
					sprintf(
						__('API connected but signing keys endpoint failed: %s. Your Mux account may not have permission to create signing keys, or the endpoint may require a different API version.', 'wp-mux'),
						$keys_result->get_error_message()
					),
					'signing_keys_error'
				)
			);
		}

		// Success!
		wp_send_json_success(
			Wp_Mux_Utils::success_response(
				[
					'api_connected' => true,
					'signing_keys_available' => true,
					'existing_keys' => count($keys_result['data'] ?? []),
				],
				__('API connection successful! Signing keys endpoint is accessible.', 'wp-mux')
			)
		);
	}

	/**
	 * AJAX handler: Migrate-back system check.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function migrate_back_system_check(): void {
		check_ajax_referer('wp_mux_migrate_back', 'nonce');

		require_once plugin_dir_path(__FILE__) . 'class-wp-mux-migrate-back.php';
		$migrator = new Wp_Mux_Migrate_Back();
		wp_send_json_success($migrator->validate_system());
	}

	/**
	 * AJAX handler: Scan Mux videos for migration.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function migrate_back_scan(): void {
		check_ajax_referer('wp_mux_migrate_back', 'nonce');

		require_once plugin_dir_path(__FILE__) . 'class-wp-mux-migrate-back.php';
		$migrator = new Wp_Mux_Migrate_Back();
		$result = $migrator->scan_mux_videos();

		if (is_wp_error($result)) {
			wp_send_json_error($result->get_error_message());
		}

		wp_send_json_success($result);
	}

	/**
	 * AJAX handler: Enable MP4 support for a video.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function migrate_back_enable_mp4(): void {
		check_ajax_referer('wp_mux_migrate_back', 'nonce');

		$asset_id = isset( $_POST['asset_id'] ) ? sanitize_text_field( wp_unslash( $_POST['asset_id'] ) ) : '';
		if ( empty( $asset_id ) ) {
			wp_send_json_error( __( 'Missing asset ID', 'wp-mux' ) );
		}

		require_once plugin_dir_path(__FILE__) . 'class-wp-mux-migrate-back.php';
		$migrator = new Wp_Mux_Migrate_Back();
		$result = $migrator->enable_mp4_support($asset_id);

		if (is_wp_error($result)) {
			wp_send_json_error($result->get_error_message());
		}

		wp_send_json_success();
	}

	/**
	 * AJAX handler: Scan content for video references.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function migrate_back_scan_content(): void {
		check_ajax_referer('wp_mux_migrate_back', 'nonce');

		require_once plugin_dir_path(__FILE__) . 'class-wp-mux-content-scanner.php';
		$scanner = new Wp_Mux_Content_Scanner();
		wp_send_json_success($scanner->scan_content_references());
	}

	/**
	 * AJAX handler: Migrate a single video back to local.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function migrate_back_video(): void {
		check_ajax_referer('wp_mux_migrate_back', 'nonce');

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'Missing attachment ID', 'wp-mux' ) );
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-wp-mux-migrate-back.php';
		$migrator = new Wp_Mux_Migrate_Back();
		$result   = $migrator->migrate_video_to_local( $attachment_id );

		if (is_wp_error($result)) {
			wp_send_json_error($result->get_error_message());
		}

		wp_send_json_success($result);
	}

	/**
	 * AJAX handler: Update content references.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function migrate_back_update_content(): void {
		check_ajax_referer('wp_mux_migrate_back', 'nonce');

		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['post_ids'] ) ) : [];
		if ( empty( $post_ids ) ) {
			wp_send_json_error( __( 'No posts specified', 'wp-mux' ) );
		}

		require_once plugin_dir_path(__FILE__) . 'class-wp-mux-content-scanner.php';
		$scanner = new Wp_Mux_Content_Scanner();
		wp_send_json_success($scanner->batch_update_content($post_ids, true));
	}

	/**
	 * AJAX handler: Cleanup Mux video.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function migrate_back_cleanup(): void {
		check_ajax_referer('wp_mux_migrate_back', 'nonce');

		$attachment_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
		if (!$attachment_id) {
			wp_send_json_error(__('Missing attachment ID', 'wp-mux'));
		}

		require_once plugin_dir_path(__FILE__) . 'class-wp-mux-migrate-back.php';
		$migrator = new Wp_Mux_Migrate_Back();
		$result = $migrator->cleanup_mux_video($attachment_id);

		if (is_wp_error($result)) {
			wp_send_json_error($result->get_error_message());
		}

		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Caption review
	// -------------------------------------------------------------------------

	/**
	 * Fetch a caption VTT file for inline editing.
	 *
	 * Retrieves the Mux-hosted VTT track and returns its text so the editor
	 * can display it in a textarea. The caller provides the attachment ID and
	 * an optional track ID; if no track ID is given we use the first one.
	 *
	 * @since 1.2.0
	 */
	public function get_caption_vtt(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_settings_nonce');

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$track_id      = isset( $_POST['track_id'] ) ? sanitize_text_field( wp_unslash( $_POST['track_id'] ) ) : '';

		if ( ! $attachment_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing attachment ID', 'wp-mux' ) ] );
		}

		$asset_id = get_post_meta( $attachment_id, '_mux_asset_id', true );
		if ( empty( $asset_id ) ) {
			wp_send_json_error( [ 'message' => __( 'No Mux asset linked to this attachment', 'wp-mux' ) ] );
		}

		// Resolve track ID if not provided.
		if (empty($track_id)) {
			$trackIds = (array) get_post_meta($attachment_id, '_mux_caption_track_ids', true);
			$track_id  = $trackIds[0] ?? '';
		}

		if (empty($track_id)) {
			wp_send_json_error(['message' => __('No caption track found', 'wp-mux')]);
		}

		// Fetch the Mux asset to get the VTT URL for this track.
		$asset_data = $this->api->get_video($asset_id);
		if (is_wp_error($asset_data)) {
			wp_send_json_error(['message' => $asset_data->get_error_message()]);
		}

		$tracks = $asset_data['data']['tracks'] ?? [];
		$vtt_url = '';
		foreach ($tracks as $track) {
			if (($track['id'] ?? '') === $track_id && ($track['type'] ?? '') === 'text') {
				$playback_id = get_post_meta($attachment_id, '_mux_playback_id', true);
				// Mux VTT endpoint: https://stream.mux.com/{playback_id}/text/{track_id}.vtt
				$vtt_url = "https://stream.mux.com/{$playback_id}/text/{$track_id}.vtt";
				break;
			}
		}

		if (empty($vtt_url)) {
			wp_send_json_error(['message' => __('Could not locate VTT URL for this track', 'wp-mux')]);
		}

		// Fetch VTT content server-side (avoids CORS in the browser).
		$response = wp_remote_get($vtt_url, ['timeout' => 15]);
		if (is_wp_error($response)) {
			wp_send_json_error(['message' => $response->get_error_message()]);
		}

		$vtt_content = wp_remote_retrieve_body($response);

		wp_send_json_success([
			'track_id'    => $track_id,
			'vtt_content' => $vtt_content,
		]);
	}

	/**
	 * Save edited VTT content back to Mux by deleting the old track and
	 * uploading the revised text as a new text track.
	 *
	 * @since 1.2.0
	 */
	public function save_caption_vtt(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_settings_nonce');

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$track_id      = isset( $_POST['track_id'] ) ? sanitize_text_field( wp_unslash( $_POST['track_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- VTT is plain text; we don't HTML-encode it.
		$vtt_content   = isset( $_POST['vtt_content'] ) ? wp_unslash( $_POST['vtt_content'] ) : '';

		if (!$attachment_id || empty($track_id) || empty($vtt_content)) {
			wp_send_json_error(['message' => __('Missing required fields', 'wp-mux')]);
		}

		$asset_id = get_post_meta($attachment_id, '_mux_asset_id', true);
		if (empty($asset_id)) {
			wp_send_json_error(['message' => __('No Mux asset linked to this attachment', 'wp-mux')]);
		}

		// Verify it's a valid WebVTT file.
		if (strpos(trim($vtt_content), 'WEBVTT') !== 0) {
			wp_send_json_error(['message' => __('Invalid VTT format: must start with WEBVTT', 'wp-mux')]);
		}

		// Step 1: delete the old track.
		$deleteResult = $this->api->delete_caption_track($asset_id, $track_id);
		if (is_wp_error($deleteResult)) {
			wp_send_json_error(['message' => $deleteResult->get_error_message()]);
		}

		// Step 2: upload the revised VTT to a temporary location Mux can fetch.
		// We store it as a transient-served endpoint since we can't push raw text to Mux.
		$transientKey = 'wp_mux_vtt_' . wp_generate_password(16, false);
		set_transient($transientKey, $vtt_content, HOUR_IN_SECONDS);

		$vtt_url = add_query_arg([
			'wp_mux_action' => 'serve_vtt',
			'key'           => $transientKey,
		], home_url('/'));

		// Step 3: ask Mux to re-ingest the revised VTT.
		$trackData = $this->api->add_caption_track( $asset_id, $vtt_url, 'en', 'Edited Captions' );

		if (is_wp_error($trackData)) {
			wp_send_json_error(['message' => $trackData->get_error_message()]);
		}

		$newTrackId = $trackData['data']['id'] ?? '';

		// Update stored track IDs.
		$existingIds = (array) get_post_meta($attachment_id, '_mux_caption_track_ids', true);
		$existingIds = array_diff($existingIds, [$track_id]);
		if (!empty($newTrackId)) {
			$existingIds[] = $newTrackId;
		}
		update_post_meta($attachment_id, '_mux_caption_track_ids', array_values($existingIds));

		wp_send_json_success(['message' => __('Captions updated successfully', 'wp-mux'), 'new_track_id' => $newTrackId]);
	}

	// -------------------------------------------------------------------------
	// URL import
	// -------------------------------------------------------------------------

	/**
	 * Import a video from a remote URL by passing it directly to Mux.
	 *
	 * No local download is required — Mux fetches the file directly from the
	 * provided URL and creates an asset. A WordPress attachment is then created
	 * as a placeholder with _mux_processing=1 so the existing polling / webhook
	 * flow finalises the metadata when encoding is complete.
	 *
	 * @since 1.2.0
	 */
	public function import_from_url(): void {
		Wp_Mux_Utils::verify_ajax_request('wp_mux_migration_nonce');

		$raw_url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
		if (empty($raw_url)) {
			wp_send_json_error(['message' => __('No URL provided', 'wp-mux')]);
		}

		// Basic scheme check — only allow http(s).
		$scheme = wp_parse_url($raw_url, PHP_URL_SCHEME);
		if (!in_array($scheme, ['http', 'https'], true)) {
			wp_send_json_error(['message' => __('URL must use http or https', 'wp-mux')]);
		}

		$playbackPolicy = get_option('wp_mux_default_playback_policy', 'public');
		$mp4Support     = get_option('wp_mux_default_mp4_support', 'none');
		$encodingTier   = get_option('wp_mux_default_encoding_tier', 'baseline');

		// Create a Mux asset with the remote URL as the input source.
		$assetResult = $this->api->create_asset_from_url( $raw_url, [
			'playback_policy' => $playbackPolicy,
			'mp4_support'     => $mp4Support,
			'encoding_tier'   => $encodingTier,
		] );

		if (is_wp_error($assetResult)) {
			wp_send_json_error(['message' => $assetResult->get_error_message()]);
		}

		$asset_id    = $assetResult['data']['id'] ?? '';
		$playback_id = $assetResult['data']['playback_ids'][0]['id'] ?? '';

		if (empty($asset_id)) {
			wp_send_json_error(['message' => __('Mux did not return an asset ID', 'wp-mux')]);
		}

		// Derive a title from the URL filename.
		$filename = basename(wp_parse_url($raw_url, PHP_URL_PATH) ?: '');
		$title    = pathinfo($filename, PATHINFO_FILENAME) ?: __('Imported video', 'wp-mux');
		$title    = ucwords(str_replace(['-', '_'], ' ', $title));

		// Create a WP attachment placeholder.
		$attachId = wp_insert_attachment([
			'post_title'     => sanitize_text_field($title),
			'post_status'    => 'inherit',
			'post_type'      => 'attachment',
			'post_mime_type' => 'video/mux',
			'post_date'      => current_time('mysql'),
		]);

		if (is_wp_error($attachId)) {
			wp_send_json_error(['message' => $attachId->get_error_message()]);
		}

		update_post_meta($attachId, '_mux_asset_id', $asset_id);
		update_post_meta($attachId, '_wp_mux_status', 'processing');
		update_post_meta($attachId, '_mux_processing', '1');

		if (!empty($playback_id)) {
			update_post_meta($attachId, '_mux_playback_id', $playback_id);
			update_post_meta($attachId, '_mux_thumbnail_url', "https://image.mux.com/{$playback_id}/thumbnail.jpg?width=240&height=160");
		}

		delete_transient('wp_mux_videos_cache');

		wp_send_json_success([
			'attachment_id' => $attachId,
			'asset_id'      => $asset_id,
			'title'         => $title,
			'message'       => __('Video import started. Mux is fetching and processing the file.', 'wp-mux'),
		]);
	}
}
