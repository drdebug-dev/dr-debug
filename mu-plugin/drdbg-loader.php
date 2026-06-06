<?php
/**
 * Plugin Name: Dr. Debug Loader
 * Description: Early error capture loader for Dr. Debug
 * Version: 1.0.0
 *
 * This file should be placed in wp-content/mu-plugins/
 * It loads before regular plugins to capture errors during plugin loading.
 *
 * @package DrDebug
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Kill switch ─────────────────────────────────────────────────
if ( defined( 'DRDBG_DISABLE' ) && DRDBG_DISABLE === true ) return;

/**
 * Locate the main Dr. Debug plugin file regardless of install folder name.
 *
 * @return string|false Absolute path to dr-debug.php, or false.
 */
function drdbg_mu_find_main_file() {
	static $cached = null;
	if ( $cached !== null ) {
		return $cached ? $cached : false;
	}

	$cached = false;
	if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
		return false;
	}

	$preferred = WP_PLUGIN_DIR . '/dr-debug/dr-debug.php';
	if ( is_readable( $preferred ) ) {
		$cached = $preferred;
		return $cached;
	}

	$matches = glob( WP_PLUGIN_DIR . '/*/dr-debug.php' );
	if ( is_array( $matches ) ) {
		foreach ( $matches as $candidate ) {
			if ( is_readable( $candidate ) ) {
				$cached = $candidate;
				return $cached;
			}
		}
	}

	return false;
}

// ─── Verify the main plugin is still present ─────────────────────
$drdbg_main_file = drdbg_mu_find_main_file();
if ( ! $drdbg_main_file ) {
	return;
}

// ─── Load dependencies in order ─────────────────────────────────
$drdbg_includes = dirname( $drdbg_main_file ) . '/includes/';

$required_files = [
	'class-drdbg-constants.php',
	'class-drdbg-fingerprint.php',
	'class-drdbg-redaction.php',
	'class-drdbg-source.php',
	'class-drdbg-db.php',
	'class-drdbg-capture.php',
];

foreach ( $required_files as $file ) {
	$path = $drdbg_includes . $file;
	if ( ! file_exists( $path ) ) {
		return;
	}
	require_once $path;
}

// ─── Register handlers early ─────────────────────────────────────
$capture = Drdbg_Capture::get_instance();
$capture->register_handlers();

// ─── Hide errors from non-admins on frontend ─────────────────────
add_action( 'init', array( $capture, 'hide_frontend_errors' ) );

// ─── Verify plugin is actually active (after WordPress loads) ─────
add_action( 'plugins_loaded', function() use ( $drdbg_main_file ) {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! is_plugin_active( plugin_basename( $drdbg_main_file ) ) ) {
		restore_error_handler();
		restore_exception_handler();
	}
}, 0 );
