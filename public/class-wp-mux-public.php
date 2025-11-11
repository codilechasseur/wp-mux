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

			// If no poster is provided, generate thumbnail URL from MUX with optional parameters
			if ( empty( $poster ) ) {
				$thumbnail_options = array(
					'time'     => isset( $attr['poster_time'] ) ? floatval( $attr['poster_time'] ) : 0,
					'width'    => isset( $attr['poster_width'] ) ? intval( $attr['poster_width'] ) : 640,
					'height'   => isset( $attr['poster_height'] ) ? intval( $attr['poster_height'] ) : 360,
					'fit_mode' => isset( $attr['poster_fit'] ) ? $attr['poster_fit'] : 'smartcrop',
					'animated' => isset( $attr['animated_poster'] ) ? (bool) $attr['animated_poster'] : false,
				);
				$poster = $this->generate_mux_thumbnail_url( $playback_id, $thumbnail_options );
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
				'<mux-player playback-id="%s" %s poster="%s" style="%s" stream-type="on-demand"%s>
					<p>%s</p>
				</mux-player>',
				esc_attr( $playback_id ),
				$controls,
				esc_attr( $poster ),
				esc_attr( $style ),
				$this->get_player_options_attributes(),
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
				$mux_video = '<mux-player playback-id="' . esc_attr( $playback_id ) . '" controls stream-type="on-demand" poster="' . esc_attr( $thumbnail_url ) . '"' . $this->get_player_options_attributes() . '>';
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

			$is_mux_video = false;
			$playback_id = '';

			// Check if this block references a MUX video attachment by ID
			if ( isset( $block['attrs']['id'] ) ) {
				$attachment_id = $block['attrs']['id'];
				$attachment = get_post( $attachment_id );

				if ( $attachment && $attachment->post_mime_type === 'video/mux' ) {
					$is_mux_video = true;
					$playback_id = get_post_meta( $attachment_id, '_mux_playback_id', true );
					error_log( 'WP MUX: Found MUX video by attachment ID: ' . $attachment_id . ', playback_id: ' . $playback_id );
				}
			}

			// Check if this block contains a MUX stream URL (both .m3u8 and .mp4)
			if ( ! $is_mux_video && strpos( $block_content, 'stream.mux.com' ) !== false && ( strpos( $block_content, '.m3u8' ) !== false || strpos( $block_content, '.mp4' ) !== false ) ) {
				$is_mux_video = true;
				error_log( 'WP MUX: Found MUX video by stream.mux.com URL' );

				// Extract URL and get playback ID
				$pattern = '/src=["\']([^"\']*stream\.mux\.com[^"\']*\.(m3u8|mp4)[^"\']*)["\']/i';
				if ( preg_match( $pattern, $block_content, $matches ) ) {
					$url = $matches[1];
					$path = parse_url( $url, PHP_URL_PATH );
					$playback_id = basename( $path, '.m3u8' );
					$playback_id = basename( $playback_id, '.mp4' );
					error_log( 'WP MUX: Extracted playback ID from URL: ' . $playback_id );
				}
			}

			// Process if this is a MUX video
			if ( $is_mux_video && ! empty( $playback_id ) ) {
				error_log( 'WP MUX: Processing as MUX video with playback ID: ' . $playback_id );

				// Check if user wants to use custom uploaded poster instead of Mux thumbnails
				$use_custom_poster = isset( $block['attrs']['mux_use_custom_poster'] ) ? $block['attrs']['mux_use_custom_poster'] : false;

				// Generate thumbnail URL with custom parameters from block attributes
				if ( ! $use_custom_poster ) {
					$thumbnail_options = array(
						'time'     => isset( $block['attrs']['mux_poster_time'] ) ? floatval( $block['attrs']['mux_poster_time'] ) : 0,
						'width'    => isset( $block['attrs']['mux_poster_width'] ) ? intval( $block['attrs']['mux_poster_width'] ) : 640,
						'height'   => isset( $block['attrs']['mux_poster_height'] ) ? intval( $block['attrs']['mux_poster_height'] ) : 360,
						'fit_mode' => 'smartcrop',
						'animated' => isset( $block['attrs']['mux_use_animated_gif'] ) ? (bool) $block['attrs']['mux_use_animated_gif'] : false,
					);
					$thumbnail_url = $this->generate_mux_thumbnail_url( $playback_id, $thumbnail_options );
				} else {
					// User wants to use custom poster - check if one is set in the poster attribute
					$thumbnail_url = isset( $block['attrs']['poster'] ) ? $block['attrs']['poster'] : $this->generate_mux_thumbnail_url( $playback_id );
				}

				// Get theme from block attributes
				$theme_id = isset( $block['attrs']['mux_theme'] ) ? $block['attrs']['mux_theme'] : '';
				error_log( 'WP MUX: Theme ID from block attributes: "' . $theme_id . '"' );
				error_log( 'WP MUX: Use custom poster: ' . ( $use_custom_poster ? 'true' : 'false' ) );
				error_log( 'WP MUX: Generated thumbnail URL: ' . $thumbnail_url );
				error_log( 'WP MUX: Available block attributes: ' . print_r( $block['attrs'], true ) );

				// Check if Gutenberg block has controls enabled
				// In Gutenberg, when controls are disabled, the video element won't have the controls attribute
				$has_controls_attr = strpos( $block_content, 'controls' ) !== false;

				// The presence of the controls attribute in HTML is the source of truth
				// If controls attribute is in the HTML, controls are enabled
				// If controls attribute is NOT in the HTML, controls are disabled
				$block_controls_enabled = $has_controls_attr;

				// Gutenberg block controls should override everything
				$controls_attr = $block_controls_enabled ? 'controls' : '';

				error_log( 'WP MUX: Has controls attribute in HTML: ' . ( $has_controls_attr ? 'true' : 'false' ) );
				error_log( 'WP MUX: Block controls enabled: ' . ( $block_controls_enabled ? 'true' : 'false' ) );
				error_log( 'WP MUX: Final controls attribute: "' . $controls_attr . '"' );

				// Build the mux-player opening tag
				$mux_player_tag = '<mux-player playback-id="' . esc_attr( $playback_id ) . '"';

				// Only add controls attribute if it should be enabled
				if ( ! empty( $controls_attr ) ) {
					$mux_player_tag .= ' ' . $controls_attr;
				}

				// Add player options
				$mux_player_tag .= $this->get_player_options_attributes();

				$mux_player_tag .= ' stream-type="on-demand" poster="' . esc_attr( $thumbnail_url ) . '"$1>';

				// Replace video element with mux-player
				$block_content = preg_replace(
					'/<video([^>]*)>/',
					$mux_player_tag,
					$block_content
				);

				// Replace closing video tag with mux-player
				$block_content = str_replace( '</video>', '</mux-player>', $block_content );

				// Remove the src attribute since mux-player doesn't need it
				$block_content = preg_replace( '/src=["\'][^"\']*["\']/', '', $block_content );

				// Apply theme to the player, passing the controls override setting
				if ( ! empty( $theme_id ) ) {
					$block_content = $this->apply_theme_to_player( $block_content, $theme_id, ! $block_controls_enabled );
				} else {
					$block_content = $this->apply_theme_to_player( $block_content, null, ! $block_controls_enabled );
				}

				error_log( 'WP MUX: Modified block content: ' . $block_content );
			}
		}

		return $block_content;
	}

	/**
	 * Generate Mux thumbnail URL with custom parameters.
	 *
	 * @since    1.0.0
	 * @param    string  $playback_id      The Mux playback ID.
	 * @param    array   $options          Optional parameters for thumbnail generation.
	 *                                     - time: (float) Time in seconds to capture (default: 0)
	 *                                     - width: (int) Width in pixels (default: 640)
	 *                                     - height: (int) Height in pixels (default: 360)
	 *                                     - fit_mode: (string) How to fit the image (crop, preserve, smartcrop) (default: smartcrop)
	 *                                     - animated: (bool) Generate animated GIF instead of static (default: false)
	 * @return   string                   The complete Mux thumbnail URL.
	 */
	private function generate_mux_thumbnail_url( $playback_id, $options = array() ) {
		// Default options
		$defaults = array(
			'time'     => 0,
			'width'    => 640,
			'height'   => 360,
			'fit_mode' => 'smartcrop',
			'animated' => false,
			'duration' => 5, // Duration for animated GIFs in seconds
		);

		$options = wp_parse_args( $options, $defaults );

		// Base URL
		$base_url = "https://image.mux.com/{$playback_id}/";

		// Choose endpoint based on animated option
		if ( $options['animated'] ) {
			$url = $base_url . 'animated.gif';
			$params = array(
				'start'  => $options['time'],
				'end'    => $options['time'] + $options['duration'], // Add end time
				'width'  => $options['width'],
				'height' => $options['height'],
				'fps'    => 15, // Default FPS for animated GIFs
			);
		} else {
			$url = $base_url . 'thumbnail.jpg';
			$params = array(
				'time'     => $options['time'],
				'width'    => $options['width'],
				'height'   => $options['height'],
				'fit_mode' => $options['fit_mode'],
			);
		}

		// Build query string
		$query_string = http_build_query( $params );

		return $url . '?' . $query_string;
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
	 * @param    string    $player_html           The player HTML.
	 * @param    string    $theme_id              Optional theme ID to apply.
	 * @param    bool      $force_disable_controls Whether to force disable controls (overrides theme settings).
	 * @return   string                           Modified player HTML.
	 */
	public function apply_theme_to_player( $player_html, $theme_id = null, $force_disable_controls = false ) {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-theme-manager.php';
		$theme_manager = new Wp_Mux_Theme_Manager();

		// Log what theme ID we received
		error_log( 'WP MUX: apply_theme_to_player called with theme_id: "' . $theme_id . '", force_disable_controls: ' . ( $force_disable_controls ? 'true' : 'false' ) );

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

		// Check if main controls are enabled in theme
		$main_controls_enabled = isset( $theme['controls']['controls'] ) ? $theme['controls']['controls'] : true;

		error_log( 'WP MUX: Theme ID: ' . $theme_id );
		error_log( 'WP MUX: Theme main controls enabled: ' . ( $main_controls_enabled ? 'true' : 'false' ) );
		error_log( 'WP MUX: Theme controls data: ' . print_r( $theme['controls'], true ) );

		// If Gutenberg explicitly disabled controls, remove the controls attribute completely
		if ( $force_disable_controls ) {
			error_log( 'WP MUX: Gutenberg block has controls disabled - removing controls attribute and adding CSS' );

			// Remove controls attribute completely (standalone or with value)
			// Match: controls, controls="", controls='', controls="true", etc.
			$player_html = preg_replace(
				'/\s+controls(?:\s*=\s*["\'][^"\']*["\'])?\s*/',
				' ',
				$player_html
			);

			// Also remove any stray standalone 'controls' that might be left
			$player_html = preg_replace(
				'/(<mux-player[^>]*)\s+controls\s+/',
				'$1 ',
				$player_html
			);
			$player_html = preg_replace(
				'/(<mux-player[^>]*)\s+controls>/',
				'$1>',
				$player_html
			);

			// Add inline style to force disable controls using Mux Player's CSS variable
			$force_disable_style = '--controls: none';

			// Check if style attribute exists
			if ( strpos( $player_html, 'style=' ) !== false ) {
				// Add to existing style
				$player_html = preg_replace(
					'/style=(["\'])([^"\']*)\1/',
					'style=$1$2; ' . $force_disable_style . '$1',
					$player_html
				);
			} else {
				// Add new style attribute
				$player_html = preg_replace(
					'/(<mux-player[^>]*?)>/',
					'$1 style="' . $force_disable_style . '">',
					$player_html
				);
			}

			error_log( 'WP MUX: After removing controls and adding CSS: ' . $player_html );
		} else {
			// Normal theme application - ensure controls attribute is present if theme has controls enabled
			// CSS will handle visibility based on theme settings
			error_log( 'WP MUX: Applying theme controls normally' );
			if ( $main_controls_enabled && strpos( $player_html, 'controls' ) === false ) {
				$player_html = preg_replace(
					'/(<mux-player[^>]*?)>/',
					'$1 controls>',
					$player_html
				);
			}
		}

		// Always add theme class regardless of controls state
		$theme_class = 'wp-mux-theme-' . esc_attr( $theme_id );

		// Check if class attribute exists
		if ( strpos( $player_html, 'class=' ) !== false ) {
			// Check if theme class is not already present
			if ( strpos( $player_html, $theme_class ) === false ) {
				// Add to existing class
				$player_html = preg_replace(
					'/class=(["\'])([^"\']*)\1/',
					'class=$1$2 ' . $theme_class . '$1',
					$player_html
				);
			}
		} else {
			// Add new class attribute
			$player_html = preg_replace(
				'/(<mux-player[^>]*?)>/',
				'$1 class="' . $theme_class . '">',
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

	/**
	 * Get global player options as HTML attributes.
	 *
	 * @since    1.4.0
	 * @return   string    HTML attributes string for mux-player
	 */
	private function get_player_options_attributes() {
		$attributes = array();

		// Prefer playback
		$prefer_playback = get_option( 'wp_mux_player_prefer_playback', 'auto' );
		if ( $prefer_playback !== 'auto' ) {
			$attributes[] = 'prefer-playback="' . esc_attr( $prefer_playback ) . '"';
		}

		// Max resolution
		$max_resolution = get_option( 'wp_mux_player_max_resolution', '' );
		if ( ! empty( $max_resolution ) ) {
			$attributes[] = 'max-resolution="' . esc_attr( $max_resolution ) . '"';
		}

		// Min resolution
		$min_resolution = get_option( 'wp_mux_player_min_resolution', '' );
		if ( ! empty( $min_resolution ) ) {
			$attributes[] = 'min-resolution="' . esc_attr( $min_resolution ) . '"';
		}

		// Preload
		$preload = get_option( 'wp_mux_player_preload', 'metadata' );
		if ( $preload !== 'metadata' ) {
			$attributes[] = 'preload="' . esc_attr( $preload ) . '"';
		}

		// Debug
		$debug = get_option( 'wp_mux_player_debug', false );
		if ( $debug ) {
			$attributes[] = 'debug';
		}

		return ! empty( $attributes ) ? ' ' . implode( ' ', $attributes ) : '';
	}

}
