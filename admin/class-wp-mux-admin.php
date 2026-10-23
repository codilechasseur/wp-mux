<?php

declare(strict_types=1);

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://wpmux.com
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
 * @author     Codi Lechasseur
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-mux-admin.css', array(), $this->version, 'all' );
		// Add custom styles for MUX badge in media library
		wp_enqueue_style( 'wp-mux-media-badge', plugin_dir_url( __FILE__ ) . 'css/wp-mux-media-badge.css', array(), $this->version );

		// Enqueue theme management styles on themes page
		$screen = get_current_screen();
		if ( $screen && 'mux-videos_page_wp-mux-themes' === $screen->id ) {
			wp_enqueue_style( 'wp-mux-themes', plugin_dir_url( __FILE__ ) . 'css/wp-mux-themes.css', array(), $this->version );
		}

		// Enqueue migration styles on migration page, settings page (for sidebar layout)
		if ( $screen && (
			'mux-videos_page_wp-mux-migration' === $screen->id ||
			'mux-videos_page_wp-mux-settings' === $screen->id ||
			'toplevel_page_wp-mux-videos' === $screen->id
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-mux-admin.js', array( 'jquery' ), $this->version, false );

		// Load theme manager for theme data
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-theme-manager.php';
		$theme_manager = new Wp_Mux_Theme_Manager();
		$themes        = $theme_manager->get_themes();
		$theme_options = array();
		foreach ( $themes as $theme_id => $theme ) {
			$theme_options[] = array(
				'id'   => $theme_id,
				'name' => $theme['name'],
			);
		}

		// Localize script with necessary data
		$api = new Wp_Mux_Api();
		wp_localize_script( $this->plugin_name, 'wpMuxData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'playerScriptUrl' => Wp_Mux_Utils::player_script_url(),
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
			wp_enqueue_script( 'mux-player', Wp_Mux_Utils::player_script_url(), array(), null, true );
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
		$current_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( ( $screen && 'mux-videos_page_wp-mux-migration' === $screen->id && 'from-mux' === $current_tab ) ||
		     ( 'wp-mux-migration' === $current_page && 'from-mux' === $current_tab ) ) {
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
			wp_add_inline_script( 'wp-mux-block-editor', 'window.wpMuxThemes = ' . wp_json_encode( $theme_options ) . ';', 'before' );

			// Pass WordPress roles for the Access Restrictions checkboxes.
			$roles        = wp_roles()->roles;
			$role_options = array();
			foreach ( $roles as $role_key => $role_data ) {
				$role_options[] = array(
					'value' => $role_key,
					'label' => translate_user_role( $role_data['name'] ),
				);
			}
			wp_add_inline_script( 'wp-mux-block-editor', 'window.wpMuxRoles = ' . wp_json_encode( $role_options ) . ';', 'before' );
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
					<h3 style="margin: 0;"><?php esc_html_e( 'Video Analytics', 'wp-mux' ); ?></h3>
					<div style="display: flex; align-items: center; gap: 10px;">
						<span class="mux-stats-updated" style="font-size: 12px; color: #646970;">
							<?php
							$analytics = $this->get_video_analytics( $post->ID, false );
							$formatted = $this->format_analytics_for_display( $analytics );
							printf( __( 'Last updated: %s', 'wp-mux' ), esc_html( $formatted['last_updated'] ) );
							?>
						</span>
						<button type="button" class="button button-small mux-refresh-stats-edit-page" data-attachment-id="<?php echo esc_attr( $post->ID ); ?>" title="<?php esc_html_e( 'Refresh Analytics', 'wp-mux' ); ?>" style="min-width: auto; padding: 4px 8px; color: #646970; border-color: #dcdcde; display: inline-flex; align-items: center; justify-content: center;">
							<span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span>
						</button>
					</div>
				</div>
				<?php $this->render_analytics_content( $post ); ?>
			</div>

			<hr style="margin: 30px 0; border: none; border-top: 1px solid #dcdcde;">

			<!-- Settings Section -->
			<div class="wp-mux-section">
				<h3><?php esc_html_e( 'Video Settings', 'wp-mux' ); ?></h3>
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
					<div class="mux-stat-label"><?php esc_html_e( 'Total Views', 'wp-mux' ); ?></div>
				</div>

				<div class="mux-stat-card">
					<div class="mux-stat-value"><?php echo esc_html( $formatted['unique_viewers'] ); ?></div>
					<div class="mux-stat-label"><?php esc_html_e( 'Unique Viewers', 'wp-mux' ); ?></div>
				</div>

				<div class="mux-stat-card">
					<div class="mux-stat-value"><?php echo esc_html( $formatted['total_watch_time'] ); ?></div>
					<div class="mux-stat-label"><?php esc_html_e( 'Total Watch Time', 'wp-mux' ); ?></div>
				</div>

				<div class="mux-stat-card">
					<div class="mux-stat-value"><?php echo esc_html( $formatted['average_view_duration'] ); ?></div>
					<div class="mux-stat-label"><?php esc_html_e( 'Avg. View Duration', 'wp-mux' ); ?></div>
				</div>

				<?php if ( $formatted['video_quality_score'] !== 'N/A' ) : ?>
				<div class="mux-stat-card">
					<div class="mux-stat-value"><?php echo esc_html( $formatted['video_quality_score'] ); ?></div>
					<div class="mux-stat-label"><?php esc_html_e( 'Quality Score', 'wp-mux' ); ?></div>
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
							alert(response.data.message || '<?php echo esc_js( __( 'Failed to refresh stats', 'wp-mux' ) ); ?>');
							$button.prop('disabled', false).removeClass('mux-stats-loading');
						}
					},
					error: function(xhr, status, error) {
						alert('<?php echo esc_js( __( 'Failed to refresh stats', 'wp-mux' ) ); ?>');
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
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_mux_settings_nonce'] ) ), 'wp_mux_save_settings' ) ) {
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
			$new_policy = sanitize_text_field( wp_unslash( $_POST['mux_playback_policy'] ) );
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
			$new_support = sanitize_text_field( wp_unslash( $_POST['mux_mp4_support'] ) );
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

		// Cookieless / privacy mode: player will not set cookies or use localStorage.
		register_setting(
			'wp_mux_settings_group',
			'wp_mux_player_disable_cookies',
			array(
				'type' => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default' => false,
			)
		);

		// Disable Mux Data analytics beaconing entirely (maximum privacy).
		register_setting(
			'wp_mux_settings_group',
			'wp_mux_player_disable_mux_data',
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

		// Add cookieless / privacy mode field
		add_settings_field(
			'wp_mux_player_disable_cookies',
			__( 'Cookieless Mode', 'wp-mux' ),
			array( $this, 'disable_cookies_field_callback' ),
			'wp-mux-settings',
			'wp_mux_player_settings'
		);

		// Add disable Mux Data analytics field
		add_settings_field(
			'wp_mux_player_disable_mux_data',
			__( 'Disable Analytics', 'wp-mux' ),
			array( $this, 'disable_mux_data_field_callback' ),
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

		// Register webhook secret setting.
		register_setting(
			'wp_mux_settings_group',
			'wp_mux_webhook_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// Add webhook settings section.
		add_settings_section(
			'wp_mux_webhook_settings',
			__( 'Webhook Settings', 'wp-mux' ),
			array( $this, 'webhook_settings_section_callback' ),
			'wp-mux-settings'
		);

		add_settings_field(
			'wp_mux_webhook_secret',
			__( 'Webhook Signing Secret', 'wp-mux' ),
			array( $this, 'webhook_secret_field_callback' ),
			'wp-mux-settings',
			'wp_mux_webhook_settings'
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
		if ( ! isset( $_GET['page'] ) || sanitize_key( wp_unslash( $_GET['page'] ) ) !== 'wp-mux-themes' ) {
			return;
		}

		if ( ! isset( $_POST['action'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_mux_themes_nonce'] ?? '' ) ), 'wp_mux_themes_action' ) ) {
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-mux-theme-manager.php';
		$theme_manager = new Wp_Mux_Theme_Manager();

		switch ( sanitize_key( wp_unslash( $_POST['action'] ) ) ) {
			case 'save_theme':
				$theme_id    = sanitize_text_field( wp_unslash( $_POST['theme_id'] ?? '' ) );
				$name        = sanitize_text_field( wp_unslash( $_POST['theme_name'] ?? '' ) );
				$description = sanitize_textarea_field( wp_unslash( $_POST['theme_description'] ?? '' ) );
				$controls    = isset( $_POST['controls'] ) ? wp_unslash( (array) $_POST['controls'] ) : array();
				$styles      = isset( $_POST['styles'] ) ? wp_unslash( (array) $_POST['styles'] ) : array();

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
				$theme_id = sanitize_text_field( wp_unslash( $_POST['theme_id'] ?? '' ) );
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
				<?php esc_html_e( 'Public (Anyone can view)', 'wp-mux' ); ?>
			</option>
			<option value="signed"
			        <?php selected( $value, 'signed' ); ?>
			        <?php echo ! $has_capability ? 'disabled' : ''; ?>>
				<?php esc_html_e( 'Signed URLs (Requires token)', 'wp-mux' ); ?>
				<?php if ( ! $has_capability ) : ?>
					<?php esc_html_e( ' - Upgrade Required', 'wp-mux' ); ?>
				<?php endif; ?>
			</option>
		</select>

		<?php if ( ! $has_capability ) : ?>
			<p class="description" style="color: #d63638;">
				<span class="dashicons dashicons-lock" style="font-size: 16px; vertical-align: middle;"></span>
				<strong><?php esc_html_e( 'Private Videos Unavailable', 'wp-mux' ); ?></strong><br>
				<?php esc_html_e( 'Your Mux account does not have access to signed URLs. This feature requires:', 'wp-mux' ); ?>
				<ul style="list-style: disc; margin-left: 25px; margin-top: 5px; padding-left: 0;">
					<li><?php esc_html_e( 'A paid Mux plan, OR', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Signing keys API access enabled for your account', 'wp-mux' ); ?></li>
				</ul>
				<a href="https://mux.com/pricing" target="_blank"><?php esc_html_e( 'View Mux Pricing', 'wp-mux' ); ?> ↗</a>
			</p>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'Public videos can be played by anyone. Signed URLs automatically create secure tokens for each viewer.', 'wp-mux' ); ?>
				<br>
				<?php if ( $value === 'signed' ) : ?>
					<span style="color: #00a32a;">
						<span class="dashicons dashicons-yes-alt" style="font-size: 16px; vertical-align: middle;"></span>
						<?php esc_html_e( 'Signing keys are configured and ready.', 'wp-mux' ); ?>
					</span>
				<?php else : ?>
					<?php esc_html_e( 'Switching to "Signed URLs" will automatically configure signing keys for you.', 'wp-mux' ); ?>
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
								if (confirm('<?php esc_html_e( 'Switching to signed URLs will automatically create and configure signing keys. Continue?', 'wp-mux' ); ?>')) {
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
												alert('<?php esc_html_e( 'Signing keys configured successfully!', 'wp-mux' ); ?>');
											} else {
												alert('<?php esc_html_e( 'Failed to configure signing keys. Please try again.', 'wp-mux' ); ?>');
												$select.val('public').trigger('change');
											}
										},
										error: function() {
											alert('<?php esc_html_e( 'Network error. Please try again.', 'wp-mux' ); ?>');
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
				<?php esc_html_e( 'None (Lowest cost)', 'wp-mux' ); ?>
			</option>
			<option value="standard" <?php selected( $value, 'standard' ); ?>>
				<?php esc_html_e( 'Standard (Enable downloadable MP4)', 'wp-mux' ); ?>
			</option>
			<option value="audio-only" <?php selected( $value, 'audio-only' ); ?>>
				<?php esc_html_e( 'Audio Only', 'wp-mux' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'MP4 support allows downloading videos but increases storage costs.', 'wp-mux' ); ?>
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
				<?php esc_html_e( 'Baseline (Standard quality)', 'wp-mux' ); ?>
			</option>
			<option value="smart" <?php selected( $value, 'smart' ); ?>>
				<?php esc_html_e( 'Smart (Optimized encoding)', 'wp-mux' ); ?>
			</option>
			<option value="plus" <?php selected( $value, 'plus' ); ?>>
				<?php esc_html_e( 'Plus (Premium quality)', 'wp-mux' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Higher tiers provide better quality but increase processing costs. Cannot be changed after upload.', 'wp-mux' ); ?>
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
			<?php esc_html_e( 'Automatically generate captions on upload', 'wp-mux' ); ?>
		</label>

		<div style="margin-top: 10px;">
			<label for="wp_mux_default_caption_language"><?php esc_html_e( 'Caption Language:', 'wp-mux' ); ?></label>
			<select name="wp_mux_default_caption_language" id="wp_mux_default_caption_language">
				<option value="en" <?php selected( $language, 'en' ); ?>><?php esc_html_e( 'English', 'wp-mux' ); ?></option>
				<option value="es" <?php selected( $language, 'es' ); ?>><?php esc_html_e( 'Spanish', 'wp-mux' ); ?></option>
				<option value="fr" <?php selected( $language, 'fr' ); ?>><?php esc_html_e( 'French', 'wp-mux' ); ?></option>
				<option value="de" <?php selected( $language, 'de' ); ?>><?php esc_html_e( 'German', 'wp-mux' ); ?></option>
				<option value="it" <?php selected( $language, 'it' ); ?>><?php esc_html_e( 'Italian', 'wp-mux' ); ?></option>
				<option value="pt" <?php selected( $language, 'pt' ); ?>><?php esc_html_e( 'Portuguese', 'wp-mux' ); ?></option>
				<option value="ja" <?php selected( $language, 'ja' ); ?>><?php esc_html_e( 'Japanese', 'wp-mux' ); ?></option>
				<option value="zh" <?php selected( $language, 'zh' ); ?>><?php esc_html_e( 'Chinese', 'wp-mux' ); ?></option>
			</select>
		</div>

		<p class="description">
			<?php esc_html_e( 'Requires Mux captions to be enabled on your account. May incur additional costs.', 'wp-mux' ); ?>
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
				<h4 style="margin-top: 0;"><?php esc_html_e( 'Quick Setup', 'wp-mux' ); ?></h4>
				<p><?php esc_html_e( 'Let WP Mux automatically create and configure signing keys for you.', 'wp-mux' ); ?></p>
				<button type="button"
						class="button button-secondary"
						id="wp-mux-auto-configure-keys"
						<?php echo $has_private_key ? 'disabled' : ''; ?>>
					<span class="dashicons dashicons-admin-network" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Auto-Configure Signing Keys', 'wp-mux' ); ?>
				</button>
				<?php if ( $has_private_key ) : ?>
					<p class="description" style="margin-top: 10px;">
						<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
						<?php esc_html_e( 'Signing keys are already configured.', 'wp-mux' ); ?>
						<button type="button"
								class="button button-link"
								id="wp-mux-reconfigure-keys"
								style="color: #b32d2e;">
							<?php esc_html_e( 'Reconfigure', 'wp-mux' ); ?>
						</button>
					</p>
				<?php endif; ?>
				<div id="wp-mux-auto-configure-status" style="margin-top: 10px;"></div>
			</div>

			<div class="wp-mux-manual-keys-section">
				<h4><?php esc_html_e( 'Manual Configuration', 'wp-mux' ); ?></h4>
				<div>
					<label for="wp_mux_signing_key_id"><?php esc_html_e( 'Signing Key ID:', 'wp-mux' ); ?></label>
					<input type="text"
						   name="wp_mux_signing_key_id"
						   id="wp_mux_signing_key_id"
						   value="<?php echo esc_attr( $key_id ); ?>"
						   class="regular-text"
						   readonly />
				</div>

				<div style="margin-top: 10px;">
					<label for="wp_mux_signing_key_private"><?php esc_html_e( 'Private Key (Base64):', 'wp-mux' ); ?></label>
					<textarea name="wp_mux_signing_key_private"
							  id="wp_mux_signing_key_private"
							  rows="4"
							  class="large-text code"
							  readonly
							  placeholder="<?php echo $has_private_key ? esc_attr__( '••••••••', 'wp-mux' ) : ''; ?>"></textarea>
					<?php if ( $has_private_key ) : ?>
						<p class="description">
							<?php esc_html_e( 'Keys are configured and stored securely.', 'wp-mux' ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<p class="description">
			<?php esc_html_e( 'Signing keys are required for signed URLs and protected videos.', 'wp-mux' ); ?>
			<br />
			<a href="https://docs.mux.com/guides/secure-video-playback" target="_blank">
				<?php esc_html_e( 'Learn more about securing video playback', 'wp-mux' ); ?> ↗
			</a>
		</p>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#wp-mux-auto-configure-keys').on('click', function() {
				var $button = $(this);
				var $status = $('#wp-mux-auto-configure-status');

				$button.prop('disabled', true).text('<?php esc_html_e( 'Configuring...', 'wp-mux' ); ?>');
				$status.html('<p><?php esc_html_e( 'Creating signing keys in Mux...', 'wp-mux' ); ?></p>');

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
							$status.html('<div class="notice notice-error inline"><p>' + (response.data.message || '<?php esc_html_e( 'Failed to configure keys', 'wp-mux' ); ?>') + '</p></div>');
							$button.prop('disabled', false).text('<?php esc_html_e( 'Auto-Configure Signing Keys', 'wp-mux' ); ?>');
						}
					},
					error: function() {
						$status.html('<div class="notice notice-error inline"><p><?php esc_html_e( 'Network error. Please try again.', 'wp-mux' ); ?></p></div>');
						$button.prop('disabled', false).text('<?php esc_html_e( 'Auto-Configure Signing Keys', 'wp-mux' ); ?>');
					}
				});
			});

			$('#wp-mux-reconfigure-keys').on('click', function(e) {
				e.preventDefault();
				if (confirm('<?php esc_html_e( 'This will create new signing keys. Old keys will still exist in Mux but can be deleted manually. Continue?', 'wp-mux' ); ?>')) {
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
				<?php esc_html_e( 'Auto (Recommended)', 'wp-mux' ); ?>
			</option>
			<option value="native" <?php selected( $value, 'native' ); ?>>
				<?php esc_html_e( 'Native (Browser built-in)', 'wp-mux' ); ?>
			</option>
			<option value="mse" <?php selected( $value, 'mse' ); ?>>
				<?php esc_html_e( 'MSE (HLS.js library)', 'wp-mux' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Controls which playback engine to use. "Native" may resolve Safari playback issues. "MSE" uses HLS.js on all browsers.', 'wp-mux' ); ?>
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
				<?php esc_html_e( 'No limit', 'wp-mux' ); ?>
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
			<?php esc_html_e( 'Limits the maximum video quality. Useful for bandwidth management or fixing playback issues (e.g., Safari).', 'wp-mux' ); ?>
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
				<?php esc_html_e( 'No limit', 'wp-mux' ); ?>
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
			<?php esc_html_e( 'Ensures videos never drop below this quality level. Leave blank for automatic quality selection.', 'wp-mux' ); ?>
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
				<?php esc_html_e( 'None (Lowest bandwidth)', 'wp-mux' ); ?>
			</option>
			<option value="metadata" <?php selected( $value, 'metadata' ); ?>>
				<?php esc_html_e( 'Metadata only (Recommended)', 'wp-mux' ); ?>
			</option>
			<option value="auto" <?php selected( $value, 'auto' ); ?>>
				<?php esc_html_e( 'Auto (Preload as much as possible)', 'wp-mux' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Controls how much video data is loaded before playback starts. "Metadata" loads duration and dimensions only.', 'wp-mux' ); ?>
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
			<?php esc_html_e( 'Enable debug logging in browser console', 'wp-mux' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Useful for troubleshooting playback issues. Outputs HLS.js and Mux Data debug messages.', 'wp-mux' ); ?>
		</p>
		<?php
	}

	/**
	 * Cookieless mode field callback.
	 *
	 * @since    1.4.0
	 */
	public function disable_cookies_field_callback() {
		$checked = checked( 1, get_option( 'wp_mux_player_disable_cookies', 0 ), false );
		?>
		<label>
			<input type="checkbox" name="wp_mux_player_disable_cookies" value="1" <?php echo $checked; ?> />
			<?php esc_html_e( 'Deliver videos without cookies', 'wp-mux' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, the player will not set cookies or use browser storage (no viewer preferences or resume-position are persisted). Recommended for GDPR / privacy-first sites and cookie-consent-free delivery.', 'wp-mux' ); ?>
		</p>
		<?php
	}

	/**
	 * Disable Mux Data analytics field callback.
	 *
	 * @since    1.4.0
	 */
	public function disable_mux_data_field_callback() {
		$checked = checked( 1, get_option( 'wp_mux_player_disable_mux_data', 0 ), false );
		?>
		<label>
			<input type="checkbox" name="wp_mux_player_disable_mux_data" value="1" <?php echo $checked; ?> />
			<?php esc_html_e( 'Disable Mux Data analytics tracking', 'wp-mux' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Stops the player from sending playback analytics to Mux Data. Enable for maximum privacy — note that you will lose view counts and playback performance metrics for your videos.', 'wp-mux' ); ?>
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
			esc_html_e( 'No MUX videos detected. Choose what happens if you uninstall this plugin:', 'wp-mux' );
		}
		echo '</p>';

		echo '<div style="background: #f0f6fc; border: 1px solid #0c5c9d; border-radius: 4px; padding: 12px 16px; margin-bottom: 20px;">';
		echo '<p style="margin: 0; color: #0c5c9d;"><strong>⚠️ ' . __( 'Important:', 'wp-mux' ) . '</strong> ';
		esc_html_e( 'Videos stored on Mux.com are NEVER automatically deleted and will continue to incur storage costs. You must delete them manually from', 'wp-mux' );
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
					<?php esc_html_e( 'Keep Everything (Recommended)', 'wp-mux' ); ?>
				</h3>
				<p style="margin: 0 0 12px 0;"><?php esc_html_e( 'Leave the checkbox below <strong>unchecked</strong> when you uninstall.', 'wp-mux' ); ?></p>
				<p style="margin: 0 0 8px 0; font-weight: 600;"><?php esc_html_e( 'What happens:', 'wp-mux' ); ?></p>
				<ul style="margin: 0 0 12px 0; padding-left: 24px; list-style-type: disc;">
					<li><?php esc_html_e( 'Temporary data cleared (caches, queues)', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'All videos, settings, and themes preserved', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Can reinstall anytime without data loss', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Video blocks in content: Will stop working but can be reactivated by reinstalling', 'wp-mux' ); ?></li>
				</ul>
				<p style="margin: 0; color: #2e7d32;"><strong><?php esc_html_e( 'Best for:', 'wp-mux' ); ?></strong> <?php esc_html_e( 'Testing, troubleshooting, or taking a break from Mux', 'wp-mux' ); ?></p>
			</div>

			<?php if ( $mux_video_count > 0 ) : ?>
			<!-- Option 2: Migrate Videos Back -->
			<div style="border: 2px solid #0073aa; border-radius: 6px; padding: 20px; margin-bottom: 16px; background: #f0f6fc;">
				<h3 style="margin: 0 0 12px 0; color: #0073aa; font-size: 16px;">
					<span style="background: #0073aa; color: white; border-radius: 50%; width: 28px; height: 28px; display: inline-block; text-align: center; line-height: 28px; margin-right: 8px;">2</span>
					<?php esc_html_e( 'Migrate Videos Back to WordPress', 'wp-mux' ); ?>
				</h3>
				<p style="margin: 0 0 12px 0;"><?php esc_html_e( 'Before uninstalling, download your videos from Mux back to WordPress hosting.', 'wp-mux' ); ?></p>
				<p style="margin: 0 0 8px 0; font-weight: 600;"><?php esc_html_e( 'Steps:', 'wp-mux' ); ?></p>
				<ol style="margin: 0 0 12px 0; padding-left: 24px;">
					<li><?php
						echo sprintf(
							__( 'Use the %sMigrate Back%s page to download videos', 'wp-mux' ),
							'<a href="' . admin_url( 'admin.php?page=wp-mux-migrate-back' ) . '">',
							'</a>'
						);
					?></li>
					<li><?php esc_html_e( 'Migration will update video blocks in your content to use WordPress-hosted videos', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Optionally delete videos from Mux.com to stop billing', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Then uninstall the plugin (Option 1 or 3)', 'wp-mux' ); ?></li>
				</ol>
				<p style="margin: 0; color: #0073aa;"><strong><?php esc_html_e( 'Best for:', 'wp-mux' ); ?></strong> <?php esc_html_e( 'Permanently moving away from Mux hosting', 'wp-mux' ); ?></p>
			</div>
			<?php endif; ?>

			<!-- Option 3: Full Cleanup -->
			<div style="border: 2px solid #dba617; border-radius: 6px; padding: 20px; background: #fffbf0;">
				<h3 style="margin: 0 0 12px 0; color: #8a6d15; font-size: 16px;">
					<span style="background: #dba617; color: white; border-radius: 50%; width: 28px; height: 28px; display: inline-block; text-align: center; line-height: 28px; margin-right: 8px;"><?php echo $mux_video_count > 0 ? '3' : '2'; ?></span>
					<?php esc_html_e( 'Full Cleanup', 'wp-mux' ); ?>
				</h3>
				<p style="margin: 0 0 12px 0;"><?php esc_html_e( '<strong>Check the box below</strong> before uninstalling to remove all plugin data.', 'wp-mux' ); ?></p>
				<p style="margin: 0 0 8px 0; font-weight: 600;"><?php esc_html_e( 'What happens:', 'wp-mux' ); ?></p>
				<ul style="margin: 0 0 12px 0; padding-left: 24px; list-style-type: disc;">
					<li><?php esc_html_e( 'All settings, themes, and credentials deleted', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'All video attachments and metadata removed', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Videos on Mux.com remain untouched', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Video blocks in content: Will show as broken/missing until manually removed or replaced with new videos', 'wp-mux' ); ?></li>
				</ul>
				<p style="margin: 0; color: #8a6d15;"><strong><?php esc_html_e( 'Best for:', 'wp-mux' ); ?></strong> <?php esc_html_e( 'Complete removal, testing, or starting fresh', 'wp-mux' ); ?></p>
			</div>

			<!-- Checkbox Control -->
			<div style="margin-top: 24px; padding: 16px; background: white; border: 1px solid #ddd; border-radius: 4px;">
				<label style="display: flex; align-items: center; cursor: pointer;">
					<input type="checkbox" name="wp_mux_delete_on_uninstall" value="1" <?php echo $checked; ?> id="wp_mux_delete_on_uninstall" style="margin: 0 8px 0 0;" />
					<span style="font-size: 14px; font-weight: 600;"><?php esc_html_e( 'Enable Full Cleanup (Option ' . ( $mux_video_count > 0 ? '3' : '2' ) . ') - Delete all plugin data when uninstalling', 'wp-mux' ); ?></span>
				</label>
				<p style="margin: 12px 0 0 28px; color: #666; font-size: 13px;">
					<?php esc_html_e( 'Leave unchecked for Option 1 (Keep Everything). Check this box only if you want Option ' . ( $mux_video_count > 0 ? '3' : '2' ) . ' (Full Cleanup).', 'wp-mux' ); ?>
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
	 * Webhook settings section description.
	 *
	 * @since 1.2.0
	 */
	public function webhook_settings_section_callback() {
		$webhook_url = rest_url( 'wp-mux/v1/webhook' );
		echo '<p>' . esc_html__( 'Configure Mux to send real-time events to this site, eliminating the need for polling.', 'wp-mux' ) . '</p>';
		echo '<p>';
		echo '<strong>' . esc_html__( 'Webhook URL:', 'wp-mux' ) . '</strong> ';
		echo '<code style="background:#f0f0f1;padding:3px 6px;border-radius:3px;">' . esc_html( $webhook_url ) . '</code>';
		echo ' <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(\'' . esc_js( $webhook_url ) . '\').then(()=>this.textContent=\'Copied!\').catch(()=>{})">' . esc_html__( 'Copy', 'wp-mux' ) . '</button>';
		echo '</p>';
		echo '<p class="description">';
		printf(
			/* translators: %s: Mux dashboard URL */
			esc_html__( 'Add this URL in %s under Webhooks, then paste the signing secret below.', 'wp-mux' ),
			'<a href="https://dashboard.mux.com/settings/webhooks" target="_blank">dashboard.mux.com</a>'
		);
		echo '</p>';
	}

	/**
	 * Webhook signing secret field.
	 *
	 * @since 1.2.0
	 */
	public function webhook_secret_field_callback() {
		$secret = get_option( 'wp_mux_webhook_secret', '' );
		echo '<input type="password" name="wp_mux_webhook_secret" id="wp_mux_webhook_secret" value="' . esc_attr( $secret ) . '" class="regular-text" autocomplete="off" />';
		echo '<button type="button" class="button button-small" style="margin-left:8px;" onclick="var f=document.getElementById(\'wp_mux_webhook_secret\');f.type=f.type===\'password\'?\'text\':\'password\';">' . esc_html__( 'Show', 'wp-mux' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Copy from the Mux dashboard after creating the webhook. Leave blank to skip signature verification (not recommended for production).', 'wp-mux' ) . '</p>';
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
			wp_enqueue_script( 'mux-player', Wp_Mux_Utils::player_script_url(), array(), null, false );
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
			'disable-cookies' => true,
			'disable-tracking' => true,
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
				'disable-cookies' => true,
				'disable-tracking' => true,
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
			$response['mux_asset_id'] = $mux_asset_id;
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

}
