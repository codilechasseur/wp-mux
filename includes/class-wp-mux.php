<?php

declare(strict_types=1);

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://wpmux.com
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
 * @author     Codi Lechasseur
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
		 * Template functions for themes: wp_mux_player() and friends.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/functions.php';

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

		/**
		 * The class responsible for handling admin AJAX requests.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-admin-ajax.php';
		$ajax_handler = new Wp_Mux_Admin_Ajax();
		$ajax_handler->register_hooks();

		/**
		 * The class responsible for managing the video upload queue.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-utils.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-queue-manager.php';
		$queue_manager = new Wp_Mux_Queue_Manager();
		$queue_manager->register_hooks();

		/**
		 * Webhook receiver — handles real-time Mux events.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-webhook.php';
		new Wp_Mux_Webhook();

		/**
		 * REST API endpoints for headless use.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-rest-api.php';
		new Wp_Mux_Rest_Api();

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

		// Handle attachment deletion - delete from MUX when deleted from WordPress
		$this->loader->add_action( 'delete_attachment', $plugin_admin, 'handle_attachment_deletion' );

		// Hook into attachment preparation for AJAX responses (for existing MUX attachments)
		$this->loader->add_filter( 'wp_prepare_attachment_for_js', $plugin_admin, 'prepare_mux_attachment_for_js', 10, 3 );

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
		// [video] / wp_video_shortcode(): before core's extension check, which Mux stream URLs fail.
		$this->loader->add_filter( 'wp_video_shortcode_override', $plugin_public, 'render_mux_video_override', 10, 2 );
		$this->loader->add_filter( 'the_content', $plugin_public, 'process_content_videos', 20 );
		$this->loader->add_filter( 'render_block', $plugin_public, 'process_video_blocks', 10, 2 );

		// Video SEO: output VideoObject JSON-LD in <head> when a Mux video is on the page.
		$this->loader->add_action( 'wp_head', $plugin_public, 'output_video_seo_schema', 5 );

		// Serve transient-stored VTT files so Mux can re-ingest edited captions.
		add_action( 'init', function() {
			$action = isset( $_GET['wp_mux_action'] ) ? sanitize_key( $_GET['wp_mux_action'] ) : '';
			if ( $action !== 'serve_vtt' ) {
				return;
			}
			$key     = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
			$content = get_transient( 'wp_mux_vtt_' . $key );
			if ( empty( $content ) ) {
				wp_die( 'VTT not found or expired', 404 );
			}
			header( 'Content-Type: text/vtt; charset=UTF-8' );
			header( 'Cache-Control: no-store' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $content;
			exit;
		} );

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
