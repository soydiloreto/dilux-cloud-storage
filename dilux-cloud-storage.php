<?php
/**
 * Plugin Name: Dilux Cloud Storage
 * Description: Offload WordPress media files to Azure Blob Storage or Dilux One Cloud — complete replacement for /uploads/ directory using stream wrappers.
 * Version: 1.1.0
 * Author: Pablo Ariel Di Loreto
 * Author URI: https://pablodiloreto.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dilux-cloud-storage
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 *
 * @package DiluxWP\CloudStorage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'DILUX_CS_VERSION', '1.1.0' );
define( 'DILUX_CS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DILUX_CS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DILUX_CS_PLUGIN_FILE', __FILE__ );

// Load enhanced autoloader
require_once DILUX_CS_PLUGIN_DIR . 'includes/enhanced-autoloader.php';

use DiluxWP\CloudStorage\Plugin;

global $dilux_cs_plugin;

/**
 * Initialize the enhanced plugin.
 *
 * Translations for plugins hosted on wordpress.org are loaded automatically
 * by WordPress since 4.6, so we no longer call load_plugin_textdomain().
 *
 * @return void
 */
function dilux_cs_init() {
	global $dilux_cs_plugin;

	$dilux_cs_plugin = Plugin::get_instance();
	$dilux_cs_plugin->init();
}
add_action( 'plugins_loaded', 'dilux_cs_init', 10 );

/**
 * Plugin activation hook.
 */
register_activation_hook(
	__FILE__,
	function () {
		\DiluxWP\CloudStorage\Logger::log( '[Dilux WP Cloud Storage] Activation hook fired.', 'info', true );
		Plugin::activate();
	}
);

/**
 * Plugin deactivation hook.
 */
register_deactivation_hook(
	__FILE__,
	function () {
		\DiluxWP\CloudStorage\Logger::log( '[Dilux WP Cloud Storage] Deactivation hook fired.', 'info', true );
		Plugin::deactivate();
	}
);
