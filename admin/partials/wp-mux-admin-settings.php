<?php

/**
 * Provide a admin area view for the plugin settings
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://codilechasseur@gmail.com
 * @since      1.0.0
 *
 * @package    Wp_Mux
 * @subpackage Wp_Mux/admin/partials
 */
?>

<div class="wrap wp-mux-settings-page">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'wp_mux_settings_group' );
        do_settings_sections( 'wp-mux-settings' );
        submit_button( __( 'Save Settings', 'wp-mux' ) );
        ?>
    </form>

    <div class="wp-mux-info">
        <h2><?php _e( 'Getting Started', 'wp-mux' ); ?></h2>
        <p><?php _e( 'To get started with WP Mux, you\'ll need to:', 'wp-mux' ); ?></p>
        <ol>
            <li><?php _e( 'Sign up for a MUX account at', 'wp-mux' ); ?> <a href="https://mux.com" target="_blank">mux.com</a></li>
            <li><?php _e( 'Navigate to your MUX dashboard and create an API token', 'wp-mux' ); ?></li>
            <li><?php _e( 'Copy your Access Token and Secret Key and paste them in the fields above', 'wp-mux' ); ?></li>
            <li><?php _e( 'Save the settings and you\'re ready to start using MUX with WordPress!', 'wp-mux' ); ?></li>
        </ol>

        <h3><?php _e( 'Security Note', 'wp-mux' ); ?></h3>
        <p><?php _e( 'Your Secret Key is stored securely and will not be visible once saved. Make sure to keep your API credentials safe and never share them publicly.', 'wp-mux' ); ?></p>
    </div>
</div>
