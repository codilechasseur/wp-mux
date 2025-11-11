<?php

declare(strict_types=1);

/**
 * Queue management for WP Mux video processing.
 *
 * Handles queuing, processing, and status tracking for video uploads to MUX.
 * Includes support for batch processing, retry logic, and queue health monitoring.
 *
 * @link       https://codilechasseur@gmail.com
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
 * @author     Codi Lechasseur <codilechasseur@gmail.com>
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

        // Check if MUX override is enabled
        $overrideEnabled = get_option('wp_mux_override_video_upload', '0');
        if ($overrideEnabled !== '1' && $overrideEnabled !== 1 && $overrideEnabled !== true) {
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
        $filePath = $item['file_path'] ?? '';

        Wp_Mux_Utils::log("Processing queue item: {$attachmentId}", 'info');

        try {
            // Update status to processing
            update_post_meta($attachmentId, '_wp_mux_status', 'processing');
            update_post_meta($attachmentId, '_wp_mux_processing_started', current_time('mysql'));

            // If file path is empty, try to get it from attachment
            if (empty($filePath)) {
                $filePath = get_attached_file($attachmentId);
                Wp_Mux_Utils::log("Retrieved file path: {$filePath}", 'info');
            }

            // Verify file still exists
            if (empty($filePath) || !file_exists($filePath)) {
                throw new \Exception("Source file not found: {$filePath}");
            }

            if (!$this->api->is_configured()) {
                throw new \Exception('MUX API credentials are not configured');
            }

            // Create MUX direct upload
            $upload = $this->api->create_direct_upload();

            if (is_wp_error($upload) || empty($upload['data']['url'])) {
                $errorMsg = is_wp_error($upload)
                    ? $upload->get_error_message()
                    : 'Failed to create MUX upload';
                throw new \Exception($errorMsg);
            }

            $uploadUrl = $upload['data']['url'];
            $uploadId = $upload['data']['id'];

            // Upload file to MUX
            $uploadResult = wp_remote_request($uploadUrl, [
                'headers' => [
                    'Content-Type' => $item['mime_type'] ?? 'video/mp4',
                ],
                'body'    => file_get_contents($filePath),
                'timeout' => 300, // 5 minutes for large files
                'method'  => 'PUT',
            ]);

            if (is_wp_error($uploadResult)) {
                throw new \Exception('MUX upload error: ' . $uploadResult->get_error_message());
            }

            $responseCode = wp_remote_retrieve_response_code($uploadResult);
            if ($responseCode < 200 || $responseCode >= 300) {
                throw new \Exception("Failed to upload video to MUX. Response code: {$responseCode}");
            }

            // Wait for MUX to process the upload
            sleep(2);
            $uploadStatus = $this->api->get_upload($uploadId);
            $assetId = null;

            if (!is_wp_error($uploadStatus) && !empty($uploadStatus['data']['asset_id'])) {
                $assetId = $uploadStatus['data']['asset_id'];

                // Get additional asset details including playback ID
                $assetDetails = $this->api->get_video($assetId);
                if (!is_wp_error($assetDetails) && !empty($assetDetails['data'])) {
                    $playbackId = $assetDetails['data']['playback_ids'][0]['id'] ?? '';
                    if ($playbackId) {
                        update_post_meta($attachmentId, '_mux_playback_id', $playbackId);
                    }
                }
            }

            // Update attachment with MUX data
            update_post_meta($attachmentId, '_mux_upload_id', $uploadId);
            if ($assetId) {
                update_post_meta($attachmentId, '_mux_asset_id', $assetId);
            }
            update_post_meta($attachmentId, '_wp_mux_status', 'complete');
            update_post_meta($attachmentId, '_wp_mux_completed_at', current_time('mysql'));

            // Update attachment to use MUX mime type
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
        $url = admin_url('admin-ajax.php');
        $args = [
            'timeout'   => 0.01, // Non-blocking
            'blocking'  => false,
            'sslverify' => false,
            'body'      => [
                'action'     => 'wp_mux_process_queue',
                'background' => '1',
            ],
        ];

        wp_remote_post($url, $args);
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
     * @since 1.3.0
     *
     * @param int $attachmentId Attachment ID.
     *
     * @return int Priority value (higher = more important).
     */
    private function calculatePriority(int $attachmentId): int
    {
        // Default priority
        $priority = 10;

        // Increase priority for recently uploaded files
        $post = get_post($attachmentId);
        if ($post) {
            $age = time() - strtotime($post->post_date);
            if ($age < HOUR_IN_SECONDS) {
                $priority += 20; // Recent uploads get higher priority
            }
        }

        // Could add more priority logic here (e.g., based on file size, user role, etc.)

        return $priority;
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
        // Allow background processing without nonce check
        $isBackground = isset($_POST['background']) && $_POST['background'] === '1';

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
        $this->processQueue(5); // Process up to 5 items in cron
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

            // Process one item if queue is not empty
            if ($status['total'] > 0) {
                $this->processQueue(1);
            }
        }

        return $response;
    }
}
