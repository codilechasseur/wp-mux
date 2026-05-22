<?php

declare(strict_types=1);

/**
 * Utility functions for WP Mux plugin.
 *
 * Provides common functionality used across the plugin to reduce code duplication.
 * Refactored with PHP 8.5 features and PSR-12 coding standards.
 *
 * @link       https://wpmux.com
 * @since      1.2.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

/**
 * WP Mux utility class.
 *
 * Static utility methods for common operations including AJAX verification,
 * response formatting, caching, and database optimizations.
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 * @author     Codi Lechasseur
 */
class Wp_Mux_Utils
{
    /**
     * Verify nonce and user capability for AJAX requests.
     *
     * Sends JSON error and dies if verification fails.
     *
     * @since 1.2.0
     *
     * @param string $nonceKey   The nonce key to verify.
     * @param string $capability Required user capability (default: 'manage_options').
     *
     * @return void Dies with JSON error if verification fails.
     */
    public static function verifyAjaxRequest(string $nonceKey, string $capability = 'manage_options'): void
    {
        // Verify nonce
        if (!check_ajax_referer($nonceKey, 'nonce', false)) {
            wp_send_json_error(
                self::errorResponse(
                    __('Security verification failed. Please refresh the page and try again.', 'wp-mux'),
                    403
                )
            );
        }

        // Verify user capability
        if (!current_user_can($capability)) {
            wp_send_json_error(
                self::errorResponse(
                    __('You do not have permission to perform this action.', 'wp-mux'),
                    403
                )
            );
        }
    }

    /**
     * Create standardized error response.
     *
     * @since 1.2.0
     *
     * @param string $message Error message.
     * @param int    $code    HTTP status code.
     * @param array  $data    Additional error data.
     *
     * @return array{success: false, message: string, code: int, data: array} Formatted error response.
     */
    public static function errorResponse(string $message, int $code = 400, array $data = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'code'    => $code,
            'data'    => $data,
        ];
    }

    /**
     * Create standardized success response.
     *
     * @since 1.2.0
     *
     * @param mixed  $data    Response data.
     * @param string $message Success message.
     *
     * @return array{success: true, data: mixed, message?: string} Formatted success response.
     */
    public static function successResponse(mixed $data = [], string $message = ''): array
    {
        $response = [
            'success' => true,
            'data'    => $data,
        ];

        if (!empty($message)) {
            $response['message'] = $message;
        }

        return $response;
    }

    /**
     * Get a cached value or fetch and cache it.
     *
     * Uses transients for caching with automatic cache invalidation.
     *
     * @since 1.2.0
     *
     * @param string   $cacheKey   Cache key.
     * @param callable $callback   Callback to fetch data if not cached.
     * @param int      $expiration Cache expiration in seconds.
     *
     * @return mixed Cached or freshly fetched data.
     */
    public static function getCachedOrFetch(string $cacheKey, callable $callback, int $expiration = HOUR_IN_SECONDS): mixed
    {
        $cached = get_transient($cacheKey);

        if (false !== $cached) {
            return $cached;
        }

        $result = $callback();

        // Only cache non-error results
        if (!is_wp_error($result)) {
            set_transient($cacheKey, $result, $expiration);
        }

        return $result;
    }

    /**
     * Invalidate cached data by pattern.
     *
     * Deletes all transients matching the given pattern (supports * wildcard).
     *
     * @since 1.2.0
     *
     * @param string $pattern Pattern to match (with wildcard *).
     *
     * @return void
     */
    public static function invalidateCache(string $pattern = 'wp_mux_*'): void
    {
        global $wpdb;

        $pattern = str_replace('*', '%', $pattern);
        $escapedPattern = $wpdb->esc_like($pattern);

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s
                AND (option_name LIKE %s
                OR option_name LIKE %s)",
                $pattern,
                "_transient_{$escapedPattern}",
                "_transient_timeout_{$escapedPattern}"
            )
        );
    }

    /**
     * Sanitize and validate theme name.
     *
     * @since 1.2.0
     *
     * @param string $name Theme name.
     *
     * @return string Sanitized theme name.
     */
    public static function sanitizeThemeName(string $name): string
    {
        return sanitize_text_field(trim($name));
    }

    /**
     * Create a slug from a name.
     *
     * @since 1.2.0
     *
     * @param string $name Name to slugify.
     *
     * @return string Slug.
     */
    public static function createSlug(string $name): string
    {
        return sanitize_title($name);
    }

    /**
     * Log debug message if WP_DEBUG is enabled.
     *
     * @since 1.2.0
     *
     * @param string $message Message to log.
     * @param string $level   Log level (info, warning, error).
     *
     * @return void
     */
    public static function log(string $message, string $level = 'info'): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $prefix = '[WP MUX ' . strtoupper($level) . ']';
        }
    }

    /**
     * Check if current request is an AJAX request.
     *
     * @since 1.2.0
     *
     * @return bool True if AJAX request.
     */
    public static function isAjax(): bool
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }

    /**
     * Check if current request is from admin area.
     *
     * @since 1.2.0
     *
     * @return bool True if admin request.
     */
    public static function isAdmin(): bool
    {
        return is_admin() && !self::isAjax();
    }

    /**
     * Format bytes to human-readable size.
     *
     * @since 1.2.0
     *
     * @param int $bytes     Bytes to format.
     * @param int $precision Decimal precision.
     *
     * @return string Formatted size string.
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Format seconds to human-readable duration.
     *
     * @since 1.2.0
     *
     * @param float $seconds Duration in seconds.
     *
     * @return string Formatted duration string (H:MM:SS or M:SS or 0:SS).
     */
    public static function formatDuration(float $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        $secs = (int) ($seconds % 60);

        return match (true) {
            $hours > 0 => sprintf('%d:%02d:%02d', $hours, $minutes, $secs),
            $minutes > 0 => sprintf('%d:%02d', $minutes, $secs),
            default => sprintf('0:%02d', $secs),
        };
    }

    /**
     * Batch update post meta for multiple posts.
     *
     * More efficient than multiple update_post_meta() calls.
     *
     * @since 1.2.0
     *
     * @param array<int, array<string, mixed>> $updates Array of [post_id => [meta_key => meta_value]].
     *
     * @return void
     */
    public static function batchUpdatePostMeta(array $updates): void
    {
        foreach ($updates as $postId => $metaData) {
            foreach ($metaData as $metaKey => $metaValue) {
                update_post_meta($postId, $metaKey, $metaValue);
            }
        }

        // Prime meta cache for all updated posts
        update_meta_cache('post', array_keys($updates));
    }

    /**
     * Get multiple post meta values in a single query.
     *
     * @since 1.2.0
     *
     * @param array<int> $postIds  Post IDs.
     * @param string     $metaKey  Meta key to fetch.
     * @param bool       $single   Return single value per post.
     *
     * @return array<int, mixed> Array of [post_id => meta_value].
     */
    public static function getMultiplePostMeta(array $postIds, string $metaKey, bool $single = true): array
    {
        if (empty($postIds)) {
            return [];
        }

        // Prime the cache
        update_meta_cache('post', $postIds);

        $results = [];
        foreach ($postIds as $postId) {
            $results[$postId] = get_post_meta($postId, $metaKey, $single);
        }

        return $results;
    }

    /**
     * Validate required fields in array.
     *
     * @since 1.2.0
     *
     * @param array<string, mixed> $data     Data to validate.
     * @param array<string>        $required Required field names.
     *
     * @return array<string> Array of missing field names (empty if all present).
     */
    public static function validateRequiredFields(array $data, array $required): array
    {
        return array_filter(
            $required,
            fn(string $field): bool => !isset($data[$field]) || empty($data[$field])
        );
    }

    /**
     * Retry a callable with exponential backoff.
     *
     * Useful for API calls or operations that might temporarily fail.
     *
     * @since 1.2.0
     *
     * @param callable $callback     Callback to execute.
     * @param int      $maxAttempts  Maximum number of attempts.
     * @param int      $initialDelay Initial delay in seconds.
     *
     * @return mixed Result of the callback.
     *
     * @throws \Exception If all attempts fail.
     */
    public static function retryWithBackoff(callable $callback, int $maxAttempts = 3, int $initialDelay = 1): mixed
    {
        $attempt = 1;
        $delay = $initialDelay;

        while ($attempt <= $maxAttempts) {
            try {
                return $callback();
            } catch (\Exception $e) {
                if ($attempt === $maxAttempts) {
                    throw $e;
                }

                self::log(
                    sprintf(
                        'Attempt %d/%d failed: %s. Retrying in %d seconds...',
                        $attempt,
                        $maxAttempts,
                        $e->getMessage(),
                        $delay
                    ),
                    'warning'
                );

                sleep($delay);
                $delay *= 2; // Exponential backoff
                $attempt++;
            }
        }

        throw new \Exception('Retry logic failed unexpectedly');
    }

    /**
     * Sanitize and validate an array of data.
     *
     * @since 1.2.0
     *
     * @param array<string, mixed>                      $data      Data to sanitize.
     * @param array<string, array{type: string, ...}>   $rules     Validation rules.
     * @param array<string, string>                     &$errors   Array to populate with errors.
     *
     * @return array<string, mixed> Sanitized data.
     */
    public static function sanitizeAndValidate(array $data, array $rules, array &$errors = []): array
    {
        $sanitized = [];
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $type = $rule['type'] ?? 'string';
            $required = $rule['required'] ?? false;

            // Check required
            if ($required && ($value === null || $value === '')) {
                $errors[$field] = sprintf(__('%s is required', 'wp-mux'), ucfirst($field));
                continue;
            }

            // Skip validation if empty and not required
            if ($value === null || $value === '') {
                continue;
            }

            // Sanitize based on type
            $sanitized[$field] = match ($type) {
                'email' => sanitize_email($value),
                'url' => esc_url_raw($value),
                'int' => (int) $value,
                'float' => (float) $value,
                'bool' => (bool) $value,
                'text' => sanitize_text_field($value),
                'textarea' => sanitize_textarea_field($value),
                'slug' => sanitize_title($value),
                'key' => sanitize_key($value),
                'html' => wp_kses_post($value),
                default => sanitize_text_field($value),
            };

            // Additional validation
            if (isset($rule['min']) && is_numeric($sanitized[$field]) && $sanitized[$field] < $rule['min']) {
                $errors[$field] = sprintf(
                    __('%s must be at least %s', 'wp-mux'),
                    ucfirst($field),
                    $rule['min']
                );
            }

            if (isset($rule['max']) && is_numeric($sanitized[$field]) && $sanitized[$field] > $rule['max']) {
                $errors[$field] = sprintf(
                    __('%s must be no more than %s', 'wp-mux'),
                    ucfirst($field),
                    $rule['max']
                );
            }

            if (isset($rule['pattern']) && !preg_match($rule['pattern'], (string) $sanitized[$field])) {
                $errors[$field] = sprintf(__('%s has an invalid format', 'wp-mux'), ucfirst($field));
            }
        }

        return $sanitized;
    }

    /**
     * Generate a signed Mux playback JWT (RS256) for a given playback ID.
     *
     * Falls back to returning an unsigned plain `https://stream.mux.com/{id}.m3u8`
     * URL when signing keys are not configured, so the player still works on
     * public assets.
     *
     * @since 1.2.0
     *
     * @param string $playback_id  Mux playback ID.
     * @param string $type         Audience type: 'video', 'thumbnail', 'storyboard'. Default 'video'.
     * @param int    $expiry       Token lifetime in seconds from now. Default 4 hours.
     *
     * @return string Signed JWT token, or empty string if signing keys unavailable.
     */
    public static function generateMuxJwt(string $playback_id, string $type = 'video', int $expiry = 14400): string
    {
        $key_id      = get_option('wp_mux_signing_key_id', '');
        $private_b64 = get_option('wp_mux_signing_key_private', '');

        if (empty($key_id) || empty($private_b64)) {
            return '';
        }

        $private_pem = base64_decode($private_b64, true);
        if ($private_pem === false || empty($private_pem)) {
            return '';
        }

        $audience_map = [
            'video'      => 'v',
            'thumbnail'  => 't',
            'storyboard' => 's',
            'gif'        => 'g',
        ];
        $aud = $audience_map[$type] ?? 'v';

        $header  = json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $key_id]);
        $payload = json_encode([
            'sub' => $playback_id,
            'aud' => $aud,
            'exp' => time() + $expiry,
        ]);

        if ($header === false || $payload === false) {
            return '';
        }

        $b64_header  = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $b64_payload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signing_input = $b64_header . '.' . $b64_payload;

        $pkey = openssl_pkey_get_private($private_pem);
        if ($pkey === false) {
            return '';
        }

        $signature = '';
        if (!openssl_sign($signing_input, $signature, $pkey, OPENSSL_ALGO_SHA256)) {
            return '';
        }

        $b64_sig = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $signing_input . '.' . $b64_sig;
    }
}
