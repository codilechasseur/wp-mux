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

		// Add save handler for attachment settings
		add_action( 'edit_attachment', array( $this, 'save_mux_attachment_settings' ) );

		// Check for deferred signing key creation
		add_action( 'admin_init', array( $this, 'maybe_create_signing_keys' ) );

		// Redirect to settings if API is not configured
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_settings' ) );

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

		$screen = get_current_screen();

		if ( $success_data ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p><strong>' . esc_html__( 'MUX Upload Successful!', 'wp-mux' ) . '</strong> ';
			echo sprintf(
				esc_html__( 'Video "%s" has been uploaded to MUX and added to your media library (ID: %d).', 'wp-mux' ),
				esc_html( $success_data['filename'] ),
				intval( $success_data['attachment_id'] )
			);
			echo '</p></div>';

			delete_transient( $transient_key );
		}

		// Check for settings update notice
		$settings_updated_key = 'wp_mux_settings_updated_' . $user_id;
		if ( get_transient( $settings_updated_key ) ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>' . esc_html__( 'MUX settings updated successfully.', 'wp-mux' ) . '</p>';
			echo '</div>';
			delete_transient( $settings_updated_key );
		}

		// Check for API required notice (redirected from protected page)
		$api_required_page = get_transient( 'wp_mux_api_required_notice' );
		if ( $api_required_page ) {
			$page_names = array(
				'wp-mux-videos' => __( 'All Videos', 'wp-mux' ),
				'wp-mux-migration' => __( 'Migrate Videos', 'wp-mux' ),
			);
			$page_name = isset( $page_names[ $api_required_page ] ) ? $page_names[ $api_required_page ] : __( 'that page', 'wp-mux' );
			
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p><strong>' . esc_html__( 'MUX API Configuration Required', 'wp-mux' ) . '</strong></p>';
			echo '<p>' . sprintf(
				esc_html__( 'To access %s, you need to configure your MUX API credentials below. Enter your Access Token and Secret Key to get started.', 'wp-mux' ),
				'<strong>' . esc_html( $page_name ) . '</strong>'
			) . '</p>';
			echo '</div>';
			delete_transient( 'wp_mux_api_required_notice' );
		}

		// Check for activation notices
		$this->show_activation_notices();
	}

	/**
	 * Show activation-related notices.
	 *
	 * @since    1.3.0
	 */
	private function show_activation_notices() {
		// Show signing key success notice
		$signing_key_success = get_transient( 'wp_mux_signing_key_success' );
		if ( $signing_key_success ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>';
			echo '<strong>' . esc_html__( 'WP Mux Activated!', 'wp-mux' ) . '</strong><br>';
			echo esc_html__( 'Your videos are automatically protected with signed URLs. Signing keys have been configured and stored securely.', 'wp-mux' );
			echo '</p>';
			echo '<p>';
			echo '<strong>' . esc_html__( 'Key ID:', 'wp-mux' ) . '</strong> ';
			echo '<code>' . esc_html( $signing_key_success ) . '</code>';
			echo '</p>';
			echo '</div>';
			delete_transient( 'wp_mux_signing_key_success' );
		}

		// Show info notice if automatic key creation wasn't available
		$signing_keys_info = get_transient( 'wp_mux_signing_keys_info' );
		if ( $signing_keys_info ) {
			echo '<div class="notice notice-info is-dismissible">';
			echo '<p>';
			echo '<strong>' . esc_html__( 'WP Mux Activated Successfully', 'wp-mux' ) . '</strong><br>';
			echo esc_html__( 'Your plugin is ready to use with public videos.', 'wp-mux' );
			echo '</p>';
			echo '<p>';
			echo '<strong>' . esc_html__( 'Want Private Videos?', 'wp-mux' ) . '</strong><br>';
			echo esc_html__( 'Automatic signing key setup requires a Mux account with signing keys API access. You can manually configure signing keys to enable private videos:', 'wp-mux' );
			echo '</p>';
			echo '<ol style="margin-left: 20px;">';
			echo '<li>' . esc_html__( 'Log into', 'wp-mux' ) . ' <a href="https://dashboard.mux.com" target="_blank">dashboard.mux.com</a></li>';
			echo '<li>' . esc_html__( 'Go to Settings → Signing Keys', 'wp-mux' ) . '</li>';
			echo '<li>' . esc_html__( 'Create a new signing key', 'wp-mux' ) . '</li>';
			echo '<li>' . esc_html__( 'Copy the Key ID and Private Key', 'wp-mux' ) . '</li>';
			echo '<li>' . esc_html__( 'Paste them in the WP Mux settings below', 'wp-mux' ) . '</li>';
			echo '</ol>';
			echo '<p>';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=wp-mux-settings' ) ) . '" class="button button-primary">';
			echo esc_html__( 'Go to Settings', 'wp-mux' );
			echo '</a>';
			echo ' ';
			echo '<a href="https://docs.mux.com/guides/secure-video-playback" target="_blank" class="button">';
			echo esc_html__( 'Learn More', 'wp-mux' );
			echo '</a>';
			echo '</p>';
			echo '</div>';
			delete_transient( 'wp_mux_signing_keys_info' );
		}

		// Show notice if API credentials need configuration
		$needs_api_config = get_transient( 'wp_mux_needs_api_config' );
		if ( $needs_api_config ) {
			echo '<div class="notice notice-info is-dismissible">';
			echo '<p>';
			echo '<strong>' . esc_html__( 'WP Mux: Setup Required', 'wp-mux' ) . '</strong><br>';
			echo esc_html__( 'Please configure your Mux API credentials to start using the plugin.', 'wp-mux' );
			echo '</p>';
			echo '<p>';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=wp-mux-settings' ) ) . '" class="button button-primary">';
			echo esc_html__( 'Configure API Credentials', 'wp-mux' );
			echo '</a>';
			echo '</p>';
			echo '</div>';
			delete_transient( 'wp_mux_needs_api_config' );
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

	// Enqueue migration styles on migration page, settings page (for sidebar layout)
	if ( $screen && ( 
		$screen->id === 'mux-videos_page_wp-mux-migration' || 
		$screen->id === 'mux-videos_page_wp-mux-settings' || 
		$screen->id === 'toplevel_page_wp-mux-videos' 
	) ) {
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

	// Enqueue migrate-back script on migration page (from-mux tab)
	if ( ( $screen && $screen->id === 'mux-videos_page_wp-mux-migration' && isset( $_GET['tab'] ) && $_GET['tab'] === 'from-mux' ) || 
	     ( isset( $_GET['page'] ) && $_GET['page'] === 'wp-mux-migration' && isset( $_GET['tab'] ) && $_GET['tab'] === 'from-mux' ) ) {
		wp_enqueue_script( 'wp-mux-migrate-back', plugin_dir_url( __FILE__ ) . 'js/wp-mux-migrate-back.js', array( 'jquery' ), $this->version, true );

		// Localize script with necessary data
		wp_localize_script( 'wp-mux-migrate-back', 'wpMuxMigrateBack', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wp_mux_migrate_back' ),
			'strings' => array(
				'noVideosFound' => __( 'No Mux videos found.', 'wp-mux' ),
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

	// Enqueue attachment settings script on attachment edit page
	if ( $screen && $screen->id === 'attachment' ) {
		wp_enqueue_script( 
			'wp-mux-attachment-settings', 
			plugin_dir_url( __FILE__ ) . 'js/wp-mux-attachment-settings.js', 
			array( 'jquery' ), 
			$this->version, 
			true 
		);
	}

	}

	/**
	 * Inject MUX data on attachment edit pages
	 *
	 * @since    1.2.0
	 */
	public function inject_mux_data_on_attachment_edit() {
		$screen = get_current_screen();

		// Check if we're on an attachment edit page
		if ( $screen && $screen->id === 'attachment' && isset( $_GET['post'] ) ) {
			$attachment_id = intval( $_GET['post'] );
			$attachment = get_post( $attachment_id );

			// Check if this is a MUX video
			if ( $attachment && $attachment->post_mime_type === 'video/mux' ) {
				$playback_id = get_post_meta( $attachment_id, '_mux_playback_id', true );

				if ( $playback_id ) {
					// Inject playback ID for JavaScript to use
					?>
					<script type="text/javascript">
						window.wpMuxAttachment = {
							playback_id: '<?php echo esc_js( $playback_id ); ?>',
							attachment_id: <?php echo intval( $attachment_id ); ?>
						};
					</script>
					<?php
				}
			}
		}
	}

	/**
	 * Display MUX player on attachment edit page
	 *
	 * @since    1.2.0
	 */
	public function display_mux_player_on_edit_page( $post ) {
		// Only run for attachment post type
		if ( $post->post_type !== 'attachment' ) {
			return;
		}

		// Check if this is a MUX video
		if ( $post->post_mime_type === 'video/mux' ) {
			$playback_id = get_post_meta( $post->ID, '_mux_playback_id', true );
			$thumbnail_url = get_post_meta( $post->ID, '_mux_thumbnail_url', true );

			if ( $playback_id ) {
				?>
				<div class="mux-video-preview" style="margin: 20px 0;">
					<mux-player
						playback-id="<?php echo esc_attr( $playback_id ); ?>"
						controls
						stream-type="on-demand"
						<?php if ( $thumbnail_url ) : ?>
						poster="<?php echo esc_attr( $thumbnail_url ); ?>"
						<?php endif; ?>
						style="width: 100%; max-width: 800px; margin: 0 auto; display: block;">
					</mux-player>
				</div>
				<?php
			}
		}
	}

	/**
	 * Suppress default WordPress video player in wp_attachment_holder for MUX videos
	 * This runs in the footer to ensure the element exists before hiding it
	 *
	 * @since    1.2.0
	 */
	public function suppress_default_video_display_footer() {
		// Only run on attachment edit page
		$screen = get_current_screen();
		if ( ! is_admin() || ! $screen || $screen->id !== 'attachment' ) {
			return;
		}

		// Get the current post
		global $post;
		if ( ! $post ) {
			return;
		}

		// Check if this is a MUX video
		if ( $post->post_mime_type === 'video/mux' ) {
			// Hide the wp_attachment_holder div using JavaScript
			?>
			<script type="text/javascript">
			(function() {
				var holder = document.querySelector('.wp_attachment_holder');
				if (holder) {
					holder.style.display = 'none';
				}
			})();
			</script>
			<?php
		}
	}

	/**
	 * Add analytics meta box to attachment edit page
	 *
	 * @since    1.2.0
	 */
	public function add_analytics_meta_box() {
		global $post;

		// Only add for MUX videos
		if ( $post && $post->post_mime_type === 'video/mux' ) {
			$mux_status = get_post_meta( $post->ID, '_wp_mux_status', true );

			// Only show for completed videos
			if ( $mux_status === 'complete' ) {
				// Combined meta box for both analytics and settings
				add_meta_box(
					'wp_mux_video_details',
					__( 'MUX Video Details', 'wp-mux' ),
					array( $this, 'render_combined_meta_box' ),
					'attachment',
					'normal',
					'default'
				);
			}
		}
	}

	/**
	 * Render combined meta box with analytics and settings
	 *
	 * @since    1.3.0
	 */
	public function render_combined_meta_box( $post ) {
		?>
		<div class="wp-mux-combined-container">
			<!-- Analytics Section -->
			<div class="wp-mux-section">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
					<h3 style="margin: 0;"><?php _e( 'Video Analytics', 'wp-mux' ); ?></h3>
					<div style="display: flex; align-items: center; gap: 10px;">
						<span class="mux-stats-updated" style="font-size: 12px; color: #646970;">
							<?php 
							$analytics = $this->get_video_analytics( $post->ID, false );
							$formatted = $this->format_analytics_for_display( $analytics );
							printf( __( 'Last updated: %s', 'wp-mux' ), esc_html( $formatted['last_updated'] ) ); 
							?>
						</span>
						<button type="button" class="button button-small mux-refresh-stats-edit-page" data-attachment-id="<?php echo esc_attr( $post->ID ); ?>" title="<?php _e( 'Refresh Analytics', 'wp-mux' ); ?>" style="min-width: auto; padding: 4px 8px; color: #646970; border-color: #dcdcde; display: inline-flex; align-items: center; justify-content: center;">
							<span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span>
						</button>
					</div>
				</div>
				<?php $this->render_analytics_content( $post ); ?>
			</div>
			
			<hr style="margin: 30px 0; border: none; border-top: 1px solid #dcdcde;">
			
			<!-- Settings Section -->
			<div class="wp-mux-section">
				<h3><?php _e( 'Video Settings', 'wp-mux' ); ?></h3>
				<?php $this->render_settings_content( $post ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render analytics content (extracted from render_analytics_meta_box)
	 *
	 * @since    1.3.0
	 */
	private function render_analytics_content( $post ) {
		// Get analytics data
		$analytics = $this->get_video_analytics( $post->ID, false );
		$formatted = $this->format_analytics_for_display( $analytics );

		// Enqueue the stats CSS if not already enqueued
		wp_enqueue_style( 'wp-mux-media-stats', plugin_dir_url( __FILE__ ) . 'css/wp-mux-media-stats.css', array(), $this->version );

		?>
		<div class="mux-video-stats" style="margin: 0; padding: 0; background: transparent; border: none;">
			<div class="mux-stats-grid">
				<div class="mux-stat-card">
					<div class="mux-stat-value"><?php echo esc_html( $formatted['total_views'] ); ?></div>
					<div class="mux-stat-label"><?php _e( 'Total Views', 'wp-mux' ); ?></div>
				</div>

				<div class="mux-stat-card">
					<div class="mux-stat-value"><?php echo esc_html( $formatted['unique_viewers'] ); ?></div>
					<div class="mux-stat-label"><?php _e( 'Unique Viewers', 'wp-mux' ); ?></div>
				</div>

				<div class="mux-stat-card">
					<div class="mux-stat-value"><?php echo esc_html( $formatted['total_watch_time'] ); ?></div>
					<div class="mux-stat-label"><?php _e( 'Total Watch Time', 'wp-mux' ); ?></div>
				</div>

				<div class="mux-stat-card">
					<div class="mux-stat-value"><?php echo esc_html( $formatted['average_view_duration'] ); ?></div>
					<div class="mux-stat-label"><?php _e( 'Avg. View Duration', 'wp-mux' ); ?></div>
				</div>

				<?php if ( $formatted['video_quality_score'] !== 'N/A' ) : ?>
				<div class="mux-stat-card">
					<div class="mux-stat-value"><?php echo esc_html( $formatted['video_quality_score'] ); ?></div>
					<div class="mux-stat-label"><?php _e( 'Quality Score', 'wp-mux' ); ?></div>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Handle refresh button click
			$('.mux-refresh-stats-edit-page').on('click', function(e) {
				e.preventDefault();

				var $button = $(this);
				var attachmentId = $button.data('attachment-id');

				// Disable button and show loading state
				$button.prop('disabled', true).addClass('mux-stats-loading');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wp_mux_refresh_video_analytics',
						nonce: '<?php echo wp_create_nonce( 'wp_mux_analytics_nonce' ); ?>',
						attachment_id: attachmentId
					},
					success: function(response) {
						if (response.success && response.data.analytics) {
							// Reload the page to show updated stats
							location.reload();
						} else {
							alert(response.data.message || '<?php _e( 'Failed to refresh stats', 'wp-mux' ); ?>');
							$button.prop('disabled', false).removeClass('mux-stats-loading');
						}
					},
					error: function(xhr, status, error) {
						alert('<?php _e( 'Failed to refresh stats', 'wp-mux' ); ?>');
						$button.prop('disabled', false).removeClass('mux-stats-loading');
					}
				});
			});
		});
		</script>
		<?php
	}

	private function render_settings_content( $post ) {
		$asset_id = get_post_meta( $post->ID, '_mux_asset_id', true );
		
		// Get current settings
		$playback_policy = get_post_meta( $post->ID, '_mux_playback_policy', true ) ?: 'public';
		$mp4_support = get_post_meta( $post->ID, '_mux_mp4_support', true ) ?: 'none';
		$encoding_tier = get_post_meta( $post->ID, '_mux_encoding_tier', true ) ?: 'baseline';
		$has_captions = get_post_meta( $post->ID, '_mux_has_captions', true );
		
		// Check if caption generation is available
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();
		$captions_available = $api->has_caption_generation_capability();
		
		// Check if video has audio track
		$has_audio = false;
		if ( ! empty( $asset_id ) ) {
			$has_audio = $api->has_audio_track( $asset_id );
		}
		
		// Check if signing keys are available (needed for signed/protected playback)
		$signing_keys_available = $api->has_signing_keys_capability();
		
		wp_nonce_field( 'wp_mux_save_settings', 'wp_mux_settings_nonce' );
		
		include plugin_dir_path( __FILE__ ) . 'partials/wp-mux-admin-attachment-settings.php';
	}

	/**
	 * Save MUX settings from attachment edit page.
	 *
	 * @since    1.3.0
	 * @param    int    $attachment_id    The attachment post ID.
	 */
	public function save_mux_attachment_settings( $attachment_id ) {
		// Verify nonce
		if ( ! isset( $_POST['wp_mux_settings_nonce'] ) || 
			 ! wp_verify_nonce( $_POST['wp_mux_settings_nonce'], 'wp_mux_save_settings' ) ) {
			return;
		}

		// Check if this is a MUX video
		$post = get_post( $attachment_id );
		if ( ! $post || $post->post_mime_type !== 'video/mux' ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			return;
		}

		$asset_id = get_post_meta( $attachment_id, '_mux_asset_id', true );
		if ( ! $asset_id ) {
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();
		$updated = false;

		// Update playback policy if changed
		if ( isset( $_POST['mux_playback_policy'] ) ) {
			$new_policy = sanitize_text_field( $_POST['mux_playback_policy'] );
			$old_policy = get_post_meta( $attachment_id, '_mux_playback_policy', true );

			if ( $new_policy !== $old_policy && in_array( $new_policy, array( 'public', 'signed' ), true ) ) {
				$response = $api->update_playback_policy( $asset_id, $new_policy );
				
				if ( ! is_wp_error( $response ) ) {
					update_post_meta( $attachment_id, '_mux_playback_policy', $new_policy );
					update_post_meta( $attachment_id, '_mux_requires_signed_url', $new_policy === 'signed' );
					$updated = true;
				}
			}
		}

		// Update MP4 support if changed
		if ( isset( $_POST['mux_mp4_support'] ) ) {
			$new_support = sanitize_text_field( $_POST['mux_mp4_support'] );
			$old_support = get_post_meta( $attachment_id, '_mux_mp4_support', true );

			if ( $new_support !== $old_support && 
				 in_array( $new_support, array( 'none', 'standard', 'audio-only' ), true ) ) {
				$response = $api->update_mp4_support( $asset_id, $new_support );
				
				if ( ! is_wp_error( $response ) ) {
					update_post_meta( $attachment_id, '_mux_mp4_support', $new_support );
					$updated = true;
				}
			}
		}

		if ( $updated ) {
			$user_id = get_current_user_id();
			set_transient( 'wp_mux_settings_updated_' . $user_id, true, 30 );
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

		// Add submenu for bulk migration
		add_submenu_page(
			'wp-mux-videos',
			__( 'Migrate Local Videos', 'wp-mux' ),
			__( 'Migrate Videos', 'wp-mux' ),
			'manage_options',
			'wp-mux-migration',
			array( $this, 'display_migration_page' )
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
				'sanitize_callback' => array( $this, 'sanitize_api_credentials' ),
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

		// Register upload default settings
		register_setting(
			'wp_mux_settings_group',
			'wp_mux_default_playback_policy',
			array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_playback_policy' ),
				'default' => 'public',
			)
		);

		register_setting(
			'wp_mux_settings_group',
			'wp_mux_default_mp4_support',
			array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_mp4_support' ),
				'default' => 'none',
			)
		);

		register_setting(
			'wp_mux_settings_group',
			'wp_mux_default_encoding_tier',
			array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encoding_tier' ),
				'default' => 'baseline',
			)
		);

		register_setting(
			'wp_mux_settings_group',
			'wp_mux_enable_auto_captions',
			array(
				'type' => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default' => false,
			)
		);

		register_setting(
			'wp_mux_settings_group',
			'wp_mux_default_caption_language',
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => 'en',
			)
		);

		register_setting(
			'wp_mux_settings_group',
			'wp_mux_signing_key_id',
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => '',
			)
		);

		register_setting(
			'wp_mux_settings_group',
			'wp_mux_signing_key_private',
			array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_signing_key' ),
				'default' => '',
			)
		);

		// Add upload settings section
		add_settings_section(
			'wp_mux_upload_settings',
			__( 'Upload Default Settings', 'wp-mux' ),
			array( $this, 'upload_settings_section_callback' ),
			'wp-mux-settings'
		);

		// Add playback policy field
		add_settings_field(
			'wp_mux_default_playback_policy',
			__( 'Default Playback Policy', 'wp-mux' ),
			array( $this, 'playback_policy_field_callback' ),
			'wp-mux-settings',
			'wp_mux_upload_settings'
		);

		// Add MP4 support field
		add_settings_field(
			'wp_mux_default_mp4_support',
			__( 'MP4 Support', 'wp-mux' ),
			array( $this, 'mp4_support_field_callback' ),
			'wp-mux-settings',
			'wp_mux_upload_settings'
		);

		// Add encoding tier field
		add_settings_field(
			'wp_mux_default_encoding_tier',
			__( 'Encoding Tier', 'wp-mux' ),
			array( $this, 'encoding_tier_field_callback' ),
			'wp-mux-settings',
			'wp_mux_upload_settings'
		);

		// Add auto captions field
		add_settings_field(
			'wp_mux_enable_auto_captions',
			__( 'Auto-Generate Captions', 'wp-mux' ),
			array( $this, 'auto_captions_field_callback' ),
			'wp-mux-settings',
			'wp_mux_upload_settings'
		);

		// Don't add manual signing key configuration field anymore
		// Signing keys are now automatically managed when users select "Signed URLs"
		// This makes the settings page much cleaner and less confusing

		// Register player options settings
		register_setting(
			'wp_mux_settings_group',
			'wp_mux_player_prefer_playback',
			array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_prefer_playback' ),
				'default' => 'auto',
			)
		);

		register_setting(
			'wp_mux_settings_group',
			'wp_mux_player_max_resolution',
			array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_max_resolution' ),
				'default' => '',
			)
		);

		register_setting(
			'wp_mux_settings_group',
			'wp_mux_player_min_resolution',
			array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_min_resolution' ),
				'default' => '',
			)
		);

		register_setting(
			'wp_mux_settings_group',
			'wp_mux_player_preload',
			array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_preload' ),
				'default' => 'metadata',
			)
		);

		register_setting(
			'wp_mux_settings_group',
			'wp_mux_player_debug',
			array(
				'type' => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default' => false,
			)
		);

		// Add player options section
		add_settings_section(
			'wp_mux_player_settings',
			__( 'Player Options', 'wp-mux' ),
			array( $this, 'player_settings_section_callback' ),
			'wp-mux-settings'
		);

		// Add prefer-playback field
		add_settings_field(
			'wp_mux_player_prefer_playback',
			__( 'Playback Engine', 'wp-mux' ),
			array( $this, 'prefer_playback_field_callback' ),
			'wp-mux-settings',
			'wp_mux_player_settings'
		);

		// Add max resolution field
		add_settings_field(
			'wp_mux_player_max_resolution',
			__( 'Maximum Resolution', 'wp-mux' ),
			array( $this, 'max_resolution_field_callback' ),
			'wp-mux-settings',
			'wp_mux_player_settings'
		);

		// Add min resolution field
		add_settings_field(
			'wp_mux_player_min_resolution',
			__( 'Minimum Resolution', 'wp-mux' ),
			array( $this, 'min_resolution_field_callback' ),
			'wp-mux-settings',
			'wp_mux_player_settings'
		);

		// Add preload field
		add_settings_field(
			'wp_mux_player_preload',
			__( 'Preload Strategy', 'wp-mux' ),
			array( $this, 'preload_field_callback' ),
			'wp-mux-settings',
			'wp_mux_player_settings'
		);

		// Add debug field
		add_settings_field(
			'wp_mux_player_debug',
			__( 'Debug Mode', 'wp-mux' ),
			array( $this, 'debug_field_callback' ),
			'wp-mux-settings',
			'wp_mux_player_settings'
		);

		// Register uninstall behavior setting
		register_setting(
			'wp_mux_settings_group',
			'wp_mux_delete_on_uninstall',
			array(
				'type' => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default' => false,
			)
		);

		// Add uninstall settings section
		add_settings_section(
			'wp_mux_uninstall_settings',
			__( 'Uninstall Options', 'wp-mux' ),
			array( $this, 'uninstall_settings_section_callback' ),
			'wp-mux-settings'
		);

		// Add uninstall behavior field
		add_settings_field(
			'wp_mux_delete_on_uninstall',
			__( 'Delete Data on Uninstall', 'wp-mux' ),
			array( $this, 'delete_on_uninstall_field_callback' ),
			'wp-mux-settings',
			'wp_mux_uninstall_settings'
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
	 * Display the migrate back page.
	 *
	 * @since    1.3.0
	 */
	public function display_migrate_back_page() {
		include_once plugin_dir_path( __FILE__ ) . 'partials/wp-mux-admin-migrate-back.php';
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

		// Clear the account stats cache so the overview updates immediately
		delete_transient( 'wp_mux_account_stats' );

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
	 * Upload settings section callback.
	 *
	 * @since    1.3.0
	 */
	public function upload_settings_section_callback() {
		echo '<p>' . __( 'Configure default settings for new video uploads to MUX. These can be overridden per-video after upload.', 'wp-mux' ) . '</p>';
	}

	/**
	 * Playback policy field callback.
	 *
	 * @since    1.3.0
	 */
	public function playback_policy_field_callback() {
		$value = get_option( 'wp_mux_default_playback_policy', 'public' );
		
		// Check if account has signing keys capability
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();
		$has_capability = $api->has_signing_keys_capability();
		
		?>
		<select name="wp_mux_default_playback_policy" 
		        id="wp_mux_default_playback_policy"
		        data-has-capability="<?php echo $has_capability ? '1' : '0'; ?>">
			<option value="public" <?php selected( $value, 'public' ); ?>>
				<?php _e( 'Public (Anyone can view)', 'wp-mux' ); ?>
			</option>
			<option value="signed" 
			        <?php selected( $value, 'signed' ); ?>
			        <?php echo ! $has_capability ? 'disabled' : ''; ?>>
				<?php _e( 'Signed URLs (Requires token)', 'wp-mux' ); ?>
				<?php if ( ! $has_capability ) : ?>
					<?php _e( ' - Upgrade Required', 'wp-mux' ); ?>
				<?php endif; ?>
			</option>
		</select>
		
		<?php if ( ! $has_capability ) : ?>
			<p class="description" style="color: #d63638;">
				<span class="dashicons dashicons-lock" style="font-size: 16px; vertical-align: middle;"></span>
				<strong><?php _e( 'Private Videos Unavailable', 'wp-mux' ); ?></strong><br>
				<?php _e( 'Your Mux account does not have access to signed URLs. This feature requires:', 'wp-mux' ); ?>
				<ul style="list-style: disc; margin-left: 25px; margin-top: 5px; padding-left: 0;">
					<li><?php _e( 'A paid Mux plan, OR', 'wp-mux' ); ?></li>
					<li><?php _e( 'Signing keys API access enabled for your account', 'wp-mux' ); ?></li>
				</ul>
				<a href="https://mux.com/pricing" target="_blank"><?php _e( 'View Mux Pricing', 'wp-mux' ); ?> ↗</a>
			</p>
		<?php else : ?>
			<p class="description">
				<?php _e( 'Public videos can be played by anyone. Signed URLs automatically create secure tokens for each viewer.', 'wp-mux' ); ?>
				<br>
				<?php if ( $value === 'signed' ) : ?>
					<span style="color: #00a32a;">
						<span class="dashicons dashicons-yes-alt" style="font-size: 16px; vertical-align: middle;"></span>
						<?php _e( 'Signing keys are configured and ready.', 'wp-mux' ); ?>
					</span>
				<?php else : ?>
					<?php _e( 'Switching to "Signed URLs" will automatically configure signing keys for you.', 'wp-mux' ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#wp_mux_default_playback_policy').on('change', function() {
				var $select = $(this);
				var newValue = $select.val();
				var hasCapability = $select.data('has-capability');
				
				if (newValue === 'signed' && hasCapability) {
					// Check if keys already exist
					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'wp_mux_check_signing_keys',
							nonce: '<?php echo wp_create_nonce( 'wp_mux_check_keys' ); ?>'
						},
						success: function(response) {
							if (!response.success || !response.data.has_keys) {
								// Need to create keys
								if (confirm('<?php _e( 'Switching to signed URLs will automatically create and configure signing keys. Continue?', 'wp-mux' ); ?>')) {
									// Auto-configure keys
									$.ajax({
										url: ajaxurl,
										method: 'POST',
										data: {
											action: 'wp_mux_auto_configure_keys',
											nonce: '<?php echo wp_create_nonce( 'wp_mux_auto_configure_keys' ); ?>'
										},
										success: function(keyResponse) {
											if (keyResponse.success) {
												alert('<?php _e( 'Signing keys configured successfully!', 'wp-mux' ); ?>');
											} else {
												alert('<?php _e( 'Failed to configure signing keys. Please try again.', 'wp-mux' ); ?>');
												$select.val('public').trigger('change');
											}
										},
										error: function() {
											alert('<?php _e( 'Network error. Please try again.', 'wp-mux' ); ?>');
											$select.val('public').trigger('change');
										}
									});
								} else {
									// User cancelled, revert
									$select.val('public').trigger('change');
								}
							}
						}
					});
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * MP4 support field callback.
	 *
	 * @since    1.3.0
	 */
	public function mp4_support_field_callback() {
		$value = get_option( 'wp_mux_default_mp4_support', 'none' );
		?>
		<select name="wp_mux_default_mp4_support" id="wp_mux_default_mp4_support">
			<option value="none" <?php selected( $value, 'none' ); ?>>
				<?php _e( 'None (Lowest cost)', 'wp-mux' ); ?>
			</option>
			<option value="standard" <?php selected( $value, 'standard' ); ?>>
				<?php _e( 'Standard (Enable downloadable MP4)', 'wp-mux' ); ?>
			</option>
			<option value="audio-only" <?php selected( $value, 'audio-only' ); ?>>
				<?php _e( 'Audio Only', 'wp-mux' ); ?>
			</option>
		</select>
		<p class="description">
			<?php _e( 'MP4 support allows downloading videos but increases storage costs.', 'wp-mux' ); ?>
		</p>
		<?php
	}

	/**
	 * Encoding tier field callback.
	 *
	 * @since    1.3.0
	 */
	public function encoding_tier_field_callback() {
		$value = get_option( 'wp_mux_default_encoding_tier', 'baseline' );
		?>
		<select name="wp_mux_default_encoding_tier" id="wp_mux_default_encoding_tier">
			<option value="baseline" <?php selected( $value, 'baseline' ); ?>>
				<?php _e( 'Baseline (Standard quality)', 'wp-mux' ); ?>
			</option>
			<option value="smart" <?php selected( $value, 'smart' ); ?>>
				<?php _e( 'Smart (Optimized encoding)', 'wp-mux' ); ?>
			</option>
			<option value="plus" <?php selected( $value, 'plus' ); ?>>
				<?php _e( 'Plus (Premium quality)', 'wp-mux' ); ?>
			</option>
		</select>
		<p class="description">
			<?php _e( 'Higher tiers provide better quality but increase processing costs. Cannot be changed after upload.', 'wp-mux' ); ?>
		</p>
		<?php
	}

	/**
	 * Auto captions field callback.
	 *
	 * @since    1.3.0
	 */
	public function auto_captions_field_callback() {
		$enabled = get_option( 'wp_mux_enable_auto_captions', false );
		$language = get_option( 'wp_mux_default_caption_language', 'en' );
		?>
		<label>
			<input type="checkbox" name="wp_mux_enable_auto_captions" value="1" <?php checked( $enabled ); ?> />
			<?php _e( 'Automatically generate captions on upload', 'wp-mux' ); ?>
		</label>
		
		<div style="margin-top: 10px;">
			<label for="wp_mux_default_caption_language"><?php _e( 'Caption Language:', 'wp-mux' ); ?></label>
			<select name="wp_mux_default_caption_language" id="wp_mux_default_caption_language">
				<option value="en" <?php selected( $language, 'en' ); ?>><?php _e( 'English', 'wp-mux' ); ?></option>
				<option value="es" <?php selected( $language, 'es' ); ?>><?php _e( 'Spanish', 'wp-mux' ); ?></option>
				<option value="fr" <?php selected( $language, 'fr' ); ?>><?php _e( 'French', 'wp-mux' ); ?></option>
				<option value="de" <?php selected( $language, 'de' ); ?>><?php _e( 'German', 'wp-mux' ); ?></option>
				<option value="it" <?php selected( $language, 'it' ); ?>><?php _e( 'Italian', 'wp-mux' ); ?></option>
				<option value="pt" <?php selected( $language, 'pt' ); ?>><?php _e( 'Portuguese', 'wp-mux' ); ?></option>
				<option value="ja" <?php selected( $language, 'ja' ); ?>><?php _e( 'Japanese', 'wp-mux' ); ?></option>
				<option value="zh" <?php selected( $language, 'zh' ); ?>><?php _e( 'Chinese', 'wp-mux' ); ?></option>
			</select>
		</div>
		
		<p class="description">
			<?php _e( 'Requires Mux captions to be enabled on your account. May incur additional costs.', 'wp-mux' ); ?>
		</p>
		<?php
	}

	/**
	 * Signing keys field callback.
	 *
	 * @since    1.3.0
	 */
	public function signing_keys_field_callback() {
		$key_id = get_option( 'wp_mux_signing_key_id', '' );
		$has_private_key = ! empty( get_option( 'wp_mux_signing_key_private', '' ) );
		?>
		<div class="wp-mux-signing-keys-container">
			<div class="wp-mux-auto-configure-section" style="background: #f0f0f1; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
				<h4 style="margin-top: 0;"><?php _e( 'Quick Setup', 'wp-mux' ); ?></h4>
				<p><?php _e( 'Let WP Mux automatically create and configure signing keys for you.', 'wp-mux' ); ?></p>
				<button type="button" 
						class="button button-secondary" 
						id="wp-mux-auto-configure-keys"
						<?php echo $has_private_key ? 'disabled' : ''; ?>>
					<span class="dashicons dashicons-admin-network" style="margin-top: 3px;"></span>
					<?php _e( 'Auto-Configure Signing Keys', 'wp-mux' ); ?>
				</button>
				<?php if ( $has_private_key ) : ?>
					<p class="description" style="margin-top: 10px;">
						<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
						<?php _e( 'Signing keys are already configured.', 'wp-mux' ); ?>
						<button type="button" 
								class="button button-link" 
								id="wp-mux-reconfigure-keys"
								style="color: #b32d2e;">
							<?php _e( 'Reconfigure', 'wp-mux' ); ?>
						</button>
					</p>
				<?php endif; ?>
				<div id="wp-mux-auto-configure-status" style="margin-top: 10px;"></div>
			</div>

			<div class="wp-mux-manual-keys-section">
				<h4><?php _e( 'Manual Configuration', 'wp-mux' ); ?></h4>
				<div>
					<label for="wp_mux_signing_key_id"><?php _e( 'Signing Key ID:', 'wp-mux' ); ?></label>
					<input type="text" 
						   name="wp_mux_signing_key_id" 
						   id="wp_mux_signing_key_id" 
						   value="<?php echo esc_attr( $key_id ); ?>" 
						   class="regular-text" 
						   readonly />
				</div>
				
				<div style="margin-top: 10px;">
					<label for="wp_mux_signing_key_private"><?php _e( 'Private Key (Base64):', 'wp-mux' ); ?></label>
					<textarea name="wp_mux_signing_key_private" 
							  id="wp_mux_signing_key_private" 
							  rows="4" 
							  class="large-text code"
							  readonly
							  placeholder="<?php echo $has_private_key ? esc_attr__( '••••••••', 'wp-mux' ) : ''; ?>"></textarea>
					<?php if ( $has_private_key ) : ?>
						<p class="description">
							<?php _e( 'Keys are configured and stored securely.', 'wp-mux' ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		
		<p class="description">
			<?php _e( 'Signing keys are required for signed URLs and protected videos.', 'wp-mux' ); ?>
			<br />
			<a href="https://docs.mux.com/guides/secure-video-playback" target="_blank">
				<?php _e( 'Learn more about securing video playback', 'wp-mux' ); ?> ↗
			</a>
		</p>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#wp-mux-auto-configure-keys').on('click', function() {
				var $button = $(this);
				var $status = $('#wp-mux-auto-configure-status');
				
				$button.prop('disabled', true).text('<?php _e( 'Configuring...', 'wp-mux' ); ?>');
				$status.html('<p><?php _e( 'Creating signing keys in Mux...', 'wp-mux' ); ?></p>');
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'wp_mux_auto_configure_keys',
						nonce: '<?php echo wp_create_nonce( 'wp_mux_auto_configure_keys' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$status.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
							if (response.data.key_id) {
								$('#wp_mux_signing_key_id').val(response.data.key_id);
							}
							setTimeout(function() {
								location.reload();
							}, 1500);
						} else {
							$status.html('<div class="notice notice-error inline"><p>' + (response.data.message || '<?php _e( 'Failed to configure keys', 'wp-mux' ); ?>') + '</p></div>');
							$button.prop('disabled', false).text('<?php _e( 'Auto-Configure Signing Keys', 'wp-mux' ); ?>');
						}
					},
					error: function() {
						$status.html('<div class="notice notice-error inline"><p><?php _e( 'Network error. Please try again.', 'wp-mux' ); ?></p></div>');
						$button.prop('disabled', false).text('<?php _e( 'Auto-Configure Signing Keys', 'wp-mux' ); ?>');
					}
				});
			});

			$('#wp-mux-reconfigure-keys').on('click', function(e) {
				e.preventDefault();
				if (confirm('<?php _e( 'This will create new signing keys. Old keys will still exist in Mux but can be deleted manually. Continue?', 'wp-mux' ); ?>')) {
					$('#wp-mux-auto-configure-keys').prop('disabled', false).trigger('click');
				}
			});
		});
		</script>

		<style>
		.wp-mux-signing-keys-container .dashicons {
			vertical-align: middle;
		}
		.wp-mux-manual-keys-section h4 {
			margin-bottom: 10px;
		}
		</style>
		<?php
	}

	/**
	 * Sanitize playback policy value.
	 *
	 * @since    1.3.0
	 * @param    string    $value    The value to sanitize.
	 * @return   string              The sanitized value.
	 */
	public function sanitize_playback_policy( $value ) {
		$allowed = array( 'public', 'signed' );
		return in_array( $value, $allowed, true ) ? $value : 'public';
	}

	/**
	 * Sanitize MP4 support value.
	 *
	 * @since    1.3.0
	 * @param    string    $value    The value to sanitize.
	 * @return   string              The sanitized value.
	 */
	public function sanitize_mp4_support( $value ) {
		$allowed = array( 'none', 'standard', 'audio-only' );
		return in_array( $value, $allowed, true ) ? $value : 'none';
	}

	/**
	 * Sanitize encoding tier value.
	 *
	 * @since    1.3.0
	 * @param    string    $value    The value to sanitize.
	 * @return   string              The sanitized value.
	 */
	public function sanitize_encoding_tier( $value ) {
		$allowed = array( 'baseline', 'smart', 'plus' );
		return in_array( $value, $allowed, true ) ? $value : 'baseline';
	}

	/**
	 * Sanitize signing key value.
	 *
	 * @since    1.3.0
	 * @param    string    $value    The value to sanitize.
	 * @return   string              The sanitized value.
	 */
	public function sanitize_signing_key( $value ) {
		if ( empty( $value ) ) {
			return get_option( 'wp_mux_signing_key_private', '' );
		}
		
		return base64_encode( $value );
	}

	/**
	 * Sanitize API credentials and trigger auto-setup of signing keys.
	 *
	 * @since    1.3.0
	 * @param    string    $value    The access token value.
	 * @return   string              The sanitized value.
	 */
	public function sanitize_api_credentials( $value ) {
		$sanitized = sanitize_text_field( $value );
		
		// Check if this is a new credential being saved (not empty)
		$old_value = get_option( 'wp_mux_access_token', '' );
		$secret_key = isset( $_POST['wp_mux_secret_key'] ) ? sanitize_text_field( $_POST['wp_mux_secret_key'] ) : '';
		
		// If credentials are being set for the first time or changed
		if ( ! empty( $sanitized ) && ! empty( $secret_key ) ) {
			// Check if signing keys don't exist yet
			$has_keys = ! empty( get_option( 'wp_mux_signing_key_id', '' ) );
			
			if ( ! $has_keys ) {
				// Schedule signing key creation on next page load
				set_transient( 'wp_mux_needs_key_creation', true, HOUR_IN_SECONDS );
			}
		}
		
		return $sanitized;
	}

	/**
	 * Maybe create signing keys if API credentials were just configured.
	 *
	 * @since    1.3.0
	 */
	public function maybe_create_signing_keys() {
		// Check if we need to create keys
		if ( ! get_transient( 'wp_mux_needs_key_creation' ) ) {
			return;
		}

		// Delete the transient so we don't retry unnecessarily
		delete_transient( 'wp_mux_needs_key_creation' );

		// Load the API class
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();

		// Check if API is configured
		if ( ! $api->is_configured() ) {
			return;
		}

		// Try to auto-configure signing keys
		$result = $api->auto_configure_signing_keys();

		if ( ! is_wp_error( $result ) ) {
			set_transient( 'wp_mux_signing_key_success', $result['key_id'], WEEK_IN_SECONDS );
			update_option( 'wp_mux_default_playback_policy', 'signed' );
		} else {
			set_transient( 'wp_mux_signing_key_error', $result->get_error_message(), DAY_IN_SECONDS );
		}
	}

	/**
	 * Redirect to settings page if API is not configured and user is trying to access protected pages.
	 *
	 * @since    1.5.0
	 */
	public function maybe_redirect_to_settings() {
		// Check if we're on an admin page
		if ( ! is_admin() ) {
			return;
		}

		// Get current page
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

		// List of pages that require API configuration
		$protected_pages = array(
			'wp-mux-videos',
			'wp-mux-migration',
		);

		// Check if current page requires API configuration
		if ( ! in_array( $current_page, $protected_pages ) ) {
			return;
		}

		// Check if API is configured
		if ( $this->is_api_configured() ) {
			return;
		}

		// Set transient to show notice on settings page
		set_transient( 'wp_mux_api_required_notice', $current_page, 30 );

		// Redirect to settings page
		wp_safe_redirect( admin_url( 'admin.php?page=wp-mux-settings' ) );
		exit;
	}

	/**
	 * Player settings section callback.
	 *
	 * @since    1.4.0
	 */
	public function player_settings_section_callback() {
		echo '<p>' . __( 'Configure advanced Mux Player options that affect playback behavior globally across your site.', 'wp-mux' ) . '</p>';
	}

	/**
	 * Prefer playback field callback.
	 *
	 * @since    1.4.0
	 */
	public function prefer_playback_field_callback() {
		$value = get_option( 'wp_mux_player_prefer_playback', 'auto' );
		?>
		<select name="wp_mux_player_prefer_playback" id="wp_mux_player_prefer_playback">
			<option value="auto" <?php selected( $value, 'auto' ); ?>>
				<?php _e( 'Auto (Recommended)', 'wp-mux' ); ?>
			</option>
			<option value="native" <?php selected( $value, 'native' ); ?>>
				<?php _e( 'Native (Browser built-in)', 'wp-mux' ); ?>
			</option>
			<option value="mse" <?php selected( $value, 'mse' ); ?>>
				<?php _e( 'MSE (HLS.js library)', 'wp-mux' ); ?>
			</option>
		</select>
		<p class="description">
			<?php _e( 'Controls which playback engine to use. "Native" may resolve Safari playback issues. "MSE" uses HLS.js on all browsers.', 'wp-mux' ); ?>
		</p>
		<?php
	}

	/**
	 * Max resolution field callback.
	 *
	 * @since    1.4.0
	 */
	public function max_resolution_field_callback() {
		$value = get_option( 'wp_mux_player_max_resolution', '' );
		?>
		<select name="wp_mux_player_max_resolution" id="wp_mux_player_max_resolution">
			<option value="" <?php selected( $value, '' ); ?>>
				<?php _e( 'No limit', 'wp-mux' ); ?>
			</option>
			<option value="270p" <?php selected( $value, '270p' ); ?>>270p</option>
			<option value="360p" <?php selected( $value, '360p' ); ?>>360p</option>
			<option value="480p" <?php selected( $value, '480p' ); ?>>480p</option>
			<option value="540p" <?php selected( $value, '540p' ); ?>>540p</option>
			<option value="720p" <?php selected( $value, '720p' ); ?>>720p</option>
			<option value="1080p" <?php selected( $value, '1080p' ); ?>>1080p</option>
			<option value="1440p" <?php selected( $value, '1440p' ); ?>>1440p</option>
			<option value="2160p" <?php selected( $value, '2160p' ); ?>>2160p (4K)</option>
		</select>
		<p class="description">
			<?php _e( 'Limits the maximum video quality. Useful for bandwidth management or fixing playback issues (e.g., Safari).', 'wp-mux' ); ?>
		</p>
		<?php
	}

	/**
	 * Min resolution field callback.
	 *
	 * @since    1.4.0
	 */
	public function min_resolution_field_callback() {
		$value = get_option( 'wp_mux_player_min_resolution', '' );
		?>
		<select name="wp_mux_player_min_resolution" id="wp_mux_player_min_resolution">
			<option value="" <?php selected( $value, '' ); ?>>
				<?php _e( 'No limit', 'wp-mux' ); ?>
			</option>
			<option value="270p" <?php selected( $value, '270p' ); ?>>270p</option>
			<option value="360p" <?php selected( $value, '360p' ); ?>>360p</option>
			<option value="480p" <?php selected( $value, '480p' ); ?>>480p</option>
			<option value="540p" <?php selected( $value, '540p' ); ?>>540p</option>
			<option value="720p" <?php selected( $value, '720p' ); ?>>720p</option>
			<option value="1080p" <?php selected( $value, '1080p' ); ?>>1080p</option>
			<option value="1440p" <?php selected( $value, '1440p' ); ?>>1440p</option>
			<option value="2160p" <?php selected( $value, '2160p' ); ?>>2160p (4K)</option>
		</select>
		<p class="description">
			<?php _e( 'Ensures videos never drop below this quality level. Leave blank for automatic quality selection.', 'wp-mux' ); ?>
		</p>
		<?php
	}

	/**
	 * Preload field callback.
	 *
	 * @since    1.4.0
	 */
	public function preload_field_callback() {
		$value = get_option( 'wp_mux_player_preload', 'metadata' );
		?>
		<select name="wp_mux_player_preload" id="wp_mux_player_preload">
			<option value="none" <?php selected( $value, 'none' ); ?>>
				<?php _e( 'None (Lowest bandwidth)', 'wp-mux' ); ?>
			</option>
			<option value="metadata" <?php selected( $value, 'metadata' ); ?>>
				<?php _e( 'Metadata only (Recommended)', 'wp-mux' ); ?>
			</option>
			<option value="auto" <?php selected( $value, 'auto' ); ?>>
				<?php _e( 'Auto (Preload as much as possible)', 'wp-mux' ); ?>
			</option>
		</select>
		<p class="description">
			<?php _e( 'Controls how much video data is loaded before playback starts. "Metadata" loads duration and dimensions only.', 'wp-mux' ); ?>
		</p>
		<?php
	}

	/**
	 * Debug field callback.
	 *
	 * @since    1.4.0
	 */
	public function debug_field_callback() {
		$checked = checked( 1, get_option( 'wp_mux_player_debug', 0 ), false );
		?>
		<label>
			<input type="checkbox" name="wp_mux_player_debug" value="1" <?php echo $checked; ?> />
			<?php _e( 'Enable debug logging in browser console', 'wp-mux' ); ?>
		</label>
		<p class="description">
			<?php _e( 'Useful for troubleshooting playback issues. Outputs HLS.js and Mux Data debug messages.', 'wp-mux' ); ?>
		</p>
		<?php
	}

	/**
	 * Uninstall settings section callback.
	 *
	 * @since    1.1.1
	 */
	public function uninstall_settings_section_callback() {
		$mux_video_count = $this->get_mux_video_count();
		
		echo '<p style="margin: 0 0 20px 0;">';
		if ( $mux_video_count > 0 ) {
			echo sprintf(
				_n( 
					'You have <strong>%d MUX video</strong> on your site. Choose what happens if you uninstall this plugin:', 
					'You have <strong>%d MUX videos</strong> on your site. Choose what happens if you uninstall this plugin:', 
					$mux_video_count, 
					'wp-mux' 
				),
				$mux_video_count
			);
		} else {
			_e( 'No MUX videos detected. Choose what happens if you uninstall this plugin:', 'wp-mux' );
		}
		echo '</p>';
		
		echo '<div style="background: #f0f6fc; border: 1px solid #0c5c9d; border-radius: 4px; padding: 12px 16px; margin-bottom: 20px;">';
		echo '<p style="margin: 0; color: #0c5c9d;"><strong>⚠️ ' . __( 'Important:', 'wp-mux' ) . '</strong> ';
		_e( 'Videos stored on Mux.com are NEVER automatically deleted and will continue to incur storage costs. You must delete them manually from', 'wp-mux' );
		echo ' <a href="https://dashboard.mux.com" target="_blank">dashboard.mux.com</a>.</p>';
		echo '</div>';
	}

	/**
	 * Delete on uninstall field callback.
	 *
	 * @since    1.1.1
	 */
	public function delete_on_uninstall_field_callback() {
		$checked = checked( 1, get_option( 'wp_mux_delete_on_uninstall', 0 ), false );
		$mux_video_count = $this->get_mux_video_count();
		?>
		
		<div style="max-width: 800px;">
			<!-- Option 1: Keep Everything (Default) -->
			<div style="border: 2px solid #46b450; border-radius: 6px; padding: 20px; margin-bottom: 16px; background: #f6fef6;">
				<h3 style="margin: 0 0 12px 0; color: #2e7d32; font-size: 16px;">
					<span style="background: #46b450; color: white; border-radius: 50%; width: 28px; height: 28px; display: inline-block; text-align: center; line-height: 28px; margin-right: 8px;">1</span>
					<?php _e( 'Keep Everything (Recommended)', 'wp-mux' ); ?>
				</h3>
				<p style="margin: 0 0 12px 0;"><?php _e( 'Leave the checkbox below <strong>unchecked</strong> when you uninstall.', 'wp-mux' ); ?></p>
				<p style="margin: 0 0 8px 0; font-weight: 600;"><?php _e( 'What happens:', 'wp-mux' ); ?></p>
				<ul style="margin: 0 0 12px 0; padding-left: 24px; list-style-type: disc;">
					<li><?php _e( 'Temporary data cleared (caches, queues)', 'wp-mux' ); ?></li>
					<li><?php _e( 'All videos, settings, and themes preserved', 'wp-mux' ); ?></li>
					<li><?php _e( 'Can reinstall anytime without data loss', 'wp-mux' ); ?></li>
					<li><?php _e( 'Video blocks in content: Will stop working but can be reactivated by reinstalling', 'wp-mux' ); ?></li>
				</ul>
				<p style="margin: 0; color: #2e7d32;"><strong><?php _e( 'Best for:', 'wp-mux' ); ?></strong> <?php _e( 'Testing, troubleshooting, or taking a break from Mux', 'wp-mux' ); ?></p>
			</div>
			
			<?php if ( $mux_video_count > 0 ) : ?>
			<!-- Option 2: Migrate Videos Back -->
			<div style="border: 2px solid #0073aa; border-radius: 6px; padding: 20px; margin-bottom: 16px; background: #f0f6fc;">
				<h3 style="margin: 0 0 12px 0; color: #0073aa; font-size: 16px;">
					<span style="background: #0073aa; color: white; border-radius: 50%; width: 28px; height: 28px; display: inline-block; text-align: center; line-height: 28px; margin-right: 8px;">2</span>
					<?php _e( 'Migrate Videos Back to WordPress', 'wp-mux' ); ?>
				</h3>
				<p style="margin: 0 0 12px 0;"><?php _e( 'Before uninstalling, download your videos from Mux back to WordPress hosting.', 'wp-mux' ); ?></p>
				<p style="margin: 0 0 8px 0; font-weight: 600;"><?php _e( 'Steps:', 'wp-mux' ); ?></p>
				<ol style="margin: 0 0 12px 0; padding-left: 24px;">
					<li><?php 
						echo sprintf(
							__( 'Use the %sMigrate Back%s page to download videos', 'wp-mux' ),
							'<a href="' . admin_url( 'admin.php?page=wp-mux-migrate-back' ) . '">',
							'</a>'
						);
					?></li>
					<li><?php _e( 'Migration will update video blocks in your content to use WordPress-hosted videos', 'wp-mux' ); ?></li>
					<li><?php _e( 'Optionally delete videos from Mux.com to stop billing', 'wp-mux' ); ?></li>
					<li><?php _e( 'Then uninstall the plugin (Option 1 or 3)', 'wp-mux' ); ?></li>
				</ol>
				<p style="margin: 0; color: #0073aa;"><strong><?php _e( 'Best for:', 'wp-mux' ); ?></strong> <?php _e( 'Permanently moving away from Mux hosting', 'wp-mux' ); ?></p>
			</div>
			<?php endif; ?>
			
			<!-- Option 3: Full Cleanup -->
			<div style="border: 2px solid #dba617; border-radius: 6px; padding: 20px; background: #fffbf0;">
				<h3 style="margin: 0 0 12px 0; color: #8a6d15; font-size: 16px;">
					<span style="background: #dba617; color: white; border-radius: 50%; width: 28px; height: 28px; display: inline-block; text-align: center; line-height: 28px; margin-right: 8px;"><?php echo $mux_video_count > 0 ? '3' : '2'; ?></span>
					<?php _e( 'Full Cleanup', 'wp-mux' ); ?>
				</h3>
				<p style="margin: 0 0 12px 0;"><?php _e( '<strong>Check the box below</strong> before uninstalling to remove all plugin data.', 'wp-mux' ); ?></p>
				<p style="margin: 0 0 8px 0; font-weight: 600;"><?php _e( 'What happens:', 'wp-mux' ); ?></p>
				<ul style="margin: 0 0 12px 0; padding-left: 24px; list-style-type: disc;">
					<li><?php _e( 'All settings, themes, and credentials deleted', 'wp-mux' ); ?></li>
					<li><?php _e( 'All video attachments and metadata removed', 'wp-mux' ); ?></li>
					<li><?php _e( 'Videos on Mux.com remain untouched', 'wp-mux' ); ?></li>
					<li><?php _e( 'Video blocks in content: Will show as broken/missing until manually removed or replaced with new videos', 'wp-mux' ); ?></li>
				</ul>
				<p style="margin: 0; color: #8a6d15;"><strong><?php _e( 'Best for:', 'wp-mux' ); ?></strong> <?php _e( 'Complete removal, testing, or starting fresh', 'wp-mux' ); ?></p>
			</div>
			
			<!-- Checkbox Control -->
			<div style="margin-top: 24px; padding: 16px; background: white; border: 1px solid #ddd; border-radius: 4px;">
				<label style="display: flex; align-items: center; cursor: pointer;">
					<input type="checkbox" name="wp_mux_delete_on_uninstall" value="1" <?php echo $checked; ?> id="wp_mux_delete_on_uninstall" style="margin: 0 8px 0 0;" />
					<span style="font-size: 14px; font-weight: 600;"><?php _e( 'Enable Full Cleanup (Option ' . ( $mux_video_count > 0 ? '3' : '2' ) . ') - Delete all plugin data when uninstalling', 'wp-mux' ); ?></span>
				</label>
				<p style="margin: 12px 0 0 28px; color: #666; font-size: 13px;">
					<?php _e( 'Leave unchecked for Option 1 (Keep Everything). Check this box only if you want Option ' . ( $mux_video_count > 0 ? '3' : '2' ) . ' (Full Cleanup).', 'wp-mux' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Get count of MUX videos on the site.
	 *
	 * @since    1.5.0
	 * @return   int    Number of MUX videos
	 */
	private function get_mux_video_count() {
		$count = wp_count_posts( 'attachment' );
		
		// Query to count only video/mux mime type
		global $wpdb;
		$mux_count = $wpdb->get_var( 
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s AND post_mime_type = %s",
				'attachment',
				'video/mux'
			)
		);
		
		return (int) $mux_count;
	}

	/**
	 * Check if MUX API is configured.
	 *
	 * @since    1.5.0
	 * @return   bool    True if API credentials are set, false otherwise
	 */
	private function is_api_configured() {
		$access_token = get_option( 'wp_mux_access_token', '' );
		$secret_key = get_option( 'wp_mux_secret_key', '' );
		
		return ! empty( $access_token ) && ! empty( $secret_key );
	}

	/**
	 * Sanitize prefer playback option.
	 *
	 * @since    1.4.0
	 * @param    string    $value    The value to sanitize.
	 * @return   string              The sanitized value.
	 */
	public function sanitize_prefer_playback( $value ) {
		$valid = array( 'auto', 'native', 'mse' );
		return in_array( $value, $valid, true ) ? $value : 'auto';
	}

	/**
	 * Sanitize max resolution option.
	 *
	 * @since    1.4.0
	 * @param    string    $value    The value to sanitize.
	 * @return   string              The sanitized value.
	 */
	public function sanitize_max_resolution( $value ) {
		$valid = array( '', '270p', '360p', '480p', '540p', '720p', '1080p', '1440p', '2160p' );
		return in_array( $value, $valid, true ) ? $value : '';
	}

	/**
	 * Sanitize min resolution option.
	 *
	 * @since    1.4.0
	 * @param    string    $value    The value to sanitize.
	 * @return   string              The sanitized value.
	 */
	public function sanitize_min_resolution( $value ) {
		$valid = array( '', '270p', '360p', '480p', '540p', '720p', '1080p', '1440p', '2160p' );
		return in_array( $value, $valid, true ) ? $value : '';
	}

	/**
	 * Sanitize preload option.
	 *
	 * @since    1.4.0
	 * @param    string    $value    The value to sanitize.
	 * @return   string              The sanitized value.
	 */
	public function sanitize_preload( $value ) {
		$valid = array( 'none', 'metadata', 'auto' );
		return in_array( $value, $valid, true ) ? $value : 'metadata';
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
		// Debug logging disabled for production
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

		// Let WordPress handle the file upload first, then queue for MUX processing
		// This is handled by queue_video_for_mux_processing hook on add_attachment
		$this->mux_debug_log( 'Letting WordPress handle upload, will queue for MUX via add_attachment hook' );
		return;

		// Note: The following code is unreachable but kept for reference
		if ( false ) {
			$this->mux_debug_log( 'MUX upload failed (unreachable)', [ 'error' => 'Method removed' ] );
			wp_send_json_error( array(
				'message' => 'Method removed'
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

		// Check if already queued or processed to prevent duplicates
		$existing_status = get_post_meta($attachment_id, '_wp_mux_status', true);
		if (!empty($existing_status) && in_array($existing_status, ['queued', 'processing', 'complete'])) {
			$this->mux_debug_log('Video already queued or processed', [
				'attachment_id' => $attachment_id,
				'status' => $existing_status
			]);
			return;
		}

		// Check if attachment is already a MUX video
		$post = get_post($attachment_id);
		if ($post && $post->post_mime_type === 'video/mux') {
			$this->mux_debug_log('Attachment is already a MUX video', ['attachment_id' => $attachment_id]);
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

			// Add MUX identifiers
			$response['mux_asset_id'] = $asset_id;
			$response['mux_playback_id'] = get_post_meta( $attachment->ID, '_mux_playback_id', true );

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
		// Debug logging disabled for production
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

	/**
	 * Delete MUX asset when WordPress attachment is deleted.
	 *
	 * This hook is called when an attachment is deleted from WordPress.
	 * If the attachment is a MUX video, we also delete it from MUX.
	 *
	 * @since    1.2.0
	 * @param    int    $attachment_id    The ID of the attachment being deleted.
	 */
	public function handle_attachment_deletion( $attachment_id ) {
		// Get the attachment
		$attachment = get_post( $attachment_id );

		// Check if this is a MUX video
		if ( ! $attachment || $attachment->post_mime_type !== 'video/mux' ) {
			return;
		}

		// Get the MUX asset ID
		$mux_asset_id = get_post_meta( $attachment_id, '_mux_asset_id', true );

		if ( empty( $mux_asset_id ) ) {
			$this->mux_debug_log( 'No MUX asset ID found for attachment', array(
				'attachment_id' => $attachment_id,
			) );
			return;
		}

		$this->mux_debug_log( 'Deleting MUX asset for attachment', array(
			'attachment_id' => $attachment_id,
			'mux_asset_id'  => $mux_asset_id,
		) );

		// Initialize MUX API
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-api.php';
		$api = new Wp_Mux_Api();

		// Attempt to delete the video from MUX
		$result = $api->delete_video( $mux_asset_id );

		if ( is_wp_error( $result ) ) {
			$this->mux_debug_log( 'Failed to delete MUX asset', array(
				'attachment_id' => $attachment_id,
				'mux_asset_id'  => $mux_asset_id,
				'error'         => $result->get_error_message(),
			) );
		} else {
			$this->mux_debug_log( 'Successfully deleted MUX asset', array(
				'attachment_id' => $attachment_id,
				'mux_asset_id'  => $mux_asset_id,
			) );

			// Clear the account stats cache so the overview updates immediately
			delete_transient( 'wp_mux_account_stats' );
		}
	}

	/**
	 * AJAX handler: Migrate-back system check
	 *
	 * @since    1.3.0
	 */
	public function ajax_migrate_back_system_check() {
		check_ajax_referer( 'wp_mux_migrate_back', 'nonce' );

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-migrate-back.php';
		$migrator = new Wp_Mux_Migrate_Back();
		$result = $migrator->validate_system();

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: Scan Mux videos for migration
	 *
	 * @since    1.3.0
	 */
	public function ajax_migrate_back_scan() {
		check_ajax_referer( 'wp_mux_migrate_back', 'nonce' );

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-migrate-back.php';
		$migrator = new Wp_Mux_Migrate_Back();
		$result = $migrator->scan_mux_videos();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: Enable MP4 support for a video
	 *
	 * @since    1.3.0
	 */
	public function ajax_migrate_back_enable_mp4() {
		check_ajax_referer( 'wp_mux_migrate_back', 'nonce' );

		$asset_id = isset( $_POST['asset_id'] ) ? sanitize_text_field( $_POST['asset_id'] ) : '';
		if ( empty( $asset_id ) ) {
			wp_send_json_error( __( 'Missing asset ID', 'wp-mux' ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-migrate-back.php';
		$migrator = new Wp_Mux_Migrate_Back();
		$result = $migrator->enable_mp4_support( $asset_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX handler: Scan content for video references
	 *
	 * @since    1.3.0
	 */
	public function ajax_migrate_back_scan_content() {
		check_ajax_referer( 'wp_mux_migrate_back', 'nonce' );

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-content-scanner.php';
		$scanner = new Wp_Mux_Content_Scanner();
		$result = $scanner->scan_content_references();

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: Migrate a single video back to local
	 *
	 * @since    1.3.0
	 */
	public function ajax_migrate_back_video() {
		check_ajax_referer( 'wp_mux_migrate_back', 'nonce' );

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'Missing attachment ID', 'wp-mux' ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-migrate-back.php';
		$migrator = new Wp_Mux_Migrate_Back();
		$result = $migrator->migrate_video_to_local( $attachment_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: Update content references
	 *
	 * @since    1.3.0
	 */
	public function ajax_migrate_back_update_content() {
		check_ajax_referer( 'wp_mux_migrate_back', 'nonce' );

		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', $_POST['post_ids'] ) : array();
		if ( empty( $post_ids ) ) {
			wp_send_json_error( __( 'No posts specified', 'wp-mux' ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-content-scanner.php';
		$scanner = new Wp_Mux_Content_Scanner();
		$result = $scanner->batch_update_content( $post_ids, true );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: Cleanup Mux video
	 *
	 * @since    1.3.0
	 */
	public function ajax_migrate_back_cleanup() {
		check_ajax_referer( 'wp_mux_migrate_back', 'nonce' );

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'Missing attachment ID', 'wp-mux' ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-migrate-back.php';
		$migrator = new Wp_Mux_Migrate_Back();
		$result = $migrator->cleanup_mux_video( $attachment_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success();
	}

}
