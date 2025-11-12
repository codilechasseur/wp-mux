	<div class="wp-mux-layout">
		<div class="wp-mux-main">
			<div class="wp-mux-migrate-back-wizard">

<!-- System Check Status (shown while checking) -->
<div id="system-check-status" class="migrate-step" style="margin-top: 0;">
	<h3 style="margin-top: 0;"><?php esc_html_e( 'Checking System Requirements...', 'wp-mux' ); ?></h3>
	<p><?php esc_html_e( 'Please wait while we verify your system is ready for migration.', 'wp-mux' ); ?></p>
</div>

<!-- Migration Not Available Notice (shown if MP4 not supported) -->
<div id="migration-not-available" class="migrate-step" style="display:none; margin-top: 0;">
	<h3 style="margin-top: 0;"><?php esc_html_e( 'Migration Not Available', 'wp-mux' ); ?></h3>
	<p id="migration-error-message" style="font-weight: bold; color: #d63638;"></p>
	<p><?php esc_html_e( 'The migrate-back feature requires a paid Mux plan with MP4 support. On the free plan, videos must remain hosted on Mux.', 'wp-mux' ); ?></p>
	<h4><?php esc_html_e( 'Your Options:', 'wp-mux' ); ?></h4>
	<ul style="list-style: disc; margin-left: 20px;">
		<li><?php esc_html_e( 'Keep using Mux - Videos remain free on the free plan', 'wp-mux' ); ?></li>
		<li><?php esc_html_e( 'Upgrade Mux plan - Get MP4 support to enable migration', 'wp-mux' ); ?></li>
		<li><?php esc_html_e( 'Manual migration - Re-upload original video files to WordPress', 'wp-mux' ); ?></li>
	</ul>
</div>

<!-- Step 1: Video Scan -->
<div id="step-video-scan" class="migrate-step" style="display:none;">
<div class="step-header">
<h2><?php esc_html_e( 'Step 1: Scan Mux Videos', 'wp-mux' ); ?></h2>
</div>
<div class="step-content">
<div id="scan-progress" style="display:none;">
<p><?php esc_html_e( 'Scanning videos...', 'wp-mux' ); ?></p>
</div>
<div id="scan-results" style="display:none;">
<div class="video-summary">
<div class="summary-box">
<strong id="migratable-count">0</strong>
<span><?php esc_html_e( 'Ready to Migrate', 'wp-mux' ); ?></span>
</div>
<div class="summary-box">
<strong id="not-migratable-count">0</strong>
<span><?php esc_html_e( 'Need MP4 Enabled', 'wp-mux' ); ?></span>
</div>
<div class="summary-box">
<strong id="total-size">0 MB</strong>
<span><?php esc_html_e( 'Total Size', 'wp-mux' ); ?></span>
</div>
</div>

<div id="not-migratable-section" style="display:none;">
<h3><?php esc_html_e( 'Videos Without MP4 Support', 'wp-mux' ); ?></h3>
<div class="notice notice-warning inline">
<p><strong><?php esc_html_e( 'Cost Warning:', 'wp-mux' ); ?></strong> <?php esc_html_e( 'Enabling MP4 support will increase your Mux storage costs by approximately $0.05/GB/month per video.', 'wp-mux' ); ?></p>
</div>
<div id="not-migratable-list"></div>
<button id="btn-enable-mp4-all" class="button button-secondary">
<?php esc_html_e( 'Enable MP4 for All Videos', 'wp-mux' ); ?>
</button>
</div>

<div id="migratable-section" style="display:none;">
<h3><?php esc_html_e( 'Videos Ready to Migrate', 'wp-mux' ); ?></h3>
<div id="migratable-list"></div>
</div>
</div>

<div class="step-actions" style="margin-top: 20px;">
<button id="btn-start-scan" class="button button-primary button-large">
<?php esc_html_e( 'Start Video Scan', 'wp-mux' ); ?>
</button>
</div>

<div class="step-actions" style="display:none;" id="scan-actions">
<button id="btn-analyze-content" class="button button-primary button-large">
<?php esc_html_e( 'Continue to Content Analysis', 'wp-mux' ); ?>
</button>
</div>
</div>
</div>

<!-- Step 3: Content Analysis -->
<div id="step-content-analysis" class="migrate-step" style="display:none;">
<div class="step-header">
<h2><?php esc_html_e( 'Step 3: Content Analysis', 'wp-mux' ); ?></h2>
</div>
<div class="step-content">
<div id="content-scan-progress">
<p><?php esc_html_e( 'Analyzing post content...', 'wp-mux' ); ?></p>
</div>
<div id="content-results" style="display:none;">
<div class="content-summary">
<p><?php esc_html_e( 'Found', 'wp-mux' ); ?> <strong id="posts-count">0</strong> <?php esc_html_e( 'posts/pages using these videos', 'wp-mux' ); ?></p>
</div>
<div id="content-preview"></div>
</div>
<div class="step-actions" style="display:none;" id="content-actions">
<button id="btn-start-migration" class="button button-primary button-large">
<?php esc_html_e( 'Start Migration', 'wp-mux' ); ?>
</button>
</div>
</div>
</div>

<!-- Step 4: Migration Progress -->
<div id="step-migration" class="migrate-step" style="display:none;">
<div class="step-header">
<h2><?php esc_html_e( 'Step 4: Migrating Videos', 'wp-mux' ); ?></h2>
</div>
<div class="step-content">
<div class="progress-container">
<div class="progress-stats">
<span id="migration-current">0</span> / <span id="migration-total">0</span> <?php esc_html_e( 'videos', 'wp-mux' ); ?>
</div>
<div class="progress-bar">
<div class="progress-fill" id="migration-progress-fill"></div>
</div>
<div id="migration-status"></div>
</div>
<div id="migration-log" class="migration-log"></div>
</div>
</div>

<!-- Step 5: Content Update -->
<div id="step-content-update" class="migrate-step" style="display:none;">
<div class="step-header">
<h2><?php esc_html_e( 'Step 5: Updating Content', 'wp-mux' ); ?></h2>
</div>
<div class="step-content">
<div id="content-update-progress">
<p><?php esc_html_e( 'Updating post content...', 'wp-mux' ); ?></p>
</div>
<div id="content-update-results" style="display:none;"></div>
<div class="step-actions" style="display:none;" id="update-actions">
<button id="btn-cleanup" class="button button-primary button-large">
<?php esc_html_e( 'Continue to Cleanup', 'wp-mux' ); ?>
</button>
</div>
</div>
</div>

<!-- Step 6: Cleanup -->
<div id="step-cleanup" class="migrate-step" style="display:none;">
<div class="step-header">
<h2><?php esc_html_e( 'Step 6: Cleanup (Optional)', 'wp-mux' ); ?></h2>
</div>
<div class="step-content">
<p><?php esc_html_e( 'Your videos have been successfully migrated to local hosting. You can now optionally delete them from Mux to stop billing charges.', 'wp-mux' ); ?></p>
<div class="cleanup-options">
<label>
<input type="checkbox" id="confirm-mux-delete" />
<?php esc_html_e( 'Yes, delete all videos from Mux (this cannot be undone)', 'wp-mux' ); ?>
</label>
</div>
<div class="step-actions">
<button id="btn-delete-from-mux" class="button button-secondary" disabled>
<?php esc_html_e( 'Delete from Mux', 'wp-mux' ); ?>
</button>
<button id="btn-finish" class="button button-primary button-large">
<?php esc_html_e( 'Finish (Keep on Mux)', 'wp-mux' ); ?>
</button>
</div>
<div id="cleanup-progress" style="display:none;"></div>
</div>
</div>

<!-- Step 7: Complete -->
<div id="step-complete" class="migrate-step" style="display:none;">
<div class="step-header">
<h2><?php esc_html_e( '✓ Migration Complete!', 'wp-mux' ); ?></h2>
</div>
<div class="step-content">
<div class="notice notice-success inline">
<p><?php esc_html_e( 'Your videos have been successfully migrated back to local WordPress hosting.', 'wp-mux' ); ?></p>
</div>
<div id="completion-summary"></div>
<div class="step-actions">
<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button button-primary">
<?php esc_html_e( 'View Media Library', 'wp-mux' ); ?>
</a>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-mux-videos' ) ); ?>" class="button">
<?php esc_html_e( 'Back to MUX Videos', 'wp-mux' ); ?>
</a>
</div>
</div>
</div>

		</div><!-- .wp-mux-migrate-back-wizard -->
	</div><!-- .wp-mux-main -->

	<div class="wp-mux-sidebar-panel">
		<div class="wp-mux-info-box">
			<h3><?php _e( 'System Requirements', 'wp-mux' ); ?></h3>
			<div id="sidebar-requirements">
				<p class="description"><?php _e( 'Checking system...', 'wp-mux' ); ?></p>
			</div>

			<h4><?php _e( 'Migration Process', 'wp-mux' ); ?></h4>
			<ol style="list-style: decimal; margin: 10px 0 15px 0; padding-left: 24px;">
				<li style="padding: 4px 0; font-size: 13px;"><?php _e( 'Scan Mux videos', 'wp-mux' ); ?></li>
				<li style="padding: 4px 0; font-size: 13px;"><?php _e( 'Analyze content usage', 'wp-mux' ); ?></li>
				<li style="padding: 4px 0; font-size: 13px;"><?php _e( 'Download videos', 'wp-mux' ); ?></li>
				<li style="padding: 4px 0; font-size: 13px;"><?php _e( 'Update content references', 'wp-mux' ); ?></li>
				<li style="padding: 4px 0; font-size: 13px;"><?php _e( 'Optional: Delete from Mux', 'wp-mux' ); ?></li>
			</ol>

			<h4><?php _e( 'Important Notes', 'wp-mux' ); ?></h4>
			<ul class="migration-tips">
				<li><?php _e( 'Attachment IDs are preserved', 'wp-mux' ); ?></li>
				<li><?php _e( 'Content is backed up automatically', 'wp-mux' ); ?></li>
				<li><?php _e( 'Process can take time for large videos', 'wp-mux' ); ?></li>
			</ul>

			<h4><?php _e( 'Need Help?', 'wp-mux' ); ?></h4>
			<ul class="migration-tips">
				<li><a href="https://www.mux.com/pricing" target="_blank"><?php _e( 'Mux Pricing & Plans', 'wp-mux' ); ?> ↗</a></li>
				<li><a href="https://docs.mux.com/guides/enable-static-mp4-renditions" target="_blank"><?php _e( 'Enable MP4 Support', 'wp-mux' ); ?> ↗</a></li>
			</ul>
		</div>
	</div>
</div><!-- .wp-mux-layout -->


<style>
.wp-mux-migrate-back-wizard {
max-width: 100%;
}
.migrate-step {
background: #fff;
border: 1px solid #c3c4c7;
padding: 20px;
margin: 20px 0;
box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.migrate-step:first-child {
margin-top: 0;
}
</style>
