<?php
/**
 * Dr. Debug — Activator
 *
 * Runs during plugin activation:
 *   1. Creates the custom database tables.
 *   2. Saves default settings.
 *   3. Copies the MU-plugin loader to wp-content/mu-plugins/.
 *   4. Schedules WP-Cron cleanup jobs.
 *
 * During deactivation:
 *   - Removes the MU-plugin loader so error capture stops immediately.
 *   - Unschedules WP-Cron cleanup jobs.
 *
 * @package Dr_Debug
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class Drdbg_Activator {

        /**
         * Activate the plugin.
         *
         * - Create / update database tables via dbDelta.
         * - Save default settings if not already present.
         * - Copy the MU-plugin loader to wp-content/mu-plugins/
         *   so that it loads before regular plugins.
         * - Schedule daily cleanup cron jobs.
         *
         * @since 1.0.0
         */
        public static function activate() {
                // Create / update database tables
                $db = Drdbg_DB::get_instance();
                $db->create_tables();

                // Save default settings if not already present
                if ( get_option( 'drdbg_settings' ) === false ) {
                        $settings = Drdbg_Settings::get_instance();
                        $settings->update( array() ); // uses defaults
                }

                // Copy MU-plugin loader
                self::install_mu_loader();

                // Schedule cron jobs
                $cleanup = Drdbg_Cleanup::get_instance();
                $cleanup->schedule_cleanup();
        }

        /**
         * Deactivate the plugin.
         *
         * - Remove the MU-plugin loader from wp-content/mu-plugins/
         *   so that error capture stops immediately.
         * - Unschedule WP-Cron cleanup jobs.
         *
         * @since 1.0.0
         */
        public static function deactivate() {
                // Remove MU-plugin loader on deactivation
                $loader_dest = WP_CONTENT_DIR . '/mu-plugins/drdbg-loader.php';
                if ( file_exists( $loader_dest ) ) {
                        unlink( $loader_dest );
                }

                // Unschedule cron jobs
                $cleanup = Drdbg_Cleanup::get_instance();
                $cleanup->unschedule_cleanup();
        }

        /**
         * Copy the MU-plugin loader to wp-content/mu-plugins/.
         *
         * This ensures the loader runs before regular plugins, allowing
         * us to capture errors during the plugin-loading phase.
         *
         * Always copies the latest version to handle plugin updates.
         *
         * @since 1.0.0
         */
        private static function install_mu_loader() {
                $mu_plugins_dir = WP_CONTENT_DIR . '/mu-plugins';

                // Create mu-plugins directory if it doesn't exist
                if ( ! file_exists( $mu_plugins_dir ) ) {
                        wp_mkdir_p( $mu_plugins_dir );
                }

                $loader_source = DRDBG_PLUGIN_DIR . 'mu-plugin/drdbg-loader.php';
                $loader_dest   = $mu_plugins_dir . '/drdbg-loader.php';

                // Always copy the latest version (handles updates)
                if ( file_exists( $loader_source ) ) {
                        copy( $loader_source, $loader_dest );
                }
        }
}
