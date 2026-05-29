<?php
/**
 * Uninstall QuickSlot.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

if (! function_exists('get_option') || ! function_exists('delete_option')) {
	exit;
}

if (! get_option('quickslot_delete_data_on_uninstall', false)) {
	return;
}

global $wpdb;

if (! isset($wpdb) || ! ($wpdb instanceof wpdb)) {
	return;
}

$tables = array(
	$wpdb->prefix . 'qs_services',
	$wpdb->prefix . 'qs_availability',
	$wpdb->prefix . 'qs_availability_exceptions',
	$wpdb->prefix . 'qs_bookings',
	$wpdb->prefix . 'qs_calendar_connections',
	$wpdb->prefix . 'qs_email_logs',
);

foreach ($tables as $table) {
	$wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option('quickslot_db_version');
delete_option('quickslot_version');
