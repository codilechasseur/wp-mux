<?php

declare(strict_types=1);

/**
 * Queue management for WP Mux video processing.
 *
 * Handles queuing, processing, and status tracking for video uploads to MUX.
 * Includes support for batch processing, retry logic, and queue health monitoring.
 *
 * @link       https://wpmux.com
 * @since      1.3.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

/**
 * WP Mux queue manager class.
 *
 * Manages the video processing queue with features including:
 * - Automatic queuing of uploaded videos
 * - Batch processing support
 * - Failure retry with exponential backoff
 * - Queue health monitoring
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 * @author     Codi Lechasseur
 */
class Wp_Mux_Queue_Manager
{
    /**
     * Queue option name.
     *
     * @since 1.3.0
     *
     * @var string
     */
    private const QUEUE_OPTION = 'wp_mux_processing_queue';

    /**
     * Failed queue option name.
     *
     * @since 1.3.0
     *
     * @var string
     */
    private const FAILED_QUEUE_OPTION = 'wp_mux_failed_queue';

    /**
     * Maximum retry attempts for failed items.
     *
     * @since 1.3.0
     *
     * @var int
     */
    private const MAX_RETRY_ATTEMPTS = 3;

    /**
     * API instance.
     *
     * @since 1.3.0
     *
     * @var Wp_Mux_Api
     */
    private Wp_Mux_Api $api;

    /**
     * Initialize the queue manager.
     *
     * @since 1.3.0
     */
    public function __construct()
    {
        require_once plugin_dir_path(__FILE__) . 'class-wp-mux-api.php';
        $this->api = new Wp_Mux_Api();
    }

    /**
     * Queue a video for MUX processing.
     *
     * @since 1.3.0
     *
     * @param int $attachmentId Attachment ID.
     *
     * @return bool True if queued successfully, false otherwise.
     */
    public function queueVideo(int $attachmentId): bool
    {
        Wp_Mux_Utils::log("Begin queueing video: {$attachmentId}", 'info');

        // Pro gate: seamless upload override requires a WP Mux Pro license.
        if ( ! wp_mux_is_pro() ) {
            return false;
        }

        // Check if MUX override is enabled
        if (get_option('wp_mux_override_video_upload', '0') !== '1') {
            return false;
        }

        // Check if this is a video file
        $filePath = get_attached_file($attachmentId);
        $mimeType = get_post_mime_type($attachmentId);

        if (!$this->isVideoFile($mimeType)) {
            return false;
        }

        // Check if already queued or processed to prevent duplicates
        $existingStatus = get_post_meta($attachmentId, '_wp_mux_status', true);
        if (!empty($existingStatus) && in_array($existingStatus, ['queued', 'processing', 'complete'], true)) {
            Wp_Mux_Utils::log(
                "Video already queued or processed: {$attachmentId}, status: {$existingStatus}",
                'info'
            );
            return false;
        }

        // Check if attachment is already a MUX video
        $post = get_post($attachmentId);
        if ($post && $post->post_mime_type === 'video/mux') {
            Wp_Mux_Utils::log("Attachment is already a MUX video: {$attachmentId}", 'info');
            return false;
        }

        Wp_Mux_Utils::log("Queueing video: {$attachmentId}, path: {$filePath}", 'info');

        // Add to processing queue
        $queue = $this->getQueue();
        $queue[] = [
            'attachment_id' => $attachmentId,
            'file_path'     => $filePath,
            'mime_type'     => $mimeType,
            'status'        => 'queued',
            'queued_at'     => current_time('mysql'),
            'attempts'      => 0,
            'priority'      => $this->calculatePriority($attachmentId),
        ];

        // Sort by priority (higher first)
        usort($queue, fn($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

        update_option(self::QUEUE_OPTION, $queue);

        // Set initial meta to show it's queued for MUX
        update_post_meta($attachmentId, '_wp_mux_status', 'queued');
        update_post_meta($attachmentId, '_wp_mux_queued_at', current_time('mysql'));
        update_post_meta($attachmentId, '_mux_from_upload', '1');
        update_post_meta($attachmentId, '_mux_processing', '1');

        // Schedule immediate processing
        wp_schedule_single_event(time(), 'wp_mux_process_queue_event');

        // Also try to process immediately via background request
        $this->triggerBackgroundProcessing();

        return true;
    }

    /**
     * Process the queue.
     *
     * @since 1.3.0
     *
     * @param int $batchSize Number of items to process in this batch.
     *
     * @return array{processed: int, failed: int, remaining: int} Processing results.
     */
    public function processQueue(int $batchSize = 1): array
    {
        Wp_Mux_Utils::log('Begin processing queue', 'info');

        $queue = $this->getQueue();
        $processed = 0;
        $failed = 0;

        if (empty($queue)) {
            Wp_Mux_Utils::log('Queue is empty', 'info');
            return ['processed' => 0, 'failed' => 0, 'remaining' => 0];
        }

        $itemsToProcess = array_slice($queue, 0, $batchSize);
        $remainingQueue = array_slice($queue, $batchSize);

        foreach ($itemsToProcess as $item) {
            $result = $this->processQueueItem($item);

            if ($result['success']) {
                $processed++;
            } else {
                $failed++;
                // Add to failed queue if max attempts reached
                if (($item['attempts'] ?? 0) >= self::MAX_RETRY_ATTEMPTS - 1) {
                    $this->addToFailedQueue($item, $result['error'] ?? 'Unknown error');
                } else {
                    // Re-queue with incremented attempt count
                    $item['attempts'] = ($item['attempts'] ?? 0) + 1;
                    $item['last_error'] = $result['error'] ?? '';
                    $remainingQueue[] = $item;
                }
            }
        }

        update_option(self::QUEUE_OPTION, $remainingQueue);

        Wp_Mux_Utils::log(
            "Queue processing complete: {$processed} processed, {$failed} failed, " .
            count($remainingQueue) . ' remaining',
            'info'
        );

        return [
            'processed' => $processed,
            'failed'    => $failed,
            'remaining' => count($remainingQueue),
        ];
    }

    /**
     * Process a single queue item.
     *
     * @since 1.3.0
     *
     * @param array $item Queue item data.
     *
     * @return array{success: bool, error?: string} Processing result.
     */
    private function processQueueItem(array $item): array
    {
        $attachmentId = (int) $item['attachment_id'];
        $filePath     = $item['file_path'] ?? '';
        $mimeType     = $item['mime_type'] ?? 'video/mp4';

        Wp_Mux_Utils::log("Processing queue item: {$attachmentId}", 'info');

        try {
            update_post_meta($attachmentId, '_wp_mux_status', 'processing');
            update_post_meta($attachmentId, '_wp_mux_processing_started', current_time('mysql'));

            if (empty($filePath)) {
                $filePath = get_attached_file($attachmentId);
            }

            if (empty($filePath) || !file_exists($filePath)) {
                throw new \Exception("Source file not found: {$filePath}");
            }

            if (!$this->api->is_configured()) {
                throw new \Exception('Mux API credentials are not configured');
            }

            $result = $this->api->upload_local_file($filePath, $mimeType);
            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            update_post_meta($attachmentId, '_mux_upload_id', $result['upload_id']);

            if ($result['asset_id']) {
                update_post_meta($attachmentId, '_mux_asset_id', $result['asset_id']);
            }

            if ($result['playback_id']) {
                update_post_meta($attachmentId, '_mux_playback_id', $result['playback_id']);
            }

            update_post_meta($attachmentId, '_wp_mux_status', 'complete');
            update_post_meta($attachmentId, '_wp_mux_completed_at', current_time('mysql'));

            wp_update_post([
                'ID'             => $attachmentId,
                'post_mime_type' => 'video/mux',
            ]);

            Wp_Mux_Utils::log("Successfully processed video: {$attachmentId}", 'info');

            return ['success' => true];
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            Wp_Mux_Utils::log("Failed to process video {$attachmentId}: {$errorMsg}", 'error');

            update_post_meta($attachmentId, '_wp_mux_status', 'failed');
            update_post_meta($attachmentId, '_wp_mux_error', $errorMsg);
            update_post_meta($attachmentId, '_wp_mux_failed_at', current_time('mysql'));

            return ['success' => false, 'error' => $errorMsg];
        }
    }

    /**
     * Get the current queue.
     *
     * @since 1.3.0
     *
     * @return array Queue items.
     */
    public function getQueue(): array
    {
        return get_option(self::QUEUE_OPTION, []);
    }

    /**
     * Get queue status.
     *
     * @since 1.3.0
     *
     * @return array{total: int, queued: int, processing: int, complete: int, failed: int} Queue statistics.
     */
    public function getQueueStatus(): array
    {
        $queue = $this->getQueue();
        $failedQueue = $this->getFailedQueue();

        $status = [
            'total'      => count($queue),
            'queued'     => 0,
            'processing' => 0,
            'complete'   => 0,
            'failed'     => count($failedQueue),
        ];

        foreach ($queue as $item) {
            $itemStatus = $item['status'] ?? 'queued';
            if (isset($status[$itemStatus])) {
                $status[$itemStatus]++;
            }
        }

        return $status;
    }

    /**
     * Get the failed queue.
     *
     * @since 1.3.0
     *
     * @return array Failed queue items.
     */
    public function getFailedQueue(): array
    {
        return get_option(self::FAILED_QUEUE_OPTION, []);
    }

    /**
     * Add item to failed queue.
     *
     * @since 1.3.0
     *
     * @param array  $item  Queue item.
     * @param string $error Error message.
     *
     * @return void
     */
    private function addToFailedQueue(array $item, string $error): void
    {
        $failedQueue = $this->getFailedQueue();
        $item['failed_at'] = current_time('mysql');
        $item['error'] = $error;
        $failedQueue[] = $item;
        update_option(self::FAILED_QUEUE_OPTION, $failedQueue);

        Wp_Mux_Utils::log(
            "Added to failed queue: {$item['attachment_id']}, error: {$error}",
            'warning'
        );
    }

    /**
     * Retry failed items.
     *
     * @since 1.3.0
     *
     * @param int|null $attachmentId Specific attachment ID to retry, or null for all.
     *
     * @return int Number of items re-queued.
     */
    public function retryFailed(?int $attachmentId = null): int
    {
        $failedQueue = $this->getFailedQueue();
        $queue = $this->getQueue();
        $requeued = 0;

        $remaining = [];
        foreach ($failedQueue as $item) {
            if ($attachmentId === null || $item['attachment_id'] === $attachmentId) {
                // Reset attempts and add back to main queue
                $item['attempts'] = 0;
                $item['status'] = 'queued';
                unset($item['failed_at'], $item['error'], $item['last_error']);
                $queue[] = $item;
                $requeued++;
            } else {
                $remaining[] = $item;
            }
        }

        update_option(self::FAILED_QUEUE_OPTION, $remaining);
        update_option(self::QUEUE_OPTION, $queue);

        return $requeued;
    }

    /**
     * Clear the entire queue.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function clearQueue(): void
    {
        delete_option(self::QUEUE_OPTION);
        Wp_Mux_Utils::log('Queue cleared', 'info');
    }

    /**
     * Clear the failed queue.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function clearFailedQueue(): void
    {
        delete_option(self::FAILED_QUEUE_OPTION);
        Wp_Mux_Utils::log('Failed queue cleared', 'info');
    }

    /**
     * Trigger background queue processing via non-blocking request.
     *
     * @since 1.3.0
     *
     * @return void
     */
    private function triggerBackgroundProcessing(): void
    {
        wp_remote_post(admin_url('admin-ajax.php'), [
            'timeout'   => 0.01,
            'blocking'  => false,
            // sslverify is intentionally false here: this is a loopback request to the
            // same server, which may use a self-signed cert in local/staging environments.
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body'      => [
                'action' => 'wp_mux_process_queue',
                'token'  => $this->getBackgroundToken(),
            ],
        ]);
    }

    /**
     * Generate a deterministic server-side token for authenticating background queue requests.
     *
     * Uses a salted hash so the token is verifiable without a user session.
     *
     * @since 1.3.0
     *
     * @return string
     */
    private function getBackgroundToken(): string
    {
        return wp_hash('wp_mux_background_queue_' . wp_salt('auth'));
    }

    /**
     * Check if file is a video.
     *
     * @since 1.3.0
     *
     * @param string|false $mimeType MIME type.
     *
     * @return bool True if video file.
     */
    private function isVideoFile(string|false $mimeType): bool
    {
        if (!$mimeType) {
            return false;
        }

        return str_starts_with($mimeType, 'video/');
    }

    /**
     * Calculate priority for queue item.
     *
     * Recent uploads (< 1 hour old) get priority 30; everything else gets 10.
     *
     * @since 1.3.0
     *
     * @param int $attachmentId Attachment ID.
     *
     * @return int Priority value (higher = more important).
     */
    private function calculatePriority(int $attachmentId): int
    {
        $post = get_post($attachmentId);
        if ($post && (time() - strtotime($post->post_date)) < HOUR_IN_SECONDS) {
            return 30;
        }

        return 10;
    }

    /**
     * Register all hooks for queue management.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function registerHooks(): void
    {
        add_action('add_attachment', [$this, 'queueVideo']);
        add_action('wp_ajax_wp_mux_process_queue', [$this, 'ajaxProcessQueue']);
        add_action('wp_ajax_wp_mux_get_queue_status', [$this, 'ajaxGetQueueStatus']);
        add_action('wp_mux_process_queue_event', [$this, 'cronProcessQueue']);
        add_filter('heartbeat_received', [$this, 'heartbeatProcessQueue'], 10, 2);
    }

    /**
     * AJAX handler for queue processing.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function ajaxProcessQueue(): void
    {
        $token = sanitize_text_field($_POST['token'] ?? '');
        $isBackground = hash_equals($this->getBackgroundToken(), $token);

        if (!$isBackground) {
            Wp_Mux_Utils::verifyAjaxRequest('wp_mux_queue_nonce');
        }

        $result = $this->processQueue();

        wp_send_json_success(
            Wp_Mux_Utils::successResponse($result)
        );
    }

    /**
     * Cron handler for queue processing.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function cronProcessQueue(): void
    {
        $this->processQueue(5);
    }

    /**
     * Heartbeat handler for queue processing.
     *
     * @since 1.3.0
     *
     * @param array $response Heartbeat response.
     * @param array $data     Heartbeat data.
     *
     * @return array Modified response.
     */
    public function heartbeatProcessQueue(array $response, array $data): array
    {
        if (!empty($data['wp_mux_check_queue'])) {
            $status = $this->getQueueStatus();
            $response['wp_mux_queue_status'] = $status;

            if ($status['total'] > 0) {
                $this->processQueue(1);
            }
        }

        return $response;
    }

    /**
     * AJAX handler for queue status.
     *
     * @since 1.3.0
     *
     * @return void
     */
    public function ajaxGetQueueStatus(): void
    {
        check_ajax_referer('wp_mux_queue_status_nonce', 'nonce');

        $attachmentId = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;

        if ($attachmentId) {
            $status  = get_post_meta($attachmentId, '_wp_mux_status', true);
            $assetId = get_post_meta($attachmentId, '_mux_asset_id', true);
            $error   = get_post_meta($attachmentId, '_wp_mux_error', true);

            wp_send_json_success([
                'attachment_id' => $attachmentId,
                'status'        => $status ?: 'unknown',
                'asset_id'      => $assetId,
                'error'         => $error,
            ]);
        }

        wp_send_json_success($this->getQueueStatus());
    }
}
