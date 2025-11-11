<?php

/**
 * Provide an admin area view for managing MUX videos
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.0.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/admin/partials
 */

// Include the API class
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'includes/class-wp-mux-api.php';
$api = new Wp_Mux_Api();

// Get account stats if API is configured
$account_stats = null;
if ( $api->is_configured() ) {
	$account_stats = $api->get_account_stats();
}
?>

<div class="wrap wp-mux-videos-page">
    <h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php if ( ! $api->is_configured() ) : ?>
        <div class="notice notice-warning">
            <p>
                <?php _e( 'MUX API credentials are not configured.', 'wp-mux' ); ?>
                <a href="<?php echo admin_url( 'admin.php?page=wp-mux-settings' ); ?>"><?php _e( 'Configure them here', 'wp-mux' ); ?></a>
            </p>
        </div>
    <?php else : ?>

        <a href="#" id="upload-new-video" class="page-title-action"><?php _e( 'Upload New Video', 'wp-mux' ); ?></a>

        <hr class="wp-header-end">

        <div class="wp-mux-layout">
            <div class="wp-mux-main">

        <!-- Upload Section -->
        <div id="upload-section" class="wp-mux-upload-section" style="display: none;">
            <div class="wp-mux-upload-box">
                <h2><?php _e( 'Upload Video to MUX', 'wp-mux' ); ?></h2>
                <div id="upload-dropzone" class="wp-mux-dropzone">
                    <div class="wp-mux-dropzone-content">
                        <span class="dashicons dashicons-cloud-upload"></span>
                        <p><?php _e( 'Drop your video file here or click to select', 'wp-mux' ); ?></p>
                        <p class="description"><?php _e( 'Supported formats: MP4, MOV, AVI, WMV, and more', 'wp-mux' ); ?></p>
                    </div>
                    <input type="file" id="video-file-input" accept="video/*" style="display: none;">
                </div>

                <div id="upload-progress" class="wp-mux-upload-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <p class="progress-text">0%</p>
                </div>

                <div id="upload-success" class="notice notice-success" style="display: none;">
                    <p><?php _e( 'Video uploaded successfully!', 'wp-mux' ); ?></p>
                </div>

                <div id="upload-error" class="notice notice-error" style="display: none;">
                    <p></p>
                </div>
            </div>
        </div>

        <!-- Videos List -->
        <div class="wp-mux-videos-container">
            <div id="videos-loading" class="wp-mux-loading">
                <span class="spinner is-active"></span>
                <p><?php _e( 'Loading videos...', 'wp-mux' ); ?></p>
            </div>

            <div id="videos-error" class="notice notice-error" style="display: none;">
                <p></p>
            </div>

            <div id="videos-list" class="wp-mux-videos-list" style="display: none;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-thumbnail"><?php _e( 'Video', 'wp-mux' ); ?></th>
                            <th scope="col" class="manage-column column-status"><?php _e( 'Status', 'wp-mux' ); ?></th>
                            <th scope="col" class="manage-column column-duration"><?php _e( 'Duration', 'wp-mux' ); ?></th>
                            <th scope="col" class="manage-column column-created"><?php _e( 'Created', 'wp-mux' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="videos-table-body">
                        <!-- Videos will be loaded here via AJAX -->
                    </tbody>
                </table>

                <div id="videos-pagination" class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num" id="videos-count">0 items</span>
                        <span class="pagination-links">
                            <button id="prev-page" class="button" disabled><?php _e( 'Previous', 'wp-mux' ); ?></button>
                            <span class="paging-input">
                                <input class="current-page" id="current-page" type="number" value="1" size="1" aria-describedby="table-paging">
                                <span class="tablenav-paging-text"> of <span class="total-pages" id="total-pages">1</span></span>
                            </span>
                            <button id="next-page" class="button" disabled><?php _e( 'Next', 'wp-mux' ); ?></button>
                        </span>
                    </div>
                </div>
            </div>

            <div id="no-videos" class="wp-mux-no-videos" style="display: none;">
                <div class="wp-mux-empty-state">
                    <div class="empty-state-icon">
                        <span class="dashicons dashicons-video-alt3"></span>
                    </div>
                    <h3><?php _e( 'No videos found', 'wp-mux' ); ?></h3>
                    <p><?php _e( 'Upload your first video to get started with MUX.', 'wp-mux' ); ?></p>
                    <button class="button button-primary" onclick="document.getElementById('upload-new-video').click()">
                        <?php _e( 'Upload Video', 'wp-mux' ); ?>
                    </button>
                </div>
            </div>
            </div>

            </div><!-- .wp-mux-main -->

            <!-- Sidebar with Account Overview -->
            <?php if ( $account_stats && ! is_wp_error( $account_stats ) ) : ?>
            <div class="wp-mux-sidebar-panel">
                <div class="wp-mux-info-box wp-mux-account-stats-sidebar">
                    <div class="wp-mux-stats-header">
                        <h3><?php _e( 'Account Overview', 'wp-mux' ); ?></h3>
                    </div>

                    <div class="wp-mux-stats-sidebar-grid">
                        <div class="wp-mux-stat-item">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-video-alt3"></span>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo number_format( $account_stats['total_videos'] ); ?></div>
                                <div class="stat-label"><?php _e( 'Total Videos', 'wp-mux' ); ?></div>
                                <div class="stat-detail">
                                    <?php
                                    echo sprintf(
                                        __( '%d ready, %d processing', 'wp-mux' ),
                                        $account_stats['videos_ready'],
                                        $account_stats['videos_processing']
                                    );
                                    if ( $account_stats['videos_errored'] > 0 ) {
                                        echo sprintf( ', <span class="error-count">%d errors</span>', $account_stats['videos_errored'] );
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="wp-mux-stat-item">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-clock"></span>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value">
                                    <?php
                                    $total_seconds = (int) $account_stats['total_duration_seconds'];
                                    $hours = (int) floor( $total_seconds / 3600 );
                                    $minutes = (int) floor( ( $total_seconds % 3600 ) / 60 );
                                    if ( $hours > 0 ) {
                                        echo sprintf( '%d hr %d min', $hours, $minutes );
                                    } else {
                                        echo sprintf( '%d min', $minutes );
                                    }
                                    ?>
                                </div>
                                <div class="stat-label"><?php _e( 'Total Content', 'wp-mux' ); ?></div>
                                <div class="stat-detail"><?php _e( 'Combined duration', 'wp-mux' ); ?></div>
                            </div>
                        </div>

                        <div class="wp-mux-stat-item">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-database"></span>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value">
                                    <?php
                                    $gb = $account_stats['total_storage_bytes'] / ( 1024 * 1024 * 1024 );
                                    if ( $gb >= 1 ) {
                                        echo number_format( $gb, 1 ) . ' GB';
                                    } else {
                                        $mb = $account_stats['total_storage_bytes'] / ( 1024 * 1024 );
                                        echo number_format( $mb, 0 ) . ' MB';
                                    }
                                    ?>
                                </div>
                                <div class="stat-label"><?php _e( 'Storage', 'wp-mux' ); ?></div>
                                <div class="stat-detail"><?php _e( 'Estimated usage', 'wp-mux' ); ?></div>
                            </div>
                        </div>

                        <?php if ( isset( $account_stats['total_views_30d'] ) ) : ?>
                        <div class="wp-mux-stat-item">
                            <div class="stat-icon">
                                <span class="dashicons dashicons-visibility"></span>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo number_format( $account_stats['total_views_30d'] ); ?></div>
                                <div class="stat-label"><?php _e( 'Views (30d)', 'wp-mux' ); ?></div>
                                <div class="stat-detail"><?php _e( 'Last 30 days', 'wp-mux' ); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <a href="https://dashboard.mux.com/" target="_blank" class="button button-secondary wp-mux-dashboard-link">
                        <?php _e( 'View MUX Dashboard', 'wp-mux' ); ?>
                        <span class="dashicons dashicons-external"></span>
                    </a>
                </div>
            </div><!-- .wp-mux-sidebar-panel -->
            <?php endif; ?>

        </div><!-- .wp-mux-layout -->

    <?php endif; ?>
</div>

<!-- Video Preview Modal -->
<div id="video-preview-modal" class="wp-mux-modal" style="display: none;">
    <div class="wp-mux-modal-overlay"></div>
    <div class="wp-mux-modal-content">
        <div class="wp-mux-modal-header">
            <h3 id="modal-video-title">Video Preview</h3>
            <button class="wp-mux-modal-close">&times;</button>
        </div>
        <div class="wp-mux-modal-body">
            <div id="video-player-container">
                <!-- Video player will be inserted here -->
            </div>
            <div class="wp-mux-video-details">
                <div class="wp-mux-detail-row">
                    <strong>Asset ID:</strong> <span id="modal-asset-id">-</span>
                </div>
                <div class="wp-mux-detail-row">
                    <strong>Playback ID:</strong>
                    <span id="modal-playback-id">-</span>
                    <button id="copy-playback-url" class="button button-small" style="margin-left: 10px;">Copy URL</button>
                </div>
                <div class="wp-mux-detail-row">
                    <strong>Status:</strong> <span id="modal-status">-</span>
                </div>
                <div class="wp-mux-detail-row">
                    <strong>Duration:</strong> <span id="modal-duration">-</span>
                </div>
                <div class="wp-mux-detail-row">
                    <strong>Created:</strong> <span id="modal-created">-</span>
                </div>
            </div>
        </div>
        <div class="wp-mux-modal-footer">
            <button class="button button-primary" id="modal-close-btn">Close</button>
            <button class="button button-link-delete" id="modal-delete-btn">Delete Video</button>
        </div>
    </div>
</div>

<script type="text/javascript">
    // Pass data to JavaScript
    window.wpMuxData = {
        ajaxUrl: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
        uploadNonce: '<?php echo wp_create_nonce( 'wp_mux_upload_nonce' ); ?>',
        videosNonce: '<?php echo wp_create_nonce( 'wp_mux_videos_nonce' ); ?>',
        isConfigured: <?php echo $api->is_configured() ? 'true' : 'false'; ?>,
        strings: {
            uploadError: '<?php echo esc_js( __( 'Upload failed. Please try again.', 'wp-mux' ) ); ?>',
            deleteConfirm: '<?php echo esc_js( __( 'Are you sure you want to delete this video? This action cannot be undone.', 'wp-mux' ) ); ?>',
            loadingError: '<?php echo esc_js( __( 'Failed to load videos. Please try again.', 'wp-mux' ) ); ?>',
            deleteError: '<?php echo esc_js( __( 'Failed to delete video. Please try again.', 'wp-mux' ) ); ?>',
            processing: '<?php echo esc_js( __( 'Processing...', 'wp-mux' ) ); ?>',
            ready: '<?php echo esc_js( __( 'Ready', 'wp-mux' ) ); ?>',
            error: '<?php echo esc_js( __( 'Error', 'wp-mux' ) ); ?>'
        }
    };
</script>
