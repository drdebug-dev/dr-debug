<?php
/**
 * Plugin Name: Dr. Debug - Smart Error Log Viewer
 * Plugin URI:  https://github.com/dr-debug/dr-debug
 * Description: Smart error log viewer for WordPress with fingerprint-based grouping, secret redaction, and a modern dashboard.
 * Version:     1.0.0
 * Author:      Dr. Debug
 * Author URI:  https://github.com/dr-debug
 * License:     GPL-2.0+
 * Text Domain: dr-debug
 * Domain Path: /languages
 * Network:     true
 *
 * Dr. Debug — Smart Error Log Viewer
 * Copyright (C) 2025 Dr. Debug
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Plugin constants (only if not already defined by MU-plugin) ──
if ( ! defined( 'DRDBG_PLUGIN_DIR' ) ) {
        define( 'DRDBG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'DRDBG_PLUGIN_URL' ) ) {
        define( 'DRDBG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'DRDBG_VERSION' ) ) {
        define( 'DRDBG_VERSION', '1.0.0' );
}
define( 'DRDBG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ─── Autoload includes ────────────────────────────────────────────
$drdbg_includes = DRDBG_PLUGIN_DIR . 'includes/';

$drdbg_required = array(
        'class-drdbg-constants.php',
        'class-drdbg-fingerprint.php',
        'class-drdbg-redaction.php',
        'class-drdbg-source.php',
        'class-drdbg-db.php',
        'class-drdbg-capture.php',
        'class-drdbg-settings.php',
        'class-drdbg-cleanup.php',
        'class-drdbg-admin-api.php',
        'class-drdbg-activator.php',
);

// Admin class (separate directory).
$drdbg_admin_file = DRDBG_PLUGIN_DIR . 'admin/class-drdbg-admin.php';
if ( file_exists( $drdbg_admin_file ) ) {
        require_once $drdbg_admin_file;
}

foreach ( $drdbg_required as $file ) {
        $path = $drdbg_includes . $file;
        if ( file_exists( $path ) ) {
                require_once $path;
        }
}

// ─── Activation / Deactivation hooks ─────────────────────────────
register_activation_hook( __FILE__, array( 'Drdbg_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Drdbg_Activator', 'deactivate' ) );

// ─── Check for schema updates on admin_init ──────────────────────
add_action( 'admin_init', function() {
        $db = Drdbg_DB::get_instance();
        $db->maybe_update_schema();
} );

// ─── Bootstrap the capture engine ────────────────────────────────
// The MU-plugin loader will have already registered handlers if it
// was installed.  If not (first install before activation), we
// register them here as a fallback.
add_action( 'plugins_loaded', function() {
        if ( ! defined( 'DRDBG_DISABLE' ) || DRDBG_DISABLE !== true ) {
                $capture = Drdbg_Capture::get_instance();
                // Note: register_handlers() can be called multiple times safely;
                // PHP replaces the previous handler each time.
                $capture->register_handlers();
        }
}, 1 ); // Priority 1 — as early as possible during plugins_loaded

// ─── Admin UI ─────────────────────────────────────────────────────
if ( is_admin() ) {
        // Initialise the admin class (menu, assets, admin bar).
        if ( class_exists( 'Drdbg_Admin' ) ) {
                Drdbg_Admin::get_instance()->init();
        }

        // Register AJAX endpoints.
        add_action( 'admin_init', function() {
                $api = Drdbg_Admin_API::get_instance();
                $api->register();
        } );

        // Register WordPress settings API.
        add_action( 'admin_init', function() {
                $settings = Drdbg_Settings::get_instance();
                $settings->register_settings();
        } );
}

// ─── REST API routes ──────────────────────────────────────────────
add_action( 'rest_api_init', function() {
        $namespace = 'drdbg/v1';

        // Stats
        register_rest_route( $namespace, '/stats', array(
                'methods'             => 'GET',
                'callback'            => 'drdbg_rest_get_stats',
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // Errors list
        register_rest_route( $namespace, '/errors', array(
                'methods'             => 'GET',
                'callback'            => 'drdbg_rest_get_errors',
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // Single error
        register_rest_route( $namespace, '/errors/(?P<id>\d+)', array(
                'methods'             => 'GET',
                'callback'            => 'drdbg_rest_get_error',
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // Update error status
        register_rest_route( $namespace, '/errors/(?P<id>\d+)', array(
                'methods'             => 'PATCH',
                'callback'            => 'drdbg_rest_update_error',
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // Delete error
        register_rest_route( $namespace, '/errors/(?P<id>\d+)', array(
                'methods'             => 'DELETE',
                'callback'            => 'drdbg_rest_delete_error',
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // Occurrences
        register_rest_route( $namespace, '/errors/(?P<id>\d+)/occurrences', array(
                'methods'             => 'GET',
                'callback'            => 'drdbg_rest_get_occurrences',
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // Batch status update
        register_rest_route( $namespace, '/errors/batch', array(
                'methods'             => 'POST',
                'callback'            => 'drdbg_rest_batch_update',
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // Settings
        register_rest_route( $namespace, '/settings', array(
                'methods'             => 'GET',
                'callback'            => 'drdbg_rest_get_settings',
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        register_rest_route( $namespace, '/settings', array(
                'methods'             => 'POST',
                'callback'            => 'drdbg_rest_save_settings',
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // Cleanup
        register_rest_route( $namespace, '/cleanup', array(
                'methods'             => 'POST',
                'callback'            => 'drdbg_rest_cleanup',
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // Seed demo data
        register_rest_route( $namespace, '/seed', array(
                'methods'             => 'POST',
                'callback'            => 'drdbg_rest_seed',
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );
} );

// ─── REST API Callbacks ───────────────────────────────────────────

function drdbg_rest_get_stats() {
        $db = Drdbg_DB::get_instance();
        return rest_ensure_response( $db->get_stats() );
}

function drdbg_rest_get_errors( WP_REST_Request $request ) {
        $db = Drdbg_DB::get_instance();
        return rest_ensure_response( $db->get_errors( array(
                'severity'    => $request->get_param( 'severity' ),
                'status'      => $request->get_param( 'status' ),
                'source_type' => $request->get_param( 'source_type' ),
                'source_slug' => $request->get_param( 'source_slug' ),
                'search'      => $request->get_param( 'search' ),
                'page'        => $request->get_param( 'page' ) ? (int) $request->get_param( 'page' ) : 1,
                'per_page'    => $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 20,
                'orderby'     => $request->get_param( 'orderby' ) ?: 'last_seen',
                'order'       => $request->get_param( 'order' ) ?: 'DESC',
        ) ) );
}

function drdbg_rest_get_error( WP_REST_Request $request ) {
        $db    = Drdbg_DB::get_instance();
        $error = $db->get_error( (int) $request['id'] );
        if ( ! $error ) {
                return new WP_Error( 'not_found', 'Error not found', array( 'status' => 404 ) );
        }
        return rest_ensure_response( $error );
}

function drdbg_rest_update_error( WP_REST_Request $request ) {
        $db     = Drdbg_DB::get_instance();
        $status = $request->get_param( 'status' );
        if ( $status === null ) {
                return new WP_Error( 'missing_param', 'status is required', array( 'status' => 400 ) );
        }
        $result = $db->update_status( (int) $request['id'], (int) $status );
        return rest_ensure_response( array( 'success' => $result ) );
}

function drdbg_rest_delete_error( WP_REST_Request $request ) {
        $db     = Drdbg_DB::get_instance();
        $result = $db->delete_errors( array( (int) $request['id'] ) );
        return rest_ensure_response( array( 'success' => (bool) $result ) );
}

function drdbg_rest_get_occurrences( WP_REST_Request $request ) {
        $db   = Drdbg_DB::get_instance();
        $page = $request->get_param( 'page' ) ? (int) $request->get_param( 'page' ) : 1;
        $data = $db->get_error( (int) $request['id'], $page );
        if ( ! $data ) {
                return new WP_Error( 'not_found', 'Error not found', array( 'status' => 404 ) );
        }
        return rest_ensure_response( $data );
}

function drdbg_rest_batch_update( WP_REST_Request $request ) {
        $db     = Drdbg_DB::get_instance();
        $ids    = $request->get_param( 'ids' );
        $status = $request->get_param( 'status' );
        if ( empty( $ids ) || $status === null ) {
                return new WP_Error( 'missing_param', 'ids and status are required', array( 'status' => 400 ) );
        }
        $count = $db->batch_update_status( $ids, (int) $status );
        return rest_ensure_response( array( 'updated' => $count ) );
}

function drdbg_rest_get_settings() {
        $settings = Drdbg_Settings::get_instance();
        return rest_ensure_response( $settings->get_all() );
}

function drdbg_rest_save_settings( WP_REST_Request $request ) {
        $settings = Drdbg_Settings::get_instance();
        $input    = array(
                'cleanup_days'      => $request->get_param( 'cleanup_days' ),
                'max_occurrences'   => $request->get_param( 'max_occurrences' ),
                'min_severity'      => $request->get_param( 'min_severity' ),
                'redaction_enabled' => $request->get_param( 'redaction_enabled' ),
                'hide_frontend'     => $request->get_param( 'hide_frontend' ),
        );
        $result = $settings->update( $input );
        return rest_ensure_response( $result );
}

function drdbg_rest_cleanup() {
        $settings    = Drdbg_Settings::get_instance()->get_all();
        $cleanup     = Drdbg_Cleanup::get_instance();
        $deleted     = $cleanup->cleanup_by_age( $settings['cleanup_days'] );
        return rest_ensure_response( array( 'deleted' => $deleted ) );
}

function drdbg_rest_seed() {
        $api = Drdbg_Admin_API::get_instance();
        // Use the seed method from the admin API
        ob_start();
        $_POST['nonce'] = wp_create_nonce( 'drdbg_admin_nonce' );
        $api->seed_demo();
        $response = json_decode( ob_get_clean(), true );
        return rest_ensure_response( isset( $response['data'] ) ? $response['data'] : array( 'created' => 0 ) );
}

// ─── Cron callbacks ───────────────────────────────────────────────
add_action( 'drdbg_daily_cleanup', array( Drdbg_Cleanup::get_instance(), 'run_daily_cleanup' ) );
add_action( 'drdbg_log_rotation', array( Drdbg_Cleanup::get_instance(), 'run_log_rotation' ) );
