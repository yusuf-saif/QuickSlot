<?php
/**
 * Shared sanitization helpers.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Sanitizer {
	/**
	 * Sanitizes plain text values.
	 *
	 * @param string $value Raw value.
	 */
	public static function text(string $value): string {
		return sanitize_text_field($value);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {
	}
}
