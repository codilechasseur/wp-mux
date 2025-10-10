<?php
/**
 * Handles syncing MUX assets into the WordPress Media Library as virtual attachments.
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

class Wp_Mux_Media_Sync {

    public function __construct() {
        // Sync MUX assets on admin init
        add_action( 'admin_init', [ $this, 'sync_mux_assets_to_media_library' ] );
        // Filter media library queries to include MUX assets
        add_filter( 'ajax_query_attachments_args', [ $this, 'filter_media_library_query' ] );
        // Render custom fields/thumbnails for MUX assets
        add_filter( 'wp_prepare_attachment_for_js', [ $this, 'prepare_mux_attachment_for_js' ], 10, 3 );
    }

    /**
     * Sync MUX assets as virtual attachments.
     */
    public function sync_mux_assets_to_media_library() {
        require_once plugin_dir_path( __FILE__ ) . 'class-wp-mux-api.php';
        $api = new Wp_Mux_Api();
        $mux_assets = $api->get_videos(100);
        if ( is_wp_error( $mux_assets ) || empty( $mux_assets['data'] ) ) {
            return;
        }
        foreach ( $mux_assets['data'] as $asset ) {
            $this->maybe_create_mux_attachment( $asset );
        }

        // Also update any placeholder attachments that are still processing
        $this->update_processing_attachments( $api );
    }

    /**
     * Update attachments that are still processing.
     */
    public function update_processing_attachments( $api ) {
        $processing_attachments = get_posts( array(
            'post_type'  => 'attachment',
            'post_status'=> 'inherit',
            'meta_key'   => '_mux_processing',
            'meta_value' => '1',
            'fields'     => 'ids',
            'posts_per_page' => 20,
        ) );

        foreach ( $processing_attachments as $attach_id ) {
            $upload_id = get_post_meta( $attach_id, '_mux_upload_id', true );
            if ( $upload_id ) {
                $upload_status = $api->get_upload( $upload_id );
                if ( ! is_wp_error( $upload_status ) && ! empty( $upload_status['data']['asset_id'] ) ) {
                    $asset_id = $upload_status['data']['asset_id'];
                    $asset = $api->get_video( $asset_id );
                    if ( ! is_wp_error( $asset ) && ! empty( $asset['data'] ) ) {
                        // Update the attachment with real MUX data
                        update_post_meta( $attach_id, '_mux_asset_id', $asset_id );
                        update_post_meta( $attach_id, '_mux_playback_id', $asset['data']['playback_ids'][0]['id'] ?? '' );
                        update_post_meta( $attach_id, '_mux_duration', $asset['data']['duration'] ?? '' );
                        update_post_meta( $attach_id, '_mux_created_at', $asset['data']['created_at'] ?? '' );

                        // Update thumbnail if available
                        if ( ! empty( $asset['data']['playback_ids'][0]['id'] ) ) {
                            $playback_id = $asset['data']['playback_ids'][0]['id'];
                            $thumbnail_url = "https://image.mux.com/{$playback_id}/thumbnail.jpg?width=240&height=160";
                            update_post_meta( $attach_id, '_mux_thumbnail_url', $thumbnail_url );
                        }

                        // Remove processing flags
                        delete_post_meta( $attach_id, '_mux_processing' );
                        delete_post_meta( $attach_id, '_mux_upload_id' );
                        delete_post_meta( $attach_id, '_mux_from_upload' );
                    }
                }
            }
        }
    }

    /**
     * Create a virtual attachment for a MUX asset if it doesn't exist.
     */
    public function maybe_create_mux_attachment( $asset ) {
        $mux_id = sanitize_text_field( $asset['id'] );

        // Check for existing attachment by _mux_asset_id meta
        $existing = get_posts( array(
            'post_type'  => 'attachment',
            'post_status'=> 'inherit',
            'meta_key'   => '_mux_asset_id',
            'meta_value' => $mux_id,
            'fields'     => 'ids',
            'posts_per_page' => 1,
        ) );
        if ( !empty($existing) ) return;

        // Also check for uploads that already have this asset ID or are processing
        // (prevents duplicates during upload->sync workflow)
        $existing_with_upload = get_posts( array(
            'post_type'  => 'attachment',
            'post_status'=> 'inherit',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'   => '_mux_processing',
                    'value' => '1',
                ),
                array(
                    'key'   => '_wp_mux_status',
                    'value' => array('queued', 'processing', 'complete'),
                    'compare' => 'IN'
                ),
                array(
                    'key'   => '_mux_upload_id',
                    'compare' => 'EXISTS'
                )
            ),
            'fields'     => 'ids',
            'posts_per_page' => 20,
        ) );

        // Check if any of these existing attachments might correspond to this asset
        foreach ($existing_with_upload as $attach_id) {
            $upload_id = get_post_meta( $attach_id, '_mux_upload_id', true );
            $existing_asset_id = get_post_meta( $attach_id, '_mux_asset_id', true );

            // If this attachment already has the same asset ID, skip
            if ($existing_asset_id === $mux_id) {
                return;
            }

            // If there's an upload in progress, check the asset via API to avoid duplicates
            if ($upload_id) {
                require_once plugin_dir_path( __FILE__ ) . 'class-wp-mux-api.php';
                $api = new Wp_Mux_Api();
                $upload_status = $api->get_upload( $upload_id );
                if ( ! is_wp_error( $upload_status ) &&
                     ! empty( $upload_status['data']['asset_id'] ) &&
                     $upload_status['data']['asset_id'] === $mux_id ) {
                    // This asset is from an upload we're tracking, skip creating virtual attachment
                    return;
                }
            }
        }

        // Insert attachment post
        $attachment = array(
            'guid'           => $mux_id,
            'post_mime_type' => 'video/mux',
            'post_title'     => $mux_id,
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_type'      => 'attachment',
        );
        $attach_id = wp_insert_attachment( $attachment, '', 0 );
        if ( $attach_id && ! is_wp_error( $attach_id ) ) {
            update_post_meta( $attach_id, '_mux_asset_id', $mux_id );
            update_post_meta( $attach_id, '_mux_playback_id', $asset['playback_ids'][0]['id'] ?? '' );
            update_post_meta( $attach_id, '_mux_thumbnail_url', $asset['thumbnail_url'] ?? '' );
            update_post_meta( $attach_id, '_mux_duration', $asset['duration'] ?? '' );
            update_post_meta( $attach_id, '_mux_created_at', $asset['created_at'] ?? '' );
            // Set post_date and post_date_gmt to MUX asset creation date
            if ( !empty($asset['created_at']) ) {
                global $wpdb;
                $gmt = gmdate( 'Y-m-d H:i:s', strtotime( $asset['created_at'] ) );
                $wpdb->update( $wpdb->posts, array( 'post_date' => $gmt, 'post_date_gmt' => $gmt ), array( 'ID' => $attach_id ) );
                if ( function_exists('clean_post_cache') ) clean_post_cache( $attach_id );
            }
        }
    }

    /**
     * Filter media library queries to include MUX assets.
     */
    public function filter_media_library_query( $query ) {
        // No-op: MUX assets are now real attachments, so they appear automatically
        return $query;
    }

    /**
     * Prepare MUX attachment for JS (media modal, etc).
     */
    public function prepare_mux_attachment_for_js( $response, $attachment, $meta ) {
        if ( $attachment->post_mime_type === 'video/mux' ) {
            $response['type'] = 'video';
            $response['subtype'] = 'mux';
            $response['icon'] = $response['image'] = get_post_meta( $attachment->ID, '_mux_thumbnail_url', true );
            $response['url'] = $this->get_mux_stream_url( $attachment->ID );
            $response['mux'] = true;
            $response['playback_id'] = get_post_meta( $attachment->ID, '_mux_playback_id', true );
            $duration = get_post_meta( $attachment->ID, '_mux_duration', true );
            $response['duration'] = $duration;
            $response['created_at'] = get_post_meta( $attachment->ID, '_mux_created_at', true );
            // Remove 'View media file' link by setting non-existent file URL
            $response['file'] = '';
        }
        return $response;
    }

    /**
     * Get the MUX stream URL for a given attachment ID.
     */
    public function get_mux_stream_url( $attach_id ) {
        $playback_id = get_post_meta( $attach_id, '_mux_playback_id', true );
        if ( $playback_id ) {
            // Return a special MUX URL that our frontend will recognize and convert
            return 'https://stream.mux.com/' . $playback_id . '.mp4?mux=1';
        }
        return '';
    }
}
