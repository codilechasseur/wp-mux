<?php
/**
 * Provide a bulk migration admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://wpmux.com
 * @since      1.2.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Check if MUX is configured
$is_configured = get_option( 'wp_mux_access_token' ) && get_option( 'wp_mux_secret_key' );

// Get current tab
$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'to-mux';

// Handle form submission for delete option (only for to-mux tab)
if ( $current_tab === 'to-mux' && isset( $_POST['wp_mux_migration_settings_nonce'] ) && wp_verify_nonce( $_POST['wp_mux_migration_settings_nonce'], 'wp_mux_migration_settings' ) ) {
	if ( current_user_can( 'manage_options' ) ) {
		$delete_local = isset( $_POST['delete_local_after_migration'] ) ? '1' : '0';
		update_option( 'wp_mux_delete_local_after_migration', $delete_local );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wp-mux' ) . '</p></div>';
	}
}

$delete_after_migration = get_option( 'wp_mux_delete_local_after_migration', '0' );
?>

<div class="wrap wp-mux-migration-page">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper wp-clearfix" style="margin: 20px 0;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-mux-migration&tab=to-mux' ) ); ?>"
		   class="nav-tab <?php echo $current_tab === 'to-mux' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Migrate to Mux', 'wp-mux' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-mux-migration&tab=from-mux' ) ); ?>"
		   class="nav-tab <?php echo $current_tab === 'from-mux' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Migrate from Mux', 'wp-mux' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-mux-migration&tab=import-url' ) ); ?>"
		   class="nav-tab <?php echo $current_tab === 'import-url' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Import from URL', 'wp-mux' ); ?>
		</a>
	</nav>

	<?php if ( ! $is_configured ) : ?>
		<div class="notice notice-error">
			<p>
				<?php esc_html_e( 'Please configure your MUX API credentials in the settings page before migrating videos.', 'wp-mux' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-mux-settings' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Go to Settings', 'wp-mux' ); ?>
				</a>
			</p>
		</div>
	<?php else : ?>

		<?php if ( $current_tab === 'to-mux' ) : ?>
			<!-- Migrate TO Mux Content -->
			<?php include dirname( __FILE__ ) . '/wp-mux-admin-migration-to-mux.php'; ?>

		<?php elseif ( $current_tab === 'from-mux' ) : ?>
			<!-- Migrate FROM Mux Content -->
			<?php include dirname( __FILE__ ) . '/wp-mux-admin-migration-from-mux.php'; ?>

		<?php elseif ( $current_tab === 'import-url' ) : ?>
			<!-- Import from URL Content -->
			<?php include dirname( __FILE__ ) . '/wp-mux-admin-migration-import-url.php'; ?>

		<?php endif; ?>

	<?php endif; ?>
</div>
