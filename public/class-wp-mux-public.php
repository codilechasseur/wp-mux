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

		// Add theme CSS for MUX players
		$this->enqueue_theme_styles();

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
		// Handle case where $url might be an array (from wp_video_shortcode filter)
		if ( is_array( $url ) ) {
			// If $url is array, it's actually the attributes, shift parameters
			$attr = $url;
			$url = isset( $attr['src'] ) ? $attr['src'] : '';
		}

		// Ensure $url is a string
		if ( ! is_string( $url ) || empty( $url ) ) {
			return $html;
		}

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

			// Apply theme to the player
			$html = $this->apply_theme_to_player( $html );

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

				// Also replace closing video tags with mux-player
				$content = str_replace( '</video>', '</mux-player>', $content );

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
			error_log( 'WP MUX: Block data: ' . print_r( $block, true ) );

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

					// Get theme from block attributes
					$theme_id = isset( $block['attrs']['mux_theme'] ) ? $block['attrs']['mux_theme'] : '';
					error_log( 'WP MUX: Theme ID from block attributes: "' . $theme_id . '"' );
					error_log( 'WP MUX: Available block attributes: ' . print_r( $block['attrs'], true ) );

					// Replace video element with mux-player
					$block_content = preg_replace(
						'/<video([^>]*)>/',
						'<mux-player playback-id="' . esc_attr( $playback_id ) . '" controls stream-type="on-demand" poster="' . esc_attr( $thumbnail_url ) . '"$1>',
						$block_content
					);

					// Replace closing video tag with mux-player
					$block_content = str_replace( '</video>', '</mux-player>', $block_content );

					// Remove the src attribute since mux-player doesn't need it
					$block_content = preg_replace( '/src=["\'][^"\']*["\']/', '', $block_content );

					// Apply theme to the player
					if ( ! empty( $theme_id ) ) {
						$block_content = $this->apply_theme_to_player( $block_content, $theme_id );
					} else {
						$block_content = $this->apply_theme_to_player( $block_content );
					}

					error_log( 'WP MUX: Modified block content: ' . $block_content );
				}
			}
		}

		return $block_content;
	}

	/**
	 * Enqueue theme styles for MUX players.
	 *
	 * @since    1.0.0
	 */
	private function enqueue_theme_styles() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-theme-manager.php';
		$theme_manager = new Wp_Mux_Theme_Manager();

		// Get all themes and generate CSS
		$themes = $theme_manager->get_themes();
		$css = '';

		foreach ( $themes as $theme_id => $theme_data ) {
			$theme_css = $theme_manager->generate_theme_css( $theme_id );
			if ( ! empty( $theme_css ) ) {
				// Apply theme-specific class to the CSS selector
				$css .= ".wp-mux-theme-{$theme_id} mux-player,\n.wp-mux-theme-{$theme_id}.wp-block-video mux-player {\n";
				if ( strpos( $theme_css, 'mux-player' ) === 0 ) {
					// Extract the CSS from inside the mux-player selector
					$inner_css = str_replace( 'mux-player { ', '', $theme_css );
					$inner_css = rtrim( $inner_css, ' }' );
					$css .= $inner_css . "\n";
				} else {
					$css .= $theme_css . "\n";
				}
				$css .= "}\n";
			}
		}

		// Add default theme CSS
		$default_theme_id = $theme_manager->get_default_theme_id();
		$default_css = $theme_manager->generate_theme_css( $default_theme_id );
		if ( ! empty( $default_css ) ) {
			$css .= $default_css . "\n";
		}

		if ( ! empty( $css ) ) {
			wp_add_inline_style( $this->plugin_name, $css );
		}
	}

	/**
	 * Apply theme to MUX player element.
	 *
	 * @since    1.0.0
	 * @param    string    $player_html    The player HTML.
	 * @param    string    $theme_id       Optional theme ID to apply.
	 * @return   string                    Modified player HTML.
	 */
	public function apply_theme_to_player( $player_html, $theme_id = null ) {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-theme-manager.php';
		$theme_manager = new Wp_Mux_Theme_Manager();

		// Log what theme ID we received
		error_log( 'WP MUX: apply_theme_to_player called with theme_id: "' . $theme_id . '"' );

		// Use default theme if none specified or if explicitly empty
		if ( empty( $theme_id ) ) {
			$theme_id = $theme_manager->get_default_theme_id();
			error_log( 'WP MUX: No theme specified, using default theme ID: "' . $theme_id . '"' );
		}

		// Get theme data
		$theme = $theme_manager->get_theme( $theme_id );
		if ( ! $theme ) {
			error_log( 'WP MUX: Theme not found for ID: "' . $theme_id . '"' );
			return $player_html;
		}

		error_log( 'WP MUX: Found theme: "' . $theme['name'] . '" for ID: "' . $theme_id . '"' );

		// Check if main controls are enabled
		$main_controls_enabled = isset( $theme['controls']['controls'] ) ? $theme['controls']['controls'] : true;

		error_log( 'WP MUX: Theme ID: ' . $theme_id );
		error_log( 'WP MUX: Main controls enabled: ' . ( $main_controls_enabled ? 'true' : 'false' ) );
		error_log( 'WP MUX: Theme controls data: ' . print_r( $theme['controls'], true ) );

		// Always ensure controls attribute is present - CSS will handle visibility
		error_log( 'WP MUX: Ensuring controls attribute is present (CSS will handle visibility)' );
		if ( strpos( $player_html, 'controls' ) === false ) {
			$player_html = preg_replace(
				'/(<mux-player[^>]*?)>/',
				'$1 controls>',
				$player_html
			);
		}

		// Always add theme class regardless of controls state
		$class_addition = 'class="wp-mux-theme-' . esc_attr( $theme_id ) . '"';

		// Check if class attribute exists
		if ( strpos( $player_html, 'class=' ) !== false ) {
			// Add to existing class
			$player_html = preg_replace(
				'/class=(["\'])([^"\']*)\1/',
				'class=$1$2 wp-mux-theme-' . esc_attr( $theme_id ) . '$1',
				$player_html
			);
		} else {
			// Add new class attribute
			$player_html = preg_replace(
				'/(<mux-player[^>]*?)>/',
				'$1 ' . $class_addition . '>',
				$player_html
			);
		}

		// Get theme styles (will return empty string if main controls disabled)
		$theme_styles = $theme_manager->get_theme_style_attributes( $theme_id );

		if ( ! empty( $theme_styles ) ) {
			// Add inline styles
			$style_addition = 'style="' . esc_attr( $theme_styles ) . '"';

			// Check if style attribute exists
			if ( strpos( $player_html, 'style=' ) !== false ) {
				// Add to existing style
				$player_html = preg_replace(
					'/style=(["\'])([^"\']*)\1/',
					'style=$1$2; ' . $theme_styles . '$1',
					$player_html
				);
			} else {
				// Add new style attribute
				$player_html = preg_replace(
					'/(<mux-player[^>]*?)>/',
					'$1 ' . $style_addition . '>',
					$player_html
				);
			}
		}

		return $player_html;
	}

}
