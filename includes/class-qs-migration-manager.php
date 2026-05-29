<?php
/**
 * Ordered database migration manager.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Migration_Manager {
	/**
	 * Stored plugin version option.
	 */
	private const PLUGIN_VERSION_OPTION = 'quickslot_version';

	/**
	 * Stored database version option.
	 */
	private const DB_VERSION_OPTION = 'quickslot_db_version';

	/**
	 * Runs base schema installation and any pending ordered migrations.
	 */
	public static function run(): void {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb)) {
			return;
		}

		if (! QS_Installer::install()) {
			return;
		}

		$installed_db_version = get_option(self::DB_VERSION_OPTION, '');
		$installed_db_version = is_string($installed_db_version) && '' !== $installed_db_version ? $installed_db_version : '0.0.0';

		foreach (self::get_migrations() as $version => $method) {
			if (version_compare($installed_db_version, $version, '>=')) {
				continue;
			}

			if (! self::$method()) {
				return;
			}

			update_option(self::DB_VERSION_OPTION, $version);
			$installed_db_version = $version;
		}

		if (version_compare($installed_db_version, QUICKSLOT_DB_VERSION, '<')) {
			update_option(self::DB_VERSION_OPTION, QUICKSLOT_DB_VERSION);
		}

		update_option(self::PLUGIN_VERSION_OPTION, QUICKSLOT_VERSION);
	}

	/**
	 * Returns ordered migration callbacks keyed by target version.
	 *
	 * @return array<string, string>
	 */
	private static function get_migrations(): array {
		return array(
			'1.0.1' => 'migrate_1_0_1',
		);
	}

	/**
	 * Aligns early booking and availability schema differences with the current model.
	 */
	private static function migrate_1_0_1(): bool {
		global $wpdb;

		$bookings_table     = $wpdb->prefix . 'qs_bookings';
		$services_table     = $wpdb->prefix . 'qs_services';
		$availability_table = $wpdb->prefix . 'qs_availability';

		if (! self::ensure_customer_note_column($bookings_table)) {
			return false;
		}

		if (! self::ensure_nullable_price($services_table)) {
			return false;
		}

		if (! self::ensure_nullable_max_bookings($availability_table)) {
			return false;
		}

		return true;
	}

	/**
	 * Ensures the canonical customer_note column exists and retains old data.
	 */
	private static function ensure_customer_note_column(string $table_name): bool {
		global $wpdb;

		$customer_note  = self::get_column_definition($table_name, 'customer_note');
		$customer_notes = self::get_column_definition($table_name, 'customer_notes');

		if (null === $customer_note && null === $customer_notes) {
			return false !== $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN customer_note TEXT NULL AFTER customer_phone"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if (null === $customer_note && null !== $customer_notes) {
			return false !== $wpdb->query("ALTER TABLE {$table_name} CHANGE COLUMN customer_notes customer_note TEXT NULL"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if (null !== $customer_note && null !== $customer_notes) {
			$updated = $wpdb->query("UPDATE {$table_name} SET customer_note = customer_notes WHERE (customer_note IS NULL OR customer_note = '') AND customer_notes IS NOT NULL AND customer_notes <> ''"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if (false === $updated) {
				return false;
			}

			return false !== $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN customer_notes"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return true;
	}

	/**
	 * Ensures the service price column remains nullable.
	 */
	private static function ensure_nullable_price(string $table_name): bool {
		$column = self::get_column_definition($table_name, 'price');

		if (null === $column) {
			return true;
		}

		if ('YES' === strtoupper((string) $column['Null']) && 'decimal(10,2)' === strtolower((string) $column['Type'])) {
			return true;
		}

		return self::alter_column_nullable_decimal($table_name, 'price');
	}

	/**
	 * Ensures max_bookings is nullable for blank/unlimited day settings.
	 */
	private static function ensure_nullable_max_bookings(string $table_name): bool {
		$column = self::get_column_definition($table_name, 'max_bookings');

		if (null === $column) {
			return true;
		}

		if ('YES' === strtoupper((string) $column['Null']) && 'int(11) unsigned' === strtolower((string) $column['Type'])) {
			return true;
		}

		global $wpdb;

		return false !== $wpdb->query("ALTER TABLE {$table_name} MODIFY COLUMN max_bookings INT(11) UNSIGNED NULL DEFAULT NULL"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns a column definition row.
	 *
	 * @return array<string, string>|null
	 */
	private static function get_column_definition(string $table_name, string $column_name): ?array {
		global $wpdb;

		$query = $wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $column_name);
		$row   = $wpdb->get_row($query, ARRAY_A);

		return is_array($row) ? $row : null;
	}

	/**
	 * Applies the canonical nullable decimal definition.
	 */
	private static function alter_column_nullable_decimal(string $table_name, string $column_name): bool {
		global $wpdb;

		return false !== $wpdb->query("ALTER TABLE {$table_name} MODIFY COLUMN {$column_name} DECIMAL(10,2) NULL DEFAULT NULL"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
