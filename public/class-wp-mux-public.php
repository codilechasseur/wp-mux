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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-mux-public.css', array(), $this->version, 'all' );

		// Add theme CSS for MUX players
		$this->enqueue_theme_styles();

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * Mux Player itself is only registered here: it is enqueued by whatever
	 * renders a player (enqueue_player()), and wp-mux-public.js loads it for
	 * any Mux <video> it converts in the browser — so pages without video
	 * don't download it.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		self::register_player();

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-mux-public.js', array( 'jquery' ), $this->version, true );

		// Pass privacy options to the JS fallback converter.
		wp_localize_script(
			$this->plugin_name,
			'wpMuxPublic',
			array(
				'disableCookies' => (bool) get_option( 'wp_mux_player_disable_cookies', false ),
				'disableTracking' => (bool) get_option( 'wp_mux_player_disable_mux_data', false ),
				'playerScriptUrl' => Wp_Mux_Utils::player_script_url(),
			)
		);

	}

	/**
	 * Register the Mux Player script (pinned to WP_MUX_PLAYER_VERSION).
	 *
	 * @since    1.2.1
	 */
	public static function register_player() {
		if ( ! wp_script_is( 'mux-player', 'registered' ) ) {
			wp_register_script( 'mux-player', Wp_Mux_Utils::player_script_url(), array(), null, true );
		}
	}

	/**
	 * Enqueue the Mux Player script for a player being rendered. Safe to call
	 * before wp_enqueue_scripts (block themes render blocks before wp_head).
	 *
	 * @since    1.2.1
	 */
	public static function enqueue_player() {
		self::register_player();
		wp_enqueue_script( 'mux-player' );
	}

	/**
	 * Render Mux videos for the [video] shortcode and wp_video_shortcode().
	 *
	 * Hooked to wp_video_shortcode_override, which runs before core checks the
	 * source's file extension: a Mux stream URL (…/{playback-id}.mp4?mux=1, as
	 * wp_get_attachment_url() returns for Mux attachments) fails that check, so
	 * core would otherwise print a plain link and no later filter would see it.
	 *
	 * Attributes: src (or mp4 / m4v / webm / ogv), and those of render_player():
	 * poster, loop, autoplay, muted, controls, preload, width, height, class,
	 * style, fit, background, pause_button, pause_button_class. Booleans take
	 * "on" / "1" / "true" and "off" / "0" / "false" / "no".
	 *
	 * @since    1.2.1
	 * @param    string  $output  Empty, or another plugin's override.
	 * @param    array   $attr    Shortcode attributes.
	 * @return   string           The player, or $output for non-Mux sources.
	 */
	public function render_mux_video_override( $output, $attr ) {
		if ( '' !== $output || ! is_array( $attr ) ) {
			return $output;
		}

		$playback_id = '';
		foreach ( array( 'src', 'mp4', 'm4v', 'webm', 'ogv' ) as $key ) {
			if ( ! empty( $attr[ $key ] ) && is_string( $attr[ $key ] ) ) {
				$playback_id = $this->playback_id_from_url( $attr[ $key ] );
				if ( $playback_id ) {
					break;
				}
			}
		}

		if ( ! $playback_id ) {
			return $output;
		}

		$args = $attr;
		foreach ( array( 'loop', 'autoplay', 'muted', 'controls', 'background', 'pause_button' ) as $key ) {
			if ( isset( $attr[ $key ] ) ) {
				$args[ $key ] = ! in_array( strtolower( trim( (string) $attr[ $key ] ) ), array( '', '0', 'false', 'off', 'no' ), true );
			}
		}

		return $this->render_player( array( 'playback_id' => $playback_id ), $args );
	}

	/**
	 * Render a video: <mux-player> for a video on Mux, <video> for a local file.
	 * Behind wp_mux_player() and the [video] shortcode.
	 *
	 * @since    1.3.0
	 * @param    array  $source  ['playback_id' => …] (Mux) or ['src' => …, 'type' => …]
	 *                           (local file), plus optional 'attachment_id'.
	 * @param    array  $args    {
	 *     @type bool       $controls           Player controls. Default true.
	 *     @type bool       $autoplay           Default false.
	 *     @type bool       $muted              Default false.
	 *     @type bool       $loop               Default false.
	 *     @type string     $preload            auto | metadata | none. Default: the Player Options setting.
	 *     @type int|string $poster             Image attachment ID or URL. Default: Mux's thumbnail.
	 *     @type string     $fit                cover | contain: how the video fills its box. Default: contain
	 *                                          (cover for background videos).
	 *     @type string     $class              Classes (on the wrapper for background videos).
	 *     @type string     $style              Inline style (likewise).
	 *     @type string|int $width, $height     Size (px when numeric).
	 *     @type bool       $background         A background video: muted, looping, no controls,
	 *                                          playing only while on screen and starting paused
	 *                                          for reduced motion, with a pause button (WCAG
	 *                                          2.2.2) and the poster shown until it plays, when
	 *                                          the video fades in (--wp-mux-fade-duration).
	 *                                          Wrapped in .wp-mux-background. Default false.
	 *     @type bool       $pause_button       Background videos: the pause button. Default true.
	 *     @type string     $pause_button_class Background videos: extra classes for the button.
	 * }
	 * @return   string
	 */
	public function render_player( array $source, array $args = array() ) {
		$args = wp_parse_args( $args, array(
			'controls'           => true,
			'autoplay'           => false,
			'muted'              => false,
			'loop'               => false,
			'preload'            => '',
			'poster'             => '',
			'fit'                => '',
			'class'              => '',
			'style'              => '',
			'width'              => '',
			'height'             => '',
			'background'         => false,
			'pause_button'       => true,
			'pause_button_class' => '',
		) );

		$background = (bool) $args['background'];
		$mux        = ! empty( $source['playback_id'] );

		if ( $background ) {
			// Started and stopped by the background controller (wp-mux-public.js).
			$args = array_merge( $args, array( 'controls' => false, 'autoplay' => false, 'muted' => true, 'loop' => true ) );
		}

		$fit     = in_array( $args['fit'], array( 'cover', 'contain' ), true ) ? $args['fit'] : ( $background ? 'cover' : '' );
		$preload = sanitize_key( (string) $args['preload'] );

		// Poster: an image attachment ID or a URL; Mux's thumbnail otherwise.
		$poster_id  = is_numeric( $args['poster'] ) ? (int) $args['poster'] : 0;
		$poster_url = $poster_id ? (string) wp_get_attachment_image_url( $poster_id, 'full' ) : ( is_numeric( $args['poster'] ) ? '' : (string) $args['poster'] );
		if ( '' === $poster_url && $mux ) {
			$poster_url = $background
				? $this->generate_mux_thumbnail_url( $source['playback_id'], array( 'width' => 1920, 'height' => 1080 ) )
				: $this->generate_mux_thumbnail_url( $source['playback_id'] );
		}

		$style = (string) $args['style'];
		foreach ( array( 'width', 'height' ) as $dimension ) {
			if ( '' !== (string) $args[ $dimension ] ) {
				$style .= ( $style ? '; ' : '' ) . $dimension . ': ' . $args[ $dimension ] . ( is_numeric( $args[ $dimension ] ) ? 'px' : '' );
			}
		}

		// A background video's class and style go on its wrapper.
		$media_class = $background ? 'wp-mux-background__media' : (string) $args['class'];
		$media_style = $background ? '' : $style;
		if ( $fit ) {
			$media_style .= ( $media_style ? '; ' : '' ) . ( $mux ? '--media-object-fit: ' : 'object-fit: ' ) . $fit;
		}

		$flags = array(
			'controls'    => (bool) $args['controls'],
			'autoplay'    => (bool) $args['autoplay'],
			'muted'       => (bool) $args['muted'],
			'loop'        => (bool) $args['loop'],
			'playsinline' => $args['autoplay'] || $background,
		);

		if ( $mux ) {
			$attributes = array(
				'playback-id' => $source['playback_id'],
				'stream-type' => 'on-demand',
				// A background video fades in over its poster image (below) once it plays.
				'poster'      => $background ? '' : $poster_url,
				'class'       => $media_class,
				'style'       => $media_style,
				'preload'     => $preload,
			) + Wp_Mux_Utils::signed_player_tokens( $source['playback_id'], (int) ( $source['attachment_id'] ?? 0 ) );

			$options = $this->get_player_options_attributes();
			if ( $preload ) {
				// An explicit preload wins over the Player Options setting.
				$options = preg_replace( '/\s*preload="[^"]*"/', '', $options );
			}

			$media = '<mux-player' . self::html_attributes( $attributes, $flags ) . ( $background ? ' nohotkeys aria-hidden="true"' : '' ) . $options . '></mux-player>';
			$media = $this->apply_theme_to_player( $media, null, ! $flags['controls'] );

			self::enqueue_player();
		} else {
			$attributes = array(
				'class'   => $media_class,
				'style'   => $media_style,
				'poster'  => $background ? '' : $poster_url,
				'preload' => $preload ? $preload : ( $background ? 'metadata' : '' ),
			);

			$media = sprintf(
				'<video%s%s><source src="%s" type="%s"></video>',
				self::html_attributes( $attributes, $flags ),
				$background ? ' aria-hidden="true"' : '',
				esc_url( $source['src'] ),
				esc_attr( $source['type'] ?? '' )
			);
		}

		if ( ! $background ) {
			return $media;
		}

		// The poster shows until the video's first frame plays, then the video
		// fades in over it. A video preloaded eagerly (a hero) gets an eager poster.
		$eager_poster = 'auto' === $preload;
		$poster       = '';
		if ( $poster_id ) {
			$poster = wp_get_attachment_image( $poster_id, 'full', false, array_filter( array(
				'class'         => 'wp-mux-background__poster',
				'alt'           => '',
				'aria-hidden'   => 'true',
				'loading'       => $eager_poster ? 'eager' : '',
				'fetchpriority' => $eager_poster ? 'high' : '',
			) ) );
		} elseif ( $poster_url ) {
			$poster = sprintf(
				'<img class="wp-mux-background__poster" src="%s" alt="" aria-hidden="true" %s decoding="async">',
				esc_url( $poster_url ),
				$eager_poster ? 'fetchpriority="high"' : 'loading="lazy"'
			);
		}

		$button = '';
		if ( $args['pause_button'] ) {
			$button = sprintf(
				'<button type="button" class="%s" aria-label="%s" data-label-pause="%s" data-label-play="%s" hidden>%s%s</button>',
				esc_attr( trim( 'wp-mux-background__toggle ' . $args['pause_button_class'] ) ),
				esc_attr__( 'Pause video', 'wp-mux' ),
				esc_attr__( 'Pause video', 'wp-mux' ),
				esc_attr__( 'Play video', 'wp-mux' ),
				'<svg class="wp-mux-background__icon wp-mux-background__icon--pause" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M7 5h3.5v14H7zm6.5 0H17v14h-3.5z"/></svg>',
				'<svg class="wp-mux-background__icon wp-mux-background__icon--play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 5.14v13.72L19 12z"/></svg>'
			);
		}

		return sprintf(
			'<div%s data-wp-mux-background>%s%s%s</div>',
			self::html_attributes( array( 'class' => trim( 'wp-mux-background ' . $args['class'] ), 'style' => $style ) ),
			$poster,
			$media,
			$button
		);
	}

	/**
	 * name="value" pairs (skipping empty values) followed by boolean flags.
	 *
	 * @since    1.3.0
	 */
	private static function html_attributes( array $attributes, array $flags = array() ): string {
		$html = '';
		foreach ( $attributes as $name => $value ) {
			if ( '' !== (string) $value ) {
				$html .= sprintf( ' %s="%s"', $name, esc_attr( (string) $value ) );
			}
		}
		foreach ( $flags as $name => $on ) {
			if ( $on ) {
				$html .= ' ' . $name;
			}
		}

		return $html;
	}

	/**
	 * The playback ID in a Mux stream URL (stream.mux.com/{id}.mp4|.m3u8), or ''.
	 *
	 * @since    1.2.1
	 */
	private function playback_id_from_url( string $url ): string {
		if ( false === strpos( $url, 'stream.mux.com' ) ) {
			return '';
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		return (string) preg_replace( '/\.(m3u8|mp4)$/i', '', basename( $path ) );
	}

	/**
	 * Process content for MUX videos
	 */
	public function process_content_videos( $content ) {

		if ( false === strpos( $content, 'stream.mux.com' ) ) {
			return $content;
		}

		// Each <video …src="…stream.mux.com/{id}.m3u8|.mp4…"> … </video> element,
		// replaced on its own (other videos in the content are left alone).
		$pattern = '/<video\b([^>]*?)\ssrc=(["\'])([^"\']*stream\.mux\.com[^"\']*\.(?:m3u8|mp4)[^"\']*)\2([^>]*)>(.*?)<\/video>/is';

		return preg_replace_callback(
			$pattern,
			function ( $match ) {
				$playback_id = $this->playback_id_from_url( $match[3] );

				if ( '' === $playback_id ) {
					return $match[0];
				}

				// Keep the element's other attributes (class, style, …).
				$attributes = $match[1] . $match[4];
				$controls   = preg_match( '/\scontrols\b/i', $attributes ) ? '' : ' controls';
				$thumbnail  = "https://image.mux.com/{$playback_id}/thumbnail.jpg?width=640&height=360";

				self::enqueue_player();

				return '<mux-player playback-id="' . esc_attr( $playback_id ) . '"' . $controls . ' stream-type="on-demand" poster="' . esc_attr( $thumbnail ) . '"' . $this->get_player_options_attributes() . $attributes . '>' . $match[5] . '</mux-player>';
			},
			$content
		);
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

					$jwt = Wp_Mux_Utils::generate_mux_jwt( $playback_id, 'video', $expiry );
					if ( ! empty( $jwt ) ) {
						$mux_player_tag .= ' playback-token="' . esc_attr( $jwt ) . '"';
						// Also sign the thumbnail and storyboard tokens.
						$thumb_jwt  = Wp_Mux_Utils::generate_mux_jwt( $playback_id, 'thumbnail', $expiry );
						$story_jwt  = Wp_Mux_Utils::generate_mux_jwt( $playback_id, 'storyboard', $expiry );
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

				self::enqueue_player();

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

		// Cookieless / privacy mode: player will not set cookies or use localStorage.
		if ( get_option( 'wp_mux_player_disable_cookies', false ) ) {
			$attributes[] = 'disable-cookies';
		}

		// Disable Mux Data analytics beaconing entirely.
		if ( get_option( 'wp_mux_player_disable_mux_data', false ) ) {
			$attributes[] = 'disable-tracking';
		}

		return ! empty( $attributes ) ? ' ' . implode( ' ', $attributes ) : '';
	}

}
