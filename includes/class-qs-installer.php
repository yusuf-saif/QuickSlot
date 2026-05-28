<?php
/**
 * Database installer.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Installer {
	/**
	 * Creates or updates plugin database tables.
	 */
	public static function install(): void {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb)) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tables_sql       = self::get_schema($wpdb->prefix, $charset_collate);

		foreach ($tables_sql as $sql) {
			dbDelta($sql);
		}

		update_option('quickslot_db_version', QUICKSLOT_DB_VERSION);
	}

	/**
	 * Returns the schema definitions for dbDelta.
	 *
	 * @param string $prefix           WordPress table prefix.
	 * @param string $charset_collate  Character set and collation.
	 * @return array<int, string>
	 */
	private static function get_schema(string $prefix, string $charset_collate): array {
		$services_table                = $prefix . 'qs_services';
		$availability_table            = $prefix . 'qs_availability';
		$exceptions_table              = $prefix . 'qs_availability_exceptions';
		$bookings_table                = $prefix . 'qs_bookings';
		$calendar_connections_table    = $prefix . 'qs_calendar_connections';
		$email_logs_table              = $prefix . 'qs_email_logs';

		return array(
			"CREATE TABLE {$services_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				description text NULL,
				duration int(11) unsigned NOT NULL DEFAULT 0,
				price decimal(10,2) NULL DEFAULT NULL,
				is_paid tinyint(1) NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'active',
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY status (status)
			) {$charset_collate};",
			// Stores JSON-encoded break data for the day in the breaks column.
			"CREATE TABLE {$availability_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				day_of_week tinyint(3) unsigned NOT NULL,
				is_available tinyint(1) NOT NULL DEFAULT 1,
				start_time time NULL,
				end_time time NULL,
				breaks longtext NULL,
				buffer_minutes int(11) unsigned NOT NULL DEFAULT 0,
				max_bookings int(11) unsigned NULL DEFAULT NULL,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY day_of_week (day_of_week)
			) {$charset_collate};",
			"CREATE TABLE {$exceptions_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				exception_date date NOT NULL,
				is_available tinyint(1) NOT NULL DEFAULT 0,
				start_time time NULL,
				end_time time NULL,
				reason varchar(255) NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY exception_date (exception_date)
			) {$charset_collate};",
			"CREATE TABLE {$bookings_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				service_id bigint(20) unsigned NOT NULL,
				customer_name varchar(255) NOT NULL,
				customer_email varchar(255) NOT NULL,
				customer_phone varchar(50) NULL,
				customer_note text NULL,
				booking_start datetime NOT NULL,
				booking_end datetime NOT NULL,
				timezone varchar(100) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				payment_status varchar(20) NOT NULL DEFAULT 'unpaid',
				booking_source varchar(50) NOT NULL DEFAULT 'website',
				calendar_provider varchar(50) NULL,
				calendar_event_id varchar(191) NULL,
				reminder_sent tinyint(1) NOT NULL DEFAULT 0,
				cancellation_token varchar(64) NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY service_id (service_id),
				KEY booking_start (booking_start),
				KEY status (status),
				KEY customer_email (customer_email),
				KEY cancellation_token (cancellation_token)
			) {$charset_collate};",
			"CREATE TABLE {$calendar_connections_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider varchar(50) NOT NULL,
				calendar_id varchar(191) NOT NULL,
				access_token longtext NULL,
				refresh_token longtext NULL,
				token_expires_at datetime NULL,
				is_connected tinyint(1) NOT NULL DEFAULT 0,
				connected_at datetime NULL,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY provider (provider),
				KEY is_connected (is_connected)
			) {$charset_collate};",
			"CREATE TABLE {$email_logs_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				booking_id bigint(20) unsigned NULL,
				email_type varchar(50) NOT NULL,
				recipient_email varchar(255) NOT NULL,
				subject varchar(255) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				sent_at datetime NULL,
				PRIMARY KEY  (id),
				KEY booking_id (booking_id),
				KEY email_type (email_type),
				KEY status (status)
			) {$charset_collate};",
		);
	}
}
