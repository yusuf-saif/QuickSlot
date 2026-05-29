<?php
/**
 * Color settings storage and sanitization.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Color_Settings {
	/**
	 * Option name.
	 */
	private const OPTION_NAME = 'qs_color_settings';

	/**
	 * Returns normalized color settings.
	 *
	 * @return array<string, bool|string>
	 */
	public function get(): array {
		$stored = get_option(self::OPTION_NAME, array());

		if (! is_array($stored)) {
			$stored = array();
		}

		$defaults = $this->get_defaults();

		return array(
			'use_site_theme' => ! empty($stored['use_site_theme']),
			'primary'        => $this->normalize_hex(isset($stored['primary']) ? (string) $stored['primary'] : $defaults['primary'], (string) $defaults['primary']),
			'accent'         => $this->normalize_hex(isset($stored['accent']) ? (string) $stored['accent'] : $defaults['accent'], (string) $defaults['accent']),
		);
	}

	/**
	 * Saves sanitized color settings.
	 *
	 * @param array<string, mixed> $data Submitted values.
	 */
	public function save(array $data): void {
		update_option(self::OPTION_NAME, $this->sanitize($data));
	}

	/**
	 * Sanitizes raw values.
	 *
	 * @param array<string, mixed> $data Submitted values.
	 * @return array<string, bool|string>
	 */
	public function sanitize(array $data): array {
		$defaults = $this->get_defaults();

		return array(
			'use_site_theme' => ! empty($data['use_site_theme']),
			'primary'        => $this->normalize_hex(isset($data['primary']) ? (string) $data['primary'] : (string) $defaults['primary'], (string) $defaults['primary']),
			'accent'         => $this->normalize_hex(isset($data['accent']) ? (string) $data['accent'] : (string) $defaults['accent'], (string) $defaults['accent']),
		);
	}

	/**
	 * Returns default settings.
	 *
	 * @return array<string, bool|string>
	 */
	public function get_defaults(): array {
		return array(
			'use_site_theme' => false,
			'primary'        => '#6366F1',
			'accent'         => '#8B5CF6',
		);
	}

	/**
	 * Normalizes a hex color with fallback.
	 */
	private function normalize_hex(string $value, string $fallback): string {
		$sanitized = sanitize_hex_color($value);

		return is_string($sanitized) && '' !== $sanitized ? strtoupper($sanitized) : strtoupper($fallback);
	}
}
