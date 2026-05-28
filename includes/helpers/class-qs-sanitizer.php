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
	 * Sanitizes textarea values.
	 *
	 * @param string $value Raw value.
	 */
	public static function textarea(string $value): string {
		return sanitize_textarea_field($value);
	}

	/**
	 * Sanitizes an absolute integer value.
	 */
	public static function absint(string $value): int {
		return absint($value);
	}

	/**
	 * Validates a Y-m-d date string.
	 */
	public static function is_date(string $value): bool {
		$parsed = DateTime::createFromFormat('Y-m-d', $value);

		return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $value;
	}

	/**
	 * Validates a Y-m month string.
	 */
	public static function is_month(string $value): bool {
		$parsed = DateTime::createFromFormat('Y-m', $value);

		return $parsed instanceof DateTime && $parsed->format('Y-m') === $value;
	}

	/**
	 * Validates a 24-hour time string.
	 */
	public static function is_time(string $value): bool {
		return 1 === preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {
	}
}
