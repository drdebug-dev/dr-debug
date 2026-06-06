<?php
/**
 * Dr. Debug — Constants and Enums
 *
 * Defines all constants used throughout the plugin including
 * severity levels, status values, source types, context types,
 * and PHP E_* error type constants. Also provides helper functions
 * for mapping and labelling.
 *
 * @package Dr_Debug
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

// ─── Plugin Version ────────────────────────────────────────────────

if ( ! defined( 'DRDBG_VERSION' ) ) {
        define( 'DRDBG_VERSION', '1.0.0' );
}

// ─── Database Schema Version ───────────────────────────────────────

if ( ! defined( 'DRDBG_DB_VERSION' ) ) {
        define( 'DRDBG_DB_VERSION', '1.0.0' );
}

// ─── Plugin Path / URL ────────────────────────────────────────────

if ( ! defined( 'DRDBG_PLUGIN_DIR' ) ) {
        define( 'DRDBG_PLUGIN_DIR', plugin_dir_path( dirname( __FILE__ ) ) );
}
if ( ! defined( 'DRDBG_PLUGIN_URL' ) ) {
        define( 'DRDBG_PLUGIN_URL', plugin_dir_url( dirname( __FILE__ ) ) );
}

// ─── Severity Levels ───────────────────────────────────────────────

if ( ! defined( 'DRDBG_SEVERITY_INFO' ) ) {
        define( 'DRDBG_SEVERITY_INFO', 1 );
}
if ( ! defined( 'DRDBG_SEVERITY_NOTICE' ) ) {
        define( 'DRDBG_SEVERITY_NOTICE', 2 );
}
if ( ! defined( 'DRDBG_SEVERITY_WARNING' ) ) {
        define( 'DRDBG_SEVERITY_WARNING', 3 );
}
if ( ! defined( 'DRDBG_SEVERITY_ERROR' ) ) {
        define( 'DRDBG_SEVERITY_ERROR', 4 );
}
if ( ! defined( 'DRDBG_SEVERITY_FATAL' ) ) {
        define( 'DRDBG_SEVERITY_FATAL', 5 );
}

// ─── Status Values ─────────────────────────────────────────────────

if ( ! defined( 'DRDBG_STATUS_NEW' ) ) {
        define( 'DRDBG_STATUS_NEW', 0 );
}
if ( ! defined( 'DRDBG_STATUS_SEEN' ) ) {
        define( 'DRDBG_STATUS_SEEN', 1 );
}
if ( ! defined( 'DRDBG_STATUS_RESOLVED' ) ) {
        define( 'DRDBG_STATUS_RESOLVED', 2 );
}
if ( ! defined( 'DRDBG_STATUS_MUTED' ) ) {
        define( 'DRDBG_STATUS_MUTED', 3 );
}

// ─── Source Types ──────────────────────────────────────────────────

if ( ! defined( 'DRDBG_SOURCE_UNKNOWN' ) ) {
        define( 'DRDBG_SOURCE_UNKNOWN', 0 );
}
if ( ! defined( 'DRDBG_SOURCE_PLUGIN' ) ) {
        define( 'DRDBG_SOURCE_PLUGIN', 1 );
}
if ( ! defined( 'DRDBG_SOURCE_THEME' ) ) {
        define( 'DRDBG_SOURCE_THEME', 2 );
}
if ( ! defined( 'DRDBG_SOURCE_CORE' ) ) {
        define( 'DRDBG_SOURCE_CORE', 3 );
}

// ─── Context Types ─────────────────────────────────────────────────

if ( ! defined( 'DRDBG_CONTEXT_WEB' ) ) {
        define( 'DRDBG_CONTEXT_WEB', 0 );
}
if ( ! defined( 'DRDBG_CONTEXT_AJAX' ) ) {
        define( 'DRDBG_CONTEXT_AJAX', 1 );
}
if ( ! defined( 'DRDBG_CONTEXT_REST' ) ) {
        define( 'DRDBG_CONTEXT_REST', 2 );
}
if ( ! defined( 'DRDBG_CONTEXT_CRON' ) ) {
        define( 'DRDBG_CONTEXT_CRON', 3 );
}
if ( ! defined( 'DRDBG_CONTEXT_CLI' ) ) {
        define( 'DRDBG_CONTEXT_CLI', 4 );
}

// ─── PHP E_* Error Type Constants ──────────────────────────────────
// Redefined for clarity and safe usage outside the normal PHP error
// handling context. Values match the native PHP E_* constants.

if ( ! defined( 'DRDBG_E_ERROR' ) ) {
        define( 'DRDBG_E_ERROR', 1 );
}
if ( ! defined( 'DRDBG_E_WARNING' ) ) {
        define( 'DRDBG_E_WARNING', 2 );
}
if ( ! defined( 'DRDBG_E_PARSE' ) ) {
        define( 'DRDBG_E_PARSE', 4 );
}
if ( ! defined( 'DRDBG_E_NOTICE' ) ) {
        define( 'DRDBG_E_NOTICE', 8 );
}
if ( ! defined( 'DRDBG_E_CORE_ERROR' ) ) {
        define( 'DRDBG_E_CORE_ERROR', 16 );
}
if ( ! defined( 'DRDBG_E_CORE_WARNING' ) ) {
        define( 'DRDBG_E_CORE_WARNING', 32 );
}
if ( ! defined( 'DRDBG_E_COMPILE_ERROR' ) ) {
        define( 'DRDBG_E_COMPILE_ERROR', 64 );
}
if ( ! defined( 'DRDBG_E_COMPILE_WARNING' ) ) {
        define( 'DRDBG_E_COMPILE_WARNING', 128 );
}
if ( ! defined( 'DRDBG_E_USER_ERROR' ) ) {
        define( 'DRDBG_E_USER_ERROR', 256 );
}
if ( ! defined( 'DRDBG_E_USER_WARNING' ) ) {
        define( 'DRDBG_E_USER_WARNING', 512 );
}
if ( ! defined( 'DRDBG_E_USER_NOTICE' ) ) {
        define( 'DRDBG_E_USER_NOTICE', 1024 );
}
if ( ! defined( 'DRDBG_E_STRICT' ) ) {
        define( 'DRDBG_E_STRICT', 2048 );
}
if ( ! defined( 'DRDBG_E_RECOVERABLE_ERROR' ) ) {
        define( 'DRDBG_E_RECOVERABLE_ERROR', 4096 );
}
if ( ! defined( 'DRDBG_E_DEPRECATED' ) ) {
        define( 'DRDBG_E_DEPRECATED', 8192 );
}
if ( ! defined( 'DRDBG_E_USER_DEPRECATED' ) ) {
        define( 'DRDBG_E_USER_DEPRECATED', 16384 );
}

// ─── Helper: Map PHP E_* type to severity level ────────────────────

if ( ! function_exists( 'drdbg_error_type_to_severity' ) ) {
        /**
         * Map a PHP E_* error type constant to a Dr. Debug severity level.
         *
         * Fatal types (E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR,
         * E_RECOVERABLE_ERROR, E_PARSE) → 5 (Fatal)
         *
         * Warning types (E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING,
         * E_USER_WARNING) → 4 (Error)
         *
         * Deprecation types (E_DEPRECATED, E_USER_DEPRECATED, E_STRICT) → 3 (Warning)
         *
         * Notice types (E_NOTICE, E_USER_NOTICE) → 2 (Notice)
         *
         * Everything else → 1 (Info)
         *
         * @since 1.0.0
         *
         * @param int $error_type A PHP E_* constant value.
         * @return int Severity level (1-5).
         */
        function drdbg_error_type_to_severity( $error_type ) {
                // Fatal types → 5
                if ( in_array(
                        $error_type,
                        array(
                                DRDBG_E_ERROR,
                                DRDBG_E_CORE_ERROR,
                                DRDBG_E_COMPILE_ERROR,
                                DRDBG_E_USER_ERROR,
                                DRDBG_E_RECOVERABLE_ERROR,
                                DRDBG_E_PARSE,
                        ),
                        true
                ) ) {
                        return DRDBG_SEVERITY_FATAL;
                }

                // Warning types → 4
                if ( in_array(
                        $error_type,
                        array(
                                DRDBG_E_WARNING,
                                DRDBG_E_CORE_WARNING,
                                DRDBG_E_COMPILE_WARNING,
                                DRDBG_E_USER_WARNING,
                        ),
                        true
                ) ) {
                        return DRDBG_SEVERITY_ERROR;
                }

                // Deprecation types → 3
                if ( in_array(
                        $error_type,
                        array(
                                DRDBG_E_DEPRECATED,
                                DRDBG_E_USER_DEPRECATED,
                                DRDBG_E_STRICT,
                        ),
                        true
                ) ) {
                        return DRDBG_SEVERITY_WARNING;
                }

                // Notice types → 2
                if ( in_array(
                        $error_type,
                        array(
                                DRDBG_E_NOTICE,
                                DRDBG_E_USER_NOTICE,
                        ),
                        true
                ) ) {
                        return DRDBG_SEVERITY_NOTICE;
                }

                // Everything else → 1
                return DRDBG_SEVERITY_INFO;
        }
}

// ─── Label Mappings ────────────────────────────────────────────────

if ( ! function_exists( 'drdbg_get_severity_label' ) ) {
        function drdbg_get_severity_label( $level ) {
                $labels = array(
                        DRDBG_SEVERITY_INFO    => 'Info',
                        DRDBG_SEVERITY_NOTICE  => 'Notice',
                        DRDBG_SEVERITY_WARNING => 'Warning',
                        DRDBG_SEVERITY_ERROR   => 'Error',
                        DRDBG_SEVERITY_FATAL   => 'Fatal',
                );
                return isset( $labels[ $level ] ) ? $labels[ $level ] : 'Unknown';
        }
}

if ( ! function_exists( 'drdbg_get_status_label' ) ) {
        function drdbg_get_status_label( $status ) {
                $labels = array(
                        DRDBG_STATUS_NEW      => 'New',
                        DRDBG_STATUS_SEEN     => 'Seen',
                        DRDBG_STATUS_RESOLVED => 'Resolved',
                        DRDBG_STATUS_MUTED    => 'Muted',
                );
                return isset( $labels[ $status ] ) ? $labels[ $status ] : 'Unknown';
        }
}

if ( ! function_exists( 'drdbg_get_source_type_label' ) ) {
        function drdbg_get_source_type_label( $type ) {
                $labels = array(
                        DRDBG_SOURCE_UNKNOWN => 'Unknown',
                        DRDBG_SOURCE_PLUGIN  => 'Plugin',
                        DRDBG_SOURCE_THEME   => 'Theme',
                        DRDBG_SOURCE_CORE    => 'Core',
                );
                return isset( $labels[ $type ] ) ? $labels[ $type ] : 'Unknown';
        }
}

if ( ! function_exists( 'drdbg_get_error_type_label' ) ) {
        function drdbg_get_error_type_label( $error_type ) {
                $labels = array(
                        DRDBG_E_ERROR             => 'E_ERROR',
                        DRDBG_E_WARNING           => 'E_WARNING',
                        DRDBG_E_PARSE             => 'E_PARSE',
                        DRDBG_E_NOTICE            => 'E_NOTICE',
                        DRDBG_E_CORE_ERROR        => 'E_CORE_ERROR',
                        DRDBG_E_CORE_WARNING      => 'E_CORE_WARNING',
                        DRDBG_E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
                        DRDBG_E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
                        DRDBG_E_USER_ERROR        => 'E_USER_ERROR',
                        DRDBG_E_USER_WARNING      => 'E_USER_WARNING',
                        DRDBG_E_USER_NOTICE       => 'E_USER_NOTICE',
                        DRDBG_E_STRICT            => 'E_STRICT',
                        DRDBG_E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
                        DRDBG_E_DEPRECATED        => 'E_DEPRECATED',
                        DRDBG_E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
                );
                return isset( $labels[ $error_type ] ) ? $labels[ $error_type ] : 'E_UNKNOWN';
        }
}
