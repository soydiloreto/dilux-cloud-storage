<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DiluxWP\CloudStorage\ConfigManager;
?>

<div class="dilux-cs-settings">
	<?php
	// Show success/error messages produced by the admin_post handler that
	// already verified its own nonce and redirected back here. The reads below
	// are display-only and never trigger side effects.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of message redirected back from a nonce-verified admin_post handler.
	if ( isset( $_GET['success'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
		$dilux_cs_msg = sanitize_text_field( wp_unslash( $_GET['success'] ) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $dilux_cs_msg ) . '</p></div>';
	}
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of message redirected back from a nonce-verified admin_post handler.
	if ( isset( $_GET['error'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
		$dilux_cs_msg = sanitize_text_field( wp_unslash( $_GET['error'] ) );
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $dilux_cs_msg ) . '</p></div>';
	}
	?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'dilux_cs_save_config' ); ?>
		<input type="hidden" name="action" value="dilux_cs_save_config">
		<input type="hidden" name="redirect_tab" value="settings">

		<!-- ========================================================================
			Upload Settings - ALWAYS EDITABLE
			======================================================================== -->
		<div class="settings-section">
			<h3><?php esc_html_e( 'Upload Settings', 'dilux-cloud-storage' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Configure how files are uploaded to cloud storage.', 'dilux-cloud-storage' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="max_file_size"><?php esc_html_e( 'Maximum File Size (MB)', 'dilux-cloud-storage' ); ?></label>
					</th>
					<td>
						<input type="number"
								id="max_file_size"
								name="max_file_size"
								value="<?php echo esc_attr( round( ( $config['max_file_size'] ?? 20971520 ) / 1048576 ) ); ?>"
								min="1"
								max="500"
								class="small-text">
						<p class="description">
							<?php esc_html_e( 'Maximum file size allowed for cloud uploads (1-500 MB).', 'dilux-cloud-storage' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="timeout"><?php esc_html_e( 'Upload Timeout (seconds)', 'dilux-cloud-storage' ); ?></label>
					</th>
					<td>
						<input type="number"
								id="timeout"
								name="timeout"
								value="<?php echo esc_attr( $config['timeout'] ); ?>"
								min="30"
								max="600"
								class="small-text">
						<p class="description">
							<?php esc_html_e( 'Maximum time to wait for cloud uploads (30-600 seconds).', 'dilux-cloud-storage' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- ========================================================================
			Offloading Settings - controls how cloud-offloaded media is served
			======================================================================== -->
		<div class="settings-section">
			<h3><?php esc_html_e( 'Offloading Settings', 'dilux-cloud-storage' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Settings that control how cloud-offloaded media is served to the front-end.', 'dilux-cloud-storage' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Cloud URL Scheme', 'dilux-cloud-storage' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
									name="force_https_on_cloud"
									value="1"
									<?php checked( $config['force_https_on_cloud'] ?? true ); ?>>
							<?php esc_html_e( 'Force HTTPS for cloud storage URLs', 'dilux-cloud-storage' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Re-applies https:// to URLs WordPress emits for the cloud storage. Needed when the site is served over plain http (typical in local dev): WP downgrades them to http and Azure rejects them with HTTP 400. Leave enabled unless you know what you are doing.', 'dilux-cloud-storage' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<?php if ( is_multisite() ) : ?>
		<!-- ========================================================================
			Multisite Settings - ALWAYS EDITABLE
			======================================================================== -->
		<div class="settings-section">
			<h3><?php esc_html_e( 'Multisite Settings', 'dilux-cloud-storage' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Configure how this plugin behaves in a multisite environment.', 'dilux-cloud-storage' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Network Configuration', 'dilux-cloud-storage' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
									name="use_network_config"
									value="1"
									<?php checked( $config['use_network_config'] ); ?>>
							<?php esc_html_e( 'Use network-wide configuration for this site', 'dilux-cloud-storage' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, this site will use the configuration set in Network Admin.', 'dilux-cloud-storage' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php endif; ?>

		<!-- ========================================================================
			Debug & Logging - ALWAYS EDITABLE
			======================================================================== -->
		<div class="settings-section">
			<h3><?php esc_html_e( 'Debug & Logging', 'dilux-cloud-storage' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Enable debug logging to troubleshoot issues. Only enable when needed as it may impact performance.', 'dilux-cloud-storage' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Debug Logging', 'dilux-cloud-storage' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
									name="enable_debug_logging"
									value="1"
									<?php checked( $config['debug_enabled'] ?? false ); ?>>
							<?php esc_html_e( 'Enable detailed debug logging', 'dilux-cloud-storage' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enable this only when troubleshooting issues. May impact performance.', 'dilux-cloud-storage' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- ========================================================================
			Submit Section
			======================================================================== -->
		<div class="submit-section">
			<button type="submit"
					name="submit"
					id="submit"
					class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'dilux-cloud-storage' ); ?>
			</button>
		</div>
	</form>
</div>


<style>
.dilux-cs-settings {
	max-width: 800px;
}

.settings-section {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 8px;
	padding: 20px;
	margin-bottom: 30px;
}

.settings-section h3 {
	margin-top: 0;
	padding-bottom: 10px;
	border-bottom: 1px solid #eee;
	color: #333;
}

.submit-section {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 8px;
	padding: 20px;
	text-align: right;
}

@media (max-width: 768px) {
	.dilux-cs-settings {
		max-width: 100%;
	}

	.submit-section {
		text-align: center;
	}

	.submit-section .button {
		margin: 5px;
		display: block;
		width: 100%;
	}
}
</style>
