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
	// Enqueue Mux Player for admin/media modal preview
	wp_enqueue_script( 'mux-player', 'https://unpkg.com/@mux/mux-player@latest/dist/mux-player.js', array(), null, true );
	// Custom JS for media library enhancements
	wp_enqueue_script( 'wp-mux-media-badge', plugin_dir_url( __FILE__ ) . 'js/wp-mux-media-badge.js', array( 'media-views' ), $this->version, true );

		// Enqueue MUX player on videos page
		$screen = get_current_screen();
		if ( $screen && $screen->id === 'toplevel_page_wp-mux-videos' ) {
			wp_enqueue_script( 'mux-player', 'https://unpkg.com/@mux/mux-player', array(), null, true );
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

}
