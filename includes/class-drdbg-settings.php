<?php
/**
 * Dr. Debug — Settings Management
 *
 * Handles plugin settings storage, retrieval, sanitization,
 * and WordPress Settings API registration.
 *
 * @package Dr_Debug
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Drdbg_Settings
 *
 * Singleton class for managing Dr. Debug plugin settings.
 * Settings are stored as a serialized array in the wp_options table
 * under the 'drdbg_settings' option key.
 */
class Drdbg_Settings {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var Drdbg_Settings|null
	 */
	private static $instance = null;

	/**
	 * Default settings values.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $defaults = array(
		'cleanup_days'      => 30,
		'max_occurrences'   => 20,
		'min_severity'      => 2,
		'redaction_enabled' => true,
		'hide_frontend'     => true,
	);

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Drdbg_Settings
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
	 * Get all settings, merged with defaults.
	 *
	 * Returns an associative array of all plugin settings. Any setting
	 * not explicitly saved by the user will fall back to its default value.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of setting key => value pairs.
	 */
	public function get_all() {
		$saved = get_option( 'drdbg_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, $this->defaults );
	}

	/**
	 * Get a single setting value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Optional. Default value if setting not found. Default null.
	 * @return mixed Setting value, or $default if not set.
	 */
	public function get( $key, $default = null ) {
		$settings = $this->get_all();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update settings with sanitization and validation.
	 *
	 * Only known keys are accepted. Values are sanitized according to
	 * their expected type and clamped to valid ranges.
	 *
	 * @since 1.0.0
	 *
	 * @param array $new_settings Associative array of setting key => value pairs to update.
	 * @return array The updated settings array (all settings, merged with defaults).
	 */
	public function update( $new_settings ) {
		$current = $this->get_all();

		if ( isset( $new_settings['cleanup_days'] ) ) {
			$current['cleanup_days'] = max( 1, absint( $new_settings['cleanup_days'] ) );
		}

		if ( isset( $new_settings['max_occurrences'] ) ) {
			$current['max_occurrences'] = max( 1, absint( $new_settings['max_occurrences'] ) );
		}

		if ( isset( $new_settings['min_severity'] ) ) {
			$val = absint( $new_settings['min_severity'] );
			$current['min_severity'] = max( 1, min( 5, $val ) );
		}

		if ( isset( $new_settings['redaction_enabled'] ) ) {
			$current['redaction_enabled'] = (bool) $new_settings['redaction_enabled'];
		}

		if ( isset( $new_settings['hide_frontend'] ) ) {
			$current['hide_frontend'] = (bool) $new_settings['hide_frontend'];
		}

		update_option( 'drdbg_settings', $current );

		return $current;
	}

	/**
	 * Reset all settings to their default values.
	 *
	 * @since 1.0.0
	 *
	 * @return array The default settings array.
	 */
	public function reset() {
		update_option( 'drdbg_settings', $this->defaults );
		return $this->defaults;
	}

	/**
	 * Register settings with the WordPress Settings API.
	 *
	 * Should be called during the 'admin_init' action.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		register_setting(
			'drdbg_settings_group',
			'drdbg_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings callback for the WordPress Settings API.
	 *
	 * Delegates to the update() method for consistent sanitization logic.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Raw input from the settings form.
	 * @return array Sanitized and validated settings.
	 */
	public function sanitize_settings( $input ) {
		return $this->update( $input );
	}
}
