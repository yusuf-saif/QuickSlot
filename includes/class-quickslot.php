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
