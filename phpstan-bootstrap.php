<?php
/**
 * PHPStan analysis bootstrap.
 *
 * Defines plugin constants that are normally created at runtime by the
 * main plugin file (dilux-cloud-storage.php). PHPStan analyzes the
 * codebase statically without executing anything, so it never sees the
 * `define()` calls there. Without these stubs, every reference to
 * `DILUX_CS_PLUGIN_DIR` and friends produces "Constant not found".
 *
 * This file is referenced from phpstan.neon's `bootstrapFiles:` list.
 * It is excluded from the wp.org deploy via .distignore. It is NOT
 * loaded at plugin runtime — only by PHPStan during analysis.
 *
 * @package DiluxWP\CloudStorage
 */

if ( ! defined( 'DILUX_CS_VERSION' ) ) {
	define( 'DILUX_CS_VERSION', '0.0.0-phpstan-stub' );
}
if ( ! defined( 'DILUX_CS_PLUGIN_DIR' ) ) {
	define( 'DILUX_CS_PLUGIN_DIR', __DIR__ . '/' );
}
if ( ! defined( 'DILUX_CS_PLUGIN_URL' ) ) {
	define( 'DILUX_CS_PLUGIN_URL', 'https://example.test/wp-content/plugins/dilux-cloud-storage/' );
}
if ( ! defined( 'DILUX_CS_PLUGIN_FILE' ) ) {
	define( 'DILUX_CS_PLUGIN_FILE', __DIR__ . '/dilux-cloud-storage.php' );
}

// Optional development-mode constants referenced by some code paths.
if ( ! defined( 'DILUX_DEV_MODE' ) ) {
	define( 'DILUX_DEV_MODE', false );
}
if ( ! defined( 'DILUX_VERBOSE_LOGGING' ) ) {
	define( 'DILUX_VERBOSE_LOGGING', false );
}
if ( ! defined( 'DILUX_API_URL' ) ) {
	define( 'DILUX_API_URL', 'https://api.diluxone.com/cloud-storage-wp/v1' );
}
