<?php

declare(strict_types=1);

/**
 * Template functions for themes and plugins.
 *
 *   wp_mux_player( $attachment_id, $args )  a video attachment as a player
 *   wp_mux_is_mux_video( $attachment_id )   whether it plays from Mux
 *   wp_mux_get_playback_id( $attachment_id )
 *
 * @link       https://wpmux.com
 * @since      1.3.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

if ( ! function_exists( 'wp_mux_get_playback_id' ) ) {
	/**
	 * The Mux playback ID of a video attachment that is ready on Mux, or ''.
	 *
	 * A local upload sent to Mux keeps its own MIME type until Mux has finished
	 * with it; it counts once its status is complete.
	 *
	 * @since 1.3.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	function wp_mux_get_playback_id( $attachment_id ): string {
		$attachment_id = (int) $attachment_id;
		$playback_id   = $attachment_id ? (string) get_post_meta( $attachment_id, '_mux_playback_id', true ) : '';

		if ( '' === $playback_id ) {
			return '';
		}

		$ready = 'video/mux' === get_post_mime_type( $attachment_id )
			|| 'complete' === get_post_meta( $attachment_id, '_wp_mux_status', true );

		return $ready ? $playback_id : '';
	}
}

if ( ! function_exists( 'wp_mux_is_mux_video' ) ) {
	/**
	 * Whether a video attachment plays from Mux.
	 *
	 * @since 1.3.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	function wp_mux_is_mux_video( $attachment_id ): bool {
		return '' !== wp_mux_get_playback_id( $attachment_id );
	}
}

if ( ! function_exists( 'wp_mux_player' ) ) {
	/**
	 * A video attachment as a player: <mux-player> when it is on Mux, <video>
	 * for a local file (e.g. one Mux hasn't finished processing yet), '' when
	 * it isn't a video. The Mux Player script is enqueued as needed.
	 *
	 *     echo wp_mux_player( $video_id, [ 'background' => true, 'poster' => $image_id ] );
	 *
	 * @since 1.3.0
	 *
	 * @param int   $attachment_id Video attachment ID.
	 * @param array $args          See Wp_Mux_Public::render_player(): controls, autoplay,
	 *                             muted, loop, preload, poster, fit, class, style, width,
	 *                             height, background, pause_button, pause_button_class.
	 * @return string
	 */
	function wp_mux_player( $attachment_id, array $args = array() ): string {
		static $renderer = null;

		$attachment_id = (int) $attachment_id;
		$type          = $attachment_id ? (string) get_post_mime_type( $attachment_id ) : '';

		if ( 0 !== strpos( $type, 'video/' ) ) {
			return '';
		}

		$playback_id = wp_mux_get_playback_id( $attachment_id );

		if ( '' !== $playback_id ) {
			$source = array( 'playback_id' => $playback_id, 'attachment_id' => $attachment_id );
		} elseif ( 'video/mux' !== $type && ( $src = wp_get_attachment_url( $attachment_id ) ) ) {
			$source = array( 'src' => $src, 'type' => $type, 'attachment_id' => $attachment_id );
		} else {
			return '';
		}

		if ( null === $renderer ) {
			$renderer = new Wp_Mux_Public( 'wp-mux', WP_MUX_VERSION );
		}

		return $renderer->render_player( $source, $args );
	}
}
