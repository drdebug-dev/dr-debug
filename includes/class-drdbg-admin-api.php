<?php
/**
 * Dr. Debug — Admin AJAX API Endpoints
 *
 * Handles all AJAX requests from the Dr. Debug admin page including
 * stats retrieval, error CRUD, status updates, cleanup, settings,
 * and demo data seeding.
 *
 * @package Dr_Debug
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Drdbg_Admin_API
 *
 * Singleton class that registers and handles all WordPress AJAX endpoints
 * for the Dr. Debug admin interface. Every endpoint verifies the nonce
 * and the 'manage_options' capability before processing.
 */
class Drdbg_Admin_API {

        /**
         * Singleton instance.
         *
         * @since 1.0.0
         * @var Drdbg_Admin_API|null
         */
        private static $instance = null;

        /**
         * Get the singleton instance.
         *
         * @since 1.0.0
         *
         * @return Drdbg_Admin_API
         */
        public static function get_instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }
                return self::$instance;
        }

        /**
         * Private constructor to prevent direct instantiation.
         *
         * @since 1.0.0
         */
        private function __construct() {
                // Singleton — no direct instantiation.
        }

        /**
         * Prevent cloning of the singleton instance.
         *
         * @since 1.0.0
         */
        private function __clone() {}

        /**
         * Prevent unserialization of the singleton instance.
         *
         * @since 1.0.0
         *
         * @throws \Exception Always.
         */
        public function __wakeup() {
                throw new \Exception( 'Cannot unserialize singleton' );
        }

        /**
         * Register all AJAX endpoints.
         *
         * Should be called during the 'wp_ajax_' registration phase
         * (typically in the main plugin file on 'init' or 'admin_init').
         *
         * @since 1.0.0
         */
        public function register() {
                // Stats.
                add_action( 'wp_ajax_drdbg_get_stats', array( $this, 'get_stats' ) );

                // Errors list.
                add_action( 'wp_ajax_drdbg_get_errors', array( $this, 'get_errors' ) );

                // Single error.
                add_action( 'wp_ajax_drdbg_get_error', array( $this, 'get_error' ) );

                // Update status.
                add_action( 'wp_ajax_drdbg_update_status', array( $this, 'update_status' ) );

                // Batch update.
                add_action( 'wp_ajax_drdbg_batch_update', array( $this, 'batch_update' ) );

                // Delete errors.
                add_action( 'wp_ajax_drdbg_delete_errors', array( $this, 'delete_errors' ) );

                // Cleanup.
                add_action( 'wp_ajax_drdbg_cleanup', array( $this, 'cleanup' ) );

                // Settings.
                add_action( 'wp_ajax_drdbg_get_settings', array( $this, 'get_settings' ) );
                add_action( 'wp_ajax_drdbg_update_settings', array( $this, 'update_settings' ) );

                // Seed demo data.
                add_action( 'wp_ajax_drdbg_seed_demo', array( $this, 'seed_demo' ) );
        }

        /**
         * Verify the AJAX request nonce and user capability.
         *
         * All AJAX endpoints must call this before processing. Sends a JSON
         * error response and terminates if verification fails.
         *
         * @since 1.0.0
         */
        private function verify_request() {
                check_ajax_referer( 'drdbg_admin_nonce', 'nonce' );

                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_send_json_error( 'Unauthorized', 403 );
                }
        }

        // ─── Endpoints ───────────────────────────────────────────────────

        /**
         * Get dashboard statistics.
         *
         * @since 1.0.0
         */
        public function get_stats() {
                $this->verify_request();

                $db = Drdbg_DB::get_instance();
                wp_send_json_success( $db->get_stats() );
        }

        /**
         * Get a paginated, filtered list of error groups.
         *
         * Accepts query parameters for severity, status, source_type,
         * source_slug, search, orderby, order, page, and per_page.
         *
         * @since 1.0.0
         */
        public function get_errors() {
                $this->verify_request();

                $db   = Drdbg_DB::get_instance();
                $args = array(
                        'severity'    => isset( $_GET['severity'] ) ? absint( $_GET['severity'] ) : null,
                        'status'      => isset( $_GET['status'] ) ? absint( $_GET['status'] ) : null,
                        'source_type' => isset( $_GET['source_type'] ) ? absint( $_GET['source_type'] ) : null,
                        'source_slug' => isset( $_GET['source_slug'] ) ? sanitize_text_field( wp_unslash( $_GET['source_slug'] ) ) : '',
                        'search'      => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
                        'orderby'     => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'last_seen',
                        'order'       => isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'desc',
                        'page'        => isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1,
                        'per_page'    => isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 15,
                );

                wp_send_json_success( $db->get_errors( $args ) );
        }

        /**
         * Get a single error with its occurrences.
         *
         * Expects $_GET['id'] to be set.
         *
         * @since 1.0.0
         */
        public function get_error() {
                $this->verify_request();

                $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

                if ( ! $id ) {
                        wp_send_json_error( 'Missing error ID', 400 );
                }

                $db = Drdbg_DB::get_instance();
                wp_send_json_success( $db->get_error( $id ) );
        }

        /**
         * Update the status of a single error.
         *
         * Expects $_POST['id'] and $_POST['status'] to be set.
         *
         * @since 1.0.0
         */
        public function update_status() {
                $this->verify_request();

                $id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
                $status = isset( $_POST['status'] ) ? absint( $_POST['status'] ) : 0;

                if ( ! $id ) {
                        wp_send_json_error( 'Missing error ID', 400 );
                }

                $db = Drdbg_DB::get_instance();
                wp_send_json_success( $db->update_status( $id, $status ) );
        }

        /**
         * Batch update the status of multiple errors.
         *
         * Expects $_POST['ids'] (array) and $_POST['status'] to be set.
         *
         * @since 1.0.0
         */
        public function batch_update() {
                $this->verify_request();

                $ids    = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', $_POST['ids'] ) : array();
                $status = isset( $_POST['status'] ) ? absint( $_POST['status'] ) : 0;

                if ( empty( $ids ) ) {
                        wp_send_json_error( 'No error IDs provided', 400 );
                }

                $db = Drdbg_DB::get_instance();
                wp_send_json_success( $db->batch_update_status( $ids, $status ) );
        }

        /**
         * Delete one or more errors by ID.
         *
         * Expects $_POST['ids'] (array of error IDs).
         * If 'ids' is empty or contains 'all', deletes all errors.
         *
         * @since 1.0.0
         */
        public function delete_errors() {
                $this->verify_request();

                $ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', $_POST['ids'] ) : array();

                $db = Drdbg_DB::get_instance();
                wp_send_json_success( $db->delete_errors( $ids ) );
        }

        /**
         * Run cleanup by age.
         *
         * Expects $_POST['days'] and optionally $_POST['status'].
         *
         * @since 1.0.0
         */
        public function cleanup() {
                $this->verify_request();

                $days   = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 30;
                $status = isset( $_POST['status'] ) ? absint( $_POST['status'] ) : null;

                $cleanup = Drdbg_Cleanup::get_instance();
                $deleted = $cleanup->cleanup_by_age( $days, $status );

                wp_send_json_success( array( 'deleted' => $deleted ) );
        }

        /**
         * Get all plugin settings.
         *
         * @since 1.0.0
         */
        public function get_settings() {
                $this->verify_request();

                $settings = Drdbg_Settings::get_instance()->get_all();
                wp_send_json_success( $settings );
        }

        /**
         * Update plugin settings.
         *
         * Accepts any subset of settings keys via $_POST.
         *
         * @since 1.0.0
         */
        public function update_settings() {
                $this->verify_request();

                // Collect only known settings keys from the POST data.
                $input = array();
                $keys  = array( 'cleanup_days', 'max_occurrences', 'min_severity', 'redaction_enabled', 'hide_frontend' );

                foreach ( $keys as $key ) {
                        if ( isset( $_POST[ $key ] ) ) {
                                $input[ $key ] = wp_unslash( $_POST[ $key ] );
                        }
                }

                $settings = Drdbg_Settings::get_instance()->update( $input );
                wp_send_json_success( $settings );
        }

        /**
         * Seed demo data for testing and development.
         *
         * Creates 18 realistic WordPress error groups with 3-8 occurrences each,
         * matching the patterns found in the Next.js seeder.
         *
         * @since 1.0.0
         */
        public function seed_demo() {
                $this->verify_request();

                $db      = Drdbg_DB::get_instance();
                $created = $this->insert_demo_data( $db );

                wp_send_json_success( array( 'created' => $created ) );
        }

        /**
         * Insert demo error and occurrence data.
         *
         * Creates 18 realistic WordPress error groups with varied occurrences,
         * mirroring the demo data from the Next.js seeder.
         *
         * @since 1.0.0
         *
         * @param Drdbg_DB $db The database class instance.
         * @return int Number of error groups created.
         */
        private function insert_demo_data( $db ) {
                global $wpdb;

                $errors_table      = $wpdb->prefix . 'drdbg_errors';
                $occurrences_table = $wpdb->prefix . 'drdbg_occurrences';

                // Clear existing data first.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is static.
                $wpdb->query( "TRUNCATE TABLE {$occurrences_table}" );
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is static.
                $wpdb->query( "TRUNCATE TABLE {$errors_table}" );

                $demo_errors = $this->get_demo_error_templates();
                $created     = 0;

                foreach ( $demo_errors as $template ) {
                        $occurrence_count = wp_rand( 2, 8 );
                        $first_seen       = $this->random_date( 14 );
                        $last_seen_offset = wp_rand( 0, min( 7 * DAY_IN_SECONDS, time() - $first_seen ) );
                        $last_seen        = $first_seen + $last_seen_offset;

                        // Pick a status for this error (weighted toward NEW).
                        $status = $this->random_status();

                        // Insert error group.
                        $inserted = $wpdb->insert(
                                $errors_table,
                                array(
                                        'fingerprint'       => $template['fingerprint'],
                                        'error_type'        => $template['error_type'],
                                        'severity_level'    => $template['severity_level'],
                                        'message'           => $template['message'],
                                        'message_normalized' => $template['message_normalized'],
                                        'file'              => $template['file'],
                                        'line'              => $template['line'],
                                        'source_type'       => $template['source_type'],
                                        'source_slug'       => $template['source_slug'],
                                        'count'             => $occurrence_count,
                                        'first_seen'        => gmdate( 'Y-m-d H:i:s', $first_seen ),
                                        'last_seen'         => gmdate( 'Y-m-d H:i:s', $last_seen ),
                                        'status'            => $status,
                                ),
                                array( '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%d' )
                        );

                        if ( ! $inserted ) {
                                continue;
                        }

                        $error_id = $wpdb->insert_id;
                        $created++;

                        // Create occurrences for this error.
                        for ( $i = 0; $i < $occurrence_count; $i++ ) {
                                $occurred_at = $first_seen + wp_rand( 0, max( 1, $last_seen - $first_seen ) );

                                $wpdb->insert(
                                        $occurrences_table,
                                        array(
                                                'error_id'       => $error_id,
                                                'occurred_at'    => gmdate( 'Y-m-d H:i:s', $occurred_at ),
                                                'request_url'    => $this->random_from( $this->get_request_urls() ),
                                                'request_method' => $this->random_from( array( 'GET', 'POST', 'PUT', 'DELETE' ) ),
                                                'context_type'   => $this->random_from( array( DRDBG_CONTEXT_WEB, DRDBG_CONTEXT_AJAX, DRDBG_CONTEXT_REST, DRDBG_CONTEXT_CRON, DRDBG_CONTEXT_CLI ) ),
                                                'user_id'        => wp_rand( 0, 5 ),
                                                'ip_hash'        => '',
                                                'memory_peak'    => wp_rand( 8388608, 134217728 ),
                                                'php_version'    => $this->random_from( array( '8.1.27', '8.2.15', '8.3.2' ) ),
                                                'wp_version'     => $this->random_from( array( '6.4.3', '6.5', '6.5.1' ) ),
                                                'stack_trace'    => $this->random_from( $this->get_stack_traces() ),
                                        ),
                                        array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
                                );
                        }
                }

                return $created;
        }

        /**
         * Get the demo error templates array.
         *
         * Returns 18 realistic WordPress error definitions with pre-computed
         * fingerprints and normalized messages.
         *
         * @since 1.0.0
         *
         * @return array Array of error template associative arrays.
         */
        private function get_demo_error_templates() {
                $errors = array(
                        array(
                                'error_type'        => DRDBG_E_ERROR,
                                'message'           => 'Uncaught Error: Call to undefined function get_field() in /var/www/html/wp-content/plugins/custom-plugin/includes/helper.php:142',
                                'file'              => '/var/www/html/wp-content/plugins/custom-plugin/includes/helper.php',
                                'line'              => 142,
                                'source_type'       => DRDBG_SOURCE_PLUGIN,
                                'source_slug'       => 'custom-plugin',
                                'severity_level'    => DRDBG_SEVERITY_FATAL,
                        ),
                        array(
                                'error_type'        => DRDBG_E_ERROR,
                                'message'           => 'Uncaught TypeError: Argument 1 passed to WP_REST_Controller::prepare_item_for_response() must be an instance of WP_Post, null given in /var/www/html/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php:534',
                                'file'              => '/var/www/html/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php',
                                'line'              => 534,
                                'source_type'       => DRDBG_SOURCE_CORE,
                                'source_slug'       => 'wordpress',
                                'severity_level'    => DRDBG_SEVERITY_FATAL,
                        ),
                        array(
                                'error_type'        => DRDBG_E_WARNING,
                                'message'           => 'Cannot modify header information - headers already sent by (output started at /var/www/html/wp-content/themes/flavor/functions.php:23) in /var/www/html/wp-includes/pluggable.php:1287',
                                'file'              => '/var/www/html/wp-includes/pluggable.php',
                                'line'              => 1287,
                                'source_type'       => DRDBG_SOURCE_CORE,
                                'source_slug'       => 'wordpress',
                                'severity_level'    => DRDBG_SEVERITY_ERROR,
                        ),
                        array(
                                'error_type'        => DRDBG_E_WARNING,
                                'message'           => 'Attempt to read property "post_title" on null in /var/www/html/wp-content/plugins/seo-optimizer/admin/class-sitemap-builder.php:89',
                                'file'              => '/var/www/html/wp-content/plugins/seo-optimizer/admin/class-sitemap-builder.php',
                                'line'              => 89,
                                'source_type'       => DRDBG_SOURCE_PLUGIN,
                                'source_slug'       => 'seo-optimizer',
                                'severity_level'    => DRDBG_SEVERITY_ERROR,
                        ),
                        array(
                                'error_type'        => DRDBG_E_WARNING,
                                'message'           => 'Undefined array key "woocommerce_cart" in /var/www/html/wp-content/plugins/woocommerce/includes/class-wc-cart.php:342',
                                'file'              => '/var/www/html/wp-content/plugins/woocommerce/includes/class-wc-cart.php',
                                'line'              => 342,
                                'source_type'       => DRDBG_SOURCE_PLUGIN,
                                'source_slug'       => 'woocommerce',
                                'severity_level'    => DRDBG_SEVERITY_ERROR,
                        ),
                        array(
                                'error_type'        => DRDBG_E_WARNING,
                                'message'           => 'Undefined variable $content in /var/www/html/wp-content/themes/flavor/template-parts/content-page.php:15',
                                'file'              => '/var/www/html/wp-content/themes/flavor/template-parts/content-page.php',
                                'line'              => 15,
                                'source_type'       => DRDBG_SOURCE_THEME,
                                'source_slug'       => 'flavor',
                                'severity_level'    => DRDBG_SEVERITY_ERROR,
                        ),
                        array(
                                'error_type'        => DRDBG_E_NOTICE,
                                'message'           => 'Undefined index: HTTPS in /var/www/html/wp-content/plugins/security-guard/includes/force-ssl.php:27',
                                'file'              => '/var/www/html/wp-content/plugins/security-guard/includes/force-ssl.php',
                                'line'              => 27,
                                'source_type'       => DRDBG_SOURCE_PLUGIN,
                                'source_slug'       => 'security-guard',
                                'severity_level'    => DRDBG_SEVERITY_NOTICE,
                        ),
                        array(
                                'error_type'        => DRDBG_E_DEPRECATED,
                                'message'           => 'Function create_function() is deprecated in /var/www/html/wp-content/plugins/old-contact-form/includes/shortcode.php:56',
                                'file'              => '/var/www/html/wp-content/plugins/old-contact-form/includes/shortcode.php',
                                'line'              => 56,
                                'source_type'       => DRDBG_SOURCE_PLUGIN,
                                'source_slug'       => 'old-contact-form',
                                'severity_level'    => DRDBG_SEVERITY_WARNING,
                        ),
                        array(
                                'error_type'        => DRDBG_E_DEPRECATED,
                                'message'           => 'Function get_page_by_title() is deprecated since version 6.2.0! Use WP_Query instead. in /var/www/html/wp-includes/functions.php:5389',
                                'file'              => '/var/www/html/wp-includes/functions.php',
                                'line'              => 5389,
                                'source_type'       => DRDBG_SOURCE_CORE,
                                'source_slug'       => 'wordpress',
                                'severity_level'    => DRDBG_SEVERITY_WARNING,
                        ),
                        array(
                                'error_type'        => DRDBG_E_WARNING,
                                'message'           => 'include(/var/www/html/wp-content/themes/flavor/404.php): Failed to open stream: No such file or directory in /var/www/html/wp-includes/template-loader.php:106',
                                'file'              => '/var/www/html/wp-includes/template-loader.php',
                                'line'              => 106,
                                'source_type'       => DRDBG_SOURCE_CORE,
                                'source_slug'       => 'wordpress',
                                'severity_level'    => DRDBG_SEVERITY_ERROR,
                        ),
                        array(
                                'error_type'        => DRDBG_E_ERROR,
                                'message'           => 'Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes) in /var/www/html/wp-content/plugins/woocommerce/includes/class-wc-product.php:78',
                                'file'              => '/var/www/html/wp-content/plugins/woocommerce/includes/class-wc-product.php',
                                'line'              => 78,
                                'source_type'       => DRDBG_SOURCE_PLUGIN,
                                'source_slug'       => 'woocommerce',
                                'severity_level'    => DRDBG_SEVERITY_FATAL,
                        ),
                        array(
                                'error_type'        => DRDBG_E_WARNING,
                                'message'           => 'Invalid argument supplied for foreach() in /var/www/html/wp-content/plugins/custom-plugin/admin/class-settings-page.php:203',
                                'file'              => '/var/www/html/wp-content/plugins/custom-plugin/admin/class-settings-page.php',
                                'line'              => 203,
                                'source_type'       => DRDBG_SOURCE_PLUGIN,
                                'source_slug'       => 'custom-plugin',
                                'severity_level'    => DRDBG_SEVERITY_ERROR,
                        ),
                        array(
                                'error_type'        => DRDBG_E_NOTICE,
                                'message'           => 'Trying to get property of non-object in /var/www/html/wp-content/themes/flavor/header.php:42',
                                'file'              => '/var/www/html/wp-content/themes/flavor/header.php',
                                'line'              => 42,
                                'source_type'       => DRDBG_SOURCE_THEME,
                                'source_slug'       => 'flavor',
                                'severity_level'    => DRDBG_SEVERITY_NOTICE,
                        ),
                        array(
                                'error_type'        => DRDBG_E_WARNING,
                                'message'           => 'Declaration of Custom_Walker::walk($elements, $max_depth) should be compatible with Walker::walk($elements, $max_depth, ...$args) in /var/www/html/wp-content/themes/flavor/includes/class-custom-walker.php:12',
                                'file'              => '/var/www/html/wp-content/themes/flavor/includes/class-custom-walker.php',
                                'line'              => 12,
                                'source_type'       => DRDBG_SOURCE_THEME,
                                'source_slug'       => 'flavor',
                                'severity_level'    => DRDBG_SEVERITY_ERROR,
                        ),
                        array(
                                'error_type'        => DRDBG_E_WARNING,
                                'message'           => 'mysqli_query(): (HY000/2006): MySQL server has gone away in /var/www/html/wp-includes/wp-db.php:2057',
                                'file'              => '/var/www/html/wp-includes/wp-db.php',
                                'line'              => 2057,
                                'source_type'       => DRDBG_SOURCE_CORE,
                                'source_slug'       => 'wordpress',
                                'severity_level'    => DRDBG_SEVERITY_ERROR,
                        ),
                        array(
                                'error_type'        => DRDBG_E_DEPRECATED,
                                'message'           => 'Function wp_make_content_images_responsive() is deprecated since version 5.5.0! Use wp_filter_content_tags() instead. in /var/www/html/wp-includes/functions.php:5389',
                                'file'              => '/var/www/html/wp-includes/functions.php',
                                'line'              => 5389,
                                'source_type'       => DRDBG_SOURCE_CORE,
                                'source_slug'       => 'wordpress',
                                'severity_level'    => DRDBG_SEVERITY_WARNING,
                        ),
                        array(
                                'error_type'        => DRDBG_E_NOTICE,
                                'message'           => "Undefined constant 'DOING_AJAX' in /var/www/html/wp-content/plugins/seo-optimizer/includes/ajax-handler.php:5",
                                'file'              => '/var/www/html/wp-content/plugins/seo-optimizer/includes/ajax-handler.php',
                                'line'              => 5,
                                'source_type'       => DRDBG_SOURCE_PLUGIN,
                                'source_slug'       => 'seo-optimizer',
                                'severity_level'    => DRDBG_SEVERITY_NOTICE,
                        ),
                        array(
                                'error_type'        => DRDBG_E_ERROR,
                                'message'           => 'Uncaught Exception: Missing required parameter: permission_callback in /var/www/html/wp-content/plugins/custom-plugin/rest-api/class-endpoints.php:34',
                                'file'              => '/var/www/html/wp-content/plugins/custom-plugin/rest-api/class-endpoints.php',
                                'line'              => 34,
                                'source_type'       => DRDBG_SOURCE_PLUGIN,
                                'source_slug'       => 'custom-plugin',
                                'severity_level'    => DRDBG_SEVERITY_FATAL,
                        ),
                );

                // Compute fingerprint and normalized message for each template.
                foreach ( $errors as &$template ) {
                        $template['fingerprint']        = Drdbg_Fingerprint::generate( $template['message'], $template['file'] );
                        $template['message_normalized'] = Drdbg_Fingerprint::normalize( $template['message'] );
                }

                return $errors;
        }

        /**
         * Get sample request URLs for demo data.
         *
         * @since 1.0.0
         *
         * @return array Array of URL strings.
         */
        private function get_request_urls() {
                return array(
                        '/',
                        '/wp-admin/post.php?post=42&action=edit',
                        '/wp-admin/admin-ajax.php',
                        '/wp-json/wp/v2/posts',
                        '/wp-cron.php',
                        '/shop/',
                        '/wp-admin/options-general.php',
                        '/wp-json/wc/v3/products',
                        '/about-us/',
                        '/wp-admin/plugins.php',
                );
        }

        /**
         * Get sample stack traces for demo data.
         *
         * @since 1.0.0
         *
         * @return array Array of stack trace strings.
         */
        private function get_stack_traces() {
                return array(
                        "#0 /var/www/html/wp-includes/class-wp-hook.php(308): custom_plugin_helper()\n#1 /var/www/html/wp-includes/plugin.php(205): WP_Hook->apply_filters()\n#2 /var/www/html/wp-settings.php(450): apply_filters()\n#3 /var/www/html/wp-config.php(96): require_once('/var/www/html/w...')\n#4 {main}",
                        "#0 /var/www/html/wp-content/plugins/woocommerce/includes/class-wc-cart.php(342): wc_doing_it_wrong()\n#1 /var/www/html/wp-content/plugins/woocommerce/includes/class-wc-cart.php(180): WC_Cart->get_cart()\n#2 /var/www/html/wp-includes/class-wp-hook.php(308): WC_Cart->init()\n#3 {main}",
                        "#0 /var/www/html/wp-admin/includes/template.php(1185): do_settings_sections()\n#1 /var/www/html/wp-content/plugins/custom-plugin/admin/class-settings-page.php(78): settings_errors()\n#2 /var/www/html/wp-admin/admin.php(234): CustomPlugin_Admin->render_page()\n#3 {main}",
                );
        }

        /**
         * Pick a random element from an array.
         *
         * @since 1.0.0
         *
         * @param array $arr The array to pick from.
         * @return mixed A random element.
         */
        private function random_from( $arr ) {
                return $arr[ wp_rand( 0, count( $arr ) - 1 ) ];
        }

        /**
         * Generate a random UNIX timestamp within the last N days.
         *
         * @since 1.0.0
         *
         * @param int $days_ago Number of days in the past.
         * @return int UNIX timestamp.
         */
        private function random_date( $days_ago ) {
                return time() - wp_rand( 0, $days_ago * DAY_IN_SECONDS );
        }

        /**
         * Get a random status, weighted toward NEW.
         *
         * @since 1.0.0
         *
         * @return int Status constant.
         */
        private function random_status() {
                $statuses = array(
                        DRDBG_STATUS_NEW,
                        DRDBG_STATUS_NEW,
                        DRDBG_STATUS_NEW,
                        DRDBG_STATUS_SEEN,
                        DRDBG_STATUS_RESOLVED,
                        DRDBG_STATUS_MUTED,
                );
                return $this->random_from( $statuses );
        }
}
