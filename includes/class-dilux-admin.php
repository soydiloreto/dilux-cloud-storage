<?php
/**
 * Admin interface for Cloud Storage plugin
 *
 * Direct $wpdb queries against the plugin's own table (`$wpdb->prefix .
 * 'dilux_cs_files'`) are used in a few read-only spots to render real-time
 * sync progress; cache layers don't apply because the value would be stale.
 * The table name is derived from $wpdb->prefix and never from user input.
 * These rules are intentionally suppressed file-wide:
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 *
 * @package DiluxWP\CloudStorage
 */

namespace DiluxWP\CloudStorage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin interface for Cloud Storage plugin.
 */
class Admin {

	/**
	 * Initialize admin hooks
	 */
	public static function init() {
		Logger::debug( '[Dilux CS] Admin::init() called - registering hooks' );
		\add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		\add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		\add_action( 'admin_post_dilux_cs_save_config', array( __CLASS__, 'save_config' ) );
		\add_action( 'admin_post_dilux_cs_save_offloading', array( __CLASS__, 'save_offloading_config' ) );
		\add_action( 'admin_post_dilux_cs_remove_provider', array( __CLASS__, 'remove_provider_config' ) );

		// Register AJAX handlers
		\add_action( 'wp_ajax_dilux_cs_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		\add_action( 'wp_ajax_dilux_cs_save_updated_credentials', array( __CLASS__, 'ajax_save_updated_credentials' ) );
		\add_action( 'wp_ajax_dilux_cs_migration_action', array( __CLASS__, 'ajax_migration_action' ) );
		\add_action( 'wp_ajax_dilux_cs_test_check', array( __CLASS__, 'ajax_test_check' ) );
		\add_action( 'wp_ajax_dilux_cs_scan_files', array( __CLASS__, 'ajax_scan_files' ) );
		// DISABLED: ajax_dilux_cs_start_sync now handled by Plugin::ajax_cs_start_sync in class-dilux-plugin-enhanced.php (legacy handler removed).
		\add_action( 'wp_ajax_dilux_cs_cancel_sync', array( __CLASS__, 'ajax_cancel_sync' ) );
		\add_action( 'wp_ajax_dilux_cs_mark_sync_complete', array( __CLASS__, 'ajax_mark_sync_complete' ) );
		\add_action( 'wp_ajax_dilux_cs_delete_local_files', array( __CLASS__, 'ajax_delete_local_files' ) );
		\add_action( 'wp_ajax_dilux_cs_retry_failed', array( __CLASS__, 'ajax_retry_failed' ) );
		\add_action( 'wp_ajax_dilux_cs_clear_failed', array( __CLASS__, 'ajax_clear_failed' ) );
		\add_action( 'wp_ajax_dilux_cs_resync_all', array( __CLASS__, 'ajax_resync_all' ) );
		\add_action( 'wp_ajax_dilux_cs_ajax_remove_provider', array( __CLASS__, 'ajax_remove_provider' ) );
		\add_action( 'wp_ajax_dilux_cs_import_config', array( __CLASS__, 'ajax_import_config' ) );
		\add_action( 'wp_ajax_dilux_cs_refresh_stats', array( __CLASS__, 'ajax_refresh_stats' ) );
		Logger::debug( '[Dilux CS] Admin hooks registered successfully' );
	}

	/**
	 * Register top-level admin menu "Dilux Cloud Storage".
	 *
	 * Standalone menu — no shared "Dilux One" parent. The menu label is intentionally
	 * left untranslated so the brand stays consistent across locales.
	 *
	 * @return void
	 */
	public static function add_admin_menu() {
		\add_menu_page(
			'Dilux Cloud Storage',              // Page title
			'Dilux Cloud Storage',              // Menu label (no translation — brand)
			'manage_options',                   // Capability
			'dilux-cloud-storage',              // Menu slug
			array( __CLASS__, 'render_admin_page' ),   // Callback
			'dashicons-cloud',                  // Icon
			81                                  // Position (below Settings block)
		);
	}

	/**
	 * Render the admin page (called dynamically by Dilux One Core)
	 */
	public static function render_admin_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing parameter, no state change.
		$current_tab = isset( $_GET['tab'] ) ? \sanitize_text_field( wp_unslash( $_GET['tab'] ?? '' ) ) : 'overview';

		// Check configuration states
		$is_configured         = ConfigManager::is_configured();
		$current_state         = ConfigManager::get_state();
		$is_synced             = ( $current_state === 'synced' || $current_state === 'offloading_active' );
		$is_offloading_enabled = ( $current_state === 'offloading_active' );

		?>
		<div class="wrap dilux-cloud-storage-admin">
			<h1>
				<span class="dashicons dashicons-cloud"></span>
				Cloud Storage
			</h1>

			<!-- Tabs Navigation -->
			<nav class="nav-tab-wrapper">
				<a href="?page=dilux-cloud-storage&tab=overview" class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-dashboard"></span>
					<?php \esc_html_e( 'Overview', 'dilux-cloud-storage' ); ?>
				</a>
				<a href="?page=dilux-cloud-storage&tab=cloud-provider" class="nav-tab <?php echo $current_tab === 'cloud-provider' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-cloud-upload"></span>
					<?php \esc_html_e( 'Cloud Provider', 'dilux-cloud-storage' ); ?>
				</a>
				<a href="?page=dilux-cloud-storage&tab=sync-offloading" class="nav-tab <?php echo ( $current_tab === 'sync-offloading' || $current_tab === 'sync' ) ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-update"></span>
					<?php \esc_html_e( 'Sync & Offloading', 'dilux-cloud-storage' ); ?>
				</a>
				<a href="?page=dilux-cloud-storage&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php \esc_html_e( 'Settings', 'dilux-cloud-storage' ); ?>
				</a>
				<a href="?page=dilux-cloud-storage&tab=status" class="nav-tab <?php echo ( $current_tab === 'status' || $current_tab === 'status-tools' ) ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-info"></span>
					<?php \esc_html_e( 'Status', 'dilux-cloud-storage' ); ?>
				</a>
				<a href="?page=dilux-cloud-storage&tab=tools" class="nav-tab <?php echo $current_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-admin-tools"></span>
					<?php \esc_html_e( 'Tools', 'dilux-cloud-storage' ); ?>
				</a>
			</nav>

			<!-- Tab Content -->
			<div class="tab-content" style="margin-top: 20px;">
				<?php
				// Connection health check (5-min TTL)
				$health = ConfigManager::check_connection_health();
				if ( $health['status'] === 'unhealthy' ) {
					self::render_connection_health_banner( $health );
				}
				?>
				<?php self::render_tab_content( $current_tab ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Register settings
	 */
	public static function register_settings() {
		\register_setting(
			'dilux_cloud_storage',
			'dilux_cloud_storage_config',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings_option' ),
				'default'           => array(),
			)
		);

		if ( \is_multisite() ) {
			\register_setting(
				'dilux_cloud_storage_network',
				'dilux_cloud_storage_network_config',
				array(
					'type'              => 'array',
					'sanitize_callback' => array( __CLASS__, 'sanitize_settings_option' ),
					'default'           => array(),
				)
			);
		}
	}

	/**
	 * Sanitize the dilux_cloud_storage{,_network}_config option.
	 *
	 * Production save paths go through ConfigManager (DTO validation +
	 * credential encryption). This callback satisfies the Settings API
	 * contract and hardens anything submitted directly through it.
	 *
	 * @param mixed $value
	 * @return array
	 */
	public static function sanitize_settings_option( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$clean = array();
		foreach ( $value as $k => $v ) {
			$key = \sanitize_key( (string) $k );
			if ( is_array( $v ) ) {
				$clean[ $key ] = self::sanitize_settings_option( $v );
			} elseif ( is_bool( $v ) || is_int( $v ) || is_float( $v ) ) {
				$clean[ $key ] = $v;
			} else {
				$clean[ $key ] = \sanitize_text_field( (string) $v );
			}
		}
		return $clean;
	}

	/**
	 * Single source of truth for the plugin version: the `Version:` line of
	 * the main plugin file. Cached per request.
	 */
	public static function get_plugin_version(): string {
		static $version = null;
		if ( $version !== null ) {
			return $version;
		}
		if ( ! \function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data    = \get_plugin_data( DILUX_CS_PLUGIN_FILE, false, false );
		$version = (string) ( $data['Version'] ?? ( defined( 'DILUX_CS_VERSION' ) ? DILUX_CS_VERSION : '' ) );
		return $version;
	}

	/**
	 * Enqueue admin assets (CSS/JS)
	 *
	 * @param mixed $hook_suffix
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		// Only load on our plugin pages
		if ( strpos( $hook_suffix, 'dilux-cloud-storage' ) === false ) {
			return;
		}

		// Enqueue CSS
		wp_enqueue_style(
			'dilux-cs-admin',
			DILUX_CS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			DILUX_CS_VERSION
		);

		// Enqueue JS
		wp_enqueue_script(
			'dilux-cs-admin',
			DILUX_CS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			DILUX_CS_VERSION,
			true
		);

		// Localize script with AJAX data
		wp_localize_script(
			'dilux-cs-admin',
			'diluxCloudStorageAdmin',
			array(
				'nonce'       => wp_create_nonce( 'dilux_cs_admin' ),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'autoRefresh' => true,
				'strings'     => array(
					'testing_connection'   => __( 'Testing connection...', 'dilux-cloud-storage' ),
					'connection_success'   => __( 'Connection successful!', 'dilux-cloud-storage' ),
					'connection_failed'    => __( 'Connection failed:', 'dilux-cloud-storage' ),
					'scanning_media'       => __( 'Scanning media library...', 'dilux-cloud-storage' ),
					'migrating_files'      => __( 'Migrating files to cloud...', 'dilux-cloud-storage' ),
					'deleting_local'       => __( 'Deleting local files...', 'dilux-cloud-storage' ),
					'downloading_files'    => __( 'Downloading files from cloud...', 'dilux-cloud-storage' ),
					'confirm_migrate'      => __( 'Are you sure you want to migrate all files to cloud storage? This cannot be undone without using the rollback feature.', 'dilux-cloud-storage' ),
					'confirm_delete_local' => __( 'Are you sure you want to delete all local files? Make sure your migration was successful first.', 'dilux-cloud-storage' ),
					'confirm_rollback'     => __( 'Are you sure you want to download all files from cloud and revert URLs? This may take a long time.', 'dilux-cloud-storage' ),
				),
			)
		);
	}

	/**
	 * Render tab content based on current tab
	 *
	 * @param mixed $current_tab
	 */
	private static function render_tab_content( $current_tab ) {
		$template_path = '';
		$template_data = array();

		switch ( $current_tab ) {
			case 'overview':
				$template_path           = 'admin-overview.php';
				$config                  = ConfigManager::get_config();
				$config['is_configured'] = ConfigManager::is_configured();

				// Fetch cloud stats for overview display
				$current_state_ov = ConfigManager::get_state();
				$is_configured_ov = ! in_array( $current_state_ov, array( 'not_configured', '' ), true );
				$cloud_stats_ov   = null;

				if ( $is_configured_ov ) {
					try {
						$client = ConfigManager::get_cloud_client();
						if ( $client instanceof \DiluxWP\CloudStorage\Providers\DiluxOneCloudProvider ) {
							$cloud_stats_ov = $client->get_stats();
						} elseif ( $client instanceof \DiluxWP\CloudStorage\Providers\AzureProvider ) {
							$cloud_stats_ov = $client->get_container_stats();
						}
					} catch ( \Exception $e ) {
						$cloud_stats_ov = array(
							'success' => false,
							'message' => $e->getMessage(),
						);
					}
				}

				$template_data = array(
					'config'          => $config,
					'cloud_stats'     => $cloud_stats_ov,
					'stats'           => self::get_basic_stats(),
					'recent_activity' => array(),
				);
				break;

			case 'settings':
				$template_path           = 'admin-settings.php';
				$config                  = ConfigManager::get_config();
				$config['is_configured'] = ConfigManager::is_configured();
				$template_data           = array(
					'config' => $config,
				);
				break;

			case 'cloud-provider':
				$template_path           = 'admin-cloud-provider.php';
				$config                  = ConfigManager::get_config();
				$config['is_configured'] = ConfigManager::is_configured();

				// Prepare cloud stats and DB data for template (no business logic in templates)
				$current_state_cp   = ConfigManager::get_state();
				$is_configured_cp   = ! in_array( $current_state_cp, array( 'not_configured', '' ), true );
				$cloud_stats_cp     = null;
				$has_files_in_db_cp = false;

				if ( $is_configured_cp ) {
					// Fetch cloud stats
					try {
						$client = ConfigManager::get_cloud_client();
						if ( $client instanceof \DiluxWP\CloudStorage\Providers\DiluxOneCloudProvider ) {
							$cloud_stats_cp = $client->get_stats();
						} elseif ( $client instanceof \DiluxWP\CloudStorage\Providers\AzureProvider ) {
							$cloud_stats_cp = $client->get_container_stats();
						}
					} catch ( \Exception $e ) {
						$cloud_stats_cp = array(
							'success' => false,
							'message' => $e->getMessage(),
						);
					}

					// Check files in DB (table name from trusted source, no user input)
					require_once DILUX_CS_PLUGIN_DIR . 'includes/class-dilux-db.php';
					global $wpdb;
					$table_name_cp = DiluxDB::get_table_name();
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted DiluxDB::get_table_name()
					$has_files_in_db_cp = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_cp}" ) > 0;
				}

				$template_data = array(
					'config'          => $config,
					'current_state'   => $current_state_cp,
					'is_configured'   => $is_configured_cp,
					'cloud_stats'     => $cloud_stats_cp,
					'has_files_in_db' => $has_files_in_db_cp,
				);
				break;

			case 'sync':
			case 'sync-offloading':
				// Check if sync tab is accessible
				$is_configured = ConfigManager::is_configured();
				if ( ! $is_configured ) {
					// Distinguish "credentials cannot be decrypted" from
					// "never configured": the recovery path is different
					// (re-enter creds vs initial setup) and the existing
					// "Steps to Enable Sync" copy misleads the user when the
					// real problem is unreadable credentials.
					$health             = ConfigManager::get_connection_health();
					$is_decrypt_failure = $health['status'] === 'unhealthy'
						&& $health['error_code'] === 'decrypt_failed';

					if ( $is_decrypt_failure ) {
						?>
						<div class="wrap">
							<div class="notice notice-error">
								<h3><?php \esc_html_e( 'Stored Credentials Unreadable', 'dilux-cloud-storage' ); ?></h3>
								<p><?php \esc_html_e( 'Sync is paused because the saved cloud credentials cannot be decrypted. This is not the same as "never configured" — the cloud provider details are still in the database, but the WordPress salts changed since they were saved (commonly after restoring a database from a different environment).', 'dilux-cloud-storage' ); ?></p>
								<p><?php \esc_html_e( 'Re-enter the credentials in the Cloud Provider tab. Everything else (provider selection, container name, sync state) is preserved.', 'dilux-cloud-storage' ); ?></p>
								<p>
									<a href="?page=dilux-cloud-storage&tab=cloud-provider" class="button button-primary">
										<?php \esc_html_e( 'Re-enter Credentials', 'dilux-cloud-storage' ); ?>
									</a>
								</p>
							</div>
						</div>
						<?php
						return;
					}
					?>
					<div class="wrap">
						<div class="notice notice-warning is-dismissible">
							<h3><?php esc_html_e( 'Sync & Offloading Not Available', 'dilux-cloud-storage' ); ?></h3>
							<p><?php esc_html_e( 'Please configure a cloud provider in the Settings tab first.', 'dilux-cloud-storage' ); ?></p>
							<p>
								<a href="?page=dilux-cloud-storage&tab=settings" class="button button-primary">
									<?php esc_html_e( 'Go to Settings', 'dilux-cloud-storage' ); ?>
								</a>
							</p>
						</div>

						<div class="card" style="max-width: 600px; margin-top: 20px;">
							<h2><?php esc_html_e( 'Steps to Enable Sync', 'dilux-cloud-storage' ); ?></h2>
							<ol>
								<li><?php esc_html_e( 'Go to the Settings tab', 'dilux-cloud-storage' ); ?></li>
								<li><?php esc_html_e( 'Configure your cloud storage provider', 'dilux-cloud-storage' ); ?></li>
								<li><?php esc_html_e( 'Test the connection', 'dilux-cloud-storage' ); ?></li>
								<li><?php esc_html_e( 'Save your configuration', 'dilux-cloud-storage' ); ?></li>
								<li><?php esc_html_e( 'Return to this tab to sync files', 'dilux-cloud-storage' ); ?></li>
							</ol>
						</div>
					</div>
					<?php
					return;
				}

				$template_path           = 'admin-sync.php';
				$config                  = ConfigManager::get_config();
				$config['is_configured'] = ConfigManager::is_configured();

				$current_state_sync = ConfigManager::get_state();

				// Smart cleanup: reset stale syncing state (no heartbeat for >90s)
				if ( $current_state_sync === 'syncing' ) {
					$sync_meta            = get_option( 'dilux_cs_sync_meta', array() );
					$last_heartbeat       = $sync_meta['last_heartbeat'] ?? 0;
					$time_since_heartbeat = time() - $last_heartbeat;

					if ( $time_since_heartbeat > 90 ) {
						ConfigManager::set_state( \DiluxWP\CloudStorage\Enums\PluginState::SYNCED );
						ConfigManager::clear_sync_progress();
						$current_state_sync = 'synced';
						Logger::info( '[Dilux Admin] Auto-reset state from syncing to SYNCED (inactive for ' . $time_since_heartbeat . 's)' );
					}
				}

				// Get failed files from DB
				require_once DILUX_CS_PLUGIN_DIR . 'includes/class-dilux-db.php';
				$failed_files_sync = DiluxDB::get_failed_files();

				// Get file counts from DB for continuation detection (table name from trusted source)
				global $wpdb;
				$table_name_sync = DiluxDB::get_table_name();
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted DiluxDB::get_table_name()
				$has_files_in_db_sync = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_sync}" ) > 0;
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$synced_count_sync = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_sync} WHERE synced=1" );
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$pending_count_sync = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_sync} WHERE synced=0 AND deleted=0" );

				$template_data = array(
					'config'          => $config,
					'current_state'   => $current_state_sync,
					'sync_progress'   => ConfigManager::get_sync_progress(),
					'stats'           => self::get_basic_stats(),
					'failed_files'    => $failed_files_sync,
					'failed_count'    => count( $failed_files_sync ),
					'has_files_in_db' => $has_files_in_db_sync,
					'synced_count'    => $synced_count_sync,
					'pending_count'   => $pending_count_sync,
				);
				break;

			case 'activity':
				$template_path = 'admin-activity.php';
				$template_data = array(
					'activity_log'   => array(),
					'activity_stats' => self::get_basic_activity_stats(),
					'chart_data'     => array(
						'labels'    => array(),
						'uploads'   => array(),
						'deletions' => array(),
					),
				);
				break;

			case 'status':
			case 'tools':
			case 'status-tools':  // legacy alias — keep for old bookmarked URLs
				// Status and Tools share a single template that renders one
				// section at a time, picked via $section. The legacy
				// 'status-tools' URL falls back to 'status'.
				$template_path           = 'admin-status-tools.php';
				$config                  = ConfigManager::get_config();
				$config['is_configured'] = ConfigManager::is_configured();
				$template_data           = array(
					'config'        => $config,
					'health_status' => self::get_basic_health_status(),
					'status_checks' => self::get_basic_status_checks(),
					'storage_stats' => self::get_basic_stats(),
					'section'       => ( $current_tab === 'tools' ) ? 'tools' : 'status',
				);
				break;

			default:
				$template_path           = 'admin-overview.php';
				$config                  = ConfigManager::get_config();
				$config['is_configured'] = ConfigManager::is_configured();
				$template_data           = array(
					'config'          => $config,
					'stats'           => self::get_basic_stats(),
					'recent_activity' => array(),
				);
		}

		$full_template_path = DILUX_CS_PLUGIN_DIR . 'templates/' . $template_path;

		if ( file_exists( $full_template_path ) ) {
			// Templates expect each value of $template_data to be available as
			// a local variable. The keys are static (set in this method) and
			// never derived from user input, so the documented extract() risk
			// (variable shadowing from untrusted keys) does not apply here.
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Keys are static and trusted; templates depend on this contract.
			extract( $template_data );
			include $full_template_path;
		} else {
			echo '<p>' . \esc_html(
				/* translators: %s: relative template file path */
				\sprintf( \__( 'Template not found: %s', 'dilux-cloud-storage' ), $template_path )
			) . '</p>';
		}
	}

	/**
	 * Short, single-line reason for a health pause. Used inline in the
	 * Status tab cards as a sub-label so each card explains *why* it is
	 * showing "paused" instead of its normal state.
	 *
	 * Mirrors the error_code branches in health_banner_copy() — keep them
	 * in sync if a new error_code is added.
	 *
	 * @param string $error_code Connection-health error_code (e.g. 'decrypt_failed', '403')
	 * @return string Short human label (already translated)
	 */
	public static function pause_reason_short( string $error_code ): string {
		switch ( $error_code ) {
			case 'decrypt_failed':
				return __( 'credentials unreadable', 'dilux-cloud-storage' );
			case '401':
			case '403':
				return __( 'permission denied', 'dilux-cloud-storage' );
			case '404':
				return __( 'container not found', 'dilux-cloud-storage' );
			case 'exception':
				return __( 'connection error', 'dilux-cloud-storage' );
			default:
				return __( 'cloud unreachable', 'dilux-cloud-storage' );
		}
	}

	/**
	 * Build the title / detail / CTA copy for the health banner based on the
	 * recorded `error_code`. Each branch maps a known failure mode to a
	 * tailored message so the user knows exactly what to do.
	 *
	 * Returns an array with keys: title, detail, cta_label.
	 *
	 * @param string $error_code    Code from connection_health (e.g. 'decrypt_failed', '403', 'exception')
	 * @param string $error_message Human-readable message from the failure source
	 * @return array{title:string,detail:string,cta_label:string}
	 */
	private static function health_banner_copy( string $error_code, string $error_message ): array {
		switch ( $error_code ) {
			case 'decrypt_failed':
				return array(
					'title'     => __( 'Stored Credentials Unreadable', 'dilux-cloud-storage' ),
					'detail'    => __( 'Your saved cloud credentials cannot be decrypted. This usually means the WordPress salts (AUTH_KEY / SECURE_AUTH_KEY) changed since these credentials were saved — for example after restoring a database from a different environment. Re-enter your credentials to fix this.', 'dilux-cloud-storage' ),
					'cta_label' => __( 'Re-enter Credentials', 'dilux-cloud-storage' ),
				);

			case '401':
			case '403':
				return array(
					'title'     => __( 'Cloud Permission Denied', 'dilux-cloud-storage' ),
					'detail'    => __( 'The cloud provider rejected the credentials. The access key may have been rotated, the SAS token may have expired, or the role assignment is missing. Verify the credentials and re-enter them.', 'dilux-cloud-storage' ),
					'cta_label' => __( 'Update Credentials', 'dilux-cloud-storage' ),
				);

			case '404':
				return array(
					'title'     => __( 'Container Not Found', 'dilux-cloud-storage' ),
					'detail'    => __( 'The configured container or bucket does not exist on the cloud provider. Check that the name is spelled correctly and that it has been created.', 'dilux-cloud-storage' ),
					'cta_label' => __( 'Open Cloud Provider Settings', 'dilux-cloud-storage' ),
				);

			case 'exception':
				return array(
					'title'     => __( 'Cloud Connection Error', 'dilux-cloud-storage' ),
					'detail'    => $error_message !== ''
						? $error_message
						: __( 'An unexpected error occurred while talking to the cloud provider.', 'dilux-cloud-storage' ),
					'cta_label' => __( 'Update your credentials in the Cloud Provider tab', 'dilux-cloud-storage' ),
				);

			default:
				// Unknown / generic — preserve the existing copy.
				return array(
					'title'     => __( 'Cloud Connection Error', 'dilux-cloud-storage' ),
					'detail'    => $error_message,
					'cta_label' => __( 'Update your credentials in the Cloud Provider tab', 'dilux-cloud-storage' ),
				);
		}
	}

	/**
	 * Render the connection health error banner.
	 *
	 * @param array $health Connection health data from ConfigManager
	 */
	private static function render_connection_health_banner( array $health ): void {
		$current_state = ConfigManager::get_state();
		$is_offloading = ( $current_state === 'offloading_active' );
		$copy          = self::health_banner_copy(
			(string) ( $health['error_code'] ?? '' ),
			(string) ( $health['error_message'] ?? '' )
		);

		// Calculate time since last success (only show if > 5 min to avoid
		// confusing "2 minutes ago" when the break just happened)
		$last_success_text = '';
		if ( $health['last_success'] > 0 ) {
			$diff = time() - $health['last_success'];
			if ( $diff < 300 ) {
				// Too recent — skip showing it (just broke, not useful context)
				$last_success_text = '';
			} elseif ( $diff < 3600 ) {
				$minutes = (int) ( $diff / 60 );
				/* translators: %d: number of minutes */
				$last_success_text = sprintf( \_n( '%d minute ago', '%d minutes ago', $minutes, 'dilux-cloud-storage' ), $minutes );
			} elseif ( $diff < 86400 ) {
				$hours = (int) ( $diff / 3600 );
				/* translators: %d: number of hours */
				$last_success_text = sprintf( \_n( '%d hour ago', '%d hours ago', $hours, 'dilux-cloud-storage' ), $hours );
			} else {
				$days = (int) ( $diff / 86400 );
				/* translators: %d: number of days */
				$last_success_text = sprintf( \_n( '%d day ago', '%d days ago', $days, 'dilux-cloud-storage' ), $days );
			}
		}
		?>
		<div class="dilux-health-banner" style="background: #fef0f0; border-left: 4px solid #d63638; padding: 16px 20px; margin-bottom: 20px; border-radius: 4px;">
			<div style="display: flex; align-items: flex-start; gap: 12px;">
				<span class="dashicons dashicons-warning" style="color: #d63638; font-size: 24px; flex-shrink: 0; margin-top: 2px;"></span>
				<div>
					<strong style="color: #721c24; font-size: 15px;">
						<?php echo \esc_html( $copy['title'] ); ?>
					</strong>
					<p style="margin: 8px 0 0; color: #721c24;">
						<?php echo \esc_html( $copy['detail'] ); ?>
					</p>
					<?php if ( $last_success_text ) : ?>
					<p style="margin: 4px 0 0; color: #856404; font-size: 13px;">
						<?php
						/* translators: %s: human-readable time, e.g. "5 minutes ago" */
						printf( \esc_html__( 'Last successful connection: %s', 'dilux-cloud-storage' ), \esc_html( $last_success_text ) );
						?>
					</p>
					<?php endif; ?>
					<?php if ( $is_offloading ) : ?>
					<p style="margin: 8px 0 0; color: #721c24; font-weight: 600;">
						<?php \esc_html_e( 'File uploads are falling back to local storage.', 'dilux-cloud-storage' ); ?>
					</p>
					<?php endif; ?>
					<p style="margin: 8px 0 0; font-size: 13px;">
						<a href="<?php echo \esc_url( \admin_url( 'admin.php?page=dilux-cloud-storage&tab=cloud-provider' ) ); ?>" style="color: #721c24; text-decoration: underline;">
							<?php echo \esc_html( $copy['cta_label'] ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get basic stats for templates
	 */
	private static function get_basic_stats() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'dilux_cs_files';

		// Get deletable files count (synced but not deleted locally)
		$deletable_stats = $wpdb->get_row(
			"SELECT
                COUNT(*) as total_files,
                COALESCE(SUM(size), 0) as total_size
             FROM {$table_name}
             WHERE synced = 1 AND deleted = 0",
			ARRAY_A
		);

		$deletable_files = $deletable_stats ? (int) $deletable_stats['total_files'] : 0;
		$deletable_size  = $deletable_stats ? (int) $deletable_stats['total_size'] : 0;

		return array(
			'total_files'     => 0,
			'total_size'      => 0,
			'cloud_files'     => 0,
			'cloud_size'      => 0,
			'local_files'     => 0,
			'local_size'      => 0,
			'files_today'     => 0,
			'size_today'      => 0,
			'deletable_files' => $deletable_files,
			'deletable_size'  => $deletable_size,
		);
	}

	/**
	 * Get basic migration status for templates
	 */
	private static function get_basic_migration_status() {
		return array(
			'status'   => 'not_started',
			'progress' => 0,
			'stats'    => self::get_basic_stats(),
		);
	}

	/**
	 * Get basic activity stats for templates
	 */
	private static function get_basic_activity_stats() {
		return array(
			'total_today'    => 0,
			'total_week'     => 0,
			'total_month'    => 0,
			'errors_count'   => 0,
			'trend_today'    => 0,
			'week_uploads'   => 0,
			'week_deletions' => 0,
			'month_size'     => 0,
			'total_entries'  => 0,
		);
	}

	/**
	 * Get basic health status for templates
	 */
	private static function get_basic_health_status() {
		return array(
			'overall'         => 50,
			'checks_passed'   => 3,
			'warnings'        => 2,
			'critical_issues' => 1,
		);
	}

	/**
	 * Get basic status checks for templates
	 */
	private static function get_basic_status_checks() {
		return array(
			'azure_connection'      => array(
				'status'  => 'warning',
				'message' => \__( 'Not configured yet', 'dilux-cloud-storage' ),
				'details' => array(),
			),
			'stream_wrapper'        => array(
				'status'  => 'success',
				'message' => \__( 'Stream wrapper registered', 'dilux-cloud-storage' ),
				'details' => array(
					'protocol'   => 'dilux://',
					'registered' => true,
				),
			),
			'file_operations'       => array(
				'status'  => 'warning',
				'message' => \__( 'Not tested yet', 'dilux-cloud-storage' ),
				'details' => array(
					'write'  => false,
					'read'   => false,
					'delete' => false,
				),
			),
			'performance'           => array(
				'status'  => 'warning',
				'message' => \__( 'Not tested yet', 'dilux-cloud-storage' ),
				'details' => array(
					'upload_speed'   => '0 MB/s',
					'download_speed' => '0 MB/s',
					'latency'        => '0 ms',
				),
			),
			'wordpress_integration' => array(
				'status'  => 'success',
				'message' => \__( 'WordPress integration active', 'dilux-cloud-storage' ),
				'details' => array(
					'media_library' => true,
					'url_rewriting' => true,
					'multisite'     => \is_multisite(),
				),
			),
			'security'              => array(
				'status'  => 'success',
				'message' => \__( 'Security features active', 'dilux-cloud-storage' ),
				'details' => array(
					'ssl'            => true,
					'encryption'     => true,
					'access_control' => true,
				),
			),
		);
	}

	/**
	 * Handle configuration save
	 *
	 * @throws \Exception When PluginSettings/ProviderConfig validation fails inside
	 *                   the inner try blocks (caught and converted to error notices).
	 */
	public static function save_config() {
		// Check nonce for security
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dilux_cs_save_config' ) ) {
			wp_die( esc_html__( 'Security check failed', 'dilux-cloud-storage' ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) );
		}

		// Get redirect tab from form (cloud-provider or settings)
		$redirect_tab = sanitize_text_field( wp_unslash( $_POST['redirect_tab'] ?? 'settings' ) );

		// ========================================================================
		// NEW ARCHITECTURE: Detect what's being saved based on tab + fields
		// ========================================================================
		// Key insight: disabled fields are NOT sent in POST, so we use redirect_tab
		// to determine intent, then check which fields were actually sent

		$is_from_settings_tab       = ( $redirect_tab === 'settings' );
		$is_from_cloud_provider_tab = ( $redirect_tab === 'cloud-provider' );

		if ( $is_from_settings_tab ) {
			// Settings-only save: Use PluginSettings::fromPost()
			try {
				$settings = \DiluxWP\CloudStorage\DTOs\PluginSettings::fromPost( $_POST );

				$result = ConfigManager::save_plugin_settings( $settings );

				if ( $result ) {
					$redirect_url = add_query_arg(
						array(
							'page'    => 'dilux-cloud-storage',
							'tab'     => $redirect_tab,
							'success' => rawurlencode( 'Settings saved successfully!' ),
						),
						admin_url( 'admin.php' )
					);
				} else {
					throw new \Exception( 'Failed to save settings to database' );
				}
			} catch ( \Exception $e ) {
				$redirect_url = add_query_arg(
					array(
						'page'  => 'dilux-cloud-storage',
						'tab'   => $redirect_tab,
						'error' => rawurlencode( 'Failed to save settings: ' . $e->getMessage() ),
					),
					admin_url( 'admin.php' )
				);
			}

			wp_safe_redirect( $redirect_url );
			exit;

		} elseif ( $is_from_cloud_provider_tab ) {
			// Cloud Provider tab: Could be full provider save OR custom domain only

			// Check if provider credentials were sent (not disabled)
			$has_provider_credentials = isset( $_POST['cloud_provider'] ) &&
				( isset( $_POST['account_name'] ) || isset( $_POST['api_key'] ) );

			if ( $has_provider_credentials ) {
				// Full provider save: credentials + custom domain
				try {
					$provider = \DiluxWP\CloudStorage\DTOs\ProviderConfig::fromPost( $_POST );

					$result = ConfigManager::save_provider_config( $provider );

					if ( $result ) {
						$redirect_url = add_query_arg(
							array(
								'page'    => 'dilux-cloud-storage',
								'tab'     => $redirect_tab,
								'success' => rawurlencode( 'Provider configuration saved successfully!' ),
							),
							admin_url( 'admin.php' )
						);
					} else {
						throw new \Exception( 'Failed to save provider configuration to database' );
					}
				} catch ( \InvalidArgumentException $e ) {
					// Validation error from ProviderConfig::fromPost()
					$redirect_url = add_query_arg(
						array(
							'page'  => 'dilux-cloud-storage',
							'tab'   => $redirect_tab,
							'error' => rawurlencode( $e->getMessage() ),
						),
						admin_url( 'admin.php' )
					);
				} catch ( \Exception $e ) {
					$redirect_url = add_query_arg(
						array(
							'page'  => 'dilux-cloud-storage',
							'tab'   => $redirect_tab,
							'error' => rawurlencode( 'Failed to save configuration: ' . $e->getMessage() ),
						),
						admin_url( 'admin.php' )
					);
				}
			} else {
				// No credentials sent (fields were disabled) — nothing to save
				$redirect_url = add_query_arg(
					array(
						'page' => 'dilux-cloud-storage',
						'tab'  => $redirect_tab,
					),
					admin_url( 'admin.php' )
				);
			}

			wp_safe_redirect( $redirect_url );
			exit;

		} else {
			// Unknown tab - shouldn't happen
			$redirect_url = add_query_arg(
				array(
					'page'  => 'dilux-cloud-storage',
					'tab'   => 'overview',
					'error' => rawurlencode( 'Invalid save request' ),
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * AJAX handler for testing connection
	 */
	public static function ajax_test_connection() {

		// Check nonce for security - try different nonce field names
		$nonce_verified = false;
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'dilux_cs_admin' ) ) {
			$nonce_verified = true;
		} elseif ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dilux_cs_admin' ) ) {
			$nonce_verified = true;
		}

		if ( ! $nonce_verified ) {
			Logger::error( '[Dilux CS] Security check failed. Available POST fields: ' . implode( ', ', array_keys( $_POST ) ) );
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'dilux-cloud-storage' ) ) );
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) ) );
			return;
		}

		// Detect provider type
		$provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? 'azure' ) );

		try {
			if ( $provider === 'diluxone' ) {
				$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
				if ( empty( $api_key ) ) {
					wp_send_json_error( array( 'message' => esc_html__( 'API Key is required', 'dilux-cloud-storage' ) ) );
					return;
				}
				$client = \DiluxWP\CloudStorage\Factories\CloudStorageFactory::create(
					'diluxone',
					array(
						'api_key' => $api_key,
					)
				);
			} else {
				$account_name   = sanitize_text_field( wp_unslash( $_POST['account_name'] ?? '' ) );
				$account_key    = sanitize_text_field( wp_unslash( $_POST['account_key'] ?? '' ) );
				$container_name = sanitize_text_field( wp_unslash( $_POST['container_name'] ?? '' ) );

				if ( empty( $account_name ) || empty( $account_key ) || empty( $container_name ) ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Missing required fields', 'dilux-cloud-storage' ) ) );
					return;
				}
				$client = \DiluxWP\CloudStorage\Factories\CloudStorageFactory::create(
					'azure',
					array(
						'storage_account' => $account_name,
						'access_key'      => $account_key,
						'container_name'  => $container_name,
					)
				);
			}

			$result = $client->test_connection();

			if ( $result['success'] ) {
				Logger::info( '[Dilux CS] Connection successful for provider: ' . $provider );
				ConfigManager::record_connection_success();

				// Save provider-aware transient for validation when saving
				if ( $provider === 'diluxone' ) {
					$transient_data = array(
						'provider'       => 'diluxone',
						'api_key_prefix' => substr( $api_key, 0, 12 ),
						'timestamp'      => time(),
					);
				} else {
					$transient_data = array(
						'provider'       => 'azure',
						'account_name'   => $account_name,
						'container_name' => $container_name,
						'timestamp'      => time(),
					);
				}

				set_transient(
					'dilux_cs_connection_test_passed_' . get_current_user_id(),
					$transient_data,
					300
				);

				\wp_send_json_success(
					array(
						'message'     => $result['message'] ?? 'Connection successful! You can now save.',
						'test_passed' => true,
					)
				);
			} else {
				Logger::info( '[Dilux CS] Connection failed: ' . $result['message'] );
				\wp_send_json_error(
					array(
						'message' => $result['message'] ?? 'Connection failed',
					)
				);
			}
		} catch ( \Exception $e ) {
			Logger::info( '[Dilux CS] Connection error: ' . $e->getMessage() );
			\wp_send_json_error(
				array(
					'message' => 'Connection error: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX handler for refreshing cloud storage statistics
	 *
	 * Calls provider-specific stats method with force_refresh=true.
	 * Uses instanceof to detect which method to call (get_stats for DiluxOne,
	 * get_container_stats for Azure) since these methods are not in the interface.
	 */
	public static function ajax_refresh_stats() {
		check_ajax_referer( 'dilux_cs_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) ) );
			return;
		}

		$client = ConfigManager::get_cloud_client();
		if ( ! $client ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Cloud client not available', 'dilux-cloud-storage' ) ) );
			return;
		}

		try {
			$stats = null;
			if ( $client instanceof \DiluxWP\CloudStorage\Providers\DiluxOneCloudProvider ) {
				$stats = $client->get_stats( true );
			} elseif ( $client instanceof \DiluxWP\CloudStorage\Providers\AzureProvider ) {
				$stats = $client->get_container_stats( true );
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Unknown provider type', 'dilux-cloud-storage' ) ) );
				return;
			}

			if ( $stats['success'] ) {
				ConfigManager::record_connection_success();
				wp_send_json_success( $stats['data'] );
			} else {
				wp_send_json_error( array( 'message' => $stats['message'] ?? 'Failed to fetch stats' ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for saving updated credentials (Update Credentials modal)
	 */
	public static function ajax_save_updated_credentials() {
		// Check nonce
		check_ajax_referer( 'dilux_cs_admin', 'nonce' );

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'dilux-cloud-storage' ) ) );
			return;
		}

		// Validate that connection test passed
		$test_data = get_transient( 'dilux_cs_connection_test_passed_' . get_current_user_id() );
		if ( ! $test_data ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You must test the connection first', 'dilux-cloud-storage' ) ) );
			return;
		}

		$provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) );

		// Build provider_data based on provider type
		if ( $provider === 'diluxone' ) {
			$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );

			// Validate transient matches diluxone test
			if ( ( $test_data['provider'] ?? '' ) !== 'diluxone' ||
				( $test_data['api_key_prefix'] ?? '' ) !== substr( $api_key, 0, 12 ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Credentials do not match tested values. Please test again.', 'dilux-cloud-storage' ) ) );
				return;
			}

			// Preserve cdn_base_url from existing config
			$current_config = ConfigManager::get_provider_config();
			$provider_data  = array(
				'cloud_provider'  => 'diluxone',
				'provider_config' => array(
					'api_key'      => $api_key,
					'cdn_base_url' => $current_config['provider_config']['cdn_base_url'] ?? '',
				),
			);
		} else {
			$account_name   = sanitize_text_field( wp_unslash( $_POST['account_name'] ?? '' ) );
			$account_key    = sanitize_text_field( wp_unslash( $_POST['account_key'] ?? '' ) );
			$container_name = sanitize_text_field( wp_unslash( $_POST['container_name'] ?? '' ) );

			// Validate transient matches azure test
			if ( ( $test_data['account_name'] ?? '' ) !== $account_name ||
				( $test_data['container_name'] ?? '' ) !== $container_name ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Credentials do not match tested values. Please test again.', 'dilux-cloud-storage' ) ) );
				return;
			}

			$provider_data = array(
				'cloud_provider'  => $provider,
				'provider_config' => array(
					'storage_account' => $account_name,
					'access_key'      => $account_key,
					'container_name'  => $container_name,
				),
			);

			// Preserve custom_domain if exists
			$current_config = ConfigManager::get_provider_config();
			if ( ! empty( $current_config['provider_config']['custom_domain'] ) ) {
				$provider_data['provider_config']['custom_domain'] =
					$current_config['provider_config']['custom_domain'];
			}
		}

		try {
			$provider_config = \DiluxWP\CloudStorage\DTOs\ProviderConfig::fromArray( $provider_data );
			ConfigManager::save_provider_config( $provider_config );

			// Clear transient
			delete_transient( 'dilux_cs_connection_test_passed_' . get_current_user_id() );

			// Log for audit
			Logger::info( '[Dilux CS] Credentials updated by user ID: ' . get_current_user_id() );

			wp_send_json_success(
				array(
					'message' => 'Credentials updated successfully',
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => 'Error saving: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Handle offloading configuration save
	 */
	public static function save_offloading_config() {
		// Check nonce for security
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dilux_cs_save_offloading' ) ) {
			wp_die( esc_html__( 'Security check failed', 'dilux-cloud-storage' ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) );
		}

		// Sanitize and validate input
		$offloading_enabled  = isset( $_POST['offloading_enabled'] );
		$offloading_strategy = sanitize_text_field( wp_unslash( $_POST['offloading_strategy'] ?? 'new_uploads_only' ) );
		$delete_local_files  = isset( $_POST['delete_local_files'] );

		// Save offloading configuration
		try {
			$config                        = ConfigManager::get_config();
			$config['offloading_enabled']  = $offloading_enabled;
			$config['offloading_strategy'] = $offloading_strategy;
			$config['delete_local_files']  = $delete_local_files;

			// Set activation timestamp for new uploads strategy
			if ( $offloading_enabled && $offloading_strategy === 'new_uploads_only' ) {
				if ( empty( $config['dilux_cs_activation_timestamp'] ) ) {
					update_option( 'dilux_cs_activation_timestamp', time() );
				}
			}

			ConfigManager::save_config( $config );

			$redirect_url = add_query_arg(
				array(
					'page'    => 'dilux-cloud-storage',
					'tab'     => 'offloading',
					'success' => rawurlencode( 'Offloading configuration saved successfully!' ),
				),
				admin_url( 'admin.php' )
			);

		} catch ( \Exception $e ) {
			$redirect_url = add_query_arg(
				array(
					'page'  => 'dilux-cloud-storage',
					'tab'   => 'offloading',
					'error' => rawurlencode( 'Failed to save offloading configuration: ' . $e->getMessage() ),
				),
				admin_url( 'admin.php' )
			);
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX handler for migration actions
	 */
	public static function ajax_migration_action() {
		// Check nonce for security
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'dilux_cs_admin' ) ) {
			wp_die( esc_html__( 'Security check failed', 'dilux-cloud-storage' ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['migration_action'] ?? '' ) );

		if ( empty( $action ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No action specified', 'dilux-cloud-storage' ) ) );
			return;
		}

		// Legacy endpoint - MigrationTools class was removed.
		// Migration is now handled via the sync flow in class-dilux-plugin-enhanced.php
		wp_send_json_error(
			array(
				'message' => 'This migration endpoint is deprecated. Please use the Sync & Offloading tab instead.',
			)
		);
	}

	/**
	 * AJAX handler for individual status checks
	 */
	public static function ajax_test_check() {
		// Check nonce for security
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'dilux_cs_admin' ) ) {
			wp_die( esc_html__( 'Security check failed', 'dilux-cloud-storage' ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) );
		}

		$check_type = sanitize_text_field( wp_unslash( $_POST['check_type'] ?? '' ) );

		if ( empty( $check_type ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No check type specified', 'dilux-cloud-storage' ) ) );
			return;
		}

		try {
			switch ( $check_type ) {
				case 'azure_connection':
					// Use new architecture: ConfigManager::get_cloud_client()
					$cloud_client = ConfigManager::get_cloud_client();

					if ( ! $cloud_client ) {
						$result = array(
							'success' => false,
							'message' => 'Cloud provider not configured',
						);
					} else {
						$result = $cloud_client->test_connection();
					}
					break;

				case 'stream_wrapper':
					$is_registered = in_array( 'diluxcloud', stream_get_wrappers(), true );
					$result        = array(
						'success' => $is_registered,
						'message' => $is_registered
							? 'Stream wrapper diluxcloud:// is registered'
							: 'Stream wrapper diluxcloud:// is NOT registered',
					);
					break;

				case 'upload_test':
				case 'download_test':
					// These legacy tests are no longer needed - stream wrapper handles uploads/downloads
					$result = array(
						'success' => true,
						'message' => 'Test deprecated - functionality handled by stream wrapper',
					);
					break;

				default:
					/* translators: %s: requested check type identifier */
					wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Invalid check type: %s', 'dilux-cloud-storage' ), $check_type ) ) );
					return;
			}

			wp_send_json_success(
				array(
					'status'      => $result['success'] ? 'passed' : 'failed',
					'status_text' => $result['success'] ? 'Passed' : 'Failed',
					'message'     => $result['message'] ?? ( $result['success'] ? 'Check passed' : 'Check failed' ),
				)
			);

		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'status'      => 'failed',
					'status_text' => 'Failed',
					'message'     => 'Check error: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Remove provider configuration
	 */
	public static function remove_provider_config() {
		// Check nonce for security
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'dilux_cs_remove_provider' ) ) {
			wp_die( esc_html__( 'Security check failed', 'dilux-cloud-storage' ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) );
		}

		try {
			Logger::info( '[Dilux CS] Removing provider configuration...' );

			// ⭐ COMPLETE DELETION: Delete all wp_options entries
			delete_option( 'dilux_cs_config' );         // Main config (credentials + settings)
			delete_option( 'dilux_cs_plugin_state' );   // Plugin state (NOT_CONFIGURED, CONFIGURED, SYNCING, etc.)
			delete_option( 'dilux_cs_sync_meta' );      // Sync metadata (total_files, start_time, concurrency, etc.)
			delete_option( 'dilux_cs_sync_progress' );  // Legacy sync progress option (may not exist)
			delete_option( 'dilux_cs_failed_files' );   // Array of files that failed to sync
			delete_option( 'dilux_cs_connection_health' ); // Connection health status

			// Clear the MySQL table: wp_dilux_cs_files (tracks all files for sync)
			require_once DILUX_CS_PLUGIN_DIR . 'includes/class-dilux-db.php';
			DiluxDB::clear_table();

			Logger::info( '[Dilux CS] Provider configuration removed successfully - all credentials, state, and tracking data deleted' );

			$redirect_url = add_query_arg(
				array(
					'page'    => 'dilux-cloud-storage',
					'tab'     => 'settings',
					'success' => rawurlencode( __( 'Cloud storage configuration removed successfully. The plugin has been reset.', 'dilux-cloud-storage' ) ),
				),
				admin_url( 'admin.php' )
			);

		} catch ( \Exception $e ) {
			Logger::info( '[Dilux CS] Error removing provider configuration: ' . $e->getMessage() );

			$redirect_url = add_query_arg(
				array(
					'page'  => 'dilux-cloud-storage',
					'tab'   => 'settings',
					'error' => rawurlencode(
						sprintf(
						/* translators: %s is the error message. */
							__( 'Failed to remove configuration: %s', 'dilux-cloud-storage' ),
							$e->getMessage()
						)
					),
				),
				admin_url( 'admin.php' )
			);
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX: Scan files for sync
	 */
	public static function ajax_scan_files() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'dilux_cs_admin' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'dilux-cloud-storage' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) );
		}

		try {
			// Get upload directory
			$upload_dir = wp_upload_dir();
			$files      = array();
			$total_size = 0;

			Logger::log( '[Dilux Admin] Scanning directory (ajax): ' . $upload_dir['basedir'], 'info', true );

			// Scan files recursively
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $upload_dir['basedir'] )
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() ) {
					$files[]     = $file->getPathname();
					$total_size += $file->getSize();
				}
			}

			wp_send_json_success(
				array(
					'total_files'          => count( $files ),
					'total_size'           => $total_size,
					'total_size_formatted' => size_format( $total_size ),
					/* translators: 1: number of files, 2: human-readable total size */
					'message'              => sprintf( __( 'Found %1$d files (%2$s)', 'dilux-cloud-storage' ), count( $files ), size_format( $total_size ) ),
				)
			);

		} catch ( \Exception $e ) {
			/* translators: %s: error message */
			wp_send_json_error( sprintf( esc_html__( 'Error scanning files: %s', 'dilux-cloud-storage' ), $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Start sync process
	 */
	public static function ajax_start_sync() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'dilux_cs_admin' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'dilux-cloud-storage' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) );
		}

		try {
			// Start sync using SyncManager
			$sync_manager = new \DiluxWP\CloudStorage\SyncManager();
			$result       = $sync_manager->start_sync();

			if ( $result ) {
				// Update state to syncing
				ConfigManager::set_state( 'syncing' );

				wp_send_json_success(
					array(
						'message'  => __( 'Sync started successfully', 'dilux-cloud-storage' ),
						'redirect' => true,
					)
				);
			} else {
				wp_send_json_error( esc_html__( 'Failed to start sync', 'dilux-cloud-storage' ) );
			}
		} catch ( \Exception $e ) {
			/* translators: %s: error message */
			wp_send_json_error( sprintf( esc_html__( 'Error starting sync: %s', 'dilux-cloud-storage' ), $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Delete local files after sync
	 */
	public static function ajax_delete_local_files() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'dilux_cs_admin' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'dilux-cloud-storage' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) );
		}

		try {
			// Check that offloading is active - only allow deletion if offloading is ON
			$current_state = ConfigManager::get_state();
			if ( $current_state !== 'offloading_active' ) {
				wp_send_json_error(
					array(
						'message' => __( 'Offloading must be active before deleting local files', 'dilux-cloud-storage' ),
					)
				);
				return;
			}

			// Get all synced files from DB
			require_once DILUX_CS_PLUGIN_DIR . 'includes/class-dilux-db.php';

			$synced_files = \DiluxWP\CloudStorage\DiluxDB::get_synced_files();

			if ( empty( $synced_files ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'No synced files found to delete', 'dilux-cloud-storage' ),
					)
				);
				return;
			}

			// Get upload directory base path
			$upload_dir = wp_upload_dir();
			$base_path  = $upload_dir['basedir'];

			$deleted_count    = 0;
			$failed_count     = 0;
			$total_size_freed = 0;
			$errors           = array();

			foreach ( $synced_files as $file ) {
				$file_path = $base_path . $file['file'];

				// Safety check: ensure file is within uploads directory
				if ( strpos( realpath( $file_path ), realpath( $base_path ) ) !== 0 ) {
					$errors[] = 'Skipped file outside uploads directory: ' . $file['file'];
					continue;
				}

				// Check if file exists
				if ( ! file_exists( $file_path ) ) {
					continue; // Already deleted or doesn't exist
				}

				// Get file size before deletion
				$file_size = filesize( $file_path );

				// Delete the file. wp_delete_file() returns void, so we re-check existence.
				wp_delete_file( $file_path );
				if ( ! file_exists( $file_path ) ) {
					++$deleted_count;
					$total_size_freed += $file_size;
				} else {
					++$failed_count;
					$errors[] = 'Failed to delete: ' . $file['file'];
				}
			}

			// Clean up empty directories
			self::cleanup_empty_directories( $base_path );

			wp_send_json_success(
				array(
					'message'       => sprintf(
						/* translators: 1: number of deleted files, 2: total MB freed (decimal), 3: number of failures */
						__( 'Deleted %1$d files (%2$.2f MB freed). %3$d failures.', 'dilux-cloud-storage' ),
						$deleted_count,
						$total_size_freed / 1048576,
						$failed_count
					),
					'deleted_count' => $deleted_count,
					'failed_count'  => $failed_count,
					'size_freed'    => $total_size_freed,
					'errors'        => $errors,
				)
			);

		} catch ( \Exception $e ) {
			/* translators: %s: error message */
			wp_send_json_error( sprintf( esc_html__( 'Error deleting local files: %s', 'dilux-cloud-storage' ), $e->getMessage() ) );
		}
	}

	/**
	 * Recursively cleanup empty directories
	 *
	 * @param mixed $path
	 */
	private static function cleanup_empty_directories( $path ) {
		if ( ! is_dir( $path ) ) {
			return;
		}

		$entries = scandir( $path );
		$entries = array_diff( $entries, array( '.', '..' ) );

		foreach ( $entries as $entry ) {
			$full_path = $path . '/' . $entry;
			if ( is_dir( $full_path ) ) {
				self::cleanup_empty_directories( $full_path );
			}
		}

		// Check again after recursive cleanup
		$entries = scandir( $path );
		$entries = array_diff( $entries, array( '.', '..' ) );

		// Only delete if empty and not the base uploads directory.
		// WP_Filesystem doesn't expose a portable rmdir() that works without
		// re-initialising file ownership credentials in admin context, so we
		// use the native call here, restricted to verified-empty subdirectories
		// under wp-content/uploads/.
		$upload_dir = wp_upload_dir();
		if ( empty( $entries ) && realpath( $path ) !== realpath( $upload_dir['basedir'] ) ) {
			// Best-effort rmdir on an already-empty directory inside the
			// uploads tree. The @ silencing matches the WP-core pattern
			// for idempotent cleanup — a "directory not empty" race
			// during plugin uninstall is harmless.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir,WordPress.PHP.NoSilencedErrors.Discouraged -- Idempotent cleanup of already-verified-empty directory.
			@rmdir( $path );
		}
	}

	/**
	 * AJAX: Cancel sync process OR reset plugin to configured state
	 *
	 * This endpoint handles two scenarios:
	 * 1. Active sync: Cancel the sync and reset to CONFIGURED
	 * 2. Synced state: Reset everything (DB + metadata + state) back to CONFIGURED
	 */
	public static function ajax_cancel_sync() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'dilux_cs_admin' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'dilux-cloud-storage' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) );
		}

		// ⭐ DOUBLE VALIDATION: Validate that this operation is safe to execute
		$session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
		$validation = \DiluxWP\CloudStorage\DiluxValidationHelper::validate_sync_operation(
			$session_id,
			'cancel_sync' // Validate that no other tab is syncing
		);

		if ( ! $validation['passed'] ) {
			Logger::warning( '[Dilux CS] Cancel sync BLOCKED by validation: ' . $validation['reason'] );
			wp_send_json_error(
				array(
					'validation_failed' => true,
					'reason'            => $validation['reason'],
					'details'           => $validation['details'],
					'message'           => __( 'Cannot cancel sync: Another tab is currently syncing', 'dilux-cloud-storage' ),
				)
			);
			return;
		}

		try {
			$current_state = ConfigManager::get_state();

			// Try to cancel sync using SyncManager
			$sync_manager = new \DiluxWP\CloudStorage\SyncManager();
			$cancelled    = $sync_manager->cancel_sync();

			if ( $cancelled ) {
				// Sync was active and got cancelled
				wp_send_json_success(
					array(
						'message' => __( 'Sync cancelled successfully', 'dilux-cloud-storage' ),
					)
				);
			} else {
				// No active sync - perform FULL RESET (for "Cancel Sync & Reset" button)
				// This allows user to discard a completed sync and start fresh

				Logger::info( '[Dilux CS] No active sync - performing full reset to CONFIGURED state' );

				// Clear DB table
				require_once DILUX_CS_PLUGIN_DIR . 'includes/class-dilux-db.php';
				\DiluxWP\CloudStorage\DiluxDB::clear_table();

				// Clear sync metadata
				delete_option( 'dilux_cs_sync_meta' );
				delete_option( 'dilux_cs_failed_files' );

				// Reset state to CONFIGURED (preserves credentials)
				ConfigManager::set_state( \DiluxWP\CloudStorage\Enums\PluginState::CONFIGURED );

				wp_send_json_success(
					array(
						'message' => __( 'Plugin reset to configured state successfully', 'dilux-cloud-storage' ),
					)
				);
			}
		} catch ( \Exception $e ) {
			Logger::info( '[Dilux CS] Error in cancel_sync: ' . $e->getMessage() );
			/* translators: %s: error message */
			wp_send_json_error( sprintf( esc_html__( 'Error: %s', 'dilux-cloud-storage' ), $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Mark sync as complete (sets state to SYNCED)
	 */
	public static function ajax_mark_sync_complete() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'dilux_cs_admin' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'dilux-cloud-storage' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) );
		}

		try {
			$plugin       = Plugin::get_instance();
			$sync_manager = $plugin->get_sync_manager();

			if ( $sync_manager ) {
				$sync_meta = get_option( 'dilux_cs_sync_meta', array() );

				if ( ! empty( $sync_meta ) ) {
					// ⭐ FIX: Clear DB table (keep synced files for stats, but clear pending)
					require_once DILUX_CS_PLUGIN_DIR . 'includes/class-dilux-db.php';

					// Get final stats before clearing
					$stats = \DiluxWP\CloudStorage\DiluxDB::get_stats();
					Logger::info( '[Dilux Admin] Final sync stats: ' . wp_json_encode( $stats ) );

					// Clear only pending files (keep synced files for history/stats)
					// Actually, we can keep the table as-is for audit purposes
					// DiluxDB::clear_table(); // Don't clear, just mark as completed

					// Update state to SYNCED
					ConfigManager::set_state( \DiluxWP\CloudStorage\Enums\PluginState::SYNCED );

					// Mark sync meta as completed (don't delete, update status)
					$sync_meta['status']   = 'completed';
					$sync_meta['end_time'] = time();
					update_option( 'dilux_cs_sync_meta', $sync_meta, false );

					Logger::info( '[Dilux Admin] Sync marked as complete - state set to SYNCED' );
					wp_send_json_success( 'Sync completed' );
				} else {
					wp_send_json_error( esc_html__( 'No sync metadata found', 'dilux-cloud-storage' ) );
				}
			} else {
				wp_send_json_error( esc_html__( 'Sync manager not available', 'dilux-cloud-storage' ) );
			}
		} catch ( \Exception $e ) {
			Logger::info( '[Dilux Admin] Error completing sync: ' . $e->getMessage() );
			/* translators: %s: error message */
			wp_send_json_error( sprintf( esc_html__( 'Error completing sync: %s', 'dilux-cloud-storage' ), $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Retry failed files (deprecated — kept as a stub).
	 *
	 * Old method removed in favor of the unified sync flow. Retry is now
	 * handled by Plugin::ajax_cs_start_sync with retry_failed=1.
	 */
	public static function ajax_retry_failed() {
		wp_send_json_error( esc_html__( 'This endpoint is deprecated. Use ajax_cs_start_sync with retry_failed parameter instead.', 'dilux-cloud-storage' ) );
	}

	/**
	 * AJAX: Clear failed files list
	 */
	public static function ajax_clear_failed() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'dilux_cs_admin' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'dilux-cloud-storage' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) );
		}

		try {
			ConfigManager::clear_failed_files();

			wp_send_json_success( 'Failed files list cleared successfully' );

		} catch ( \Exception $e ) {
			/* translators: %s: error message */
			wp_send_json_error( sprintf( esc_html__( 'Error clearing failed files: %s', 'dilux-cloud-storage' ), $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Resync all files (re-scan and upload missing files)
	 */
	public static function ajax_resync_all() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'dilux_cs_admin' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'dilux-cloud-storage' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) );
		}

		try {
			// Clear table and start sync from scratch
			require_once DILUX_CS_PLUGIN_DIR . 'includes/class-dilux-db.php';

			\DiluxWP\CloudStorage\DiluxDB::clear_table();

			// Start sync using SyncManager
			$sync_manager = new \DiluxWP\CloudStorage\SyncManager();
			$result       = $sync_manager->start_sync();

			if ( $result['success'] ) {
				ConfigManager::set_state( \DiluxWP\CloudStorage\Enums\PluginState::SYNCING );

				wp_send_json_success(
					array(
						'message'     => __( 'Resync started - scanning files...', 'dilux-cloud-storage' ),
						'total_files' => $result['total_files'] ?? 0,
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => __( 'Failed to start resync', 'dilux-cloud-storage' ),
					)
				);
			}
		} catch ( \Exception $e ) {
			Logger::info( '[Dilux Admin] Resync error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'Error starting resync: ', 'dilux-cloud-storage' ) . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX handler to remove provider configuration
	 */
	public static function ajax_remove_provider() {
		// Check nonce
		check_ajax_referer( 'dilux_cs_admin', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions', 'dilux-cloud-storage' ) ) );
			return;
		}

		try {
			Logger::info( '[Dilux CS] AJAX: Removing provider configuration...' );

			// Deactivate stream wrapper before removing config (prevents inconsistent state)
			CloudStreamWrapper::deactivate_offloading();
			Logger::info( '[Dilux CS] AJAX: Stream wrapper deactivated during provider removal' );

			// ⭐ COMPLETE DELETION: Delete all wp_options entries
			delete_option( 'dilux_cs_config' );
			delete_option( 'dilux_cs_plugin_state' );
			delete_option( 'dilux_cs_sync_meta' );
			delete_option( 'dilux_cs_sync_progress' );
			delete_option( 'dilux_cs_failed_files' );
			delete_option( 'dilux_cs_connection_health' );

			// Clear the MySQL table
			require_once DILUX_CS_PLUGIN_DIR . 'includes/class-dilux-db.php';
			DiluxDB::clear_table();

			// Clean up all transients
			delete_transient( 'dilux_cs_azure_stats' );
			delete_transient( 'dilux_cs_stats' );
			delete_transient( 'dilux_cs_sas_token' );
			delete_transient( 'dilux_cs_connection_test_passed_' . get_current_user_id() );

			Logger::info( '[Dilux CS] AJAX: Provider configuration removed successfully' );

			wp_send_json_success(
				array(
					'message' => 'Cloud storage configuration deleted successfully. Reloading page...',
				)
			);

		} catch ( \Exception $e ) {
			Logger::info( '[Dilux CS] AJAX: Error removing provider: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => 'Error deleting configuration: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX handler to import configuration from JSON
	 */
	public static function ajax_import_config() {
		// Check nonce
		check_ajax_referer( 'dilux_cs_admin', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'dilux-cloud-storage' ) ) );
			return;
		}

		try {
			// Get config JSON from request. The body is a JSON blob that
			// we json_decode + structurally validate below; passing it through
			// sanitize_text_field would corrupt the structure. Nonce was already
			// verified at the top of this AJAX handler.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload validated by json_decode below.
			$config_json = isset( $_POST['config'] ) ? wp_unslash( (string) $_POST['config'] ) : '';

			if ( empty( $config_json ) ) {
				wp_send_json_error( __( 'No configuration data provided', 'dilux-cloud-storage' ) );
				return;
			}

			// Decode JSON
			$config_data = json_decode( $config_json, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wp_send_json_error( __( 'Invalid JSON format: ', 'dilux-cloud-storage' ) . json_last_error_msg() );
				return;
			}

			if ( empty( $config_data ) || ! is_array( $config_data ) ) {
				wp_send_json_error( __( 'Invalid configuration data', 'dilux-cloud-storage' ) );
				return;
			}

			Logger::info( '[Dilux CS] Importing configuration with ' . count( $config_data ) . ' options' );

			// Validate that we have dilux_cs_ options
			$dilux_options_count = 0;
			foreach ( $config_data as $option_name => $option_value ) {
				if ( strpos( $option_name, 'dilux_cs_' ) === 0 ) {
					++$dilux_options_count;
				}
			}

			if ( $dilux_options_count === 0 ) {
				wp_send_json_error( __( 'No valid Dilux Cloud Storage options found in import data', 'dilux-cloud-storage' ) );
				return;
			}

			// Import each option
			$imported_count = 0;
			$errors         = array();

			foreach ( $config_data as $option_name => $option_value ) {
				// Only import dilux_cs_ options
				if ( strpos( $option_name, 'dilux_cs_' ) !== 0 ) {
					continue;
				}

				try {
					// update_option() automatically serializes arrays/objects
					// So we just pass the value directly - no manual serialization needed
					update_option( $option_name, $option_value );
					++$imported_count;

					Logger::info( "[Dilux CS] Imported option: {$option_name}" );

				} catch ( \Exception $e ) {
					$errors[] = "Error importing {$option_name}: " . $e->getMessage();
					Logger::info( "[Dilux CS] Error importing {$option_name}: " . $e->getMessage() );
				}
			}

			if ( $imported_count > 0 ) {
				$message = sprintf(
					/* translators: %d: number of imported configuration options */
					__( 'Successfully imported %d configuration options.', 'dilux-cloud-storage' ),
					$imported_count
				);

				if ( ! empty( $errors ) ) {
					/* translators: %s: comma-separated list of error messages */
					$message .= ' ' . sprintf( __( 'Errors: %s', 'dilux-cloud-storage' ), implode( ', ', $errors ) );
				}

				Logger::info( "[Dilux CS] Configuration import completed: {$imported_count} options imported" );

				wp_send_json_success( $message );
			} else {
				wp_send_json_error( __( 'No options were imported', 'dilux-cloud-storage' ) );
			}
		} catch ( \Exception $e ) {
			Logger::info( '[Dilux CS] AJAX: Error importing configuration: ' . $e->getMessage() );
			wp_send_json_error( __( 'Error importing configuration: ', 'dilux-cloud-storage' ) . $e->getMessage() );
		}
	}
}

// Initialize admin
Admin::init();
