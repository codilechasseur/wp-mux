	<div class="wp-mux-layout">
	<div class="wp-mux-main">
		<div class="migration-controls">
				<button id="start-migration" class="button button-secondary button-large" style="display: none;" disabled>
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Start Migration', 'wp-mux' ); ?>
				</button>

				<button id="pause-migration" class="button button-secondary" style="display: none;">
					<span class="dashicons dashicons-controls-pause"></span>
					<?php esc_html_e( 'Pause', 'wp-mux' ); ?>
				</button>

				<button id="resume-migration" class="button button-secondary" style="display: none;">
					<span class="dashicons dashicons-controls-play"></span>
					<?php esc_html_e( 'Resume', 'wp-mux' ); ?>
				</button>
			</div>

			<div id="scan-progress" class="migration-progress">
				<h3><?php esc_html_e( 'Scanning Media Library...', 'wp-mux' ); ?></h3>
				<div class="progress-bar">
					<div class="progress-fill" style="width: 0%;"></div>
				</div>
				<p class="progress-text"><?php esc_html_e( 'Please wait...', 'wp-mux' ); ?></p>
			</div>

			<div id="scan-results" class="migration-results" style="display: none;">
				<h2><?php esc_html_e( 'Scan Results', 'wp-mux' ); ?></h2>
				<div class="scan-summary">
					<div class="summary-box">
						<div class="summary-number" id="total-videos">0</div>
						<div class="summary-label"><?php esc_html_e( 'Local Videos Found', 'wp-mux' ); ?></div>
					</div>
					<div class="summary-box">
						<div class="summary-number" id="total-size">0 MB</div>
						<div class="summary-label"><?php esc_html_e( 'Total Size', 'wp-mux' ); ?></div>
					</div>
					<div class="summary-box">
						<div class="summary-number" id="estimated-time">~0 min</div>
						<div class="summary-label"><?php esc_html_e( 'Est. Time', 'wp-mux' ); ?></div>
					</div>
				</div>

				<div class="migration-option-inline">
					<label>
						<input type="checkbox"
							   id="delete_local_after_migration"
							   name="delete_local_after_migration"
							   value="1"
							   <?php checked( $delete_after_migration, '1' ); ?> />
						<strong><?php esc_html_e( 'Delete local video files after successful migration', 'wp-mux' ); ?></strong>
					</label>
				</div>

				<div class="videos-list-container">
					<h3><?php esc_html_e( 'Videos to Migrate', 'wp-mux' ); ?></h3>
					<div id="videos-list" class="videos-list"></div>
				</div>
			</div>

			<div id="migration-progress" class="migration-progress" style="display: none;">
				<h2><?php esc_html_e( 'Migration in Progress', 'wp-mux' ); ?></h2>

				<div class="overall-progress">
					<div class="progress-stats">
						<span id="progress-current">0</span> / <span id="progress-total">0</span>
						<?php esc_html_e( 'videos migrated', 'wp-mux' ); ?>
					</div>
					<div class="progress-bar">
						<div class="progress-fill" id="overall-progress-fill" style="width: 0%;"></div>
					</div>
					<div class="progress-percentage">
						<span id="progress-percentage">0</span>%
					</div>
				</div>

				<div class="current-video">
					<h3><?php esc_html_e( 'Currently Processing:', 'wp-mux' ); ?></h3>
					<div id="current-video-info" class="current-video-info">
						<?php esc_html_e( 'Waiting to start...', 'wp-mux' ); ?>
					</div>
					<div class="progress-bar">
						<div class="progress-fill" id="current-video-progress" style="width: 0%;"></div>
					</div>
				</div>

				<div class="migration-log">
					<h3><?php esc_html_e( 'Migration Log', 'wp-mux' ); ?></h3>
					<div id="migration-log" class="log-container"></div>
				</div>
			</div>

			<div id="migration-complete" class="migration-complete" style="display: none;">
				<div class="migration-notice success">
					<h2><?php esc_html_e( 'Migration Complete!', 'wp-mux' ); ?></h2>
					<div class="completion-summary">
						<p>
							<strong id="completed-count">0</strong>
							<?php esc_html_e( 'videos successfully migrated to MUX.', 'wp-mux' ); ?>
						</p>
						<p id="failed-count-container" style="display: none;">
							<strong id="failed-count">0</strong>
							<?php esc_html_e( 'videos failed to migrate.', 'wp-mux' ); ?>
						</p>
					</div>
					<div class="completion-actions">
						<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'View Media Library', 'wp-mux' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-mux-videos' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'View MUX Videos', 'wp-mux' ); ?>
						</a>
						<button id="start-new-migration" class="button button-secondary">
							<?php esc_html_e( 'Scan Again', 'wp-mux' ); ?>
						</button>
					</div>
				</div>

				<div id="failed-videos-list" class="failed-videos" style="display: none;">
					<h3><?php esc_html_e( 'Failed Migrations', 'wp-mux' ); ?></h3>
					<div id="failed-videos" class="failed-list"></div>
				</div>
			</div>

		</div>

		<div class="wp-mux-sidebar-panel">
			<div class="wp-mux-info-box">
				<h3><?php esc_html_e( 'About Migration', 'wp-mux' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'This tool helps you migrate locally hosted videos to MUX.', 'wp-mux' ); ?>
				</p>

				<h4><?php esc_html_e( 'What happens:', 'wp-mux' ); ?></h4>
				<ul class="migration-features">
					<li><?php esc_html_e( 'Scans your media library for local video files', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Uploads each video to MUX for processing', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Preserves original upload dates', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Updates references to use MUX playback', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Optionally deletes local files to save space', 'wp-mux' ); ?></li>
				</ul>

				<h4><?php esc_html_e( 'Tips:', 'wp-mux' ); ?></h4>
				<ul class="migration-tips">
					<li><?php esc_html_e( 'Videos are processed one at a time', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'You can pause and resume anytime', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Large videos may take several minutes', 'wp-mux' ); ?></li>
					<li><?php esc_html_e( 'Keep this page open during migration', 'wp-mux' ); ?></li>
				</ul>
			</div>
		</div>
	</div>

