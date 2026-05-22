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

// Pro gate
if ( ! wp_mux_is_pro() ) {
	$upgrade_url = ( function_exists( 'wp_mux_fs' ) && wp_mux_fs() !== null )
		? wp_mux_fs()->get_upgrade_url()
		: 'https://wpmux.com/#pricing';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Migrate Videos', 'wp-mux' ); ?></h1>
		<div class="notice notice-info" style="padding:20px; margin-top:20px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'WP Mux Pro Feature', 'wp-mux' ); ?></h2>
			<p><?php esc_html_e( 'Video migration is available on the Pro plan. It lets you:', 'wp-mux' ); ?></p>
			<ul style="list-style:disc; margin-left:24px;">
				<li><?php esc_html_e( 'Bulk migrate all your locally hosted videos to Mux in one click', 'wp-mux' ); ?></li>
				<li><?php esc_html_e( 'Import videos from an external URL directly into Mux', 'wp-mux' ); ?></li>
				<li><?php esc_html_e( 'Migrate Mux videos back to local WordPress hosting', 'wp-mux' ); ?></li>
			</ul>
			<p style="margin-top:16px;">
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-large">
					<?php esc_html_e( 'Upgrade to Pro', 'wp-mux' ); ?>
				</a>
			</p>
		</div>
	</div>
	<?php
	return;
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
