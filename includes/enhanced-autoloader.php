<?php
/**
 * Autoloader for Dilux WP Cloud Storage
 *
 * Loads plugin classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader function
 */

function dilux_cs_autoloader( $class_name ) {
	// Only autoload our classes
	if ( strpos( $class_name, 'DiluxWP\\CloudStorage\\' ) !== 0 ) {
		return;
	}

	// Remove namespace prefix
	$class_name = str_replace( 'DiluxWP\\CloudStorage\\', '', $class_name );

	// Class to file mappings
	$class_mappings = array(
		// Core classes
		'ConfigManager'                           => 'includes/class-dilux-config-manager.php',
		'SyncManager'                             => 'includes/class-dilux-sync-manager.php',
		'CloudStreamWrapper'                      => 'includes/class-dilux-cloud-stream-wrapper.php',
		'Logger'                                  => 'includes/class-dilux-logger.php',
		'Crypto'                                  => 'includes/class-dilux-crypto.php',
		'Admin'                                   => 'includes/class-dilux-admin.php',
		'Plugin'                                  => 'includes/class-dilux-plugin-enhanced.php',
		'DiluxValidationHelper'                   => 'includes/class-dilux-validation-helper.php',
		'DiluxMimeHelper'                         => 'includes/class-dilux-mime-helper.php',

		// Image Editors
		'Dilux_Image_Editor_Imagick'              => 'includes/class-dilux-image-editor-imagick.php',
		'Dilux_Image_Editor_GD'                   => 'includes/class-dilux-image-editor-gd.php',

		// Enums
		'Enums\\PluginState'                      => 'includes/Enums/class-plugin-state.php',

		// Interfaces
		'Interfaces\\CloudStorageClientInterface' => 'includes/interfaces/interface-cloud-storage-client.php',

		// Providers
		'Providers\\AzureProvider'                => 'includes/providers/class-azure-provider.php',
		'Providers\\DiluxOneCloudProvider'        => 'includes/providers/class-diluxone-cloud-provider.php',

		// Factories
		'Factories\\CloudStorageFactory'          => 'includes/factories/class-cloud-storage-factory.php',

		// DTOs (Data Transfer Objects)
		'DTOs\\OperationResult'                   => 'includes/DTOs/OperationResult.php',
		'DTOs\\UploadResult'                      => 'includes/DTOs/UploadResult.php',
		'DTOs\\ConnectionResult'                  => 'includes/DTOs/ConnectionResult.php',
		'DTOs\\FileInfo'                          => 'includes/DTOs/FileInfo.php',
		'DTOs\\AzureConfig'                       => 'includes/DTOs/AzureConfig.php',
		'DTOs\\PluginConfig'                      => 'includes/DTOs/PluginConfig.php',
		'DTOs\\ProviderConfig'                    => 'includes/DTOs/ProviderConfig.php',
		'DTOs\\PluginSettings'                    => 'includes/DTOs/PluginSettings.php',
		'DTOs\\SyncProgress'                      => 'includes/DTOs/SyncProgress.php',
		'DTOs\\SyncFilter'                        => 'includes/DTOs/SyncFilter.php',
		'DTOs\\SyncResult'                        => 'includes/DTOs/SyncResult.php',

		// Enums
		'Enums\\SyncStatus'                       => 'includes/Enums/SyncStatus.php',
	);

	// Check if we have a mapping for this class
	if ( isset( $class_mappings[ $class_name ] ) ) {
		$file_path = DILUX_CS_PLUGIN_DIR . $class_mappings[ $class_name ];

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
			// error_log('[Dilux Autoloader] Loaded: ' . $class_name . ' from ' . $file_path);
			return;
		}
	}

	// Fallback: try to auto-generate file path
	$potential_paths = array(
		'includes/class-dilux-' . strtolower( str_replace( '\\', '-', $class_name ) ) . '.php',
		'includes/class-dilux-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php',
	);

	foreach ( $potential_paths as $path ) {
		$full_path = DILUX_CS_PLUGIN_DIR . $path;
		if ( file_exists( $full_path ) ) {
			require_once $full_path;
			return;
		}
	}
}

// Register the autoloader
spl_autoload_register( 'dilux_cs_autoloader' );
