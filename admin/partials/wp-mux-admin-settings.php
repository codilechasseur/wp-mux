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

    <div class="migration-layout">
        <div class="migration-main">
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wp_mux_settings_group' );
                do_settings_sections( 'wp-mux-settings' );
                submit_button( __( 'Save Settings', 'wp-mux' ) );
                ?>
            </form>
        </div>

        <div class="migration-sidebar">
            <div class="migration-info-box">
                <h3><?php _e( 'Getting Started', 'wp-mux' ); ?></h3>
                <p class="description">
                    <?php _e( 'Follow these steps to configure WP Mux:', 'wp-mux' ); ?>
                </p>

                <ol style="list-style: decimal; margin: 10px 0 15px 0; padding-left: 24px;">
                    <li style="padding: 4px 0; font-size: 13px; line-height: 1.5;"><?php _e( 'Sign up for a MUX account at', 'wp-mux' ); ?> <a href="https://mux.com" target="_blank">mux.com</a></li>
                    <li style="padding: 4px 0; font-size: 13px; line-height: 1.5;"><?php _e( 'Navigate to your MUX dashboard and create an API token', 'wp-mux' ); ?></li>
                    <li style="padding: 4px 0; font-size: 13px; line-height: 1.5;"><?php _e( 'Copy your Access Token and Secret Key and paste them in the fields', 'wp-mux' ); ?></li>
                    <li style="padding: 4px 0; font-size: 13px; line-height: 1.5;"><?php _e( 'Save the settings and you\'re ready to start using MUX!', 'wp-mux' ); ?></li>
                </ol>

                <h4><?php _e( 'Security Note', 'wp-mux' ); ?></h4>
                <p class="description">
                    <?php _e( 'Your Secret Key is stored securely and will not be visible once saved. Never share your API credentials publicly.', 'wp-mux' ); ?>
                </p>

                <h4><?php _e( 'Need Help?', 'wp-mux' ); ?></h4>
                <ul class="migration-tips">
                    <li><a href="https://docs.mux.com" target="_blank"><?php _e( 'MUX Documentation', 'wp-mux' ); ?> ↗</a></li>
                    <li><a href="https://dashboard.mux.com/settings/access-tokens" target="_blank"><?php _e( 'Create API Token', 'wp-mux' ); ?> ↗</a></li>
                    <li><a href="https://mux.com/pricing" target="_blank"><?php _e( 'View Pricing', 'wp-mux' ); ?> ↗</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
