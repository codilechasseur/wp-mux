<?php

declare(strict_types=1);
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
        // Filter REST API attachment responses for Gutenberg
        add_filter( 'rest_prepare_attachment', [ $this, 'rest_prepare_mux_attachment' ], 10, 3 );
        // Filter attachment image src for list view thumbnails
        add_filter( 'wp_get_attachment_image_src', [ $this, 'filter_attachment_image_src' ], 10, 4 );
        // Filter attachment metadata for thumbnails
        add_filter( 'wp_get_attachment_metadata', [ $this, 'filter_attachment_metadata' ], 10, 2 );
        // Filter attachment URL to return MUX stream URL instead of local file (for grid view)
        add_filter( 'wp_get_attachment_url', [ $this, 'filter_attachment_url' ], 10, 2 );
        // Display MUX player on attachment pages
        add_filter( 'prepend_attachment', [ $this, 'display_mux_player_on_attachment_page' ], 10, 1 );
        // Filter media row actions to fix "View" link
        add_filter( 'media_row_actions', [ $this, 'filter_media_row_actions' ], 10, 2 );
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

                        // Store initial settings from asset
                        $playback_policy = $asset['data']['playback_policy'][0] ?? 'public';
                        $mp4_support = $asset['data']['mp4_support'] ?? 'none';
                        $encoding_tier = $asset['data']['encoding_tier'] ?? 'baseline';

                        update_post_meta( $attach_id, '_mux_playback_policy', $playback_policy );
                        update_post_meta( $attach_id, '_mux_mp4_support', $mp4_support );
                        update_post_meta( $attach_id, '_mux_encoding_tier', $encoding_tier );
                        update_post_meta( $attach_id, '_mux_requires_signed_url', $playback_policy === 'signed' );

                        // Update thumbnail if available
                        if ( ! empty( $asset['data']['playback_ids'][0]['id'] ) ) {
                            $playback_id = $asset['data']['playback_ids'][0]['id'];
                            $thumbnail_url = "https://image.mux.com/{$playback_id}/thumbnail.jpg?width=240&height=160";
                            update_post_meta( $attach_id, '_mux_thumbnail_url', $thumbnail_url );
                        }

                        // Generate auto-captions if enabled
                        if ( get_option( 'wp_mux_enable_auto_captions', false ) ) {
                            $language = get_option( 'wp_mux_default_caption_language', 'en' );
                            $caption_response = $api->generate_captions( $asset_id, $language );

                            if ( ! is_wp_error( $caption_response ) && isset( $caption_response['data']['id'] ) ) {
                                update_post_meta( $attach_id, '_mux_has_captions', true );
                                update_post_meta( $attach_id, '_mux_caption_track_ids', array( $caption_response['data']['id'] ) );
                            }
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
        // Use -1 to get all results in case of duplicates
        $existing = get_posts( array(
            'post_type'  => 'attachment',
            'post_status'=> 'inherit',
            'meta_key'   => '_mux_asset_id',
            'meta_value' => $mux_id,
            'fields'     => 'ids',
            'posts_per_page' => -1,
        ) );

        // If any attachments exist with this asset ID, don't create another
        if ( !empty($existing) ) {
            // If there are duplicates, log it for debugging
            if ( count($existing) > 1 ) {
            }
            return;
        }

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

        // Generate a friendly title for the imported video
        $video_title = 'MUX Video';
        if ( ! empty( $asset['created_at'] ) ) {
            $created_date = gmdate( 'Y-m-d H:i', strtotime( $asset['created_at'] ) );
            $video_title = 'MUX Video - ' . $created_date;
        }

        // Insert attachment post
        $attachment = array(
            'guid'           => $mux_id,
            'post_mime_type' => 'video/mux',
            'post_title'     => $video_title,
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
            update_post_meta( $attach_id, '_wp_mux_status', 'complete' );
            // Set post_date and post_date_gmt to MUX asset creation date
            if ( !empty($asset['created_at']) ) {
                global $wpdb;
                // $asset['created_at'] is already a Unix timestamp, use it directly
                $timestamp = is_numeric($asset['created_at']) ? intval($asset['created_at']) : strtotime($asset['created_at']);
                $gmt = gmdate( 'Y-m-d H:i:s', $timestamp );
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
        // Check if this is a MUX video by mime type OR by having MUX metadata
        $is_mux_video = ( $attachment->post_mime_type === 'video/mux' );
        $mux_status = get_post_meta( $attachment->ID, '_wp_mux_status', true );
        $playback_id = get_post_meta( $attachment->ID, '_mux_playback_id', true );

        // Also treat regular videos with MUX status as MUX videos
        if ( ! $is_mux_video && $mux_status && $playback_id ) {
            $is_mux_video = true;
        }

        if ( $is_mux_video ) {
            $response['type'] = 'video';
            $response['subtype'] = 'mux';
            $response['mime_type'] = 'video/mux'; // Ensure mime_type is set for JS compatibility

            // Get MUX metadata
            $thumbnail_url = get_post_meta( $attachment->ID, '_mux_thumbnail_url', true );
            $duration = get_post_meta( $attachment->ID, '_mux_duration', true );
            $stream_url = $this->get_mux_stream_url( $attachment->ID );

            // Set icon and image for list view thumbnails
            $response['icon'] = $thumbnail_url;
            $response['image'] = array(
                'src' => $thumbnail_url,
                'width' => 640,
                'height' => 360
            );

            // Add sizes array for different thumbnail sizes used in list and grid views
            $response['sizes'] = array(
                'thumbnail' => array(
                    'url' => $thumbnail_url,
                    'width' => 150,
                    'height' => 150,
                    'orientation' => 'landscape'
                ),
                'medium' => array(
                    'url' => $thumbnail_url,
                    'width' => 300,
                    'height' => 300,
                    'orientation' => 'landscape'
                ),
                'full' => array(
                    'url' => $thumbnail_url,
                    'width' => 640,
                    'height' => 360,
                    'orientation' => 'landscape'
                )
            );

            // Add MUX-specific properties
            $response['mux'] = true;
            $response['playback_id'] = $playback_id;
            $response['mux_playback_id'] = $playback_id; // Also add with this key for JS compatibility
            $response['duration'] = $duration;
            $response['created_at'] = get_post_meta( $attachment->ID, '_mux_created_at', true );
            $response['mux_status'] = $mux_status;

            // Set filename to empty to hide it (no local file exists)
            $response['filename'] = '';
            $response['file'] = '';

            // Override URLs - provide MUX stream URL for Gutenberg blocks
            $response['editLink'] = get_edit_post_link( $attachment->ID, 'raw' );
            $response['link'] = ''; // Remove "View" link (no local file exists)
            // Set URL to MUX stream so Gutenberg blocks can use it
            $response['url'] = $stream_url;
        }
        return $response;
    }

    /**
     * Prepare MUX attachment for REST API (for Gutenberg editor)
     */
    public function rest_prepare_mux_attachment( $response, $attachment, $request ) {
        // Check if this is a MUX video by mime type OR by having MUX metadata
        $is_mux_video = ( $attachment->post_mime_type === 'video/mux' );
        $mux_status = get_post_meta( $attachment->ID, '_wp_mux_status', true );
        $playback_id = get_post_meta( $attachment->ID, '_mux_playback_id', true );

        // Also treat regular videos with MUX status as MUX videos
        if ( ! $is_mux_video && $mux_status && $playback_id ) {
            $is_mux_video = true;
        }

        if ( $is_mux_video ) {
            $data = $response->get_data();

            // Get MUX metadata
            $thumbnail_url = get_post_meta( $attachment->ID, '_mux_thumbnail_url', true );
            $duration = get_post_meta( $attachment->ID, '_mux_duration', true );
            $stream_url = $this->get_mux_stream_url( $attachment->ID );

            // Update mime type
            $data['mime_type'] = 'video/mux';
            $data['subtype'] = 'mux';

            // Add MUX-specific fields
            $data['mux'] = true;
            $data['mux_status'] = $mux_status;
            $data['mux_playback_id'] = $playback_id;
            $data['playback_id'] = $playback_id;
            $data['duration'] = $duration;
            $data['thumbnail_url'] = $thumbnail_url;

            // Update media details
            if ( $thumbnail_url ) {
                $data['media_details']['sizes'] = array(
                    'thumbnail' => array(
                        'file' => $thumbnail_url,
                        'width' => 150,
                        'height' => 150,
                        'mime_type' => 'image/jpeg',
                        'source_url' => $thumbnail_url,
                    ),
                    'medium' => array(
                        'file' => $thumbnail_url,
                        'width' => 300,
                        'height' => 300,
                        'mime_type' => 'image/jpeg',
                        'source_url' => $thumbnail_url,
                    ),
                    'full' => array(
                        'file' => $thumbnail_url,
                        'width' => 640,
                        'height' => 360,
                        'mime_type' => 'image/jpeg',
                        'source_url' => $thumbnail_url,
                    ),
                );
            }

            // Update source URL to MUX stream
            if ( $stream_url ) {
                $data['source_url'] = $stream_url;
                // Also add as 'url' for compatibility
                $data['url'] = $stream_url;
            }

            $response->set_data( $data );
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

    /**
     * Filter attachment image src to provide MUX thumbnails for list view
     */
    public function filter_attachment_image_src( $image, $attachment_id, $size, $icon ) {
        $attachment = get_post( $attachment_id );

        if ( $attachment && $attachment->post_mime_type === 'video/mux' ) {
            $thumbnail_url = get_post_meta( $attachment_id, '_mux_thumbnail_url', true );

            if ( $thumbnail_url ) {
                // Return array with thumbnail URL and dimensions
                // WordPress expects: [ url, width, height, is_intermediate ]
                return array(
                    $thumbnail_url,
                    150, // width
                    150, // height
                    false // is_intermediate
                );
            }
        }

        return $image;
    }

    /**
     * Filter attachment metadata to include MUX thumbnail info
     */
    public function filter_attachment_metadata( $data, $attachment_id ) {
        $attachment = get_post( $attachment_id );

        if ( $attachment && $attachment->post_mime_type === 'video/mux' ) {
            $thumbnail_url = get_post_meta( $attachment_id, '_mux_thumbnail_url', true );

            if ( $thumbnail_url && is_array( $data ) ) {
                // Add thumbnail to metadata
                if ( ! isset( $data['sizes'] ) ) {
                    $data['sizes'] = array();
                }

                $data['sizes']['thumbnail'] = array(
                    'file' => $thumbnail_url,
                    'width' => 150,
                    'height' => 150,
                    'mime-type' => 'image/jpeg'
                );

                $data['sizes']['medium'] = array(
                    'file' => $thumbnail_url,
                    'width' => 300,
                    'height' => 300,
                    'mime-type' => 'image/jpeg'
                );

                $data['sizes']['full'] = array(
                    'file' => $thumbnail_url,
                    'width' => 640,
                    'height' => 360,
                    'mime-type' => 'image/jpeg'
                );
            }
        }


        return $data;
    }

    /**
     * Filter attachment URL to return MUX stream URL instead of local file
     * This prevents WordPress from showing broken file URLs
     */
    public function filter_attachment_url( $url, $attachment_id ) {
        $attachment = get_post( $attachment_id );

        if ( $attachment && $attachment->post_mime_type === 'video/mux' ) {
            // Return MUX stream URL so blocks can use it
            return $this->get_mux_stream_url( $attachment_id );
        }

        return $url;
    }

    /**
     * Display MUX player on attachment pages for video/mux attachments
     * This filter is called by WordPress to display attachment content
     */
    public function display_mux_player_on_attachment_page( $content ) {
        global $post;

        // Only process if this is a MUX video attachment
        if ( ! $post || $post->post_mime_type !== 'video/mux' ) {
            return $content;
        }

        // Get the playback ID
        $playback_id = get_post_meta( $post->ID, '_mux_playback_id', true );
        if ( ! $playback_id ) {
            return $content;
        }

        // Get thumbnail for poster
        $thumbnail_url = get_post_meta( $post->ID, '_mux_thumbnail_url', true );

        // Build MUX player HTML
        $mux_player = sprintf(
            '<div class="mux-attachment-player" style="max-width: 100%%; margin: 0 auto;">
                <mux-player
                    playback-id="%s"
                    controls
                    stream-type="on-demand"
                    %s
                    style="width: 100%%; max-width: 100%%;">
                </mux-player>
            </div>',
            esc_attr( $playback_id ),
            $thumbnail_url ? 'poster="' . esc_attr( $thumbnail_url ) . '"' : ''
        );

        return $mux_player;
    }

    /**
     * Filter media row actions to remove "View" link for MUX videos
     * Since local files don't exist, the View link would just 404
     */
    public function filter_media_row_actions( $actions, $post ) {
        // Only process MUX videos
        if ( $post->post_mime_type !== 'video/mux' ) {
            return $actions;
        }

        // Remove the "View" action since there's no local file
        unset( $actions['view'] );

        return $actions;
    }
}
