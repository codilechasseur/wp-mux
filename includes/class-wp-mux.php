<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.0.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wp_Mux
 * @subpackage Wp_Mux/includes
 * @author     Codi Lechasseur <codilechasseur@gmail.com>
 */
class Wp_Mux {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Wp_Mux_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'WP_MUX_VERSION' ) ) {
			$this->version = WP_MUX_VERSION;
		} else {
			$this->version = '1.0.1';
		}
		$this->plugin_name = 'wp-mux';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wp_Mux_Loader. Orchestrates the hooks of the plugin.
	 * - Wp_Mux_i18n. Defines internationalization functionality.
	 * - Wp_Mux_Admin. Defines all hooks for the admin area.
	 * - Wp_Mux_Public. Defines all hooks for the public side of the site.
	 * - Wp_Mux_Api. Handles MUX API communication.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-i18n.php';

		/**
		 * The class responsible for MUX API communication.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wp-mux-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wp-mux-public.php';

		/**
		 * The class responsible for managing MUX player themes.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-theme-manager.php';

		/**
		 * The class responsible for syncing MUX assets to media library.
		 * Needs to be loaded in all contexts for REST API support.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-media-sync.php';
		new Wp_Mux_Media_Sync();

		$this->loader = new Wp_Mux_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wp_Mux_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Wp_Mux_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Wp_Mux_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		// Register settings fields for the settings page
		$this->loader->add_action( 'admin_init', $plugin_admin, 'init_settings' );

		// Handle theme form submissions early in admin_init
		$this->loader->add_action( 'admin_init', $plugin_admin, 'handle_theme_form_submissions' );

		// Inject MUX data on attachment edit pages
		$this->loader->add_action( 'admin_head', $plugin_admin, 'inject_mux_data_on_attachment_edit' );

		// Display MUX player on attachment edit page
		$this->loader->add_action( 'edit_form_after_title', $plugin_admin, 'display_mux_player_on_edit_page' );

		// Suppress default WordPress video display for MUX videos on edit page
		$this->loader->add_action( 'admin_footer', $plugin_admin, 'suppress_default_video_display_footer' );

		// Add analytics meta box to attachment edit page
		$this->loader->add_action( 'add_meta_boxes_attachment', $plugin_admin, 'add_analytics_meta_box' );

		// Post-upload video processing queue system
		$this->loader->add_action( 'add_attachment', $plugin_admin, 'queue_video_for_mux_processing' );
		$this->loader->add_action( 'wp_ajax_wp_mux_process_queue', $plugin_admin, 'process_mux_queue' );
		$this->loader->add_action( 'wp_ajax_wp_mux_get_queue_status', $plugin_admin, 'get_queue_status' );
		$this->loader->add_action( 'wp_ajax_wp_mux_get_attachment_info', $plugin_admin, 'get_attachment_info' );
		$this->loader->add_action( 'wp_mux_process_queue_event', $plugin_admin, 'process_mux_queue_cron' );

		// Add heartbeat to regularly process queue
		$this->loader->add_filter( 'heartbeat_received', $plugin_admin, 'heartbeat_process_queue', 10, 2 );

		// Debug: log all AJAX requests to see what action is actually used
		$this->loader->add_action( 'wp_loaded', $plugin_admin, 'debug_all_ajax' );

		// Hook into attachment preparation for AJAX responses (for existing MUX attachments)
		$this->loader->add_filter( 'wp_prepare_attachment_for_js', $plugin_admin, 'prepare_mux_attachment_for_js', 10, 3 );

		// Add AJAX handlers for admin functionality
		$this->loader->add_action( 'wp_ajax_wp_mux_create_upload', $plugin_admin, 'ajax_create_upload' );
		$this->loader->add_action( 'wp_ajax_wp_mux_load_videos', $plugin_admin, 'ajax_load_videos' );
		$this->loader->add_action( 'wp_ajax_wp_mux_delete_video', $plugin_admin, 'ajax_delete_video' );
		$this->loader->add_action( 'wp_ajax_wp_mux_get_video_details', $plugin_admin, 'ajax_get_video_details' );

		// Add AJAX handlers for theme functionality
		$this->loader->add_action( 'wp_ajax_wp_mux_save_theme', $plugin_admin, 'ajax_save_theme' );
		$this->loader->add_action( 'wp_ajax_wp_mux_delete_theme', $plugin_admin, 'ajax_delete_theme' );
		$this->loader->add_action( 'wp_ajax_wp_mux_set_default_theme', $plugin_admin, 'ajax_set_default_theme' );

		// Add AJAX handler for video analytics refresh
		$this->loader->add_action( 'wp_ajax_wp_mux_refresh_video_analytics', $plugin_admin, 'ajax_refresh_video_analytics' );

		// Add AJAX handlers for bulk migration
		$this->loader->add_action( 'wp_ajax_wp_mux_scan_local_videos', $plugin_admin, 'ajax_scan_local_videos' );
		$this->loader->add_action( 'wp_ajax_wp_mux_migrate_single_video', $plugin_admin, 'ajax_migrate_single_video' );
		$this->loader->add_action( 'wp_ajax_wp_mux_get_migration_status', $plugin_admin, 'ajax_get_migration_status' );
		$this->loader->add_action( 'wp_ajax_wp_mux_save_delete_option', $plugin_admin, 'ajax_save_delete_option' );

		// Add Gutenberg/Block editor support for MUX videos
		$this->loader->add_action( 'enqueue_block_editor_assets', $plugin_admin, 'enqueue_block_editor_assets' );

		// Allow mux-video elements in WordPress content
		$this->loader->add_action( 'init', $plugin_admin, 'allow_mux_video_elements' );

		// Schedule analytics refresh cron
		$this->loader->add_action( 'wp_mux_refresh_analytics_event', $plugin_admin, 'cron_refresh_analytics' );
		if ( ! wp_next_scheduled( 'wp_mux_refresh_analytics_event' ) ) {
			wp_schedule_event( time(), 'hourly', 'wp_mux_refresh_analytics_event' );
		}

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Wp_Mux_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Add filters to handle MUX video rendering in different contexts
		$this->loader->add_filter( 'wp_video_shortcode', $plugin_public, 'render_mux_video', 10, 3 );
		$this->loader->add_filter( 'the_content', $plugin_public, 'process_content_videos', 20 );
		$this->loader->add_filter( 'render_block', $plugin_public, 'process_video_blocks', 10, 2 );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Wp_Mux_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
