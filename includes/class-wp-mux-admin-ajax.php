<?php

declare(strict_types=1);

/**
 * AJAX request handlers for WP Mux admin functionality.
 *
 * Extracted from Wp_Mux_Admin to improve code organization and maintainability.
 * Handles all AJAX requests from the admin interface.
 *
 * @link       https://codilechasseur@gmail.com
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
 * @author     Codi Lechasseur <codilechasseur@gmail.com>
 */
class Wp_Mux_Admin_Ajax
{
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
    private Wp_Mux_Theme_Manager $themeManager;

    /**
     * Initialize the AJAX handler.
     *
     * @since 1.3.0
     */
    public function __construct()
    {
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
    public function registerHooks(): void
    {
        // Video management
        add_action('wp_ajax_wp_mux_create_upload', [$this, 'createUpload']);
        add_action('wp_ajax_wp_mux_load_videos', [$this, 'loadVideos']);
        add_action('wp_ajax_wp_mux_delete_video', [$this, 'deleteVideo']);
        add_action('wp_ajax_wp_mux_get_video_details', [$this, 'getVideoDetails']);

        // Theme management
        add_action('wp_ajax_wp_mux_save_theme', [$this, 'saveTheme']);
        add_action('wp_ajax_wp_mux_delete_theme', [$this, 'deleteTheme']);
        add_action('wp_ajax_wp_mux_set_default_theme', [$this, 'setDefaultTheme']);

        // Analytics
        add_action('wp_ajax_wp_mux_refresh_video_analytics', [$this, 'refreshVideoAnalytics']);

        // Migration
        add_action('wp_ajax_wp_mux_scan_local_videos', [$this, 'scanLocalVideos']);
        add_action('wp_ajax_wp_mux_migrate_single_video', [$this, 'migrateSingleVideo']);
        add_action('wp_ajax_wp_mux_get_migration_status', [$this, 'getMigrationStatus']);

        // Settings
        add_action('wp_ajax_wp_mux_save_delete_option', [$this, 'saveDeleteOption']);

        // Caption and policy management
        add_action('wp_ajax_wp_mux_generate_captions', [$this, 'generateCaptions']);
        add_action('wp_ajax_wp_mux_update_playback_policy', [$this, 'updatePlaybackPolicy']);
        
        // Auto-configure signing keys
        add_action('wp_ajax_wp_mux_auto_configure_keys', [$this, 'autoConfigureKeys']);
        
        // Check signing keys
        add_action('wp_ajax_wp_mux_check_signing_keys', [$this, 'checkSigningKeys']);
        
        // Test API connection
        add_action('wp_ajax_wp_mux_test_api_connection', [$this, 'testApiConnection']);
    }

    /**
     * Handle AJAX request for creating a direct upload.
     *
     * @since 1.3.0
     *
     * @return void Sends JSON response and dies.
     */
    public function createUpload(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_upload_nonce');

        $upload = $this->api->create_direct_upload();

        if (is_wp_error($upload)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse($upload->get_error_message())
            );
        }

        wp_send_json_success(
            Wp_Mux_Utils::successResponse($upload)
        );
    }

    /**
     * Handle AJAX request for loading videos.
     *
     * @since 1.3.0
     *
     * @return void Sends JSON response and dies.
     */
    public function loadVideos(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_videos_nonce');

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;

        // Use caching for video list
        $cacheKey = "wp_mux_videos_page_{$page}";
        $videos = Wp_Mux_Utils::getCachedOrFetch(
            $cacheKey,
            fn() => $this->api->get_videos(25, $page),
            15 * MINUTE_IN_SECONDS
        );

        if (is_wp_error($videos)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse($videos->get_error_message())
            );
        }

        wp_send_json_success(
            Wp_Mux_Utils::successResponse($videos)
        );
    }

    /**
     * Handle AJAX request for deleting a video.
     *
     * @since 1.3.0
     *
     * @return void Sends JSON response and dies.
     */
    public function deleteVideo(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_videos_nonce');

        $assetId = sanitize_text_field($_POST['asset_id'] ?? '');

        if (empty($assetId)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Invalid asset ID', 'wp-mux'))
            );
        }

        $result = $this->api->delete_video($assetId);

        if (is_wp_error($result)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse($result->get_error_message())
            );
        }

        // Invalidate caches
        Wp_Mux_Utils::invalidateCache('wp_mux_videos_*');
        Wp_Mux_Utils::invalidateCache('wp_mux_account_*');

        wp_send_json_success(
            Wp_Mux_Utils::successResponse(
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
    public function getVideoDetails(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_videos_nonce');

        $assetId = sanitize_text_field($_POST['asset_id'] ?? '');

        if (empty($assetId)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Invalid asset ID', 'wp-mux'))
            );
        }

        // Use caching for video details
        $cacheKey = "wp_mux_video_{$assetId}";
        $video = Wp_Mux_Utils::getCachedOrFetch(
            $cacheKey,
            fn() => $this->api->get_video($assetId),
            HOUR_IN_SECONDS
        );

        if (is_wp_error($video)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse($video->get_error_message())
            );
        }

        wp_send_json_success(
            Wp_Mux_Utils::successResponse($video)
        );
    }

    /**
     * Handle AJAX request for saving a theme.
     *
     * @since 1.3.0
     *
     * @return void Sends JSON response and dies.
     */
    public function saveTheme(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_themes_nonce');

        $themeId = sanitize_text_field($_POST['theme_id'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $controls = $_POST['controls'] ?? [];
        $styles = $_POST['styles'] ?? [];

        // Validate required fields
        $missingFields = Wp_Mux_Utils::validateRequiredFields(
            ['name' => $name],
            ['name']
        );

        if (!empty($missingFields)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Theme name is required', 'wp-mux'))
            );
        }

        // Generate theme ID if creating new theme
        if (empty($themeId)) {
            $themeId = $this->themeManager->generate_theme_id($name);
        }

        $result = $this->themeManager->save_theme(
            $themeId,
            $name,
            $description,
            $controls,
            $styles
        );

        if (is_wp_error($result)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse($result->get_error_message())
            );
        }

        wp_send_json_success(
            Wp_Mux_Utils::successResponse(
                ['theme_id' => $themeId],
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
    public function deleteTheme(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_themes_nonce');

        $themeId = sanitize_text_field($_POST['theme_id'] ?? '');

        if (empty($themeId)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Invalid theme ID', 'wp-mux'))
            );
        }

        $result = $this->themeManager->delete_theme($themeId);

        if (is_wp_error($result)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse($result->get_error_message())
            );
        }

        if ($result === true) {
            wp_send_json_success(
                Wp_Mux_Utils::successResponse(
                    ['deleted' => true],
                    __('Theme deleted successfully', 'wp-mux')
                )
            );
        }

        wp_send_json_error(
            Wp_Mux_Utils::errorResponse(__('Failed to delete theme', 'wp-mux'))
        );
    }

    /**
     * Handle AJAX request for setting default theme.
     *
     * @since 1.3.0
     *
     * @return void Sends JSON response and dies.
     */
    public function setDefaultTheme(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_themes_nonce');

        $themeId = sanitize_text_field($_POST['theme_id'] ?? '');

        if (empty($themeId)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Invalid theme ID', 'wp-mux'))
            );
        }

        $result = $this->themeManager->set_default_theme($themeId);

        if ($result === false) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Theme not found', 'wp-mux'))
            );
        }

        wp_send_json_success(
            Wp_Mux_Utils::successResponse(
                ['default_theme' => $themeId],
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
    public function refreshVideoAnalytics(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_videos_nonce');

        $assetId = sanitize_text_field($_POST['asset_id'] ?? '');

        if (empty($assetId)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Invalid asset ID', 'wp-mux'))
            );
        }

        // Invalidate cache for this video's analytics
        Wp_Mux_Utils::invalidateCache("wp_mux_analytics_{$assetId}*");

        // Fetch fresh analytics (returns array, not WP_Error)
        $analytics = $this->api->get_video_analytics($assetId);

        wp_send_json_success(
            Wp_Mux_Utils::successResponse(
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
    public function scanLocalVideos(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_migration_nonce');

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

        $totalSize = 0;
        $results = array_map(function ($video) use (&$totalSize) {
            $filesize = filesize(get_attached_file($video->ID));
            $totalSize += $filesize;
            
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
            Wp_Mux_Utils::successResponse(
                [
                    'videos'               => $results,
                    'total'                => count($results),
                    'total_size'           => $totalSize,
                    'total_size_formatted' => size_format($totalSize),
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
    public function migrateSingleVideo(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_migration_nonce');

        $attachmentId = absint($_POST['attachment_id'] ?? 0);

        if (empty($attachmentId)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Invalid attachment ID', 'wp-mux'))
            );
        }

        // Get attachment info
        $attachment = get_post($attachmentId);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Attachment not found', 'wp-mux'))
            );
        }

        // Verify it's a video
        if (!str_starts_with((string) get_post_mime_type($attachmentId), 'video/')) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Not a video attachment', 'wp-mux'))
            );
        }

        // Check if already migrated
        $existingAssetId = get_post_meta($attachmentId, '_mux_asset_id', true);
        if (!empty($existingAssetId)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Video already migrated to MUX', 'wp-mux'))
            );
        }

        $filePath = get_attached_file($attachmentId);
        if (!file_exists($filePath)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Video file not found', 'wp-mux'))
            );
        }

        // Create MUX direct upload
        $upload = $this->api->create_direct_upload();

        if (is_wp_error($upload) || empty($upload['data']['url'])) {
            $errorMsg = is_wp_error($upload)
                ? $upload->get_error_message()
                : __('Failed to create MUX upload', 'wp-mux');

            wp_send_json_error(
                Wp_Mux_Utils::errorResponse($errorMsg)
            );
        }

        $uploadUrl = $upload['data']['url'];
        $uploadId = $upload['data']['id'];

        // Upload file to MUX
        $uploadResult = wp_remote_request($uploadUrl, [
            'headers' => [
                'Content-Type' => get_post_mime_type($attachmentId),
            ],
            'body'    => file_get_contents($filePath),
            'timeout' => 300, // 5 minutes for large files
            'method'  => 'PUT',
        ]);

        if (is_wp_error($uploadResult)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(
                    __('MUX upload error: ', 'wp-mux') . $uploadResult->get_error_message()
                )
            );
        }

        $responseCode = wp_remote_retrieve_response_code($uploadResult);
        if ($responseCode < 200 || $responseCode >= 300) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(
                    sprintf(__('Failed to upload video to MUX. Response code: %d', 'wp-mux'), $responseCode)
                )
            );
        }

        // Update attachment with MUX data
        update_post_meta($attachmentId, '_mux_upload_id', $uploadId);
        update_post_meta($attachmentId, '_wp_mux_status', 'processing');
        update_post_meta($attachmentId, '_mux_migrated', '1');

        wp_send_json_success(
            Wp_Mux_Utils::successResponse(
                [
                    'upload_id'     => $uploadId,
                    'attachment_id' => $attachmentId,
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
    public function getMigrationStatus(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_migration_nonce');

        $status = get_option('wp_mux_migration_status', [
            'total'       => 0,
            'completed'   => 0,
            'failed'      => 0,
            'in_progress' => false,
        ]);

        wp_send_json_success(
            Wp_Mux_Utils::successResponse($status)
        );
    }

    /**
     * Handle AJAX request for saving delete option.
     *
     * @since 1.3.0
     *
     * @return void Sends JSON response and dies.
     */
    public function saveDeleteOption(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_settings_nonce');

        $deleteOnUninstall = isset($_POST['delete_on_uninstall']) && $_POST['delete_on_uninstall'] === 'true';

        update_option('wp_mux_delete_on_uninstall', $deleteOnUninstall);

        wp_send_json_success(
            Wp_Mux_Utils::successResponse(
                ['delete_on_uninstall' => $deleteOnUninstall],
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
    public function generateCaptions(): void
    {
        error_log('[WP MUX] generateCaptions called');
        
        // Verify nonce - action is 'wp_mux_save_settings', field is 'wp_mux_settings_nonce'
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_mux_save_settings')) {
            error_log('[WP MUX] Nonce verification failed');
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Security verification failed. Please refresh the page and try again.', 'wp-mux'), 'nonce_failed')
            );
        }

        if (!current_user_can('upload_files')) {
            error_log('[WP MUX] Permission denied for caption generation');
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Insufficient permissions.', 'wp-mux'), 'permission_denied')
            );
        }

        $attachmentId = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : 'en';

        error_log('[WP MUX] Caption generation - Attachment ID: ' . $attachmentId . ', Language: ' . $language);

        if (!$attachmentId) {
            error_log('[WP MUX] Invalid attachment ID');
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Invalid attachment ID.', 'wp-mux'), 'invalid_attachment')
            );
        }

        $assetId = get_post_meta($attachmentId, '_mux_asset_id', true);
        error_log('[WP MUX] Asset ID for attachment ' . $attachmentId . ': ' . $assetId);

        if (!$assetId) {
            error_log('[WP MUX] No MUX asset ID found for attachment');
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('MUX asset ID not found.', 'wp-mux'), 'no_asset_id')
            );
        }

        error_log('[WP MUX] Calling API to generate captions for asset: ' . $assetId);
        $response = $this->api->generate_captions($assetId, $language);

        if (is_wp_error($response)) {
            error_log('[WP MUX] Caption generation failed: ' . $response->get_error_message());
            error_log('[WP MUX] Error data: ' . print_r($response->get_error_data(), true));
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse($response->get_error_message())
            );
        }

        error_log('[WP MUX] Caption generation response: ' . print_r($response, true));

        // Update metadata
        update_post_meta($attachmentId, '_mux_has_captions', true);
        $trackIds = get_post_meta($attachmentId, '_mux_caption_track_ids', true) ?: [];
        if (isset($response['data'])) {
            $trackIds = array_merge($trackIds, array_column($response['data'], 'id'));
        }
        update_post_meta($attachmentId, '_mux_caption_track_ids', $trackIds);

        error_log('[WP MUX] Caption generation successful');

        wp_send_json_success(
            Wp_Mux_Utils::successResponse(
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
    public function updatePlaybackPolicy(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_settings_nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Insufficient permissions.', 'wp-mux'), 'permission_denied')
            );
        }

        $attachmentId = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        $policy = isset($_POST['policy']) ? sanitize_text_field($_POST['policy']) : 'public';

        if (!in_array($policy, ['public', 'signed'], true)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Invalid playback policy.', 'wp-mux'), 'invalid_policy')
            );
        }

        $assetId = get_post_meta($attachmentId, '_mux_asset_id', true);

        if (!$assetId) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('MUX asset ID not found.', 'wp-mux'), 'no_asset_id')
            );
        }

        $response = $this->api->update_playback_policy($assetId, $policy);

        if (is_wp_error($response)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse($response->get_error_message(), 'api_error')
            );
        }

        // Update metadata
        update_post_meta($attachmentId, '_mux_playback_policy', $policy);
        update_post_meta($attachmentId, '_mux_requires_signed_url', $policy === 'signed');

        wp_send_json_success(
            Wp_Mux_Utils::successResponse(
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
    public function autoConfigureKeys(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_auto_configure_keys');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Insufficient permissions.', 'wp-mux'), 'permission_denied')
            );
        }

        $result = $this->api->auto_configure_signing_keys();

        if (is_wp_error($result)) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse($result->get_error_message(), 'configuration_failed')
            );
        }

        wp_send_json_success(
            Wp_Mux_Utils::successResponse(
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
    public function checkSigningKeys(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_check_keys');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Insufficient permissions.', 'wp-mux'), 'permission_denied')
            );
        }

        $key_id = get_option('wp_mux_signing_key_id', '');
        $has_keys = !empty($key_id);

        wp_send_json_success(
            Wp_Mux_Utils::successResponse(
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
    public function testApiConnection(): void
    {
        Wp_Mux_Utils::verifyAjaxRequest('wp_mux_test_api');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(__('Insufficient permissions.', 'wp-mux'), 'permission_denied')
            );
        }

        // Test 1: Check if API is configured
        if (!$this->api->is_configured()) {
            wp_send_json_error(
                Wp_Mux_Utils::errorResponse(
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
                Wp_Mux_Utils::errorResponse(
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
                Wp_Mux_Utils::errorResponse(
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
            Wp_Mux_Utils::successResponse(
                [
                    'api_connected' => true,
                    'signing_keys_available' => true,
                    'existing_keys' => count($keys_result['data'] ?? []),
                ],
                __('API connection successful! Signing keys endpoint is accessible.', 'wp-mux')
            )
        );
    }
}