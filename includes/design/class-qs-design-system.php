<?php
/**
 * Design token overrides and theme inheritance.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Design_System {
	/**
	 * Color settings helper.
	 *
	 * @var QS_Color_Settings
	 */
	private QS_Color_Settings $color_settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->color_settings = new QS_Color_Settings();
	}

	/**
	 * Registers output hooks.
	 */
	public function init(): void {
		add_action('wp_head', array($this, 'render_frontend_css'), 20);
		add_action('admin_head', array($this, 'render_admin_css'), 20);
	}

	/**
	 * Outputs frontend token overrides.
	 */
	public function render_frontend_css(): void {
		echo $this->build_style_tag('.qs-booking-form, .qs-booking-form *');
	}

	/**
	 * Outputs admin preview token overrides on QuickSlot pages.
	 */
	public function render_admin_css(): void {
		if (! is_admin()) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;

		if (! $screen || false === strpos((string) $screen->id, 'quickslot')) {
			return;
		}

		echo $this->build_style_tag('.qs-design-preview, .qs-settings-admin');
	}

	/**
	 * Darkens a hex color.
	 */
	public function darken_hex(string $hex, int $percent): string {
		list($red, $green, $blue) = $this->hex_to_rgb($hex);
		$factor = max(0, min(100, $percent)) / 100;

		return $this->rgb_to_hex(
			(int) round($red * (1 - $factor)),
			(int) round($green * (1 - $factor)),
			(int) round($blue * (1 - $factor))
		);
	}

	/**
	 * Lightens a hex color.
	 */
	public function lighten_hex(string $hex, int $lightness): string {
		list($red, $green, $blue) = $this->hex_to_rgb($hex);
		$factor = max(0, min(100, $lightness)) / 100;

		return $this->rgb_to_hex(
			(int) round($red + ((255 - $red) * $factor)),
			(int) round($green + ((255 - $green) * $factor)),
			(int) round($blue + ((255 - $blue) * $factor))
		);
	}

	/**
	 * Converts hex to rgba().
	 */
	public function hex_to_rgba(string $hex, float $alpha): string {
		list($red, $green, $blue) = $this->hex_to_rgb($hex);
		$alpha = max(0, min(1, $alpha));

		return 'rgba(' . $red . ', ' . $green . ', ' . $blue . ', ' . $alpha . ')';
	}

	/**
	 * Builds a scoped style tag.
	 */
	private function build_style_tag(string $selector): string {
		$settings = $this->color_settings->get();
		$css      = ! empty($settings['use_site_theme'])
			? $this->build_site_theme_css($selector)
			: $this->build_custom_css($selector, (string) $settings['primary'], (string) $settings['accent']);

		if ('' === $css) {
			return '';
		}

		return '<style id="qs-design-system-overrides">' . $css . '</style>';
	}

	/**
	 * Builds theme inheritance CSS.
	 */
	private function build_site_theme_css(string $selector): string {
		$scoped = $selector . '{'
			. '--qs-color-primary:var(--wp--preset--color--primary,var(--global--palette--primary,var(--ast-global-color-0,var(--theme-palette-color-1,var(--global-palette1,#6366F1)))));'
			. '--qs-color-primary-hover:var(--global--palette--primary-dark,var(--ast-global-color-1,var(--theme-palette-color-2,var(--global-palette2,#4F46E5))));'
			. '--qs-color-primary-light:var(--wp--preset--color--secondary,var(--theme-palette-color-6,var(--global-palette9,#E0E7FF)));'
			. '--qs-color-accent:var(--wp--preset--color--secondary,var(--global--palette--secondary,var(--ast-global-color-1,var(--theme-palette-color-2,var(--global-palette2,#8B5CF6)))));'
			. '--qs-color-bg:var(--wp--preset--color--background,var(--global--palette--background,var(--ast-global-color-4,var(--theme-palette-color-7,#F8FAFC))));'
			. '--qs-color-surface:var(--wp--preset--color--base,var(--global--palette--base,var(--ast-global-color-5,var(--theme-palette-color-8,#FFFFFF))));'
			. '--qs-color-text:var(--wp--preset--color--foreground,var(--global--palette--foreground,var(--ast-global-color-3,var(--theme-palette-color-3,#0F172A))));'
			. '--qs-color-muted:var(--global--palette--text-alt,var(--ast-global-color-2,var(--theme-palette-color-4,#64748B)));'
			. '--qs-color-border:var(--global--palette--border,var(--ast-border-color,var(--theme-palette-color-6,#DBE3EF)));'
			. '--qs-font-family-base:var(--wp--preset--font-family--base,var(--global-body-font-family,var(--ast-font-family,var(--theme-font-family,#Inter,"Segoe UI",sans-serif))));'
			. '--qs-radius-md:var(--wp--custom--border-radius,var(--global-radius,0.625rem));'
			. '--qs-radius-lg:var(--wp--custom--border-radius,var(--global-radius,0.875rem));'
			. '--qs-radius-xl:var(--wp--custom--border-radius,var(--global-radius,1.25rem));'
			. '--qs-shadow-focus:0 0 0 3px ' . $this->hex_to_rgba('#6366F1', 0.24) . ';'
			. '}';

		return $scoped;
	}

	/**
	 * Builds custom color CSS.
	 */
	private function build_custom_css(string $selector, string $primary, string $accent): string {
		$primary = sanitize_hex_color($primary) ?: '#6366F1';
		$accent  = sanitize_hex_color($accent) ?: '#8B5CF6';

		return $selector . '{'
			. '--qs-color-primary:' . esc_attr($primary) . ';'
			. '--qs-color-primary-hover:' . esc_attr($this->darken_hex($primary, 12)) . ';'
			. '--qs-color-primary-light:' . esc_attr($this->lighten_hex($primary, 85)) . ';'
			. '--qs-color-accent:' . esc_attr($accent) . ';'
			. '--qs-shadow-focus:0 0 0 3px ' . esc_attr($this->hex_to_rgba($primary, 0.24)) . ';'
			. '}';
	}

	/**
	 * Converts hex to RGB values.
	 *
	 * @return array<int, int>
	 */
	private function hex_to_rgb(string $hex): array {
		$hex = ltrim((string) sanitize_hex_color($hex), '#');

		if (3 === strlen($hex)) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if (6 !== strlen($hex)) {
			$hex = '6366F1';
		}

		return array(
			hexdec(substr($hex, 0, 2)),
			hexdec(substr($hex, 2, 2)),
			hexdec(substr($hex, 4, 2)),
		);
	}

	/**
	 * Converts RGB values to hex.
	 */
	private function rgb_to_hex(int $red, int $green, int $blue): string {
		$red   = max(0, min(255, $red));
		$green = max(0, min(255, $green));
		$blue  = max(0, min(255, $blue));

		return sprintf('#%02X%02X%02X', $red, $green, $blue);
	}
}
