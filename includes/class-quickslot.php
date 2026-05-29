<?php
/**
 * Main plugin bootstrap class.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QuickSlot {
	/**
	 * Admin bootstrap instance.
	 *
	 * @var QS_Admin|null
	 */
	private ?QS_Admin $admin = null;

	/**
	 * Frontend bootstrap instance.
	 *
	 * @var QS_Frontend|null
	 */
	private ?QS_Frontend $frontend = null;

	/**
	 * REST bootstrap instance.
	 *
	 * @var QS_REST_Controller|null
	 */
	private ?QS_REST_Controller $rest = null;

	/**
	 * Singleton instance.
	 *
	 * @var QuickSlot|null
	 */
	private static ?QuickSlot $instance = null;

	/**
	 * Returns the singleton instance.
	 */
	public static function get_instance(): QuickSlot {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Prevent direct instantiation.
	 */
	private function __construct() {
	}

	/**
	 * Initializes plugin hooks.
	 */
	public function init(): void {
		add_action('plugins_loaded', array($this, 'load_textdomain'));
		add_action('plugins_loaded', array($this, 'maybe_upgrade_database'));
		$this->init_email();
		$this->init_rest();

		if (is_admin()) {
			$this->init_admin();
		} else {
			$this->init_frontend();
		}
	}

	/**
	 * Loads admin functionality.
	 */
	private function init_admin(): void {
		require_once QUICKSLOT_PATH . 'includes/admin/class-qs-admin.php';

		$this->admin = new QS_Admin();
		$this->admin->init();
	}

	/**
	 * Loads frontend functionality.
	 */
	private function init_frontend(): void {
		require_once QUICKSLOT_PATH . 'includes/frontend/class-qs-booking-form.php';
		require_once QUICKSLOT_PATH . 'includes/frontend/class-qs-frontend.php';

		$this->frontend = new QS_Frontend();
		$this->frontend->init();
	}

	/**
	 * Loads REST API functionality.
	 */
	private function init_rest(): void {
		require_once QUICKSLOT_PATH . 'includes/core/class-qs-slot-generator.php';
		require_once QUICKSLOT_PATH . 'includes/core/class-qs-availability-checker.php';
		require_once QUICKSLOT_PATH . 'includes/core/class-qs-booking-handler.php';
		require_once QUICKSLOT_PATH . 'includes/api/class-qs-services-endpoint.php';
		require_once QUICKSLOT_PATH . 'includes/api/class-qs-availability-endpoint.php';
		require_once QUICKSLOT_PATH . 'includes/api/class-qs-bookings-endpoint.php';
		require_once QUICKSLOT_PATH . 'includes/api/class-qs-rest-controller.php';

		$this->rest = new QS_REST_Controller();
		$this->rest->init();
	}

	/**
	 * Loads email functionality.
	 */
	private function init_email(): void {
		require_once QUICKSLOT_PATH . 'includes/email/class-qs-email-templates.php';
		require_once QUICKSLOT_PATH . 'includes/email/class-qs-email-logger.php';
		require_once QUICKSLOT_PATH . 'includes/email/class-qs-mailer.php';
		require_once QUICKSLOT_PATH . 'includes/email/class-qs-reminder-scheduler.php';

		QS_Mailer::instance();
		QS_Reminder_Scheduler::instance();
	}

	/**
	 * Loads plugin translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain('quickslot', false, dirname(QUICKSLOT_BASENAME) . '/languages');
	}

	/**
	 * Runs schema updates when the database version changes.
	 */
	public function maybe_upgrade_database(): void {
		$installed_version = get_option('quickslot_db_version', '');

		if (! is_string($installed_version) || version_compare($installed_version, QUICKSLOT_DB_VERSION, '<')) {
			QS_Installer::install();
		}
	}
}
