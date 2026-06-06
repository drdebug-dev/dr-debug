<?php
/**
 * Dr. Debug — Database Operations
 *
 * Singleton class wrapping all database operations for the Dr. Debug
 * plugin. Provides methods for upserting errors, adding occurrences,
 * querying with filters/pagination, stats, and cleanup.
 *
 * All queries use $wpdb->prepare() to prevent SQL injection.
 *
 * @package Dr_Debug
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class Drdbg_DB
 *
 * Centralised database access layer for Dr. Debug.
 * Uses bigint AUTO_INCREMENT primary keys per the schema spec.
 */
class Drdbg_DB {

        /**
         * Singleton instance.
         *
         * @since 1.0.0
         * @var Drdbg_DB|null
         */
        private static $instance = null;

        /**
         * Get the singleton instance.
         *
         * @since 1.0.0
         *
         * @return Drdbg_DB
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
        private function __construct() {}

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
         * Get the fully-qualified errors table name.
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         * @return string
         */
        public function get_errors_table() {
                global $wpdb;
                return $wpdb->prefix . 'drdbg_errors';
        }

        /**
         * Get the fully-qualified occurrences table name.
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         * @return string
         */
        public function get_occurrences_table() {
                global $wpdb;
                return $wpdb->prefix . 'drdbg_occurrences';
        }

        /**
         * Upsert an error group.
         *
         * If a row with the same fingerprint already exists, increments
         * count and updates last_seen, message, and line. Otherwise inserts
         * a new row. Returns the error_id in either case.
         *
         * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert.
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         *
         * @param array $data {
         *     Associative array of error data.
         *     @type string $fingerprint        SHA-1 fingerprint (40 chars).
         *     @type int    $error_type         PHP E_* constant.
         *     @type int    $severity_level     1-5 severity.
         *     @type string $message            Raw error message.
         *     @type string $message_normalized Normalized message for grouping.
         *     @type string $file               File path.
         *     @type int    $line               Line number.
         *     @type int    $source_type        0-3 source type.
         *     @type string $source_slug        Plugin/theme slug.
         *     @type string $first_seen         Datetime string (only used on insert).
         *     @type string $last_seen          Datetime string.
         * }
         * @return int|false Error ID on success, false on failure.
         */
        public function upsert_error( $data ) {
                global $wpdb;

                $table = $this->get_errors_table();

                // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert.
                $result = $wpdb->query(
                        $wpdb->prepare(
                                "INSERT INTO {$table} (
                                        fingerprint, error_type, severity_level, message,
                                        message_normalized, file, line, source_type,
                                        source_slug, count, first_seen, last_seen, status
                                ) VALUES (
                                        %s, %d, %d, %s,
                                        %s, %s, %d, %d,
                                        %s, 1, %s, %s, %d
                                ) ON DUPLICATE KEY UPDATE
                                        count          = count + 1,
                                        last_seen      = VALUES(last_seen),
                                        message        = VALUES(message),
                                        line           = VALUES(line),
                                        severity_level = VALUES(severity_level)",
                                $data['fingerprint'],
                                $data['error_type'],
                                $data['severity_level'],
                                $data['message'],
                                $data['message_normalized'],
                                $data['file'],
                                $data['line'],
                                $data['source_type'],
                                $data['source_slug'],
                                $data['first_seen'],
                                $data['last_seen'],
                                DRDBG_STATUS_NEW
                        )
                );

                if ( false === $result ) {
                        return false;
                }

                // Get the error_id — either the new insert or the existing row.
                $error_id = $wpdb->insert_id;
                if ( 0 === $error_id ) {
                        // ON DUPLICATE KEY UPDATE doesn't set insert_id for existing rows.
                        $error_id = (int) $wpdb->get_var(
                                $wpdb->prepare(
                                        "SELECT id FROM {$table} WHERE fingerprint = %s LIMIT 1",
                                        $data['fingerprint']
                                )
                        );
                }

                return $error_id;
        }

        /**
         * Add an occurrence record for an error.
         *
         * Inserts the occurrence and then trims the ring buffer so that
         * only the last N occurrences are kept (where N = max_occurrences
         * from settings).
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         *
         * @param array $data {
         *     Associative array of occurrence data.
         *     @type int    $error_id       Parent error ID.
         *     @type string $occurred_at    Datetime string.
         *     @type string $request_url    Request URL.
         *     @type string $request_method HTTP method.
         *     @type int    $context_type   0-4 context type.
         *     @type int    $user_id        User ID or 0.
         *     @type string $ip_hash        SHA-256 hash of client IP.
         *     @type int    $memory_peak    Peak memory in bytes.
         *     @type string $php_version    PHP version string.
         *     @type string $wp_version     WordPress version string.
         *     @type string $stack_trace    Stack trace text.
         *     @type string $extra          JSON-encoded extra data.
         * }
         * @return int|false Occurrence ID on success, false on failure.
         */
        public function add_occurrence( $data ) {
                global $wpdb;

                $table = $this->get_occurrences_table();

                $result = $wpdb->insert(
                        $table,
                        array(
                                'error_id'       => $data['error_id'],
                                'occurred_at'    => $data['occurred_at'],
                                'request_url'    => $data['request_url'],
                                'request_method' => $data['request_method'],
                                'context_type'   => $data['context_type'],
                                'user_id'        => $data['user_id'],
                                'ip_hash'        => $data['ip_hash'],
                                'memory_peak'    => $data['memory_peak'],
                                'php_version'    => $data['php_version'],
                                'wp_version'     => $data['wp_version'],
                                'stack_trace'    => $data['stack_trace'],
                                'extra'          => $data['extra'],
                        ),
                        array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
                );

                if ( false === $result ) {
                        return false;
                }

                $occurrence_id = $wpdb->insert_id;

                // Trim ring buffer: keep only the last max_occurrences per error.
                $this->trim_occurrences( $data['error_id'] );

                return $occurrence_id;
        }

        /**
         * Trim occurrences for an error to the ring buffer limit.
         *
         * Deletes the oldest occurrences beyond max_occurrences setting.
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         *
         * @param int $error_id Parent error ID.
         */
        private function trim_occurrences( $error_id ) {
                global $wpdb;

                $table    = $this->get_occurrences_table();
                $settings = get_option( 'drdbg_settings', array() );
                $max      = isset( $settings['max_occurrences'] ) ? (int) $settings['max_occurrences'] : 20;

                if ( $max < 1 ) {
                        return;
                }

                // Delete oldest occurrences beyond the limit.
                $wpdb->query(
                        $wpdb->prepare(
                                "DELETE FROM {$table}
                                 WHERE error_id = %d
                                 AND id NOT IN (
                                         SELECT id FROM (
                                                 SELECT id FROM {$table}
                                                 WHERE error_id = %d
                                                 ORDER BY occurred_at DESC
                                                 LIMIT %d
                                         ) AS keep_ids
                                 )",
                                $error_id,
                                $error_id,
                                $max
                        )
                );
        }

        /**
         * Get errors with filtering, sorting, and pagination.
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         *
         * @param array $args {
         *     Optional. Query arguments.
         *     @type int    $severity     Filter by severity level.
         *     @type int    $status       Filter by status.
         *     @type int    $source_type  Filter by source type.
         *     @type string $source_slug  Filter by source slug.
         *     @type string $search       Search term for message/file.
         *     @type string $orderby      Column to order by. Default 'last_seen'.
         *     @type string $order        'ASC' or 'DESC'. Default 'DESC'.
         *     @type int    $page         Page number (1-based). Default 1.
         *     @type int    $per_page     Items per page. Default 20.
         * }
         * @return array {
         *     @type array $errors Array of error row objects.
         *     @type int   $total  Total number of matching errors.
         *     @type int   $pages  Total number of pages.
         * }
         */
        public function get_errors( $args = array() ) {
                global $wpdb;

                $table = $this->get_errors_table();

                $defaults = array(
                        'severity'    => null,
                        'status'      => null,
                        'source_type' => null,
                        'source_slug' => '',
                        'search'      => '',
                        'orderby'     => 'last_seen',
                        'order'       => 'DESC',
                        'page'        => 1,
                        'per_page'    => 20,
                );
                $args = wp_parse_args( $args, $defaults );

                // Build WHERE clauses.
                $where  = array( '1=1' );
                $values = array();

                if ( null !== $args['severity'] ) {
                        $where[]  = 'severity_level = %d';
                        $values[] = (int) $args['severity'];
                }

                if ( null !== $args['status'] ) {
                        $where[]  = 'status = %d';
                        $values[] = (int) $args['status'];
                }

                if ( null !== $args['source_type'] ) {
                        $where[]  = 'source_type = %d';
                        $values[] = (int) $args['source_type'];
                }

                if ( ! empty( $args['source_slug'] ) ) {
                        $where[]  = 'source_slug = %s';
                        $values[] = $args['source_slug'];
                }

                if ( ! empty( $args['search'] ) ) {
                        $where[]  = '(message LIKE %s OR file LIKE %s)';
                        $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
                        $values[] = $like;
                        $values[] = $like;
                }

                $where_sql = implode( ' AND ', $where );

                // Validate orderby to prevent injection.
                $allowed_orderby = array(
                        'id', 'fingerprint', 'error_type', 'severity_level',
                        'message', 'file', 'line', 'source_type', 'source_slug',
                        'count', 'first_seen', 'last_seen', 'status',
                );
                if ( ! in_array( $args['orderby'], $allowed_orderby, true ) ) {
                        $args['orderby'] = 'last_seen';
                }

                $order = strtoupper( $args['order'] );
                if ( 'ASC' !== $order ) {
                        $order = 'DESC';
                }

                // Get total count.
                $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
                if ( ! empty( $values ) ) {
                        $count_sql = $wpdb->prepare( $count_sql, $values );
                }
                $total = (int) $wpdb->get_var( $count_sql );

                // Pagination.
                $per_page = max( 1, (int) $args['per_page'] );
                $page     = max( 1, (int) $args['page'] );
                $offset   = ( $page - 1 ) * $per_page;
                $pages    = (int) ceil( $total / $per_page );

                // Get errors.
                $query_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$args['orderby']} {$order} LIMIT %d OFFSET %d";
                $query_values = array_merge( $values, array( $per_page, $offset ) );
                $errors       = $wpdb->get_results(
                        $wpdb->prepare( $query_sql, $query_values )
                );

                return array(
                        'errors' => $errors,
                        'total'  => $total,
                        'pages'  => $pages,
                );
        }

        /**
         * Get a single error with its occurrences (paginated).
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         *
         * @param int $id                   Error ID.
         * @param int $occurrences_page     Page number for occurrences. Default 1.
         * @param int $occurrences_per_page Items per page for occurrences. Default 20.
         * @return array|false {
         *     @type object $error               Error row object.
         *     @type array  $occurrences         Array of occurrence row objects.
         *     @type int    $total_occurrences   Total occurrence count.
         *     @type int    $occurrence_pages    Total pages of occurrences.
         * } False if error not found.
         */
        public function get_error( $id, $occurrences_page = 1, $occurrences_per_page = 20 ) {
                global $wpdb;

                $errors_table      = $this->get_errors_table();
                $occurrences_table = $this->get_occurrences_table();

                // Get error.
                $error = $wpdb->get_row(
                        $wpdb->prepare(
                                "SELECT * FROM {$errors_table} WHERE id = %d",
                                $id
                        )
                );

                if ( ! $error ) {
                        return false;
                }

                // Get total occurrences count.
                $total_occurrences = (int) $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT COUNT(*) FROM {$occurrences_table} WHERE error_id = %d",
                                $id
                        )
                );

                // Pagination for occurrences.
                $occurrences_page     = max( 1, (int) $occurrences_page );
                $occurrences_per_page = max( 1, (int) $occurrences_per_page );
                $offset               = ( $occurrences_page - 1 ) * $occurrences_per_page;
                $occurrence_pages     = (int) ceil( $total_occurrences / $occurrences_per_page );

                // Get occurrences (newest first).
                $occurrences = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT * FROM {$occurrences_table} WHERE error_id = %d ORDER BY occurred_at DESC LIMIT %d OFFSET %d",
                                $id,
                                $occurrences_per_page,
                                $offset
                        )
                );

                return array(
                        'error'             => $error,
                        'occurrences'       => $occurrences,
                        'total_occurrences' => $total_occurrences,
                        'occurrence_pages'  => $occurrence_pages,
                );
        }

        /**
         * Update the status of a single error.
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         *
         * @param int $id     Error ID.
         * @param int $status New status value.
         * @return bool True on success, false on failure.
         */
        public function update_status( $id, $status ) {
                global $wpdb;

                $table = $this->get_errors_table();

                $result = $wpdb->update(
                        $table,
                        array( 'status' => (int) $status ),
                        array( 'id' => (int) $id ),
                        array( '%d' ),
                        array( '%d' )
                );

                return false !== $result;
        }

        /**
         * Batch update status for multiple errors.
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         *
         * @param array $ids    Array of error IDs.
         * @param int   $status New status value.
         * @return int|false Number of rows updated, or false on error.
         */
        public function batch_update_status( $ids, $status ) {
                global $wpdb;

                if ( empty( $ids ) ) {
                        return 0;
                }

                $table = $this->get_errors_table();

                // Build IN clause safely.
                $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                $values       = array_merge( array( (int) $status ), array_map( 'intval', $ids ) );

                $result = $wpdb->query(
                        $wpdb->prepare(
                                "UPDATE {$table} SET status = %d WHERE id IN ({$placeholders})",
                                $values
                        )
                );

                return $result;
        }

        /**
         * Get aggregated statistics for the dashboard.
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         *
         * @return array {
         *     @type int   $total          Total number of error groups.
         *     @type array $by_severity    Counts keyed by severity level.
         *     @type array $by_source_type Counts keyed by source type.
         *     @type array $by_status      Counts keyed by status.
         *     @type array $recent_trend   Daily counts for the last 7 days.
         *     @type array $top_sources    Top 5 source slugs by count.
         * }
         */
        public function get_stats() {
                global $wpdb;

                $table = $this->get_errors_table();

                // Total.
                $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

                // By severity.
                $by_severity = array();
                $rows = $wpdb->get_results( "SELECT severity_level, COUNT(*) AS cnt FROM {$table} GROUP BY severity_level" );
                foreach ( $rows as $row ) {
                        $by_severity[ (int) $row->severity_level ] = (int) $row->cnt;
                }

                // By source type.
                $by_source_type = array();
                $rows = $wpdb->get_results( "SELECT source_type, COUNT(*) AS cnt FROM {$table} GROUP BY source_type" );
                foreach ( $rows as $row ) {
                        $by_source_type[ (int) $row->source_type ] = (int) $row->cnt;
                }

                // By status.
                $by_status = array();
                $rows = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status" );
                foreach ( $rows as $row ) {
                        $by_status[ (int) $row->status ] = (int) $row->cnt;
                }

                // Recent trend: daily new errors for the last 7 days.
                $recent_trend = array();
                $rows = $wpdb->get_results(
                        "SELECT DATE(first_seen) AS day, COUNT(*) AS cnt
                         FROM {$table}
                         WHERE first_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                         GROUP BY DATE(first_seen)
                         ORDER BY day ASC"
                );
                foreach ( $rows as $row ) {
                        $recent_trend[ $row->day ] = (int) $row->cnt;
                }

                // Top 5 sources by error count.
                $top_sources = array();
                $rows = $wpdb->get_results(
                        "SELECT source_slug, source_type, SUM(count) AS total_count
                         FROM {$table}
                         WHERE source_slug != ''
                         GROUP BY source_slug, source_type
                         ORDER BY total_count DESC
                         LIMIT 5"
                );
                foreach ( $rows as $row ) {
                        $top_sources[] = array(
                                'slug'        => $row->source_slug,
                                'source_type' => (int) $row->source_type,
                                'count'       => (int) $row->total_count,
                        );
                }

                return array(
                        'total'          => $total,
                        'by_severity'    => $by_severity,
                        'by_source_type' => $by_source_type,
                        'by_status'      => $by_status,
                        'recent_trend'   => $recent_trend,
                        'top_sources'    => $top_sources,
                );
        }

        /**
         * Get the count of errors with "new" status.
         *
         * Used by the admin bar indicator to show the count of
         * unseen errors to administrators.
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         *
         * @return int Number of new (unseen) error groups.
         */
        public function get_new_count() {
                global $wpdb;

                $table = $this->get_errors_table();

                return (int) $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT COUNT(*) FROM {$table} WHERE status = %d",
                                DRDBG_STATUS_NEW
                        )
                );
        }

        /**
         * Delete error groups and their associated occurrences.
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         *
         * @param array|null $ids Array of error IDs to delete, or null to delete all.
         * @return int|false Number of error rows deleted, or false on error.
         */
        public function delete_errors( $ids = null ) {
                global $wpdb;

                $errors_table      = $this->get_errors_table();
                $occurrences_table = $this->get_occurrences_table();

                if ( null === $ids ) {
                        // Delete all.
                        $wpdb->query( "DELETE FROM {$occurrences_table}" );
                        return $wpdb->query( "DELETE FROM {$errors_table}" );
                }

                if ( empty( $ids ) ) {
                        return 0;
                }

                $ids          = array_map( 'intval', $ids );
                $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

                // Delete occurrences first.
                $wpdb->query(
                        $wpdb->prepare(
                                "DELETE FROM {$occurrences_table} WHERE error_id IN ({$placeholders})",
                                $ids
                        )
                );

                // Delete errors.
                return $wpdb->query(
                        $wpdb->prepare(
                                "DELETE FROM {$errors_table} WHERE id IN ({$placeholders})",
                                $ids
                        )
                );
        }

        /**
         * Cleanup old errors.
         *
         * Deletes errors older than a specified number of days.
         * Optionally filters by status.
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         *
         * @param int      $older_than_days Delete errors last seen more than this many days ago.
         * @param int|null $status          Optional. Only delete errors with this status.
         * @return int|false Number of error rows deleted, or false on error.
         */
        public function cleanup( $older_than_days, $status = null ) {
                global $wpdb;

                $errors_table      = $this->get_errors_table();
                $occurrences_table = $this->get_occurrences_table();

                // Build the WHERE for errors to delete.
                if ( null !== $status ) {
                        $ids_to_delete = $wpdb->get_col(
                                $wpdb->prepare(
                                        "SELECT id FROM {$errors_table} WHERE last_seen < DATE_SUB(NOW(), INTERVAL %d DAY) AND status = %d",
                                        $older_than_days,
                                        $status
                                )
                        );
                } else {
                        $ids_to_delete = $wpdb->get_col(
                                $wpdb->prepare(
                                        "SELECT id FROM {$errors_table} WHERE last_seen < DATE_SUB(NOW(), INTERVAL %d DAY)",
                                        $older_than_days
                                )
                        );
                }

                if ( empty( $ids_to_delete ) ) {
                        return 0;
                }

                return $this->delete_errors( $ids_to_delete );
        }

        /**
         * Create (or update) the custom database tables.
         *
         * Called during plugin activation. Uses dbDelta() for safe
         * schema creation and migration.
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         */
        public function create_tables() {
                global $wpdb;

                $charset_collate   = $wpdb->get_charset_collate();
                $errors_table      = $this->get_errors_table();
                $occurrences_table = $this->get_occurrences_table();

                $sql_errors = "CREATE TABLE {$errors_table} (
                        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        fingerprint varchar(40) NOT NULL,
                        error_type int NOT NULL,
                        severity_level int NOT NULL,
                        message longtext NOT NULL,
                        message_normalized longtext NOT NULL,
                        file varchar(500) NOT NULL,
                        line int NOT NULL DEFAULT 0,
                        source_type int NOT NULL DEFAULT 0,
                        source_slug varchar(100) NOT NULL DEFAULT '',
                        count int NOT NULL DEFAULT 1,
                        status int NOT NULL DEFAULT 0,
                        first_seen datetime NOT NULL,
                        last_seen datetime NOT NULL,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        UNIQUE KEY fingerprint (fingerprint),
                        KEY last_seen (last_seen),
                        KEY severity_level (severity_level),
                        KEY status (status),
                        KEY source_slug (source_slug)
                ) $charset_collate;";

                $sql_occurrences = "CREATE TABLE {$occurrences_table} (
                        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        error_id bigint(20) unsigned NOT NULL,
                        occurred_at datetime NOT NULL,
                        request_url varchar(500) NOT NULL DEFAULT '',
                        request_method varchar(10) NOT NULL DEFAULT '',
                        context_type int NOT NULL DEFAULT 0,
                        user_id bigint NOT NULL DEFAULT 0,
                        ip_hash varchar(64) NOT NULL DEFAULT '',
                        memory_peak int NOT NULL DEFAULT 0,
                        php_version varchar(20) NOT NULL DEFAULT '',
                        wp_version varchar(20) NOT NULL DEFAULT '',
                        stack_trace longtext NOT NULL DEFAULT '',
                        extra longtext NOT NULL DEFAULT '',
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        KEY error_occurred (error_id, occurred_at)
                ) $charset_collate;";

                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                dbDelta( $sql_errors );
                dbDelta( $sql_occurrences );

                // Save schema version.
                update_option( 'drdbg_db_version', DRDBG_DB_VERSION );
        }

        /**
         * Drop the custom tables.
         *
         * Called during plugin uninstall (not deactivation).
         *
         * @since 1.0.0
         *
         * @global wpdb $wpdb
         */
        public function drop_tables() {
                global $wpdb;

                $occurrences_table = $this->get_occurrences_table();
                $errors_table      = $this->get_errors_table();

                $wpdb->query( "DROP TABLE IF EXISTS {$occurrences_table}" );
                $wpdb->query( "DROP TABLE IF EXISTS {$errors_table}" );
        }

        /**
         * Check and update schema if needed.
         *
         * Compares the current DRDBG_DB_VERSION constant with the saved
         * 'drdbg_db_version' option. If they differ, re-runs dbDelta()
         * to apply any schema changes.
         *
         * @since 1.0.0
         */
        public function maybe_update_schema() {
                $saved_version = get_option( 'drdbg_db_version', '0' );

                if ( version_compare( $saved_version, DRDBG_DB_VERSION, '<' ) ) {
                        $this->create_tables();
                }
        }
}
