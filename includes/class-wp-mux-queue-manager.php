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
class Wp_Mux_Queue_Manager {
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
	public function __construct() {
		require_once plugin_dir_path(__FILE__) . 'class-wp-mux-api.php';
		$this->api = new Wp_Mux_Api();
	}

	/**
	 * Queue a video for MUX processing.
	 *
	 * @since 1.3.0
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool True if queued successfully, false otherwise.
	 */
	public function queue_video(int $attachment_id): bool {
		Wp_Mux_Utils::log("Begin queueing video: {$attachment_id}", 'info');

		// Pro gate: seamless upload override requires a WP Mux Pro license.
		if ( ! wp_mux_is_pro() ) {
			return false;
		}

		// Check if MUX override is enabled
		if (get_option('wp_mux_override_video_upload', '0') !== '1') {
			return false;
		}

		// Check if this is a video file
		$file_path = get_attached_file($attachment_id);
		$mime_type = get_post_mime_type($attachment_id);

		if (!$this->is_video_file($mime_type)) {
			return false;
		}

		// Check if already queued or processed to prevent duplicates
		$existing_status = get_post_meta($attachment_id, '_wp_mux_status', true);
		if (!empty($existing_status) && in_array($existing_status, ['queued', 'processing', 'complete'], true)) {
			Wp_Mux_Utils::log(
				"Video already queued or processed: {$attachment_id}, status: {$existing_status}",
				'info'
			);
			return false;
		}

		// Check if attachment is already a MUX video
		$post = get_post($attachment_id);
		if ($post && $post->post_mime_type === 'video/mux') {
			Wp_Mux_Utils::log("Attachment is already a MUX video: {$attachment_id}", 'info');
			return false;
		}

		Wp_Mux_Utils::log("Queueing video: {$attachment_id}, path: {$file_path}", 'info');

		// Add to processing queue
		$queue = $this->get_queue();
		$queue[] = [
			'attachment_id' => $attachment_id,
			'file_path'     => $file_path,
			'mime_type'     => $mime_type,
			'status'        => 'queued',
			'queued_at'     => current_time('mysql'),
			'attempts'      => 0,
			'priority'      => $this->calculate_priority($attachment_id),
		];

		// Sort by priority (higher first)
		usort($queue, fn($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

		update_option(self::QUEUE_OPTION, $queue);

		// Set initial meta to show it's queued for MUX
		update_post_meta($attachment_id, '_wp_mux_status', 'queued');
		update_post_meta($attachment_id, '_wp_mux_queued_at', current_time('mysql'));
		update_post_meta($attachment_id, '_mux_from_upload', '1');
		update_post_meta($attachment_id, '_mux_processing', '1');

		// Schedule immediate processing
		wp_schedule_single_event(time(), 'wp_mux_process_queue_event');

		// Also try to process immediately via background request
		$this->trigger_background_processing();

		return true;
	}

	/**
	 * Process the queue.
	 *
	 * @since 1.3.0
	 *
	 * @param int $batch_size Number of items to process in this batch.
	 *
	 * @return array{processed: int, failed: int, remaining: int} Processing results.
	 */
	public function process_queue(int $batch_size = 1): array {
		Wp_Mux_Utils::log('Begin processing queue', 'info');

		$queue = $this->get_queue();
		$processed = 0;
		$failed = 0;

		if (empty($queue)) {
			Wp_Mux_Utils::log('Queue is empty', 'info');
			return ['processed' => 0, 'failed' => 0, 'remaining' => 0];
		}

		$items_to_process = array_slice($queue, 0, $batch_size);
		$remaining_queue = array_slice($queue, $batch_size);

		foreach ($items_to_process as $item) {
			$result = $this->process_queue_item($item);

			if ($result['success']) {
				$processed++;
			} else {
				$failed++;
				// Add to failed queue if max attempts reached
				if (($item['attempts'] ?? 0) >= self::MAX_RETRY_ATTEMPTS - 1) {
					$this->add_to_failed_queue($item, $result['error'] ?? 'Unknown error');
				} else {
					// Re-queue with incremented attempt count
					$item['attempts'] = ($item['attempts'] ?? 0) + 1;
					$item['last_error'] = $result['error'] ?? '';
					$remaining_queue[] = $item;
				}
			}
		}

		update_option(self::QUEUE_OPTION, $remaining_queue);

		Wp_Mux_Utils::log(
			"Queue processing complete: {$processed} processed, {$failed} failed, " .
			count($remaining_queue) . ' remaining',
			'info'
		);

		return [
			'processed' => $processed,
			'failed'    => $failed,
			'remaining' => count($remaining_queue),
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
	private function process_queue_item(array $item): array {
		$attachment_id = (int) $item['attachment_id'];
		$file_path     = $item['file_path'] ?? '';
		$mime_type     = $item['mime_type'] ?? 'video/mp4';

		Wp_Mux_Utils::log("Processing queue item: {$attachment_id}", 'info');

		try {
			update_post_meta($attachment_id, '_wp_mux_status', 'processing');
			update_post_meta($attachment_id, '_wp_mux_processing_started', current_time('mysql'));

			if (empty($file_path)) {
				$file_path = get_attached_file($attachment_id);
			}

			if (empty($file_path) || !file_exists($file_path)) {
				throw new \Exception("Source file not found: {$file_path}");
			}

			if (!$this->api->is_configured()) {
				throw new \Exception('Mux API credentials are not configured');
			}

			$result = $this->api->upload_local_file($file_path, $mime_type);
			if (is_wp_error($result)) {
				throw new \Exception($result->get_error_message());
			}

			update_post_meta($attachment_id, '_mux_upload_id', $result['upload_id']);

			if ($result['asset_id']) {
				update_post_meta($attachment_id, '_mux_asset_id', $result['asset_id']);
			}

			if ($result['playback_id']) {
				update_post_meta($attachment_id, '_mux_playback_id', $result['playback_id']);
			}

			update_post_meta($attachment_id, '_wp_mux_status', 'complete');
			update_post_meta($attachment_id, '_wp_mux_completed_at', current_time('mysql'));

			wp_update_post([
				'ID'             => $attachment_id,
				'post_mime_type' => 'video/mux',
			]);

			Wp_Mux_Utils::log("Successfully processed video: {$attachment_id}", 'info');

			return ['success' => true];
		} catch (\Exception $e) {
			$errorMsg = $e->getMessage();
			Wp_Mux_Utils::log("Failed to process video {$attachment_id}: {$errorMsg}", 'error');

			update_post_meta($attachment_id, '_wp_mux_status', 'failed');
			update_post_meta($attachment_id, '_wp_mux_error', $errorMsg);
			update_post_meta($attachment_id, '_wp_mux_failed_at', current_time('mysql'));

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
	public function get_queue(): array {
		return get_option(self::QUEUE_OPTION, []);
	}

	/**
	 * Get queue status.
	 *
	 * @since 1.3.0
	 *
	 * @return array{total: int, queued: int, processing: int, complete: int, failed: int} Queue statistics.
	 */
	public function get_queueStatus(): array {
		$queue = $this->get_queue();
		$failed_queue = $this->get_failed_queue();

		$status = [
			'total'      => count($queue),
			'queued'     => 0,
			'processing' => 0,
			'complete'   => 0,
			'failed'     => count($failed_queue),
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
	public function get_failed_queue(): array {
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
	private function add_to_failed_queue(array $item, string $error): void {
		$failed_queue = $this->get_failed_queue();
		$item['failed_at'] = current_time('mysql');
		$item['error'] = $error;
		$failed_queue[] = $item;
		update_option(self::FAILED_QUEUE_OPTION, $failed_queue);

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
	 * @param int|null $attachment_id Specific attachment ID to retry, or null for all.
	 *
	 * @return int Number of items re-queued.
	 */
	public function retry_failed(?int $attachment_id = null): int {
		$failed_queue = $this->get_failed_queue();
		$queue = $this->get_queue();
		$requeued = 0;

		$remaining = [];
		foreach ($failed_queue as $item) {
			if ($attachment_id === null || $item['attachment_id'] === $attachment_id) {
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
	public function clear_queue(): void {
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
	public function clear_failed_queue(): void {
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
	private function trigger_background_processing(): void {
		wp_remote_post(admin_url('admin-ajax.php'), [
			'timeout'   => 0.01,
			'blocking'  => false,
			// sslverify is intentionally false here: this is a loopback request to the
			// same server, which may use a self-signed cert in local/staging environments.
			'sslverify' => apply_filters('https_local_ssl_verify', false),
			'body'      => [
				'action' => 'wp_mux_process_queue',
				'token'  => $this->get_background_token(),
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
	private function get_background_token(): string {
		return wp_hash('wp_mux_background_queue_' . wp_salt('auth'));
	}

	/**
	 * Check if file is a video.
	 *
	 * @since 1.3.0
	 *
	 * @param string|false $mime_type MIME type.
	 *
	 * @return bool True if video file.
	 */
	private function is_video_file(string|false $mime_type): bool {
		if (!$mime_type) {
			return false;
		}

		return str_starts_with($mime_type, 'video/');
	}

	/**
	 * Calculate priority for queue item.
	 *
	 * Recent uploads (< 1 hour old) get priority 30; everything else gets 10.
	 *
	 * @since 1.3.0
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return int Priority value (higher = more important).
	 */
	private function calculate_priority(int $attachment_id): int {
		$post = get_post($attachment_id);
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
	public function register_hooks(): void {
		add_action('add_attachment', [$this, 'queue_video']);
		add_action('wp_ajax_wp_mux_process_queue', [$this, 'ajax_process_queue']);
		add_action('wp_ajax_wp_mux_get_queue_status', [$this, 'ajax_get_queue_status']);
		add_action('wp_mux_process_queue_event', [$this, 'cron_process_queue']);
		add_filter('heartbeat_received', [$this, 'heartbeat_process_queue'], 10, 2);
	}

	/**
	 * AJAX handler for queue processing.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function ajax_process_queue(): void {
		$token = sanitize_text_field($_POST['token'] ?? '');
		$isBackground = hash_equals($this->get_background_token(), $token);

		if (!$isBackground) {
			Wp_Mux_Utils::verify_ajax_request('wp_mux_queue_nonce');
		}

		$result = $this->process_queue();

		wp_send_json_success(
			Wp_Mux_Utils::success_response($result)
		);
	}

	/**
	 * Cron handler for queue processing.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function cron_process_queue(): void {
		$this->process_queue(5);
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
	public function heartbeat_process_queue(array $response, array $data): array {
		if (!empty($data['wp_mux_check_queue'])) {
			$status = $this->get_queueStatus();
			$response['wp_mux_queue_status'] = $status;

			if ($status['total'] > 0) {
				$this->process_queue(1);
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
	public function ajax_get_queue_status(): void {
		check_ajax_referer('wp_mux_queue_status_nonce', 'nonce');

		$attachment_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;

		if ($attachment_id) {
			$status  = get_post_meta($attachment_id, '_wp_mux_status', true);
			$asset_id = get_post_meta($attachment_id, '_mux_asset_id', true);
			$error   = get_post_meta($attachment_id, '_wp_mux_error', true);

			wp_send_json_success([
				'attachment_id' => $attachment_id,
				'status'        => $status ?: 'unknown',
				'asset_id'      => $asset_id,
				'error'         => $error,
			]);
		}

		wp_send_json_success($this->get_queueStatus());
	}
}
