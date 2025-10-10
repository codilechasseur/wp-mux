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

		// Add admin notices for MUX upload success
		add_action( 'admin_notices', array( $this, 'show_mux_upload_notices' ) );

	}

	/**
	 * Show MUX upload success notices.
	 *
	 * @since    1.1.0
	 */
	public function show_mux_upload_notices() {
		$user_id = get_current_user_id();
		$transient_key = 'wp_mux_upload_success_' . $user_id;
		$success_data = get_transient( $transient_key );

		// Debug: always show if we're on upload page
		$screen = get_current_screen();
		error_log( '[WP MUX] Checking for success notice - Screen: ' . ($screen ? $screen->id : 'none') . ', User: ' . $user_id . ', Transient key: ' . $transient_key );
		error_log( '[WP MUX] Retrieved success data: ' . print_r($success_data, true) );

		if ( $success_data ) {
			error_log( '[WP MUX] SUCCESS NOTICE WILL BE DISPLAYED' );
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p><strong>' . esc_html__( 'MUX Upload Successful!', 'wp-mux' ) . '</strong> ';
			echo sprintf(
				esc_html__( 'Video "%s" has been uploaded to MUX and added to your media library (ID: %d).', 'wp-mux' ),
				esc_html( $success_data['filename'] ),
				intval( $success_data['attachment_id'] )
			);
			echo '</p></div>';

			// Delete the transient so it only shows once
			delete_transient( $transient_key );
			error_log( '[WP MUX] Success notice displayed and transient deleted' );
		} else {
			error_log( '[WP MUX] No success data found for transient key: ' . $transient_key );
		}
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

	// Enqueue migration styles on migration page
	if ( $screen && $screen->id === 'mux-videos_page_wp-mux-migration' ) {
		wp_enqueue_style( 'wp-mux-migration', plugin_dir_url( __FILE__ ) . 'css/wp-mux-migration.css', array(), $this->version );
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

		// Enqueue media override script for better upload handling
		wp_enqueue_script( 'wp-mux-media-override', plugin_dir_url( __FILE__ ) . 'js/wp-mux-media-override.js', array( 'media-views', 'plupload' ), $this->version, true );
	}

	// Enqueue media library refresh script more broadly (just jQuery dependency)
	if ( $screen && (
		$screen->id === 'upload' ||
		$screen->id === 'media' ||
		$screen->base === 'media' ||
		$screen->base === 'upload' ||
		strpos( $screen->base, 'media' ) !== false
	) ) {
		wp_enqueue_script( 'wp-mux-media-library', plugin_dir_url( __FILE__ ) . 'js/wp-mux-media-library.js', array( 'jquery' ), $this->version, true );
		wp_enqueue_style( 'wp-mux-media-status', plugin_dir_url( __FILE__ ) . 'css/wp-mux-media-status.css', array(), $this->version );

		// Enqueue media stats script and styles
		wp_enqueue_script( 'wp-mux-media-stats', plugin_dir_url( __FILE__ ) . 'js/wp-mux-media-stats.js', array( 'jquery', 'media-views' ), $this->version, true );
		wp_enqueue_style( 'wp-mux-media-stats', plugin_dir_url( __FILE__ ) . 'css/wp-mux-media-stats.css', array(), $this->version );

		// Provide data to the media stats script
		wp_localize_script( 'wp-mux-media-stats', 'wpMuxAnalytics', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wp_mux_analytics_nonce' ),
			'strings' => array(
				'refreshing' => __( 'Refreshing...', 'wp-mux' ),
				'refreshed' => __( 'Stats refreshed!', 'wp-mux' ),
				'error' => __( 'Failed to refresh stats', 'wp-mux' )
			)
		) );

		// Provide data to the media library script
		wp_localize_script( 'wp-mux-media-library', 'wpMuxMedia', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wp_mux_queue_status_nonce' ),
			'strings' => array(
				'queued' => __( 'Queued for MUX', 'wp-mux' ),
				'processing' => __( 'Processing...', 'wp-mux' ),
				'complete' => __( 'Ready', 'wp-mux' ),
				'failed' => __( 'Failed', 'wp-mux' )
			)
		) );

		// Add debug info to help troubleshoot
		wp_add_inline_script( 'wp-mux-media-library',
			'console.log("WP MUX: Script enqueued on screen: ' . $screen->id . ', base: ' . $screen->base . '");',
			'before'
		);
	}

	// Enqueue theme management script on themes page
	if ( $screen && $screen->id === 'mux-videos_page_wp-mux-themes' ) {
		wp_enqueue_script( 'wp-mux-themes', plugin_dir_url( __FILE__ ) . 'js/wp-mux-themes.js', array( 'jquery' ), $this->version, true );
	}

	// Enqueue migration script on migration page
	if ( $screen && $screen->id === 'mux-videos_page_wp-mux-migration' ) {
		wp_enqueue_script( 'wp-mux-migration', plugin_dir_url( __FILE__ ) . 'js/wp-mux-migration.js', array( 'jquery' ), $this->version, true );

		// Localize script with necessary data
		wp_localize_script( 'wp-mux-migration', 'wpMuxMigration', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wp_mux_migration_nonce' ),
			'strings' => array(
				'noVideosFound' => __( 'No local videos found that need migration.', 'wp-mux' ),
				'pending' => __( 'Pending', 'wp-mux' ),
				'processing' => __( 'Processing...', 'wp-mux' ),
				'success' => __( 'Success', 'wp-mux' ),
				'failed' => __( 'Failed', 'wp-mux' ),
			),
		) );
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

		// Add submenu for bulk migration
		add_submenu_page(
			'wp-mux-videos',
			__( 'Migrate Local Videos', 'wp-mux' ),
			__( 'Migrate Videos', 'wp-mux' ),
			'manage_options',
			'wp-mux-migration',
			array( $this, 'display_migration_page' )
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

		// Register override upload setting
		register_setting(
			'wp_mux_settings_group',
			'wp_mux_override_video_upload',
			array(
				'type' => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default' => false,
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

		// Add override upload field
		add_settings_field(
			'wp_mux_override_video_upload',
			__( 'Override Video Uploads', 'wp-mux' ),
			array( $this, 'override_video_upload_field_callback' ),
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
	 * Display the migration page.
	 *
	 * @since    1.2.0
	 */
	public function display_migration_page() {
		include_once plugin_dir_path( __FILE__ ) . 'partials/wp-mux-admin-migration.php';
	}

	/**
	 * Check if there are local videos that can be migrated.
	 *
	 * @since    1.2.0
	 * @return   bool    True if local videos exist, false otherwise.
	 */
	public function has_local_videos() {
		// Use a transient to cache this check for better performance
		$cache_key = 'wp_mux_has_local_videos';
		$cached_result = get_transient( $cache_key );

		if ( $cached_result !== false ) {
			return (bool) $cached_result;
		}

		// Query for video attachments that are NOT already migrated to MUX
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'post_mime_type' => array( 'video/mp4', 'video/mpeg', 'video/quicktime', 'video/avi', 'video/webm', 'video/ogg' ),
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_mux_asset_id',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_mux_asset_id',
					'value'   => '',
					'compare' => '=',
				),
			),
		);

		$query = new WP_Query( $args );
		$has_videos = $query->have_posts();

		// Cache for 5 minutes
		set_transient( $cache_key, $has_videos ? 1 : 0, 5 * MINUTE_IN_SECONDS );

		return $has_videos;
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
	 * AJAX handler to refresh video analytics.
	 *
	 * @since    1.1.0
	 */
	public function ajax_refresh_video_analytics() {
		// Verify nonce
		check_ajax_referer( 'wp_mux_analytics_nonce', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Insufficient permissions', 'wp-mux' ),
			) );
		}

		$attachment_id = intval( $_POST['attachment_id'] ?? 0 );
		if ( empty( $attachment_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid attachment ID', 'wp-mux' ),
			) );
		}

		// Verify this is a MUX video
		$mux_asset_id = get_post_meta( $attachment_id, '_mux_asset_id', true );
		if ( empty( $mux_asset_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'This is not a MUX video', 'wp-mux' ),
			) );
		}

		// Force refresh analytics
		$analytics = $this->get_video_analytics( $attachment_id, true );

		if ( empty( $analytics ) ) {
			wp_send_json_error( array(
				'message' => __( 'Failed to fetch analytics. The video may not have any views yet.', 'wp-mux' ),
			) );
		}

		// Format for display
		$formatted = $this->format_analytics_for_display( $analytics );

		wp_send_json_success( array(
			'message' => __( 'Analytics refreshed successfully', 'wp-mux' ),
			'analytics' => $formatted,
		) );
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
	 * Override video upload field callback.
	 *
	 * @since    1.1.0
	 */
	public function override_video_upload_field_callback() {
		$checked = checked( 1, get_option( 'wp_mux_override_video_upload', 0 ), false );
		echo '<input type="checkbox" id="wp_mux_override_video_upload" name="wp_mux_override_video_upload" value="1" ' . $checked . ' /> ';
		echo '<label for="wp_mux_override_video_upload">' . esc_html__( 'If enabled, all video uploads in the media library will be sent to MUX instead of stored locally.', 'wp-mux' ) . '</label>';
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

	/**
	 * Log debug info for MUX upload override.
	 */
	private function mux_debug_log( $message, $context = [] ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log = '[WP MUX] ' . $message;
			if ( ! empty( $context ) ) {
				$log .= ' | ' . json_encode( $context );
			}
			error_log( $log );
		}
	}

	/**
	 * Intercept video uploads and send to MUX if override is enabled.
	 *
	 * @param array $file Uploaded file array.
	 * @return array Modified file array or error.
	 * @since 1.1.0
	 */
	/**
	 * Check if a file or mime type is a video file.
	 *
	 * @param array|string $file_or_mime The file array or mime type string.
	 * @return bool True if video file, false otherwise.
	 * @since 1.1.0
	 */
	private function is_video_file( $file_or_mime ) {
		$video_mimes = array(
			'video/mp4',
			'video/mpeg',
			'video/quicktime',
			'video/avi',
			'video/x-msvideo',
			'video/wmv',
			'video/x-ms-wmv',
			'video/webm',
			'video/ogg',
			'video/x-flv',
			'video/flv',
			'video/mkv',
			'video/x-matroska'
		);

		// Handle both file array and direct mime type string
		if (is_array($file_or_mime)) {
			return isset( $file_or_mime['type'] ) && in_array( $file_or_mime['type'], $video_mimes, true );
		} else {
			return in_array( $file_or_mime, $video_mimes, true );
		}
	}

	/**
	 * Handle video upload via AJAX to completely replace WordPress upload processing.
	 *
	 * @since 1.1.0
	 */
	public function handle_video_upload_ajax() {
		$this->mux_debug_log( 'AJAX Handler called - handle_video_upload_ajax' );

		// Check if override is enabled
		$override_enabled = get_option( 'wp_mux_override_video_upload', '0' );
		$this->mux_debug_log( 'Override setting in AJAX handler', [ 'enabled' => $override_enabled ] );

		if ( $override_enabled !== '1' && $override_enabled !== 1 && $override_enabled !== true ) {
			$this->mux_debug_log( 'Override not enabled in AJAX, letting WordPress handle normally' );
			// Let WordPress handle normally
			return;
		}

		// Check if this is a video upload
		if ( empty( $_FILES['async-upload'] ) ) {
			$this->mux_debug_log( 'No async-upload file found' );
			return;
		}

		$file = $_FILES['async-upload'];
		$this->mux_debug_log( 'File found in AJAX handler', $file );

		// Check if it's a video file
		if ( ! $this->is_video_file( $file ) ) {
			$this->mux_debug_log( 'Not a video file, letting WordPress handle' );
			return;
		}

		$this->mux_debug_log( 'Intercepting AJAX video upload', $file );

		// Process with MUX
		$result = $this->process_mux_video_upload( $file );

		if ( is_wp_error( $result ) ) {
			$this->mux_debug_log( 'MUX upload failed', [ 'error' => $result->get_error_message() ] );
			wp_send_json_error( array(
				'message' => $result->get_error_message()
			));
		} else {
			$this->mux_debug_log( 'MUX upload successful', [ 'attachment_id' => $result ] );
			// Get attachment data for response
			$attachment = get_post( $result );
			if ( $attachment ) {
				// Format response similar to WordPress media upload
				$response = array(
					'success' => true,
					'data' => array(
						'id' => $result,
						'title' => $attachment->post_title,
						'filename' => $attachment->post_name,
						'url' => wp_get_attachment_url( $result ),
						'type' => 'video',
						'subtype' => 'mux',
						'icon' => get_post_meta( $result, '_mux_thumbnail_url', true ),
						'dateFormatted' => get_the_date( '', $result ),
						'nonces' => array(
							'update' => wp_create_nonce( 'update-post_' . $result ),
							'delete' => wp_create_nonce( 'delete-post_' . $result ),
							'edit' => wp_create_nonce( 'edit-post_' . $result )
						)
					)
				);
				$this->mux_debug_log( 'Sending JSON response', $response );
				wp_send_json( $response );
			} else {
				$this->mux_debug_log( 'Failed to get attachment after MUX upload' );
				wp_send_json_error( array(
					'message' => 'Failed to create attachment'
				));
			}
		}
	}

	/**
	 * Queue video attachment for MUX processing after WordPress upload
	 */
	public function queue_video_for_mux_processing($attachment_id) {
		$this->mux_debug_log('Begin queue_video_for_mux_processing', ['attachment_id' => $attachment_id]);

		// Check if MUX override is enabled
		$override_enabled = get_option('wp_mux_override_video_upload', '0');
		if ($override_enabled !== '1' && $override_enabled !== 1 && $override_enabled !== true) {
			return;
		}

		// Check if this is a video file
		$file_path = get_attached_file($attachment_id);
		$mime_type = get_post_mime_type($attachment_id);

		if (!$this->is_video_file($mime_type)) {
			return;
		}

		$this->mux_debug_log('Queueing video for MUX processing', [
			'attachment_id' => $attachment_id,
			'file_path' => $file_path,
			'mime_type' => $mime_type
		]);

		// Add to processing queue
		$queue = get_option('wp_mux_processing_queue', array());
		$queue[] = array(
			'attachment_id' => $attachment_id,
			'file_path' => $file_path,
			'mime_type' => $mime_type,
			'status' => 'queued',
			'queued_at' => current_time('mysql')
		);
		update_option('wp_mux_processing_queue', $queue);

		// Set initial meta to show it's queued for MUX
		update_post_meta($attachment_id, '_wp_mux_status', 'queued');
		update_post_meta($attachment_id, '_wp_mux_queued_at', current_time('mysql'));
		update_post_meta($attachment_id, '_mux_from_upload', '1'); // Mark as uploaded via WordPress
		update_post_meta($attachment_id, '_mux_processing', '1'); // Mark as processing to prevent sync duplicates

		// Schedule immediate processing
		wp_schedule_single_event(time(), 'wp_mux_process_queue_event');

		// Also try to process immediately via background request
		$this->trigger_background_queue_processing();
	}

	/**
	 * Trigger background queue processing via non-blocking request
	 */
	private function trigger_background_queue_processing() {
		$url = admin_url('admin-ajax.php');
		$args = array(
			'timeout' => 0.01, // Non-blocking
			'blocking' => false,
			'sslverify' => false,
			'body' => array(
				'action' => 'wp_mux_process_queue',
				'background' => '1'
			)
		);

		wp_remote_post($url, $args);
	}

	/**
	 * Process the MUX upload queue via AJAX or cron
	 */
	public function process_mux_queue() {
		$this->mux_debug_log('Begin process_mux_queue');

		$queue = get_option('wp_mux_processing_queue', array());

		if (empty($queue)) {
			$this->mux_debug_log('Queue is empty');
			if (defined('DOING_AJAX') && DOING_AJAX) {
				wp_send_json_error('Queue is empty');
			}
			return;
		}

		// Process first item in queue
		$item = array_shift($queue);
		update_option('wp_mux_processing_queue', $queue);

		$attachment_id = $item['attachment_id'];
		$file_path = $item['file_path'];

		$this->mux_debug_log('Processing queue item', $item);

		try {
			// Update status to processing
			update_post_meta($attachment_id, '_wp_mux_status', 'processing');
			update_post_meta($attachment_id, '_wp_mux_processing_started', current_time('mysql'));

			// If file path is empty, try to get it from attachment
			if (empty($file_path)) {
				$file_path = get_attached_file($attachment_id);
				$this->mux_debug_log('File path was empty, retrieved from attachment', ['file_path' => $file_path]);
			}

			// Verify file still exists
			if (empty($file_path) || !file_exists($file_path)) {
				throw new Exception('Source file not found or path is empty: ' . $file_path);
			}

			// Initialize MUX API
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp-mux-api.php';
			$api = new Wp_Mux_Api();

			if (!$api->is_configured()) {
				throw new Exception('MUX API credentials are not configured');
			}

			// Create MUX direct upload
			$upload = $api->create_direct_upload();
			$this->mux_debug_log('create_direct_upload result', $upload);

			if (is_wp_error($upload) || empty($upload['data']['url'])) {
				$error_msg = is_wp_error($upload) ? $upload->get_error_message() : 'Failed to create MUX upload';
				throw new Exception($error_msg);
			}

			$upload_url = $upload['data']['url'];
			$upload_id = $upload['data']['id'];

			// Upload file to MUX
			$upload_result = wp_remote_request($upload_url, array(
				'headers' => array(
					'Content-Type' => $item['mime_type'],
				),
				'body' => file_get_contents($file_path),
				'timeout' => 300, // 5 minutes for large files
				'method' => 'PUT',
			));

			if (is_wp_error($upload_result)) {
				throw new Exception('MUX upload error: ' . $upload_result->get_error_message());
			}

			$response_code = wp_remote_retrieve_response_code($upload_result);
			if ($response_code < 200 || $response_code >= 300) {
				throw new Exception('Failed to upload video to MUX. Response code: ' . $response_code);
			}

			// Wait a moment for MUX to process the upload, then get asset ID
			sleep(2);
			$upload_status = $api->get_upload($upload_id);
			$asset_id = null;

			if (!is_wp_error($upload_status) && !empty($upload_status['data']['asset_id'])) {
				$asset_id = $upload_status['data']['asset_id'];

				// Get additional asset details including playback ID
				$asset_details = $api->get_video($asset_id);
				if (!is_wp_error($asset_details) && !empty($asset_details['data'])) {
					$playback_id = $asset_details['data']['playback_ids'][0]['id'] ?? '';
					if ($playback_id) {
						update_post_meta($attachment_id, '_mux_playback_id', $playback_id);
					}
				}
			}

			// Update attachment with MUX data
			update_post_meta($attachment_id, '_mux_upload_id', $upload_id);
			if ($asset_id) {
				update_post_meta($attachment_id, '_mux_asset_id', $asset_id);
			}
			update_post_meta($attachment_id, '_wp_mux_status', 'complete');
			update_post_meta($attachment_id, '_wp_mux_completed_at', current_time('mysql'));

			// Keep the processing flag until we have the asset details
			// This prevents the media sync from creating a duplicate

			// Update attachment to use MUX mime type
			wp_update_post(array(
				'ID' => $attachment_id,
				'post_mime_type' => 'video/mux'
			));

			// Delete local file if upload successful (optional - you may want to keep it)
			// if (file_exists($file_path)) {
			//     unlink($file_path);
			// }

			$this->mux_debug_log('MUX processing complete', [
				'attachment_id' => $attachment_id,
				'mux_upload_id' => $upload_id
			]);

			if (defined('DOING_AJAX') && DOING_AJAX) {
				wp_send_json_success(array(
					'message' => 'Video uploaded to MUX successfully',
					'attachment_id' => $attachment_id,
					'mux_upload_id' => $upload_id
				));
			}

		} catch (Exception $e) {
			$this->mux_debug_log('MUX processing failed', [
				'attachment_id' => $attachment_id,
				'error' => $e->getMessage()
			]);

			// Update status to failed
			update_post_meta($attachment_id, '_wp_mux_status', 'failed');
			update_post_meta($attachment_id, '_wp_mux_error', $e->getMessage());
			update_post_meta($attachment_id, '_wp_mux_failed_at', current_time('mysql'));

			if (defined('DOING_AJAX') && DOING_AJAX) {
				wp_send_json_error(array(
					'message' => 'MUX upload failed: ' . $e->getMessage(),
					'attachment_id' => $attachment_id
				));
			}
		}
	}

	/**
	 * Get MUX queue status for an attachment via AJAX
	 */
	public function get_queue_status() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wp_mux_queue_status_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! isset( $_POST['attachment_id'] ) ) {
			wp_send_json_error( 'No attachment ID provided' );
		}

		$attachment_id = intval( $_POST['attachment_id'] );
		$status = get_post_meta( $attachment_id, '_wp_mux_status', true );
		$error = get_post_meta( $attachment_id, '_wp_mux_error', true );

		if ( empty( $status ) ) {
			wp_send_json_error( 'No MUX status found' );
		}

		wp_send_json_success( array(
			'status' => $status,
			'error' => $error,
			'attachment_id' => $attachment_id
		) );
	}

	/**
	 * Get MUX attachment info including thumbnail URL via AJAX
	 */
	public function get_attachment_info() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'wp_mux_queue_status_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! isset( $_POST['attachment_id'] ) ) {
			wp_send_json_error( 'No attachment ID provided' );
		}

		$attachment_id = intval( $_POST['attachment_id'] );
		$asset_id = get_post_meta( $attachment_id, '_mux_asset_id', true );
		$playback_id = get_post_meta( $attachment_id, '_mux_playback_id', true );

		if ( empty( $asset_id ) && empty( $playback_id ) ) {
			wp_send_json_error( 'No MUX asset info found' );
		}

		$thumbnail_url = '';
		if ( ! empty( $playback_id ) ) {
			$thumbnail_url = "https://image.mux.com/{$playback_id}/thumbnail.jpg?width=300&height=300&fit_mode=smartcrop";
		}

		wp_send_json_success( array(
			'attachment_id' => $attachment_id,
			'asset_id' => $asset_id,
			'playback_id' => $playback_id,
			'thumbnail_url' => $thumbnail_url
		) );
	}

	/**
	 * Process MUX queue via cron event
	 */
	public function process_mux_queue_cron() {
		$this->process_mux_queue();
	}

	/**
	 * Handle heartbeat to regularly process queue
	 */
	public function heartbeat_process_queue($response, $data) {
		// Only process if there are items in queue and we're on media library page
		$queue = get_option('wp_mux_processing_queue', array());
		if (!empty($queue)) {
			// Trigger background processing
			$this->trigger_background_queue_processing();

			// Return queue status for any admin pages
			$response['wp_mux_queue_size'] = count($queue);
		}

		return $response;
	}

	/**
	 * Prepare MUX attachment data for JavaScript (AJAX responses).
	 *
	 * @param array $response Array of attachment data.
	 * @param WP_Post $attachment Attachment post object.
	 * @param array $meta Array of attachment metadata.
	 * @return array Modified attachment data.
	 * @since 1.1.0
	 */
	public function prepare_mux_attachment_for_js( $response, $attachment, $meta ) {
		// Check for both MUX videos and videos with MUX status
		$mux_status = get_post_meta($attachment->ID, '_wp_mux_status', true);
		$is_mux_video = ($attachment->post_mime_type === 'video/mux') || !empty($mux_status);

		if ($is_mux_video) {
			$this->mux_debug_log( 'Preparing MUX attachment for JS', [ 'attachment_id' => $attachment->ID, 'mux_status' => $mux_status ] );

			// Override the response data for MUX videos
			$response['type'] = 'video';
			$response['subtype'] = 'mux';

			// Get MUX-specific metadata
			$mux_upload_id = get_post_meta( $attachment->ID, '_mux_upload_id', true );
			$mux_processing = get_post_meta( $attachment->ID, '_mux_processing', true );
			$mux_thumbnail_url = get_post_meta( $attachment->ID, '_mux_thumbnail_url', true );
			$mux_error = get_post_meta( $attachment->ID, '_wp_mux_error', true );
			$mux_asset_id = get_post_meta( $attachment->ID, '_mux_asset_id', true );
			$mux_playback_id = get_post_meta( $attachment->ID, '_mux_playback_id', true );

			// Set the playback URL for the "View" link
			if ( ! empty( $mux_playback_id ) ) {
				$playback_url = 'https://stream.mux.com/' . $mux_playback_id . '.m3u8';
				$response['url'] = $playback_url;

				// Get the attachment page URL for the View link
				$attachment_url = get_attachment_link( $attachment->ID );
				$response['link'] = $attachment_url;

				// Set noembed property - WordPress uses this for the View link
				$response['noembed'] = array(
					'html' => sprintf(
						'<mux-player playback-id="%s" metadata-video-title="%s" style="width: 100%%; max-width: 100%%;"></mux-player>',
						esc_attr( $mux_playback_id ),
						esc_attr( $attachment->post_title )
					)
				);
			}

			// Set status-specific captions and states
			switch ($mux_status) {
				case 'queued':
					$response['caption'] = __( 'Queued for MUX processing...', 'wp-mux' );
					$response['uploadedToThisPost'] = false;
					break;
				case 'processing':
					$response['caption'] = __( 'Processing video for MUX...', 'wp-mux' );
					$response['uploadedToThisPost'] = false;
					break;
				case 'failed':
					$response['caption'] = __( 'MUX processing failed', 'wp-mux' );
					if ($mux_error) {
						$response['caption'] .= ': ' . $mux_error;
					}
					break;
				case 'complete':
					// Don't set a caption for complete videos - let WordPress use the default
					$response['mime'] = 'video/mux';
					break;
				default:
					if ($mux_processing) {
						$response['caption'] = __( 'Processing video...', 'wp-mux' );
						$response['uploadedToThisPost'] = false;
					}
					break;
			}

			if ( $mux_thumbnail_url ) {
				// Use thumbnail for display preview in media library
				$response['image'] = array(
					'src' => $mux_thumbnail_url,
					'width' => 640,
					'height' => 360
				);
				$response['sizes'] = array(
					'full' => array(
						'url' => $mux_thumbnail_url,
						'width' => 640,
						'height' => 360,
						'orientation' => 'landscape'
					),
					'thumbnail' => array(
						'url' => $mux_thumbnail_url,
						'width' => 150,
						'height' => 150,
						'orientation' => 'landscape'
					)
				);
				// Keep the playback URL if we set it earlier
				if ( empty( $response['url'] ) ) {
					$response['url'] = $mux_thumbnail_url;
				}
			}

			if ( $mux_processing ) {
				$response['uploadedToThisPost'] = false;
				$response['caption'] = __( 'Processing video...', 'wp-mux' );
			}

			$response['mux_upload_id'] = $mux_upload_id;
			$response['mux_processing'] = $mux_processing;

			// Add analytics data if video is complete
			if ( $mux_status === 'complete' ) {
				$analytics = $this->get_video_analytics( $attachment->ID, false );

				if ( ! empty( $analytics ) && is_array( $analytics ) ) {
					$response['mux_analytics'] = $this->format_analytics_for_display( $analytics );
					$response['mux_has_analytics'] = true;
				} else {
					// Still set flag but with empty/zero analytics
					$response['mux_analytics'] = $this->format_analytics_for_display( null );
					$response['mux_has_analytics'] = true; // Show section even with zero views
				}
			}
		}

		return $response;
	}

	/**
	 * Get cached video analytics or fetch fresh data.
	 *
	 * @since    1.1.0
	 * @param    int    $attachment_id    The attachment post ID.
	 * @param    bool   $force_refresh    Whether to force a fresh API call.
	 * @return   array|null               Analytics data or null if unavailable.
	 */
	public function get_video_analytics( $attachment_id, $force_refresh = false ) {
		$mux_asset_id = get_post_meta( $attachment_id, '_mux_asset_id', true );

		if ( empty( $mux_asset_id ) ) {
			return null;
		}

		// Check cache unless force refresh
		if ( ! $force_refresh ) {
			$cached_stats = get_post_meta( $attachment_id, '_mux_analytics_cache', true );
			$cache_timestamp = get_post_meta( $attachment_id, '_mux_analytics_timestamp', true );

			// Cache valid for 1 hour
			if ( ! empty( $cached_stats ) && ! empty( $cache_timestamp ) ) {
				$cache_age = current_time( 'timestamp' ) - intval( $cache_timestamp );
				if ( $cache_age < 3600 ) {
					return $cached_stats;
				}
			}
		}

		// Fetch fresh analytics from MUX API
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();

		$analytics = $api->get_video_analytics( $mux_asset_id );

		if ( ! is_wp_error( $analytics ) && is_array( $analytics ) ) {
			// Cache the results
			update_post_meta( $attachment_id, '_mux_analytics_cache', $analytics );
			update_post_meta( $attachment_id, '_mux_analytics_timestamp', current_time( 'timestamp' ) );

			return $analytics;
		}

		return null;
	}

	/**
	 * Clear analytics cache for a specific attachment.
	 *
	 * @since    1.1.0
	 * @param    int    $attachment_id    The attachment post ID.
	 * @return   bool                     Whether cache was cleared.
	 */
	public function clear_analytics_cache( $attachment_id ) {
		delete_post_meta( $attachment_id, '_mux_analytics_cache' );
		delete_post_meta( $attachment_id, '_mux_analytics_timestamp' );
		return true;
	}

	/**
	 * Format analytics data for display.
	 *
	 * @since    1.1.0
	 * @param    array    $analytics    Raw analytics data.
	 * @return   array                  Formatted analytics data.
	 */
	public function format_analytics_for_display( $analytics ) {
		if ( empty( $analytics ) || ! is_array( $analytics ) ) {
			return array(
				'total_views' => '0',
				'total_watch_time' => '0 min',
				'average_view_duration' => '0 min',
				'unique_viewers' => '0',
				'video_quality_score' => 'N/A',
				'last_updated' => 'Never',
			);
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';

		$formatted = array(
			'total_views' => number_format( intval( $analytics['total_views'] ?? 0 ) ),
			'total_watch_time' => Wp_Mux_Api::format_watch_time( intval( $analytics['total_watch_time'] ?? 0 ) ),
			'average_view_duration' => Wp_Mux_Api::format_duration( floatval( $analytics['average_view_duration'] ?? 0 ) ),
			'unique_viewers' => number_format( intval( $analytics['unique_viewers'] ?? 0 ) ),
			'video_quality_score' => ! empty( $analytics['video_quality_score'] ) ? round( $analytics['video_quality_score'], 1 ) . '%' : 'N/A',
			'rebuffer_percentage' => ! empty( $analytics['rebuffer_percentage'] ) ? round( $analytics['rebuffer_percentage'], 1 ) . '%' : '0%',
			'video_startup_time' => ! empty( $analytics['video_startup_time'] ) ? round( $analytics['video_startup_time'] ) . 'ms' : 'N/A',
			'last_updated' => ! empty( $analytics['last_updated'] ) ?
				human_time_diff( $analytics['last_updated'], current_time( 'timestamp' ) ) . ' ago' :
				'Never',
		);

		return $formatted;
	}

	/**
	 * Cron job handler to refresh analytics for all MUX videos.
	 *
	 * @since    1.1.0
	 */
	public function cron_refresh_analytics() {
		// Get all MUX video attachments
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 50, // Process 50 videos per run
			'meta_query'     => array(
				array(
					'key'     => '_mux_asset_id',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_wp_mux_status',
					'value'   => 'complete',
					'compare' => '=',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$attachments = get_posts( $args );

		foreach ( $attachments as $attachment ) {
			// Check cache age - only refresh if older than 1 hour
			$cache_timestamp = get_post_meta( $attachment->ID, '_mux_analytics_timestamp', true );
			$cache_age = current_time( 'timestamp' ) - intval( $cache_timestamp );

			// Refresh if cache is older than 1 hour
			if ( empty( $cache_timestamp ) || $cache_age > 3600 ) {
				$this->get_video_analytics( $attachment->ID, true );

				// Add a small delay to avoid rate limiting
				usleep( 100000 ); // 0.1 second delay
			}
		}
	}

	/**
	 * Debug function to log all AJAX requests
	 */
	public function debug_all_ajax() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			error_log( 'WP-MUX: AJAX request detected - action = ' . ( isset( $_POST['action'] ) ? $_POST['action'] : ( isset( $_GET['action'] ) ? $_GET['action'] : 'not set' ) ) );
		}
	}

	/**
	 * AJAX handler to scan for local videos that can be migrated.
	 *
	 * @since    1.2.0
	 */
	public function ajax_scan_local_videos() {
		// Verify nonce
		check_ajax_referer( 'wp_mux_migration_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'wp-mux' ) ) );
		}

		// Query for local video attachments that haven't been migrated
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'post_mime_type' => array( 'video/mp4', 'video/mpeg', 'video/quicktime', 'video/avi', 'video/webm', 'video/ogg', 'video/x-msvideo', 'video/wmv', 'video/mkv', 'video/x-matroska' ),
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_mux_asset_id',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_mux_asset_id',
					'value'   => '',
					'compare' => '=',
				),
			),
			'orderby'        => 'date',
			'order'          => 'ASC',
		);

		$attachments = get_posts( $args );
		$videos = array();
		$total_size = 0;

		foreach ( $attachments as $attachment ) {
			$file_path = get_attached_file( $attachment->ID );
			$file_size = file_exists( $file_path ) ? filesize( $file_path ) : 0;
			$total_size += $file_size;

			$videos[] = array(
				'id'          => $attachment->ID,
				'title'       => $attachment->post_title,
				'filename'    => basename( $file_path ),
				'size'        => $file_size,
				'size_formatted' => size_format( $file_size ),
				'date'        => $attachment->post_date,
				'date_formatted' => get_the_date( '', $attachment->ID ),
				'mime_type'   => $attachment->post_mime_type,
				'url'         => wp_get_attachment_url( $attachment->ID ),
			);
		}

		// Clear the cache now that we've scanned
		delete_transient( 'wp_mux_has_local_videos' );

		wp_send_json_success( array(
			'videos'     => $videos,
			'total'      => count( $videos ),
			'total_size' => $total_size,
			'total_size_formatted' => size_format( $total_size ),
		) );
	}

	/**
	 * AJAX handler to migrate a single video to MUX.
	 *
	 * @since    1.2.0
	 */
	public function ajax_migrate_single_video() {
		// Verify nonce
		check_ajax_referer( 'wp_mux_migration_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'wp-mux' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID', 'wp-mux' ) ) );
		}

		// Get attachment info
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			wp_send_json_error( array( 'message' => __( 'Attachment not found', 'wp-mux' ) ) );
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! file_exists( $file_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Video file not found', 'wp-mux' ) ) );
		}

		// Store original post date for later restoration
		$original_date = $attachment->post_date;
		$original_date_gmt = $attachment->post_date_gmt;

		try {
			// Initialize MUX API
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
			$api = new Wp_Mux_Api();

			if ( ! $api->is_configured() ) {
				throw new Exception( __( 'MUX API credentials are not configured', 'wp-mux' ) );
			}

			// Create MUX direct upload
			$upload = $api->create_direct_upload();

			if ( is_wp_error( $upload ) || empty( $upload['data']['url'] ) ) {
				$error_msg = is_wp_error( $upload ) ? $upload->get_error_message() : __( 'Failed to create MUX upload', 'wp-mux' );
				throw new Exception( $error_msg );
			}

			$upload_url = $upload['data']['url'];
			$upload_id = $upload['data']['id'];

			// Upload file to MUX
			$upload_result = wp_remote_request( $upload_url, array(
				'headers' => array(
					'Content-Type' => get_post_mime_type( $attachment_id ),
				),
				'body'    => file_get_contents( $file_path ),
				'timeout' => 300, // 5 minutes for large files
				'method'  => 'PUT',
			) );

			if ( is_wp_error( $upload_result ) ) {
				throw new Exception( __( 'MUX upload error: ', 'wp-mux' ) . $upload_result->get_error_message() );
			}

			$response_code = wp_remote_retrieve_response_code( $upload_result );
			if ( $response_code < 200 || $response_code >= 300 ) {
				throw new Exception( sprintf( __( 'Failed to upload video to MUX. Response code: %d', 'wp-mux' ), $response_code ) );
			}

			// Wait for MUX to process the upload
			sleep( 2 );
			$upload_status = $api->get_upload( $upload_id );
			$asset_id = null;
			$playback_id = null;

			if ( ! is_wp_error( $upload_status ) && ! empty( $upload_status['data']['asset_id'] ) ) {
				$asset_id = $upload_status['data']['asset_id'];

				// Get additional asset details including playback ID
				$asset_details = $api->get_video( $asset_id );
				if ( ! is_wp_error( $asset_details ) && ! empty( $asset_details['data'] ) ) {
					if ( ! empty( $asset_details['data']['playback_ids'][0]['id'] ) ) {
						$playback_id = $asset_details['data']['playback_ids'][0]['id'];
						update_post_meta( $attachment_id, '_mux_playback_id', $playback_id );

						// Generate and store thumbnail URL
						$thumbnail_url = "https://image.mux.com/{$playback_id}/thumbnail.jpg?width=640&height=360&fit_mode=smartcrop";
						update_post_meta( $attachment_id, '_mux_thumbnail_url', $thumbnail_url );
					}
				}
			}

			// Update attachment with MUX data
			update_post_meta( $attachment_id, '_mux_upload_id', $upload_id );
			if ( $asset_id ) {
				update_post_meta( $attachment_id, '_mux_asset_id', $asset_id );
			}
			update_post_meta( $attachment_id, '_wp_mux_status', 'complete' );
			update_post_meta( $attachment_id, '_wp_mux_completed_at', current_time( 'mysql' ) );
			update_post_meta( $attachment_id, '_mux_migrated', '1' );

			// Update attachment to use MUX mime type
			wp_update_post( array(
				'ID'             => $attachment_id,
				'post_mime_type' => 'video/mux',
			) );

			// Restore original dates
			wp_update_post( array(
				'ID'            => $attachment_id,
				'post_date'     => $original_date,
				'post_date_gmt' => $original_date_gmt,
			) );

			// Delete local file if setting is enabled
			$delete_after_migration = get_option( 'wp_mux_delete_local_after_migration', '0' );
			$file_deleted = false;
			if ( $delete_after_migration === '1' ) {
				if ( @unlink( $file_path ) ) {
					$file_deleted = true;
					// Also try to delete the file's directory if it's empty
					$upload_dir = dirname( $file_path );
					@rmdir( $upload_dir );
				}
			}

			wp_send_json_success( array(
				'message'      => __( 'Video migrated successfully', 'wp-mux' ),
				'attachment_id' => $attachment_id,
				'mux_asset_id' => $asset_id,
				'mux_playback_id' => $playback_id,
				'file_deleted' => $file_deleted,
			) );

		} catch ( Exception $e ) {
			// Update status to failed
			update_post_meta( $attachment_id, '_wp_mux_status', 'failed' );
			update_post_meta( $attachment_id, '_wp_mux_error', $e->getMessage() );

			wp_send_json_error( array(
				'message'       => $e->getMessage(),
				'attachment_id' => $attachment_id,
			) );
		}
	}

	/**
	 * AJAX handler to get migration status.
	 *
	 * @since    1.2.0
	 */
	public function ajax_get_migration_status() {
		// Verify nonce
		check_ajax_referer( 'wp_mux_migration_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'wp-mux' ) ) );
		}

		// Return any ongoing migration status
		// This could be expanded to track progress in the database
		wp_send_json_success( array(
			'status' => 'ready',
		) );
	}

	/**
	 * AJAX handler to save delete local files option.
	 *
	 * @since    1.2.0
	 */
	public function ajax_save_delete_option() {
		// Verify nonce
		check_ajax_referer( 'wp_mux_migration_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'wp-mux' ) ) );
		}

		$delete_local = isset( $_POST['delete_local'] ) ? sanitize_text_field( $_POST['delete_local'] ) : '0';
		update_option( 'wp_mux_delete_local_after_migration', $delete_local );

		wp_send_json_success( array(
			'message' => __( 'Option saved', 'wp-mux' ),
		) );
	}

}
