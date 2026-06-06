<?php
/**
 * Dr. Debug — Admin Page Registration
 *
 * Registers the admin menu, enqueues scripts/styles, renders the
 * admin page shell, and adds the admin bar indicator.
 *
 * @package Dr_Debug
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Drdbg_Admin
 *
 * Singleton class that manages the WordPress admin interface for Dr. Debug.
 * Handles menu registration, asset enqueueing, admin bar indicator,
 * and the single-page app shell rendering.
 */
class Drdbg_Admin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var Drdbg_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Drdbg_Admin
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
	 * Register admin hooks.
	 *
	 * Should be called during the 'init' or 'admin_init' action.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_indicator' ), 999 );
	}

	/**
	 * Add admin menu page and submenu pages.
	 *
	 * @since 1.0.0
	 */
	public function add_menu() {
		add_menu_page(
			'دکتر دیباگ',           // Page title.
			'دکتر دیباگ',           // Menu title.
			'manage_options',       // Capability.
			'dr-debug',             // Menu slug.
			array( $this, 'render_page' ), // Callback.
			'dashicons-shield',     // Icon.
			80                      // Position.
		);

		// Submenu pages.
		add_submenu_page(
			'dr-debug',
			'داشبورد',
			'داشبورد',
			'manage_options',
			'dr-debug',
			array( $this, 'render_page' )
		);
		add_submenu_page(
			'dr-debug',
			'خطاها',
			'خطاها',
			'manage_options',
			'dr-debug-errors',
			array( $this, 'render_page' )
		);
		add_submenu_page(
			'dr-debug',
			'تنظیمات',
			'تنظیمات',
			'manage_options',
			'dr-debug-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS.
	 *
	 * Only loads on Dr. Debug admin pages (hook contains 'dr-debug').
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'dr-debug' ) === false ) {
			return;
		}

		// Brand fonts — Vazirmatn (Fa), Inter (En UI), Roboto Mono (code).
		wp_enqueue_style(
			'drdbg-fonts',
			'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Roboto+Mono:wght@600&family=Vazirmatn:wght@400;600;700&display=swap',
			array(),
			null
		);

		// CSS.
		wp_enqueue_style(
			'drdbg-admin',
			DRDBG_PLUGIN_URL . 'assets/css/drdbg-admin.css',
			array( 'drdbg-fonts' ),
			DRDBG_VERSION
		);

		// JS — depends on jQuery (bundled with WordPress).
		wp_enqueue_script(
			'drdbg-admin',
			DRDBG_PLUGIN_URL . 'assets/js/drdbg-admin.js',
			array( 'jquery' ),
			DRDBG_VERSION,
			true
		);

		// Localize script with AJAX URL, nonce, and Persian strings.
		wp_localize_script( 'drdbg-admin', 'drdbg', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'drdbg_admin_nonce' ),
			'strings'  => array(
				'confirm_delete'      => 'آیا از حذف اطمینان دارید؟',
				'confirm_delete_all'  => 'آیا از حذف همه خطاها اطمینان دارید؟ این عمل قابل بازگشت نیست.',
				'confirm_cleanup'     => 'آیا از پاکسازی خطاهای قدیمی اطمینان دارید؟',
				'status_updated'      => 'وضعیت به‌روز شد.',
				'error_occurred'      => 'خطایی رخ داد.',
				'no_errors'           => 'خطایی یافت نشد.',
				'loading'             => 'در حال بارگذاری...',
				'saved'               => 'تنظیمات ذخیره شد.',
				'deleted'             => 'خطاها حذف شدند.',
				'batch_updated'       => 'وضعیت خطاها به‌روز شد.',
				'seeded'              => 'داده‌های نمونه ایجاد شدند.',
				'cleaned'             => 'پاکسازی انجام شد.',
				'reset_confirm'       => 'آیا از بازنشانی تنظیمات به پیش‌فرض اطمینان دارید؟',
				'copied'              => 'در کلیپ‌بورد کپی شد.',
				'copy_failed'         => 'کپی انجام نشد.',
				'copy'                => 'کپی',
				'copy_summary'        => 'کپی خلاصه',
				'copy_all'            => 'کپی همه جزئیات',
				'copy_occurrence'     => 'کپی وقوع',
			),
			'page'     => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'dr-debug',
		) );
	}

	/**
	 * Add admin bar indicator for admins.
	 *
	 * Shows a badge with the count of new (unseen) errors.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar object.
	 */
	public function add_admin_bar_indicator( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$db        = Drdbg_DB::get_instance();
		$new_count = $db->get_new_count();

		$wp_admin_bar->add_node( array(
			'id'    => 'drdbg-indicator',
			'title' => sprintf(
				'<span class="drdbg-bar-icon">🐛</span> %s %s',
				'دکتر دیباگ',
				$new_count > 0 ? sprintf( '<span class="drdbg-badge">%d</span>', $new_count ) : ''
			),
			'href'  => admin_url( 'admin.php?page=dr-debug-errors' ),
			'meta'  => array(
				'class' => 'drdbg-admin-bar',
			),
		) );
	}

	/**
	 * Render the admin page shell.
	 *
	 * The actual content is rendered by JavaScript (single-page app).
	 *
	 * @since 1.0.0
	 */
	public function render_page() {
		echo '<div class="wrap drdbg-wrap" dir="rtl">';
		echo '<div id="drdbg-app"></div>';
		echo '</div>';
	}
}
