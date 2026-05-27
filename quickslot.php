<?php
/**
 * Plugin Name: QuickSlot
 * Plugin URI: https://example.com/quickslot
 * Description: Appointment booking plugin for WordPress.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: QuickSlot
 * Text Domain: quickslot
 * Domain Path: /languages
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

define('QUICKSLOT_VERSION', '1.0.0');
define('QUICKSLOT_DB_VERSION', '1.0.0');
define('QUICKSLOT_FILE', __FILE__);
define('QUICKSLOT_PATH', plugin_dir_path(__FILE__));
define('QUICKSLOT_URL', plugin_dir_url(__FILE__));
define('QUICKSLOT_BASENAME', plugin_basename(__FILE__));

require_once QUICKSLOT_PATH . 'includes/class-qs-installer.php';
require_once QUICKSLOT_PATH . 'includes/class-qs-activator.php';
require_once QUICKSLOT_PATH . 'includes/class-qs-deactivator.php';
require_once QUICKSLOT_PATH . 'includes/helpers/class-qs-sanitizer.php';
require_once QUICKSLOT_PATH . 'includes/class-quickslot.php';

/**
 * Checks whether the current environment meets plugin requirements.
 */
function quickslot_requirements_met(): bool {
	global $wp_version;

	return version_compare(PHP_VERSION, '8.0', '>=')
		&& isset($wp_version)
		&& version_compare((string) $wp_version, '6.0', '>=');
}

/**
 * Renders an admin notice for unsupported environments.
 */
function quickslot_render_requirements_notice(): void {
	if (! current_user_can('activate_plugins')) {
		return;
	}

	$message = sprintf(
		/* translators: 1: minimum WordPress version, 2: minimum PHP version. */
		esc_html__('QuickSlot requires WordPress %1$s or higher and PHP %2$s or higher.', 'quickslot'),
		esc_html('6.0'),
		esc_html('8.0')
	);

	printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
}

/**
 * Runs the plugin activation routine.
 */
function quickslot_activate(): void {
	if (! quickslot_requirements_met()) {
		wp_die(
			esc_html__('QuickSlot requires WordPress 6.0+ and PHP 8.0+.', 'quickslot'),
			esc_html__('Plugin activation failed', 'quickslot'),
			array(
				'back_link' => true,
			)
		);
	}

	QS_Activator::activate();
}

register_activation_hook(__FILE__, 'quickslot_activate');
register_deactivation_hook(__FILE__, array('QS_Deactivator', 'deactivate'));

if (! quickslot_requirements_met()) {
	add_action('admin_notices', 'quickslot_render_requirements_notice');
	return;
}

QuickSlot::get_instance()->init();
