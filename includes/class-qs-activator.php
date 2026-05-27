<?php
/**
 * Activation handler.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Activator {
	/**
	 * Runs plugin activation tasks.
	 */
	public static function activate(): void {
		QS_Installer::install();
		flush_rewrite_rules();
	}
}
