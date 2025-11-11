<?php

declare(strict_types=1);

/**
 * Validation utilities for WP Mux plugin.
 *
 * Provides standardized validation methods for theme data, API credentials,
 * and other plugin data to ensure consistency and reduce duplication.
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.3.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

/**
 * WP Mux validator class.
 *
 * Static validation methods for common operations.
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 * @author     Codi Lechasseur <codilechasseur@gmail.com>
 */
class Wp_Mux_Validator
{
    /**
     * Validate theme data.
     *
     * @since 1.3.0
     *
     * @param array $data Theme data to validate.
     *
     * @return true|array True if valid, array of errors otherwise.
     */
    public static function validateThemeData(array $data): true|array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = __('Theme name is required', 'wp-mux');
        }

        if (!isset($data['controls'])) {
            $errors['controls'] = __('Theme controls must be specified', 'wp-mux');
        }

        if (isset($data['name']) && strlen($data['name']) > 100) {
            $errors['name'] = __('Theme name must be 100 characters or less', 'wp-mux');
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Validate API credentials.
     *
     * @since 1.3.0
     *
     * @param string $token  API token.
     * @param string $secret API secret.
     *
     * @return true|array True if valid, array of errors otherwise.
     */
    public static function validateApiCredentials(string $token, string $secret): true|array
    {
        $errors = [];

        if (empty($token)) {
            $errors['token'] = __('MUX Token ID is required', 'wp-mux');
        }

        if (empty($secret)) {
            $errors['secret'] = __('MUX Token Secret is required', 'wp-mux');
        }

        // Basic format validation (MUX tokens are typically alphanumeric)
        if (!empty($token) && !preg_match('/^[a-zA-Z0-9-_]+$/', $token)) {
            $errors['token'] = __('Invalid MUX Token ID format', 'wp-mux');
        }

        if (!empty($secret) && !preg_match('/^[a-zA-Z0-9-_+\/=]+$/', $secret)) {
            $errors['secret'] = __('Invalid MUX Token Secret format', 'wp-mux');
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Validate video file.
     *
     * @since 1.3.0
     *
     * @param string $filePath Path to video file.
     * @param int    $maxSize  Maximum file size in bytes (0 for no limit).
     *
     * @return true|array True if valid, array of errors otherwise.
     */
    public static function validateVideoFile(string $filePath, int $maxSize = 0): true|array
    {
        $errors = [];

        if (!file_exists($filePath)) {
            $errors['file'] = __('Video file not found', 'wp-mux');
            return $errors;
        }

        if (!is_readable($filePath)) {
            $errors['file'] = __('Video file is not readable', 'wp-mux');
            return $errors;
        }

        $fileSize = filesize($filePath);
        if ($maxSize > 0 && $fileSize > $maxSize) {
            $errors['size'] = sprintf(
                __('File size (%s) exceeds maximum allowed size (%s)', 'wp-mux'),
                Wp_Mux_Utils::formatBytes($fileSize),
                Wp_Mux_Utils::formatBytes($maxSize)
            );
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        if (!str_starts_with((string) $mimeType, 'video/')) {
            $errors['type'] = __('File is not a valid video', 'wp-mux');
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Validate asset ID.
     *
     * @since 1.3.0
     *
     * @param string $assetId Asset ID to validate.
     *
     * @return bool True if valid.
     */
    public static function validateAssetId(string $assetId): bool
    {
        // MUX asset IDs are typically alphanumeric with possible hyphens
        return !empty($assetId) && preg_match('/^[a-zA-Z0-9-_]+$/', $assetId) === 1;
    }

    /**
     * Validate playback ID.
     *
     * @since 1.3.0
     *
     * @param string $playbackId Playback ID to validate.
     *
     * @return bool True if valid.
     */
    public static function validatePlaybackId(string $playbackId): bool
    {
        // MUX playback IDs are typically alphanumeric
        return !empty($playbackId) && preg_match('/^[a-zA-Z0-9]+$/', $playbackId) === 1;
    }

    /**
     * Validate color hex code.
     *
     * @since 1.3.0
     *
     * @param string $color Color hex code.
     *
     * @return bool True if valid.
     */
    public static function validateColorHex(string $color): bool
    {
        return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color) === 1;
    }

    /**
     * Validate URL.
     *
     * @since 1.3.0
     *
     * @param string $url URL to validate.
     *
     * @return bool True if valid.
     */
    public static function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate email address.
     *
     * @since 1.3.0
     *
     * @param string $email Email to validate.
     *
     * @return bool True if valid.
     */
    public static function validateEmail(string $email): bool
    {
        return is_email($email) !== false;
    }

    /**
     * Validate attachment ID belongs to a video.
     *
     * @since 1.3.0
     *
     * @param int $attachmentId Attachment ID.
     *
     * @return true|string True if valid, error message otherwise.
     */
    public static function validateVideoAttachment(int $attachmentId): true|string
    {
        if ($attachmentId <= 0) {
            return __('Invalid attachment ID', 'wp-mux');
        }

        $post = get_post($attachmentId);
        if (!$post || $post->post_type !== 'attachment') {
            return __('Attachment not found', 'wp-mux');
        }

        $mimeType = get_post_mime_type($attachmentId);
        if (!str_starts_with((string) $mimeType, 'video/')) {
            return __('Attachment is not a video', 'wp-mux');
        }

        return true;
    }

    /**
     * Sanitize theme name.
     *
     * @since 1.3.0
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
     * Sanitize and validate integer in range.
     *
     * @since 1.3.0
     *
     * @param mixed $value Value to validate.
     * @param int   $min   Minimum value.
     * @param int   $max   Maximum value.
     * @param int   $default Default value if invalid.
     *
     * @return int Validated integer.
     */
    public static function validateIntRange(mixed $value, int $min, int $max, int $default): int
    {
        $intValue = filter_var($value, FILTER_VALIDATE_INT);

        if ($intValue === false) {
            return $default;
        }

        return max($min, min($max, $intValue));
    }
}
