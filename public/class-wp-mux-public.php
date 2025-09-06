<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.0.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/public
 * @author     Codi Lechasseur <codilechasseur@gmail.com>
 */
class Wp_Mux_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() method
		 * defined in Wp_Mux_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wp_Mux_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-mux-public.css', array(), $this->version, 'all' );

	}	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wp_Mux_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wp_Mux_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		// Enqueue MUX player element for video playback
		wp_enqueue_script( 'mux-player', 'https://unpkg.com/@mux/mux-player', array(), null, true );

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-mux-public.js', array( 'jquery', 'mux-player' ), $this->version, true );

	}

	/**
	 * Handle video rendering for MUX videos
	 */
	public function render_mux_video( $html, $url, $attr ) {
		// Debug logging
		error_log( 'WP MUX: render_mux_video called with URL: ' . $url );

		// Check if this is a MUX stream URL (both .m3u8 and .mp4, or has mux=1 parameter)
		if ( strpos( $url, 'stream.mux.com' ) !== false &&
			 ( strpos( $url, '.m3u8' ) !== false ||
			   strpos( $url, '.mp4' ) !== false ||
			   strpos( $url, 'mux=1' ) !== false ) ) {

			error_log( 'WP MUX: Processing MUX video URL' );

			// Extract playback ID from the URL
			$parsed_url = parse_url( $url );
			$path = $parsed_url['path'];
			$playback_id = basename( $path, '.m3u8' );
			$playback_id = basename( $playback_id, '.mp4' );

			error_log( 'WP MUX: Extracted playback ID: ' . $playback_id );

			$poster = isset( $attr['poster'] ) ? $attr['poster'] : '';
			
			// If no poster is provided, generate thumbnail URL from MUX
			if ( empty( $poster ) ) {
				$poster = "https://image.mux.com/{$playback_id}/thumbnail.jpg?width=640&height=360";
			}
			
			$controls = isset( $attr['controls'] ) ? 'controls' : 'controls';
			$width = isset( $attr['width'] ) ? $attr['width'] : '';
			$height = isset( $attr['height'] ) ? $attr['height'] : '';

			$style = '';
			if ( $width ) {
				$style .= 'width: ' . esc_attr( $width ) . ( is_numeric( $width ) ? 'px' : '' ) . ';';
			}
			if ( $height ) {
				$style .= 'height: ' . esc_attr( $height ) . ( is_numeric( $height ) ? 'px' : '' ) . ';';
			}

			// Use mux-player element for optimal video playback
			$html = sprintf(
				'<mux-player playback-id="%s" %s poster="%s" style="%s" stream-type="on-demand">
					<p>%s</p>
				</mux-player>',
				esc_attr( $playback_id ),
				$controls,
				esc_attr( $poster ),
				esc_attr( $style ),
				__( 'Your browser does not support HTML5 video.', 'wp-mux' )
			);

			error_log( 'WP MUX: Generated HTML: ' . $html );
		}

		return $html;
	}

	/**
	 * Process content for MUX videos
	 */
	public function process_content_videos( $content ) {
		error_log( 'WP MUX: process_content_videos called' );

		// Look for video elements with MUX URLs (both .m3u8 and .mp4)
		$pattern = '/<video[^>]*src=["\']([^"\']*stream\.mux\.com[^"\']*\.(m3u8|mp4)[^"\']*)["\']/i';

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			error_log( 'WP MUX: Found ' . count( $matches ) . ' MUX videos in content' );

			foreach ( $matches as $match ) {
				$full_match = $match[0];
				$url = $match[1];

				error_log( 'WP MUX: Processing video with URL: ' . $url );

				// Extract playback ID
				$path = parse_url( $url, PHP_URL_PATH );
				$playback_id = basename( $path, '.m3u8' );
				$playback_id = basename( $playback_id, '.mp4' );

				// Generate thumbnail URL
				$thumbnail_url = "https://image.mux.com/{$playback_id}/thumbnail.jpg?width=640&height=360";

				// Replace the video element with mux-player
				$mux_video = '<mux-player playback-id="' . esc_attr( $playback_id ) . '" controls stream-type="on-demand" poster="' . esc_attr( $thumbnail_url ) . '">';
				$replacement = str_replace( '<video', $mux_video, $full_match );
				$replacement = str_replace( 'src="' . $url . '"', '', $replacement );

				$content = str_replace( $full_match, $replacement, $content );

				error_log( 'WP MUX: Replaced with: ' . $replacement );
			}
		}

		return $content;
	}

	/**
	 * Process Gutenberg video blocks
	 */
	public function process_video_blocks( $block_content, $block ) {
		if ( $block['blockName'] === 'core/video' ) {
			error_log( 'WP MUX: Processing video block' );
			error_log( 'WP MUX: Block content: ' . $block_content );

			// Check if this block contains a MUX video (both .m3u8 and .mp4)
			if ( strpos( $block_content, 'stream.mux.com' ) !== false && ( strpos( $block_content, '.m3u8' ) !== false || strpos( $block_content, '.mp4' ) !== false ) ) {
				error_log( 'WP MUX: Found MUX video in block' );

				// Extract URL and replace with mux-video element
				$pattern = '/src=["\']([^"\']*stream\.mux\.com[^"\']*\.(m3u8|mp4)[^"\']*)["\']/i';
				if ( preg_match( $pattern, $block_content, $matches ) ) {
					$url = $matches[1];
					$path = parse_url( $url, PHP_URL_PATH );
					$playback_id = basename( $path, '.m3u8' );
					$playback_id = basename( $playback_id, '.mp4' );

					error_log( 'WP MUX: Extracted playback ID: ' . $playback_id );

					// Generate thumbnail URL
					$thumbnail_url = "https://image.mux.com/{$playback_id}/thumbnail.jpg?width=640&height=360";

					// Replace video element with mux-player
					$block_content = preg_replace(
						'/<video([^>]*)>/',
						'<mux-player playback-id="' . esc_attr( $playback_id ) . '" controls stream-type="on-demand" poster="' . esc_attr( $thumbnail_url ) . '"$1>',
						$block_content
					);

					// Remove the src attribute since mux-video doesn't need it
					$block_content = preg_replace( '/src=["\'][^"\']*["\']/', '', $block_content );

					error_log( 'WP MUX: Modified block content: ' . $block_content );
				}
			}
		}

		return $block_content;
	}

}
