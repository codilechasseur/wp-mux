<?php

declare(strict_types=1);

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://wpmux.com
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
 * @author     Codi Lechasseur
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

		// Check if this is a MUX stream URL (both .m3u8 and .mp4, or has mux=1 parameter)
		if ( strpos( $url, 'stream.mux.com' ) !== false &&
			 ( strpos( $url, '.m3u8' ) !== false ||
			   strpos( $url, '.mp4' ) !== false ||
			   strpos( $url, 'mux=1' ) !== false ) ) {


			// Extract playback ID from the URL
			$parsed_url = parse_url( $url );
			$path = $parsed_url['path'];
			$playback_id = basename( $path, '.m3u8' );
			$playback_id = basename( $playback_id, '.mp4' );


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

		}

		return $html;
	}

	/**
	 * Process content for MUX videos
	 */
	public function process_content_videos( $content ) {

		// Look for video elements with MUX URLs (both .m3u8 and .mp4)
		$pattern = '/<video[^>]*src=["\']([^"\']*stream\.mux\.com[^"\']*\.(m3u8|mp4)[^"\']*)["\']/i';

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {

			foreach ( $matches as $match ) {
				$full_match = $match[0];
				$url = $match[1];


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

			}
		}

		return $content;
	}

	/**
	 * Process Gutenberg video blocks
	 */
	public function process_video_blocks( $block_content, $block ) {
		if ( $block['blockName'] === 'core/video' ) {

			$is_mux_video = false;
			$playback_id = '';

			// Check if this block references a MUX video attachment by ID
			if ( isset( $block['attrs']['id'] ) ) {
				$attachment_id = $block['attrs']['id'];
				$attachment = get_post( $attachment_id );

				if ( $attachment && $attachment->post_mime_type === 'video/mux' ) {
					$is_mux_video = true;
					$playback_id = get_post_meta( $attachment_id, '_mux_playback_id', true );
				}
			}

			// Check if this block contains a MUX stream URL (both .m3u8 and .mp4)
			if ( ! $is_mux_video && strpos( $block_content, 'stream.mux.com' ) !== false && ( strpos( $block_content, '.m3u8' ) !== false || strpos( $block_content, '.mp4' ) !== false ) ) {
				$is_mux_video = true;

				// Extract URL and get playback ID
				$pattern = '/src=["\']([^"\']*stream\.mux\.com[^"\']*\.(m3u8|mp4)[^"\']*)["\']/i';
				if ( preg_match( $pattern, $block_content, $matches ) ) {
					$url = $matches[1];
					$path = parse_url( $url, PHP_URL_PATH );
					$playback_id = basename( $path, '.m3u8' );
					$playback_id = basename( $playback_id, '.mp4' );
				}
			}

			// Process if this is a MUX video
			if ( $is_mux_video && ! empty( $playback_id ) ) {

				// ---- Feature #5: Access restrictions ----
				// Block-level role restriction: if set, only allow the listed roles.
				$required_roles_raw = isset( $block['attrs']['mux_required_roles'] ) ? trim( $block['attrs']['mux_required_roles'] ) : '';
				if ( ! empty( $required_roles_raw ) ) {
					$required_roles = array_filter( array_map( 'trim', explode( ',', $required_roles_raw ) ) );
					if ( ! empty( $required_roles ) ) {
						$user_allowed = false;
						if ( is_user_logged_in() ) {
							$current_user = wp_get_current_user();
							foreach ( $required_roles as $role ) {
								if ( in_array( $role, (array) $current_user->roles, true ) ) {
									$user_allowed = true;
									break;
								}
							}
							// Admins always have access.
							if ( current_user_can( 'manage_options' ) ) {
								$user_allowed = true;
							}
						}
						if ( ! $user_allowed ) {
							// Replace the whole block with an access-denied message.
							return '<div class="wp-mux-access-denied" style="padding:24px;border:1px solid #ddd;border-radius:4px;text-align:center;background:#f9f9f9;">' .
								'<p>' . esc_html__( 'This video is restricted. Please log in with an account that has the required access.', 'wp-mux' ) . '</p>' .
								'</div>';
						}
					}
				}

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

				// Check if Gutenberg block has controls enabled
				// In Gutenberg, when controls are disabled, the video element won't have the controls attribute
				$has_controls_attr = strpos( $block_content, 'controls' ) !== false;

				// The presence of the controls attribute in HTML is the source of truth
				// If controls attribute is in the HTML, controls are enabled
				// If controls attribute is NOT in the HTML, controls are disabled
				$block_controls_enabled = $has_controls_attr;

				// Gutenberg block controls should override everything
				$controls_attr = $block_controls_enabled ? 'controls' : '';


				// Build the mux-player opening tag
				$mux_player_tag = '<mux-player playback-id="' . esc_attr( $playback_id ) . '"';

				// ---- Feature #4: Per-block signed URL token expiry ----
				// If the playback policy for this asset is 'signed', generate a JWT.
				$playback_policy = '';
				if ( isset( $attachment_id ) ) {
					$playback_policy = get_post_meta( $attachment_id, '_mux_playback_policy', true );
				}
				if ( $playback_policy === 'signed' ) {
					$global_expiry = (int) get_option( 'wp_mux_signed_url_expiry', 14400 );
					$block_expiry  = isset( $block['attrs']['mux_token_expiry'] ) ? (int) $block['attrs']['mux_token_expiry'] : 0;
					$expiry        = ( $block_expiry > 0 ) ? $block_expiry : $global_expiry;

					$jwt = Wp_Mux_Utils::generateMuxJwt( $playback_id, 'video', $expiry );
					if ( ! empty( $jwt ) ) {
						$mux_player_tag .= ' playback-token="' . esc_attr( $jwt ) . '"';
						// Also sign the thumbnail and storyboard tokens.
						$thumb_jwt  = Wp_Mux_Utils::generateMuxJwt( $playback_id, 'thumbnail', $expiry );
						$story_jwt  = Wp_Mux_Utils::generateMuxJwt( $playback_id, 'storyboard', $expiry );
						if ( ! empty( $thumb_jwt ) ) {
							$mux_player_tag .= ' thumbnail-token="' . esc_attr( $thumb_jwt ) . '"';
						}
						if ( ! empty( $story_jwt ) ) {
							$mux_player_tag .= ' storyboard-token="' . esc_attr( $story_jwt ) . '"';
						}
					}
				}

				// Only add controls attribute if it should be enabled
				if ( ! empty( $controls_attr ) ) {
					$mux_player_tag .= ' ' . $controls_attr;
				}

				// Add player options
				$mux_player_tag .= $this->get_player_options_attributes();

				// Storyboard scrubbing — injected automatically when the video is ready.
				$mux_player_tag .= ' storyboard-src="https://image.mux.com/' . esc_attr( $playback_id ) . '/storyboard.vtt"';

				// Video clipping: start-time / end-time from block attributes.
				$clip_start = isset( $block['attrs']['mux_clip_start'] ) ? floatval( $block['attrs']['mux_clip_start'] ) : null;
				$clip_end   = isset( $block['attrs']['mux_clip_end'] )   ? floatval( $block['attrs']['mux_clip_end'] )   : null;
				if ( $clip_start !== null && $clip_start > 0 ) {
					$mux_player_tag .= ' start-time="' . esc_attr( (string) $clip_start ) . '"';
				}
				if ( $clip_end !== null && $clip_end > 0 ) {
					// mux-player has no native end-time attribute; use data attr + JS.
					$mux_player_tag .= ' data-end-time="' . esc_attr( (string) $clip_end ) . '"';
				}

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

		// Use default theme if none specified or if explicitly empty
		if ( empty( $theme_id ) ) {
			$theme_id = $theme_manager->get_default_theme_id();
		}

		// Get theme data
		$theme = $theme_manager->get_theme( $theme_id );
		if ( ! $theme ) {
			return $player_html;
		}


		// Check if main controls are enabled in theme
		$main_controls_enabled = isset( $theme['controls']['controls'] ) ? $theme['controls']['controls'] : true;


		// If Gutenberg explicitly disabled controls, remove the controls attribute completely
		if ( $force_disable_controls ) {

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

		} else {
			// Normal theme application - ensure controls attribute is present if theme has controls enabled
			// CSS will handle visibility based on theme settings
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
	/**
	 * Output VideoObject JSON-LD schema for any Mux videos on the current singular page.
	 *
	 * Hooked to wp_head (priority 5) so it runs before third-party SEO plugins
	 * that may also add schema, allowing them to merge/override if desired.
	 *
	 * @since 1.2.0
	 */
	public function output_video_seo_schema() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		// Find all Mux attachment IDs referenced in this post's blocks.
		$post    = get_post( $post_id );
		$content = $post->post_content ?? '';

		// Collect from block attrs (id field on core/video blocks).
		$attach_ids = [];
		if ( has_blocks( $content ) ) {
			$blocks = parse_blocks( $content );
			$this->collect_mux_block_ids( $blocks, $attach_ids );
		}

		if ( empty( $attach_ids ) ) {
			return;
		}

		foreach ( $attach_ids as $attach_id ) {
			$attachment = get_post( $attach_id );
			if ( ! $attachment || $attachment->post_mime_type !== 'video/mux' ) {
				continue;
			}

			$playback_id = get_post_meta( $attach_id, '_mux_playback_id', true );
			if ( empty( $playback_id ) ) {
				continue;
			}

			$duration       = (float) ( get_post_meta( $attach_id, '_mux_duration', true ) ?: 0 );
			$thumbnail_url  = "https://image.mux.com/{$playback_id}/thumbnail.jpg?width=1280&height=720&fit_mode=smartcrop";
			$upload_date    = get_the_date( 'c', $attach_id );
			$title          = get_the_title( $attach_id );
			$description    = $attachment->post_content ?: $attachment->post_excerpt ?: $title;
			$content_url    = "https://stream.mux.com/{$playback_id}.m3u8";
			$embed_url      = home_url( '/?attachment_id=' . $attach_id );

			$schema = [
				'@context'     => 'https://schema.org',
				'@type'        => 'VideoObject',
				'name'         => $title,
				'description'  => wp_strip_all_tags( $description ),
				'thumbnailUrl' => $thumbnail_url,
				'uploadDate'   => $upload_date,
				'contentUrl'   => $content_url,
				'embedUrl'     => $embed_url,
			];

			if ( $duration > 0 ) {
				// ISO 8601 duration.
				$schema['duration'] = $this->seconds_to_iso8601_duration( $duration );
			}

			echo "<script type=\"application/ld+json\">\n";
			// wp_json_encode escapes </script> and uses UNESCAPED_UNICODE flags.
			echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
			echo "\n</script>\n";
		}
	}

	/**
	 * Recursively collect Mux attachment IDs from parsed block tree.
	 *
	 * @since  1.2.0
	 * @param  array $blocks     Parsed block array from parse_blocks().
	 * @param  array &$ids       Accumulator for found attachment IDs.
	 */
	private function collect_mux_block_ids( array $blocks, array &$ids ): void {
		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === 'core/video' && ! empty( $block['attrs']['id'] ) ) {
				$ids[] = (int) $block['attrs']['id'];
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->collect_mux_block_ids( $block['innerBlocks'], $ids );
			}
		}
	}

	/**
	 * Convert a duration in seconds to an ISO 8601 duration string (PTxHxMxS).
	 *
	 * @since  1.2.0
	 * @param  float $seconds
	 * @return string
	 */
	private function seconds_to_iso8601_duration( float $seconds ): string {
		$h = (int) floor( $seconds / 3600 );
		$m = (int) floor( fmod( $seconds, 3600 ) / 60 );
		$s = round( fmod( $seconds, 60 ), 2 );
		$duration = 'PT';
		if ( $h > 0 ) $duration .= $h . 'H';
		if ( $m > 0 ) $duration .= $m . 'M';
		if ( $s > 0 || ( $h === 0.0 && $m === 0.0 ) ) $duration .= $s . 'S';
		return $duration;
	}

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
