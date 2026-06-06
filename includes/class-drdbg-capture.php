<?php
/**
 * Dr. Debug — Error Capture Engine
 *
 * Implements three capture mechanisms:
 *   1. set_error_handler()   — warnings, notices, deprecated calls
 *   2. set_exception_handler() — uncaught exceptions
 *   3. register_shutdown_function() — fatal errors (E_ERROR, E_PARSE, …)
 *
 * Errors are buffered in memory during the request and flushed to the
 * database at shutdown (batch write).  A reentrancy guard prevents
 * infinite loops if the handler itself triggers an error.
 *
 * IMPORTANT design constraints:
 *   - handle_error() returns false to preserve WordPress debug.log writing.
 *   - All handler methods have try/catch — never let our handler crash the site.
 *   - Fatal errors have NO stack trace — this is a PHP limitation.
 *   - IP addresses are SHA-256 hashed, never stored raw.
 *   - Backtraces use DEBUG_BACKTRACE_IGNORE_ARGS (no sensitive function arguments).
 *   - The DRDBG_DISABLE kill switch instantly disables all capture.
 *
 * @package DrDebug
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Drdbg_Capture {

        /** @var self|null Singleton instance */
        private static $instance = null;

        /**
         * In-memory buffer for batch writing.
         *
         * Keyed by fingerprint so that duplicate errors within the same
         * request are only recorded once (dedup).
         *
         * @var array
         */
        private $buffer = [];

        /**
         * Reentrancy guard.
         *
         * Prevents infinite loops when our handler triggers another error
         * (e.g. during database writes or option reads).
         *
         * @var bool
         */
        private static $in_handler = false;

        // ─── Singleton ────────────────────────────────────────────────

        /**
         * Private constructor — use get_instance().
         */
        private function __construct() {}

        /**
         * Get the singleton instance.
         *
         * @return self
         */
        public static function get_instance() {
                if ( self::$instance === null ) {
                        self::$instance = new self();
                }
                return self::$instance;
        }

        // ─── Kill Switch ──────────────────────────────────────────────

        /**
         * Check the kill switch.
         *
         * When the constant DRDBG_DISABLE is defined and true, all capture
         * is immediately disabled — no handlers fire, no buffer is flushed.
         *
         * @return bool
         */
        private static function is_disabled() {
                return defined( 'DRDBG_DISABLE' ) && DRDBG_DISABLE === true;
        }

        // ─── Handler Registration ─────────────────────────────────────

        /**
         * Register all error handlers.
         *
         * Called from the MU-plugin loader (early loading) so that errors
         * during plugin loading are captured.
         */
        public function register_handlers() {
                if ( self::is_disabled() ) return;

                set_error_handler( [ $this, 'handle_error' ] );
                set_exception_handler( [ $this, 'handle_exception' ] );
                register_shutdown_function( [ $this, 'handle_shutdown' ] );
        }

        // ─── 1. Error Handler (warnings, notices, deprecated) ─────────

        /**
         * Custom error handler for warnings, notices, deprecated calls, etc.
         *
         * MUST return false at the end so that WordPress's own error logging
         * (WP_DEBUG_LOG → wp-content/debug.log) continues to work.
         *
         * Also respects the @ operator and the current error_reporting level.
         *
         * @param int    $errno   One of the E_* constants.
         * @param string $errstr  Error message.
         * @param string $errfile File where the error occurred.
         * @param int    $errline Line number.
         * @return false  Always returns false to preserve debug.log.
         */
        public function handle_error( $errno, $errstr, $errfile, $errline ) {
                // Reentrancy guard — prevent infinite loops
                if ( self::$in_handler ) return false;
                self::$in_handler = true;

                try {
                        // Respect @ operator and error_reporting level.
                        // When @ is used, error_reporting() returns 0 (or the suppressed level).
                        if ( ! ( error_reporting() & $errno ) ) {
                                self::$in_handler = false;
                                return false;
                        }

                        // Map PHP E_* to Dr. Debug severity
                        $severity = drdbg_error_type_to_severity( $errno );

                        // Check minimum severity from settings
                        $settings     = get_option( 'drdbg_settings', [] );
                        $min_severity = isset( $settings['min_severity'] ) ? (int) $settings['min_severity'] : 2;
                        if ( $severity < $min_severity ) {
                                self::$in_handler = false;
                                return false;
                        }

                        // Get backtrace (without function arguments for security)
                        $trace     = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
                        $trace_str = $this->format_backtrace( $trace );

                        $this->buffer_error( [
                                'error_type'     => $errno,
                                'severity_level' => $severity,
                                'message'        => $errstr,
                                'file'           => $errfile,
                                'line'           => $errline,
                                'stack_trace'    => $trace_str,
                        ] );
                } catch ( \Throwable $e ) {
                        // Silently fail — never let our handler crash the site.
                }

                self::$in_handler = false;
                return false; // IMPORTANT: return false to preserve debug.log
        }

        // ─── 2. Exception Handler (uncaught exceptions) ───────────────

        /**
         * Exception handler for uncaught exceptions.
         *
         * This fires when an exception reaches the top of the call stack
         * without being caught.
         *
         * @param \Throwable $exception  The uncaught exception.
         */
        public function handle_exception( $exception ) {
                if ( self::is_disabled() ) return;
                if ( self::$in_handler ) return;
                self::$in_handler = true;

                try {
                        $severity  = DRDBG_SEVERITY_FATAL;
                        $trace_str = $this->format_backtrace( $exception->getTrace() );

                        $this->buffer_error( [
                                'error_type'     => E_ERROR,
                                'severity_level' => $severity,
                                'message'        => $exception->getMessage(),
                                'file'           => $exception->getFile(),
                                'line'           => $exception->getLine(),
                                'stack_trace'    => $trace_str,
                        ] );
                } catch ( \Throwable $e ) {
                        // Silently fail
                }

                self::$in_handler = false;
        }

        // ─── 3. Shutdown Handler (fatal errors) ───────────────────────

        /**
         * Shutdown handler for fatal errors.
         *
         * This is the ONLY way to catch E_ERROR, E_PARSE, E_CORE_ERROR,
         * and E_COMPILE_ERROR — PHP does not call set_error_handler for these.
         *
         * NOTE: Fatal errors have NO stack trace. This is a fundamental PHP
         * limitation — the engine is in an unstable state when a fatal error
         * occurs and the call stack may no longer be valid.
         *
         * If no fatal error occurred, this method simply flushes the buffer
         * (writes buffered warnings/notices to the database).
         */
        public function handle_shutdown() {
                if ( self::is_disabled() ) return;

                $error = error_get_last();

                if ( ! $error ) {
                        // No fatal error — just flush the buffer
                        $this->flush_buffer();
                        return;
                }

                // Only handle fatal-level types
                $fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ];
                if ( ! in_array( $error['type'], $fatal_types, true ) ) {
                        $this->flush_buffer();
                        return;
                }

                if ( self::$in_handler ) {
                        $this->flush_buffer();
                        return;
                }
                self::$in_handler = true;

                try {
                        $this->buffer_error( [
                                'error_type'     => $error['type'],
                                'severity_level' => DRDBG_SEVERITY_FATAL,
                                'message'        => $error['message'],
                                'file'           => $error['file'],
                                'line'           => $error['line'],
                                'stack_trace'    => '', // No trace available for fatals — PHP limitation
                        ] );
                } catch ( \Throwable $e ) {
                        // Silently fail
                }

                self::$in_handler = false;
                $this->flush_buffer();
        }

        // ─── Buffer & Flush ──────────────────────────────────────────

        /**
         * Add an error to the in-memory buffer with dedup.
         *
         * Enriches the error data with request context (URL, method, user,
         * IP hash, memory, versions), applies redaction, generates a
         * fingerprint, and detects the source (plugin/theme/core).
         *
         * Dedup: if the same fingerprint appears twice within the same
         * request, the duplicate is silently skipped.
         *
         * @param array $data  Base error data from the handler methods.
         */
        private function buffer_error( $data ) {
                // ── Request context ───────────────────────────────────────
                $data['request_url']    = $this->get_request_url();
                $data['request_method'] = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
                $data['context_type']   = $this->detect_context();
                $data['user_id']        = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
                $data['ip_hash']        = $this->hash_ip();
                $data['memory_peak']    = memory_get_peak_usage( true );
                $data['php_version']    = PHP_VERSION;
                $data['wp_version']     = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '';

                // ── Redaction ─────────────────────────────────────────────
                $settings = get_option( 'drdbg_settings', [] );
                if ( ! empty( $settings['redaction_enabled'] ) ) {
                        Drdbg_Redaction::redact_error_data( $data );
                }

                // ── Fingerprint & source detection ────────────────────────
                $data['fingerprint']        = Drdbg_Fingerprint::generate( $data['message'], $data['file'] );
                $data['message_normalized'] = Drdbg_Fingerprint::normalize( $data['message'] );

                $source              = Drdbg_Source::detect( $data['file'] );
                $data['source_type'] = $source['type'];
                $data['source_slug'] = $source['slug'];

                // ── Timestamps (used by upsert) ───────────────────────────
                $now = current_time( 'mysql' );
                $data['first_seen'] = $now;
                $data['last_seen']  = $now;

                // ── Dedup: same fingerprint in same request = skip ─────────
                $key = $data['fingerprint'];
                if ( ! isset( $this->buffer[ $key ] ) ) {
                        $this->buffer[ $key ] = $data;
                }
        }

        /**
         * Flush the buffer to the database (batch write at shutdown).
         *
         * This is the only point where database writes occur, minimising
         * the performance impact of error capture during the request.
         *
         * Guard: if WordPress hasn't fully loaded (wp_loaded action hasn't
         * fired), we still try to write if $wpdb is available, since fatal
         * errors can occur before full boot.
         */
        private function flush_buffer() {
                if ( empty( $this->buffer ) ) return;
                if ( self::is_disabled() ) return;

                // Guard: make sure we have a working database connection
                if ( function_exists( 'did_action' ) && ! did_action( 'wp_loaded' ) ) {
                        // WordPress hasn't fully loaded — try direct DB if $wpdb is available
                        global $wpdb;
                        if ( ! $wpdb ) return;
                }

                $db = Drdbg_DB::get_instance();

                foreach ( $this->buffer as $data ) {
                        try {
                                // Upsert error group (insert or increment count)
                                $error_id = $db->upsert_error( $data );

                                if ( $error_id ) {
                                        // Add occurrence record
                                        $occurrence_data = [
                                                'error_id'       => $error_id,
                                                'occurred_at'    => current_time( 'mysql' ),
                                                'request_url'    => $data['request_url'],
                                                'request_method' => $data['request_method'],
                                                'context_type'   => $data['context_type'],
                                                'user_id'        => $data['user_id'],
                                                'ip_hash'        => $data['ip_hash'],
                                                'memory_peak'    => $data['memory_peak'],
                                                'php_version'    => $data['php_version'],
                                                'wp_version'     => $data['wp_version'],
                                                'stack_trace'    => isset( $data['stack_trace'] ) ? $data['stack_trace'] : '',
                                                'extra'          => '',
                                        ];
                                        $db->add_occurrence( $occurrence_data );
                                }
                        } catch ( \Throwable $e ) {
                                // Never let flush crash the site
                                error_log( '[Dr. Debug] Flush error: ' . $e->getMessage() );
                        }
                }

                $this->buffer = [];
        }

        // ─── Utility Methods ──────────────────────────────────────────

        /**
         * Format a backtrace array to a human-readable string.
         *
         * @param array $trace  Output of debug_backtrace() or Throwable::getTrace().
         * @return string       Multi-line stack trace.
         */
        private function format_backtrace( $trace ) {
                if ( empty( $trace ) ) return '';

                $lines = [];
                foreach ( $trace as $i => $frame ) {
                        $file = isset( $frame['file'] ) ? $frame['file'] : '[internal]';
                        $line = isset( $frame['line'] ) ? $frame['line'] : '';
                        $func = '';
                        if ( isset( $frame['class'] ) ) {
                                $func = $frame['class'] . $frame['type'] . $frame['function'];
                        } elseif ( isset( $frame['function'] ) ) {
                                $func = $frame['function'];
                        }
                        $lines[] = "#{$i} {$file}({$line}): {$func}()";
                }

                return implode( "\n", $lines );
        }

        /**
         * Detect the request context type.
         *
         * Distinguishes between web, AJAX, REST, cron, and CLI requests.
         *
         * @return int  One of the DRDBG_CONTEXT_* constants.
         */
        private function detect_context() {
                if ( defined( 'WP_CLI' ) && WP_CLI )              return DRDBG_CONTEXT_CLI;
                if ( defined( 'DOING_CRON' ) && DOING_CRON )      return DRDBG_CONTEXT_CRON;
                if ( defined( 'REST_REQUEST' ) && REST_REQUEST )   return DRDBG_CONTEXT_REST;
                if ( defined( 'DOING_AJAX' ) && DOING_AJAX )       return DRDBG_CONTEXT_AJAX;
                return DRDBG_CONTEXT_WEB;
        }

        /**
         * Get the request URL.
         *
         * @return string  The REQUEST_URI, or empty string.
         */
        private function get_request_url() {
                if ( isset( $_SERVER['REQUEST_URI'] ) ) {
                        return sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
                }
                return '';
        }

        /**
         * Hash the client IP address for privacy.
         *
         * Raw IPs are never stored — only a SHA-256 hash using AUTH_SALT
         * as the pepper.
         *
         * @return string  64-char hex hash, or empty string.
         */
        private function hash_ip() {
                $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
                if ( empty( $ip ) ) return '';

                // Use AUTH_SALT as pepper — if not defined (very early load), skip hashing
                $salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : '';
                return hash( 'sha256', $ip . $salt );
        }

        /**
         * Hide errors from frontend for non-admin users.
         *
         * Hooks into 'init' to disable display_errors and override
         * WP_DEBUG_DISPLAY for non-admin visitors.
         */
        public function hide_frontend_errors() {
                $settings = get_option( 'drdbg_settings', [] );
                if ( ! empty( $settings['hide_frontend'] ) ) {
                        if ( ! current_user_can( 'manage_options' ) ) {
                                ini_set( 'display_errors', 0 );

                                // Override WP_DEBUG_DISPLAY via filter (can't redefine constant)
                                add_filter( 'wp_debug_display', '__return_false' );
                        }
                }
        }
}
