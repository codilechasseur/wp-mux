<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.0.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/admin
 * @author     Codi Lechasseur <codilechasseur@gmail.com>
 */
class Wp_Mux_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

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

	wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-mux-admin.css', array(), $this->version, 'all' );
	// Add custom styles for MUX badge in media library
	wp_enqueue_style( 'wp-mux-media-badge', plugin_dir_url( __FILE__ ) . 'css/wp-mux-media-badge.css', array(), $this->version );

	// Enqueue theme management styles on themes page
	$screen = get_current_screen();
	if ( $screen && $screen->id === 'mux-videos_page_wp-mux-themes' ) {
		wp_enqueue_style( 'wp-mux-themes', plugin_dir_url( __FILE__ ) . 'css/wp-mux-themes.css', array(), $this->version );
	}

	}

	/**
	 * Register the JavaScript for the admin area.
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

	wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-mux-admin.js', array( 'jquery' ), $this->version, false );

	// Load theme manager for theme data
	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-theme-manager.php';
	$theme_manager = new Wp_Mux_Theme_Manager();
	$themes = $theme_manager->get_themes();
	$theme_options = array();
	foreach ( $themes as $theme_id => $theme ) {
		$theme_options[] = array(
			'id' => $theme_id,
			'name' => $theme['name'],
		);
	}

	// Localize script with necessary data
	$api = new Wp_Mux_Api();
	wp_localize_script( $this->plugin_name, 'wpMuxData', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'wp_mux_admin_nonce' ),
		'uploadNonce' => wp_create_nonce( 'wp_mux_upload_nonce' ),
		'videosNonce' => wp_create_nonce( 'wp_mux_videos_nonce' ),
		'themesNonce' => wp_create_nonce( 'wp_mux_themes_nonce' ),
		'isConfigured' => $api->is_configured(),
		'themes' => $theme_options,
		'strings' => array(
			'uploadError' => __( 'Upload failed. Please try again.', 'wp-mux' ),
			'deleteConfirm' => __( 'Are you sure you want to delete this video?', 'wp-mux' ),
			'deleteThemeConfirm' => __( 'Are you sure you want to delete this theme?', 'wp-mux' ),
			'themeSaved' => __( 'Theme saved successfully', 'wp-mux' ),
			'themeDeleted' => __( 'Theme deleted successfully', 'wp-mux' ),
			'defaultThemeSet' => __( 'Default theme updated', 'wp-mux' ),
		),
	) );

	// Only enqueue mux-player once with consistent URL
	if ( ! wp_script_is( 'mux-player', 'enqueued' ) ) {
		wp_enqueue_script( 'mux-player', 'https://unpkg.com/@mux/mux-player', array(), null, true );
	}

	// Re-enable media badge script with better targeting
	$screen = get_current_screen();
	if ( $screen && (
		$screen->id === 'upload' ||
		$screen->id === 'media' ||
		strpos( $screen->base, 'media' ) !== false ||
		strpos( $screen->id, 'page_wp-mux' ) !== false
	) ) {
		wp_enqueue_script( 'wp-mux-media-badge', plugin_dir_url( __FILE__ ) . 'js/wp-mux-media-badge.js', array( 'media-views' ), $this->version, true );
	}

	// Enqueue theme management script on themes page
	if ( $screen && $screen->id === 'mux-videos_page_wp-mux-themes' ) {
		wp_enqueue_script( 'wp-mux-themes', plugin_dir_url( __FILE__ ) . 'js/wp-mux-themes.js', array( 'jquery' ), $this->version, true );
	}

	// Enqueue block editor script for Gutenberg
	if ( $screen && ( $screen->is_block_editor() || $screen->id === 'post' || $screen->id === 'page' ) ) {
		wp_enqueue_script( 'wp-mux-block-editor', plugin_dir_url( __FILE__ ) . 'js/wp-mux-block-editor.js', array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data' ), $this->version, true );

		// Make themes available to block editor
		wp_add_inline_script( 'wp-mux-block-editor', 'window.wpMuxThemes = ' . json_encode( $theme_options ) . ';', 'before' );
	}

	}

	/**
	 * Add settings link to plugin action links.
	 *
	 * @since    1.0.0
	 * @param    array    $links    Plugin action links.
	 * @return   array              Modified plugin action links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=wp-mux-settings' ) . '">' . __( 'Settings', 'wp-mux' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Add admin menu pages.
	 *
	 * @since    1.0.0
	 */
	public function add_admin_menu() {
		// Add main menu page for videos
		add_menu_page(
			__( 'MUX Videos', 'wp-mux' ),
			__( 'MUX Videos', 'wp-mux' ),
			'manage_options',
			'wp-mux-videos',
			array( $this, 'display_videos_page' ),
			'dashicons-video-alt3',
			30
		);

		// Add submenu for videos (default)
		add_submenu_page(
			'wp-mux-videos',
			__( 'All Videos', 'wp-mux' ),
			__( 'All Videos', 'wp-mux' ),
			'manage_options',
			'wp-mux-videos',
			array( $this, 'display_videos_page' )
		);

		// Add submenu for settings
		add_submenu_page(
			'wp-mux-videos',
			__( 'MUX Settings', 'wp-mux' ),
			__( 'Settings', 'wp-mux' ),
			'manage_options',
			'wp-mux-settings',
			array( $this, 'display_settings_page' )
		);

		// Add submenu for themes
		add_submenu_page(
			'wp-mux-videos',
			__( 'Player Themes', 'wp-mux' ),
			__( 'Themes', 'wp-mux' ),
			'manage_options',
			'wp-mux-themes',
			array( $this, 'display_themes_page' )
		);
	}

	/**
	 * Initialize plugin settings.
	 *
	 * @since    1.0.0
	 */
	public function init_settings() {
		// Register settings
		register_setting(
			'wp_mux_settings_group',
			'wp_mux_access_token',
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => '',
			)
		);

		register_setting(
			'wp_mux_settings_group',
			'wp_mux_secret_key',
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => '',
			)
		);

		// Add settings section
		add_settings_section(
			'wp_mux_api_settings',
			__( 'API Settings', 'wp-mux' ),
			array( $this, 'api_settings_section_callback' ),
			'wp-mux-settings'
		);

		// Add access token field
		add_settings_field(
			'wp_mux_access_token',
			__( 'Access Token', 'wp-mux' ),
			array( $this, 'access_token_field_callback' ),
			'wp-mux-settings',
			'wp_mux_api_settings'
		);

		// Add secret key field
		add_settings_field(
			'wp_mux_secret_key',
			__( 'Secret Key', 'wp-mux' ),
			array( $this, 'secret_key_field_callback' ),
			'wp-mux-settings',
			'wp_mux_api_settings'
		);
	}

	/**
	 * Display the settings page.
	 *
	 * @since    1.0.0
	 */
	public function display_settings_page() {
		include_once plugin_dir_path( __FILE__ ) . 'partials/wp-mux-admin-settings.php';
	}

	/**
	 * Display the videos page.
	 *
	 * @since    1.0.0
	 */
	public function display_videos_page() {
		include_once plugin_dir_path( __FILE__ ) . 'partials/wp-mux-admin-videos.php';
	}

	/**
	 * Display the themes page.
	 *
	 * @since    1.0.0
	 */
	public function display_themes_page() {
		include_once plugin_dir_path( __FILE__ ) . 'partials/wp-mux-admin-themes.php';
	}

	/**
	 * Handle theme form submissions and redirects.
	 *
	 * @since    1.0.0
	 */
	public function handle_theme_form_submissions() {
		// Only process on our themes page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wp-mux-themes' ) {
			return;
		}

		if ( ! isset( $_POST['action'] ) || ! wp_verify_nonce( $_POST['wp_mux_themes_nonce'], 'wp_mux_themes_action' ) ) {
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-theme-manager.php';
		$theme_manager = new Wp_Mux_Theme_Manager();

		switch ( $_POST['action'] ) {
			case 'save_theme':
				$theme_id = sanitize_text_field( $_POST['theme_id'] );
				$name = sanitize_text_field( $_POST['theme_name'] );
				$description = sanitize_textarea_field( $_POST['theme_description'] );
				$controls = isset( $_POST['controls'] ) ? $_POST['controls'] : array();
				$styles = isset( $_POST['styles'] ) ? $_POST['styles'] : array();

				if ( empty( $theme_id ) ) {
					$theme_id = $theme_manager->generate_theme_id( $name );
				}

				$result = $theme_manager->save_theme( $theme_id, $name, $description, $controls, $styles );

				if ( is_wp_error( $result ) ) {
					set_transient( 'wp_mux_theme_error_' . get_current_user_id(), $result->get_error_message(), 30 );
					$redirect_url = admin_url( 'admin.php?page=wp-mux-themes&action_completed=1' );
				} else {
					$redirect_url = admin_url( 'admin.php?page=wp-mux-themes&edit=' . urlencode( $theme_id ) . '&updated=1' );
				}

				wp_redirect( $redirect_url );
				exit;
				break;

			case 'delete_theme':
				$theme_id = sanitize_text_field( $_POST['theme_id'] );
				$result = $theme_manager->delete_theme( $theme_id );

				if ( is_wp_error( $result ) ) {
					set_transient( 'wp_mux_theme_error_' . get_current_user_id(), $result->get_error_message(), 30 );
				} else {
					set_transient( 'wp_mux_theme_success_' . get_current_user_id(), __( 'Theme deleted successfully!', 'wp-mux' ), 30 );
				}

				$redirect_url = admin_url( 'admin.php?page=wp-mux-themes&action_completed=1' );
				wp_redirect( $redirect_url );
				exit;
				break;

			case 'set_default':
				$theme_id = sanitize_text_field( $_POST['theme_id'] );
				$result = $theme_manager->set_default_theme( $theme_id );

				if ( $result ) {
					set_transient( 'wp_mux_theme_success_' . get_current_user_id(), __( 'Default theme updated!', 'wp-mux' ), 30 );
				} else {
					set_transient( 'wp_mux_theme_error_' . get_current_user_id(), __( 'Failed to update default theme.', 'wp-mux' ), 30 );
				}

				$redirect_url = admin_url( 'admin.php?page=wp-mux-themes&action_completed=1' );
				wp_redirect( $redirect_url );
				exit;
				break;
		}
	}

	/**
	 * Handle AJAX request for creating direct upload.
	 *
	 * @since    1.0.0
	 */
	public function ajax_create_upload() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_mux_upload_nonce' ) ) {
			wp_die( __( 'Security check failed', 'wp-mux' ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions', 'wp-mux' ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();

		$upload = $api->create_direct_upload();

		if ( is_wp_error( $upload ) ) {
			wp_send_json_error( $upload->get_error_message() );
		}

		wp_send_json_success( $upload );
	}

	/**
	 * Handle AJAX request for loading videos.
	 *
	 * @since    1.0.0
	 */
	public function ajax_load_videos() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_mux_videos_nonce' ) ) {
			wp_die( __( 'Security check failed', 'wp-mux' ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions', 'wp-mux' ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();

		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$videos = $api->get_videos( 25, $page );

		if ( is_wp_error( $videos ) ) {
			wp_send_json_error( $videos->get_error_message() );
		}

		wp_send_json_success( $videos );
	}

	/**
	 * Handle AJAX request for deleting a video.
	 *
	 * @since    1.0.0
	 */
	public function ajax_delete_video() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_mux_videos_nonce' ) ) {
			wp_die( __( 'Security check failed', 'wp-mux' ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions', 'wp-mux' ) );
		}

		$asset_id = sanitize_text_field( $_POST['asset_id'] );
		if ( empty( $asset_id ) ) {
			wp_send_json_error( __( 'Invalid asset ID', 'wp-mux' ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();

		$result = $api->delete_video( $asset_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Video deleted successfully', 'wp-mux' ) );
	}

	/**
	 * Handle AJAX request for getting video details.
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_video_details() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_mux_videos_nonce' ) ) {
			wp_die( __( 'Security check failed', 'wp-mux' ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions', 'wp-mux' ) );
		}

		$asset_id = sanitize_text_field( $_POST['asset_id'] );
		if ( empty( $asset_id ) ) {
			wp_send_json_error( __( 'Invalid asset ID', 'wp-mux' ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();

		$video = $api->get_video( $asset_id );

		if ( is_wp_error( $video ) ) {
			wp_send_json_error( $video->get_error_message() );
		}

		wp_send_json_success( $video );
	}

	/**
	 * Handle AJAX request for saving a theme.
	 *
	 * @since    1.0.0
	 */
	public function ajax_save_theme() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_mux_themes_nonce' ) ) {
			wp_die( __( 'Security check failed', 'wp-mux' ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions', 'wp-mux' ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-theme-manager.php';
		$theme_manager = new Wp_Mux_Theme_Manager();

		$theme_id = sanitize_text_field( $_POST['theme_id'] );
		$name = sanitize_text_field( $_POST['name'] );
		$description = sanitize_textarea_field( $_POST['description'] );
		$controls = isset( $_POST['controls'] ) ? $_POST['controls'] : array();
		$styles = isset( $_POST['styles'] ) ? $_POST['styles'] : array();

		// Generate theme ID if creating new theme
		if ( empty( $theme_id ) ) {
			$theme_id = $theme_manager->generate_theme_id( $name );
		}

		$result = $theme_manager->save_theme( $theme_id, $name, $description, $controls, $styles );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( array(
			'theme_id' => $theme_id,
			'message' => __( 'Theme saved successfully', 'wp-mux' ),
		) );
	}

	/**
	 * Handle AJAX request for deleting a theme.
	 *
	 * @since    1.0.0
	 */
	public function ajax_delete_theme() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_mux_themes_nonce' ) ) {
			wp_die( __( 'Security check failed', 'wp-mux' ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions', 'wp-mux' ) );
		}

		$theme_id = sanitize_text_field( $_POST['theme_id'] );
		if ( empty( $theme_id ) ) {
			wp_send_json_error( __( 'Invalid theme ID', 'wp-mux' ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-theme-manager.php';
		$theme_manager = new Wp_Mux_Theme_Manager();

		$result = $theme_manager->delete_theme( $theme_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Theme deleted successfully', 'wp-mux' ) );
	}

	/**
	 * Handle AJAX request for setting default theme.
	 *
	 * @since    1.0.0
	 */
	public function ajax_set_default_theme() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_mux_themes_nonce' ) ) {
			wp_die( __( 'Security check failed', 'wp-mux' ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions', 'wp-mux' ) );
		}

		$theme_id = sanitize_text_field( $_POST['theme_id'] );
		if ( empty( $theme_id ) ) {
			wp_send_json_error( __( 'Invalid theme ID', 'wp-mux' ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-theme-manager.php';
		$theme_manager = new Wp_Mux_Theme_Manager();

		$result = $theme_manager->set_default_theme( $theme_id );

		if ( ! $result ) {
			wp_send_json_error( __( 'Failed to set default theme', 'wp-mux' ) );
		}

		wp_send_json_success( __( 'Default theme updated successfully', 'wp-mux' ) );
	}

	/**
	 * API settings section callback.
	 *
	 * @since    1.0.0
	 */
	public function api_settings_section_callback() {
		echo '<p>' . __( 'Enter your MUX API credentials below. You can find these in your MUX dashboard.', 'wp-mux' ) . '</p>';
	}

	/**
	 * Access token field callback.
	 *
	 * @since    1.0.0
	 */
	public function access_token_field_callback() {
		$access_token = get_option( 'wp_mux_access_token', '' );
		echo '<input type="text" id="wp_mux_access_token" name="wp_mux_access_token" value="' . esc_attr( $access_token ) . '" class="regular-text" placeholder="' . esc_attr__( 'Enter your MUX access token', 'wp-mux' ) . '" />';
		echo '<p class="description wp-mux-field-description">' . __( 'Your MUX access token. You can find this in your MUX dashboard under Settings > Access Tokens.', 'wp-mux' ) . '</p>';
	}

	/**
	 * Secret key field callback.
	 *
	 * @since    1.0.0
	 */
	public function secret_key_field_callback() {
		$secret_key = get_option( 'wp_mux_secret_key', '' );
		$placeholder_text = empty( $secret_key ) ? __( 'Enter your MUX secret key', 'wp-mux' ) : __( 'Secret key is set (hidden for security)', 'wp-mux' );
		$display_value = empty( $secret_key ) ? '' : '••••••••••••••••••••';

		echo '<div class="wp-mux-password-field-wrapper">';
		echo '<input type="password" id="wp_mux_secret_key" name="wp_mux_secret_key" value="' . esc_attr( $secret_key ) . '" class="regular-text" placeholder="' . esc_attr( $placeholder_text ) . '" />';
		echo '</div>';
		echo '<p class="description wp-mux-field-description">' . __( 'Your MUX secret key (kept private). This field is obfuscated for security.', 'wp-mux' ) . '</p>';
	}

	/**
	 * Enqueue block editor assets for Gutenberg support
	 *
	 * @since    1.0.0
	 */
	public function enqueue_block_editor_assets() {
		// Only enqueue mux-player once with consistent URL
		if ( ! wp_script_is( 'mux-player', 'enqueued' ) ) {
			wp_enqueue_script( 'mux-player', 'https://unpkg.com/@mux/mux-player', array(), null, false );
		}

		// Enqueue custom block editor script for MUX video handling
		wp_enqueue_script(
			$this->plugin_name . '-block-editor',
			plugin_dir_url( __FILE__ ) . 'js/wp-mux-block-editor.js',
			array( 'wp-dom-ready', 'wp-blocks', 'wp-hooks', 'mux-player' ),
			$this->version,
			false  // Load in head, not footer
		);

		// Add CSS for mux-video elements in editor
		wp_enqueue_style(
			$this->plugin_name . '-block-editor-style',
			plugin_dir_url( __FILE__ ) . 'css/wp-mux-admin.css',
			array(),
			$this->version
		);

		// Add inline CSS specifically for mux elements in editor
		$inline_css = '
			mux-video, mux-player {
				display: block !important;
				width: 100% !important;
				max-width: 100% !important;
				height: auto !important;
				background: #000;
				min-height: 200px;
			}
			.wp-block-video mux-video, .wp-block-video mux-player {
				width: 100% !important;
				height: auto !important;
			}
			.block-editor-block-list__block mux-video, .block-editor-block-list__block mux-player {
				width: 100% !important;
				min-height: 200px;
			}
		';
		wp_add_inline_style( $this->plugin_name . '-block-editor-style', $inline_css );

		// Add inline script to ensure MUX elements are working
		$inline_js = '
			console.log("WP MUX: Block editor assets loaded");

			// Make sure custom elements are supported
			if (typeof customElements !== "undefined") {
				console.log("WP MUX: Custom elements supported");
				setTimeout(function() {
					const muxVideoRegistered = customElements.get("mux-video");
					const muxPlayerRegistered = customElements.get("mux-player");

					console.log("WP MUX: mux-video registered:", !!muxVideoRegistered);
					console.log("WP MUX: mux-player registered:", !!muxPlayerRegistered);

					if (!muxVideoRegistered && !muxPlayerRegistered) {
						console.log("WP MUX: Neither MUX element registered - libraries may not be loaded");
					}
				}, 1000);
			}
		';
		wp_add_inline_script( $this->plugin_name . '-block-editor', $inline_js );
	}

	/**
	 * Allow mux-video and mux-player elements in WordPress content
	 *
	 * @since    1.0.0
	 */
	public function allow_mux_video_elements() {
		global $allowedposttags;

		// Add mux-video and mux-player to allowed HTML tags
		$mux_attributes = array(
			'playback-id' => true,
			'controls' => true,
			'stream-type' => true,
			'poster' => true,
			'width' => true,
			'height' => true,
			'style' => true,
			'class' => true,
			'id' => true,
			'env-key' => true,
			'metadata-video-title' => true,
			'metadata-viewer-user-id' => true,
			'metadata-video-id' => true,
		);

		$allowedposttags['mux-video'] = $mux_attributes;
		$allowedposttags['mux-player'] = $mux_attributes;

		// Also add to wp_kses filters
		add_filter( 'wp_kses_allowed_html', array( $this, 'allow_mux_video_in_kses' ), 10, 2 );
	}

	/**
	 * Allow mux-video and mux-player in wp_kses filtering
	 *
	 * @since    1.0.0
	 */
	public function allow_mux_video_in_kses( $tags, $context ) {
		if ( $context === 'post' ) {
			$mux_attributes = array(
				'playback-id' => true,
				'controls' => true,
				'stream-type' => true,
				'poster' => true,
				'width' => true,
				'height' => true,
				'style' => true,
				'class' => true,
				'id' => true,
				'env-key' => true,
				'metadata-video-title' => true,
				'metadata-viewer-user-id' => true,
				'metadata-video-id' => true,
			);

			$tags['mux-video'] = $mux_attributes;
			$tags['mux-player'] = $mux_attributes;
		}
		return $tags;
	}

}
