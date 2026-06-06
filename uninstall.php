<?php
/**
 * Dr. Debug — Uninstall
 *
 * Removes all plugin data when the plugin is deleted via the
 * WordPress Plugins screen. This file is called automatically
 * by WordPress when the plugin is uninstalled.
 *
 * @package Dr_Debug
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ─── Remove database tables ───────────────────────────────────────
global $wpdb;

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}drdbg_occurrences" );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}drdbg_errors" );

// ─── Remove options ───────────────────────────────────────────────
delete_option( 'drdbg_settings' );
delete_option( 'drdbg_db_version' );

// ─── Remove MU-plugin loader ──────────────────────────────────────
$loader = WP_CONTENT_DIR . '/mu-plugins/drdbg-loader.php';
if ( file_exists( $loader ) ) {
	unlink( $loader );
}

// ─── Unschedule cron jobs ─────────────────────────────────────────
wp_clear_scheduled_hook( 'drdbg_daily_cleanup' );
wp_clear_scheduled_hook( 'drdbg_log_rotation' );
