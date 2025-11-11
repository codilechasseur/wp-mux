# Quick Reference: Using Refactored WP Mux Classes

## PHP 8.5 & PSR-12 Refactored Classes

### 1. Wp_Mux_Utils - Utility Functions

#### AJAX Request Verification
```php
// Before (in each AJAX handler):
if (!wp_verify_nonce($_POST['nonce'], 'wp_mux_videos_nonce')) {
    wp_die(__('Security check failed', 'wp-mux'));
}
if (!current_user_can('manage_options')) {
    wp_die(__('Insufficient permissions', 'wp-mux'));
}

// After:
Wp_Mux_Utils::verifyAjaxRequest('wp_mux_videos_nonce');
```

#### Response Formatting
```php
// Error response
return Wp_Mux_Utils::errorResponse(
    message: 'Video not found',
    code: 404,
    data: ['video_id' => $id]
);

// Success response
return Wp_Mux_Utils::successResponse(
    data: $video,
    message: 'Video retrieved successfully'
);
```

#### Caching
```php
// Get cached or fetch fresh data
$videos = Wp_Mux_Utils::getCachedOrFetch(
    cacheKey: 'wp_mux_videos_all',
    callback: fn() => $api->get_videos(),
    expiration: 15 * MINUTE_IN_SECONDS
);

// Invalidate cache by pattern
Wp_Mux_Utils::invalidateCache('wp_mux_videos_*');
```

#### Field Validation
```php
// Check for missing required fields
$missing = Wp_Mux_Utils::validateRequiredFields(
    data: $_POST,
    required: ['name', 'email', 'video_id']
);

if (!empty($missing)) {
    wp_send_json_error("Missing fields: " . implode(', ', $missing));
}
```

#### Sanitize and Validate
```php
$rules = [
    'email' => ['type' => 'email', 'required' => true],
    'age' => ['type' => 'int', 'min' => 18, 'max' => 120],
    'website' => ['type' => 'url', 'required' => false],
    'bio' => ['type' => 'textarea', 'required' => false],
];

$sanitized = Wp_Mux_Utils::sanitizeAndValidate($_POST, $rules, $errors);

if (!empty($errors)) {
    // Handle validation errors
}
```

#### Retry with Backoff
```php
$result = Wp_Mux_Utils::retryWithBackoff(
    callback: fn() => $api->upload_video($file),
    maxAttempts: 3,
    initialDelay: 1
);
```

---

### 2. Wp_Mux_Admin_Ajax - AJAX Handlers

#### Initialize and Register
```php
// In your main plugin file or admin class:
$ajax = new Wp_Mux_Admin_Ajax();
$ajax->registerHooks();
```

#### Available Endpoints
All registered automatically via `registerHooks()`:

- `wp_ajax_wp_mux_create_upload`
- `wp_ajax_wp_mux_load_videos`
- `wp_ajax_wp_mux_delete_video`
- `wp_ajax_wp_mux_get_video_details`
- `wp_ajax_wp_mux_save_theme`
- `wp_ajax_wp_mux_delete_theme`
- `wp_ajax_wp_mux_set_default_theme`
- `wp_ajax_wp_mux_refresh_video_analytics`
- `wp_ajax_wp_mux_scan_local_videos`
- `wp_ajax_wp_mux_migrate_single_video`
- `wp_ajax_wp_mux_get_migration_status`
- `wp_ajax_wp_mux_save_delete_option`

#### Features
- ✅ Automatic nonce verification
- ✅ Automatic permission checks
- ✅ Integrated caching
- ✅ Cache invalidation on writes
- ✅ Standardized error/success responses

---

### 3. Wp_Mux_Queue_Manager - Video Processing Queue

#### Initialize
```php
$queue_manager = new Wp_Mux_Queue_Manager();
```

#### Queue a Video
```php
$success = $queue_manager->queueVideo($attachment_id);

if ($success) {
    echo "Video queued for processing";
}
```

#### Process Queue (Manual)
```php
// Process 1 item
$result = $queue_manager->processQueue(batchSize: 1);

// Process up to 5 items
$result = $queue_manager->processQueue(batchSize: 5);

// Result contains:
// ['processed' => 2, 'failed' => 0, 'remaining' => 3]
```

#### Get Queue Status
```php
$status = $queue_manager->getQueueStatus();

// Returns:
// [
//     'total' => 5,
//     'queued' => 3,
//     'processing' => 1,
//     'complete' => 0,
//     'failed' => 1
// ]
```

#### Retry Failed Items
```php
// Retry all failed items
$requeued = $queue_manager->retryFailed();

// Retry specific item
$requeued = $queue_manager->retryFailed(attachmentId: 123);
```

#### Clear Queues
```php
// Clear main queue
$queue_manager->clearQueue();

// Clear failed queue
$queue_manager->clearFailedQueue();
```

#### Hook Integration
```php
// AJAX handler
add_action('wp_ajax_wp_mux_process_queue', [$queue_manager, 'ajaxProcessQueue']);

// Cron handler
add_action('wp_mux_process_queue_event', [$queue_manager, 'cronProcessQueue']);

// Heartbeat handler
add_filter('heartbeat_received', [$queue_manager, 'heartbeatProcessQueue'], 10, 2);
```

---

### 4. Wp_Mux_Validator - Data Validation

#### Validate Theme Data
```php
$result = Wp_Mux_Validator::validateThemeData([
    'name' => 'My Theme',
    'controls' => ['play', 'volume']
]);

if ($result === true) {
    // Valid
} else {
    // $result is array of errors
    foreach ($result as $field => $error) {
        echo "{$field}: {$error}";
    }
}
```

#### Validate API Credentials
```php
$result = Wp_Mux_Validator::validateApiCredentials($token, $secret);

if ($result !== true) {
    // Invalid credentials
    echo "Errors: " . implode(', ', $result);
}
```

#### Validate Video File
```php
$result = Wp_Mux_Validator::validateVideoFile(
    filePath: '/path/to/video.mp4',
    maxSize: 100 * 1024 * 1024 // 100MB
);

if ($result === true) {
    // Valid video file
} else {
    // Handle errors
}
```

#### Quick Validations
```php
// Validate asset ID
if (!Wp_Mux_Validator::validateAssetId($asset_id)) {
    wp_send_json_error('Invalid asset ID');
}

// Validate playback ID
if (!Wp_Mux_Validator::validatePlaybackId($playback_id)) {
    wp_send_json_error('Invalid playback ID');
}

// Validate color
if (!Wp_Mux_Validator::validateColorHex('#FF5733')) {
    echo 'Invalid color';
}

// Validate URL
if (!Wp_Mux_Validator::validateUrl($url)) {
    echo 'Invalid URL';
}

// Validate video attachment
$result = Wp_Mux_Validator::validateVideoAttachment($attachment_id);
if ($result !== true) {
    echo "Error: {$result}"; // Returns error message string
}
```

#### Integer Range Validation
```php
// Ensure value is between 1 and 100, default to 10
$limit = Wp_Mux_Validator::validateIntRange(
    value: $_GET['limit'] ?? null,
    min: 1,
    max: 100,
    default: 10
);
```

---

### 5. Wp_Mux_Api - Enhanced with Caching

#### Get Videos with Cache
```php
$api = new Wp_Mux_Api();

// Use cache (15 min expiration)
$videos = $api->get_videos(limit: 25, page: 1);

// Bypass cache for fresh data
$videos = $api->get_videos(limit: 25, page: 1, bypass_cache: true);
```

#### Get Video Details with Cache
```php
// Use cache (1 hour expiration)
$video = $api->get_video(asset_id: 'abc123');

// Bypass cache
$video = $api->get_video(asset_id: 'abc123', bypass_cache: true);
```

#### Cache Invalidation
```php
// After video deletion
Wp_Mux_Utils::invalidateCache('wp_mux_videos_*');
Wp_Mux_Utils::invalidateCache('wp_mux_video_abc123');

// After account changes
Wp_Mux_Utils::invalidateCache('wp_mux_account_*');
```

---

## Migration Guide

### Old Code → New Code

#### AJAX Handler Migration
```php
// OLD: In Wp_Mux_Admin class
public function ajax_load_videos() {
    if (!wp_verify_nonce($_POST['nonce'], 'wp_mux_videos_nonce')) {
        wp_die(__('Security check failed', 'wp-mux'));
    }
    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions', 'wp-mux'));
    }

    $api = new Wp_Mux_Api();
    $videos = $api->get_videos();

    if (is_wp_error($videos)) {
        wp_send_json_error($videos->get_error_message());
    }

    wp_send_json_success($videos);
}

// NEW: In Wp_Mux_Admin_Ajax class
public function loadVideos(): void
{
    Wp_Mux_Utils::verifyAjaxRequest('wp_mux_videos_nonce');

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;

    $videos = Wp_Mux_Utils::getCachedOrFetch(
        cacheKey: "wp_mux_videos_page_{$page}",
        callback: fn() => $this->api->get_videos(25, $page),
        expiration: 15 * MINUTE_IN_SECONDS
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
```

#### Queue Processing Migration
```php
// OLD: Direct processing in Wp_Mux_Admin
public function process_video($attachment_id) {
    // 100+ lines of queue logic...
}

// NEW: Use queue manager
$queue_manager = new Wp_Mux_Queue_Manager();
$queue_manager->queueVideo($attachment_id);

// Processing happens automatically via:
// - Cron job
// - AJAX request
// - Heartbeat API
```

---

## Performance Tips

### 1. Use Caching Strategically
```php
// For frequently accessed data
$videos = Wp_Mux_Utils::getCachedOrFetch(
    cacheKey: 'wp_mux_popular_videos',
    callback: fn() => $this->getPopularVideos(),
    expiration: HOUR_IN_SECONDS
);
```

### 2. Batch Process Queue Items
```php
// Process multiple items in cron
$result = $queue_manager->processQueue(batchSize: 5);
```

### 3. Invalidate Specific Caches Only
```php
// Instead of clearing everything:
Wp_Mux_Utils::invalidateCache('wp_mux_*'); // ❌

// Be specific:
Wp_Mux_Utils::invalidateCache("wp_mux_video_{$asset_id}"); // ✅
```

### 4. Use Priority Queue
```php
// Queue manager automatically prioritizes recent uploads
// You can extend priority calculation in calculatePriority() method
```

---

## Testing Examples

### Unit Test for Validator
```php
public function test_validate_asset_id() {
    $this->assertTrue(Wp_Mux_Validator::validateAssetId('abc123'));
    $this->assertTrue(Wp_Mux_Validator::validateAssetId('abc-123'));
    $this->assertFalse(Wp_Mux_Validator::validateAssetId(''));
    $this->assertFalse(Wp_Mux_Validator::validateAssetId('abc@123'));
}
```

### Integration Test for Queue
```php
public function test_queue_video_processing() {
    $attachment_id = $this->create_test_video();
    $queue_manager = new Wp_Mux_Queue_Manager();

    $result = $queue_manager->queueVideo($attachment_id);
    $this->assertTrue($result);

    $status = $queue_manager->getQueueStatus();
    $this->assertEquals(1, $status['total']);
}
```

---

## Common Patterns

### AJAX Endpoint Pattern
```php
public function myCustomAjax(): void
{
    Wp_Mux_Utils::verifyAjaxRequest('my_nonce_key');

    // Sanitize input
    $id = absint($_POST['id'] ?? 0);

    // Validate
    if ($id === 0) {
        wp_send_json_error(
            Wp_Mux_Utils::errorResponse(__('Invalid ID', 'wp-mux'))
        );
    }

    // Process
    $result = $this->doSomething($id);

    // Return
    wp_send_json_success(
        Wp_Mux_Utils::successResponse($result)
    );
}
```

### Cached API Call Pattern
```php
$data = Wp_Mux_Utils::getCachedOrFetch(
    cacheKey: "my_data_{$id}",
    callback: fn() => $this->fetchData($id),
    expiration: 30 * MINUTE_IN_SECONDS
);
```

### Validation Pattern
```php
$errors = [];
$sanitized = Wp_Mux_Utils::sanitizeAndValidate($data, $rules, $errors);

if (!empty($errors)) {
    wp_send_json_error(
        Wp_Mux_Utils::errorResponse(
            'Validation failed',
            400,
            ['errors' => $errors]
        )
    );
}
```

---

*Last Updated: November 11, 2025*
*Version: 1.3.0-dev*
