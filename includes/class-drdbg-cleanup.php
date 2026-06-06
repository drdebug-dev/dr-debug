<?php
/**
 * Dr. Debug — Cleanup Logic
 *
 * Implements the multi-layer cleanup strategy: time-based, size-based,
 * resolved errors, log rotation, and WP-Cron scheduling.
 *
 * @package Dr_Debug
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Drdbg_Cleanup
 *
 * Singleton class that provides all cleanup operations for the Dr. Debug plugin:
 *   - Time-based cleanup (errors older than N days)
 *   - Size-based cleanup (trim to max row count)
 *   - Resolved errors cleanup
 *   - Full data purge
 *   - Debug log file rotation
 *   - WP-Cron scheduling for automatic daily cleanup
 */
class Drdbg_Cleanup {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var Drdbg_Cleanup|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Drdbg_Cleanup
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
	 * Time-based cleanup: delete errors older than N days.
	 *
	 * Deletes occurrences (child records) first, then the parent error rows.
	 * Optionally filters by status.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $days   Number of days threshold. Errors with last_seen
	 *                         older than this are deleted.
	 * @param int|null $status Optional. Filter by status constant
	 *                         (e.g. DRDBG_STATUS_RESOLVED). Default null (all statuses).
	 * @return int|false Number of error rows deleted, or false on error.
	 */
	public function cleanup_by_age( $days, $status = null ) {
		global $wpdb;

		$errors_table      = $wpdb->prefix . 'drdbg_errors';
		$occurrences_table = $wpdb->prefix . 'drdbg_occurrences';

		// Build the WHERE clause.
		$where = $wpdb->prepare(
			'last_seen < DATE_SUB(NOW(), INTERVAL %d DAY)',
			$days
		);

		if ( null !== $status ) {
			$where .= $wpdb->prepare( ' AND status = %d', $status );
		}

		// Delete occurrences first (child records).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is already prepared.
		$wpdb->query(
			"DELETE FROM {$occurrences_table}
			 WHERE error_id IN (
				 SELECT id FROM {$errors_table} WHERE {$where}
			 )"
		);

		// Then delete the error rows.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is already prepared.
		$deleted = $wpdb->query(
			"DELETE FROM {$errors_table} WHERE {$where}"
		);

		return $deleted;
	}

	/**
	 * Size-based cleanup: delete oldest errors when row count exceeds limit.
	 *
	 * When the total number of error rows exceeds $max_rows, the oldest
	 * errors (by last_seen) are removed to bring the count back to $max_rows.
	 *
	 * @since 1.0.0
	 *
	 * @param int $max_rows Maximum number of error rows to keep. Default 10000.
	 * @return int Number of error rows deleted.
	 */
	public function cleanup_by_size( $max_rows = 10000 ) {
		global $wpdb;

		$errors_table      = $wpdb->prefix . 'drdbg_errors';
		$occurrences_table = $wpdb->prefix . 'drdbg_occurrences';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is static.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$errors_table}" );

		if ( $count <= $max_rows ) {
			return 0;
		}

		$delete_count = $count - $max_rows;

		// Get IDs to delete (oldest first).
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is static.
				"SELECT id FROM {$errors_table} ORDER BY last_seen ASC LIMIT %d",
				$delete_count
			)
		);

		if ( empty( $ids ) ) {
			return 0;
		}

		$id_list = implode( ',', array_map( 'intval', $ids ) );

		// Delete occurrences for these errors.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $id_list is safely built from intval().
		$wpdb->query( "DELETE FROM {$occurrences_table} WHERE error_id IN ({$id_list})" );

		// Delete the error rows.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $id_list is safely built from intval().
		$wpdb->query( "DELETE FROM {$errors_table} WHERE id IN ({$id_list})" );

		return count( $ids );
	}

	/**
	 * Delete all resolved errors.
	 *
	 * Uses cleanup_by_age() with days=0 and status=RESOLVED to remove
	 * all error groups that have been marked as resolved.
	 *
	 * @since 1.0.0
	 *
	 * @return int|false Number of error rows deleted, or false on error.
	 */
	public function cleanup_resolved() {
		return $this->cleanup_by_age( 0, DRDBG_STATUS_RESOLVED );
	}

	/**
	 * Delete all errors and occurrences.
	 *
	 * Truncates both the occurrences and errors tables.
	 *
	 * @since 1.0.0
	 */
	public function delete_all() {
		global $wpdb;

		$errors_table      = $wpdb->prefix . 'drdbg_errors';
		$occurrences_table = $wpdb->prefix . 'drdbg_occurrences';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are static.
		$wpdb->query( "TRUNCATE TABLE {$occurrences_table}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are static.
		$wpdb->query( "TRUNCATE TABLE {$errors_table}" );
	}

	/**
	 * Debug log file rotation.
	 *
	 * Archives the current debug.log as a gzip-compressed file if it
	 * exceeds 10 MB. Keeps up to $keep_archives rotated files, deleting
	 * the oldest ones beyond that limit.
	 *
	 * @since 1.0.0
	 *
	 * @param int $keep_archives Maximum number of archive files to keep. Default 5.
	 * @return bool True if rotation was performed, false otherwise.
	 */
	public function rotate_debug_log( $keep_archives = 5 ) {
		$log_file = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $log_file ) ) {
			return false;
		}

		// Only rotate if file is larger than 10 MB.
		$size = filesize( $log_file );
		if ( $size < 10 * 1024 * 1024 ) {
			return false;
		}

		// Archive current log with timestamp.
		$archive_name = 'debug.log.' . gmdate( 'Y-m-d-His' ) . '.gz';
		$archive_path = WP_CONTENT_DIR . '/' . $archive_name;

		// Read, compress, and write archive.
		$data       = file_get_contents( $log_file );
		$compressed = gzencode( $data );

		if ( false === $compressed ) {
			return false;
		}

		$written = file_put_contents( $archive_path, $compressed );

		if ( false === $written ) {
			return false;
		}

		// Clear the current log file.
		file_put_contents( $log_file, '' );

		// Clean old archives beyond the keep limit.
		$archives = glob( WP_CONTENT_DIR . '/debug.log.*.gz' );

		if ( is_array( $archives ) && count( $archives ) > $keep_archives ) {
			sort( $archives );
			$to_delete = array_slice( $archives, 0, count( $archives ) - $keep_archives );
			foreach ( $to_delete as $file ) {
				if ( file_exists( $file ) ) {
					unlink( $file );
				}
			}
		}

		return true;
	}

	/**
	 * Schedule WP-Cron jobs for automatic cleanup.
	 *
	 * Should be called during plugin activation. Schedules two daily events:
	 *   - 'drdbg_daily_cleanup'  — Database cleanup (time + size based).
	 *   - 'drdbg_log_rotation'   — Debug log file rotation.
	 *
	 * @since 1.0.0
	 */
	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'drdbg_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'drdbg_daily_cleanup' );
		}

		if ( ! wp_next_scheduled( 'drdbg_log_rotation' ) ) {
			wp_schedule_event( time(), 'daily', 'drdbg_log_rotation' );
		}
	}

	/**
	 * Unschedule all Dr. Debug WP-Cron jobs.
	 *
	 * Should be called during plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public function unschedule_cleanup() {
		wp_clear_scheduled_hook( 'drdbg_daily_cleanup' );
		wp_clear_scheduled_hook( 'drdbg_log_rotation' );
	}

	/**
	 * Run the daily cleanup routine (called by WP-Cron).
	 *
	 * Performs:
	 *   1. Time-based cleanup using the configured cleanup_days setting.
	 *   2. Size-based cleanup (cap at 10,000 error rows).
	 *   3. Debug log file rotation.
	 *
	 * @since 1.0.0
	 */
	public function run_daily_cleanup() {
		$settings = Drdbg_Settings::get_instance()->get_all();

		// Time-based cleanup.
		$this->cleanup_by_age( $settings['cleanup_days'] );

		// Size-based cleanup (max 10,000 rows).
		$this->cleanup_by_size( 10000 );

		// Log rotation.
		$this->rotate_debug_log();
	}

	/**
	 * Run the daily log rotation (called by WP-Cron).
	 *
	 * Separate cron hook so log rotation runs independently
	 * of the database cleanup.
	 *
	 * @since 1.0.0
	 */
	public function run_log_rotation() {
		$this->rotate_debug_log();
	}
}
