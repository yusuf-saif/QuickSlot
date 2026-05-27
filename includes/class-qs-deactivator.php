<?php
/**
 * Deactivation handler.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Deactivator {
	/**
	 * Runs plugin deactivation tasks.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
