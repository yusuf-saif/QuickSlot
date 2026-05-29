<?php
/**
 * Booking creation logic.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Booking_Handler {
	/**
	 * Availability checker.
	 *
	 * @var QS_Availability_Checker
	 */
	private QS_Availability_Checker $availability_checker;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->availability_checker = new QS_Availability_Checker();
	}

	/**
	 * Creates a booking row.
	 *
	 * @param array<string, mixed> $data Sanitized booking data.
	 * @return int|WP_Error
	 */
	public function create(array $data) {
		global $wpdb;

		$service_id      = isset($data['service_id']) ? (int) $data['service_id'] : 0;
		$booking_date    = isset($data['booking_date']) ? (string) $data['booking_date'] : '';
		$booking_time    = isset($data['booking_time']) ? (string) $data['booking_time'] : '';
		$customer_name   = isset($data['customer_name']) ? (string) $data['customer_name'] : '';
		$customer_email  = isset($data['customer_email']) ? (string) $data['customer_email'] : '';
		$customer_phone  = isset($data['customer_phone']) ? (string) $data['customer_phone'] : '';
		$customer_note   = isset($data['customer_note']) ? (string) $data['customer_note'] : '';
		$customer_tz     = isset($data['timezone']) ? (string) $data['timezone'] : '';
		$status          = isset($data['status']) ? (string) $data['status'] : 'pending';
		$booking_source  = isset($data['booking_source']) ? (string) $data['booking_source'] : 'frontend';

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb)) {
			return new WP_Error('booking_creation_failed', __('Unable to create the booking right now.', 'quickslot'));
		}

		if (! in_array($status, array('pending', 'confirmed', 'cancelled', 'completed', 'no-show'), true)) {
			$status = 'pending';
		}

		if (! in_array($booking_source, array('frontend', 'admin'), true)) {
			$booking_source = 'frontend';
		}

		$service = $this->get_active_service($service_id);

		if (null === $service) {
			return new WP_Error('validation_error', __('The selected service is unavailable.', 'quickslot'));
		}

		if (! $this->availability_checker->is_available($service_id, $booking_date, $booking_time)) {
			return new WP_Error('slot_unavailable', __('This time slot is no longer available.', 'quickslot'));
		}

		$site_timezone = wp_timezone();
		$utc_timezone  = new DateTimeZone('UTC');
		$start_local   = DateTimeImmutable::createFromFormat('Y-m-d H:i', $booking_date . ' ' . $booking_time, $site_timezone);

		if (! $start_local instanceof DateTimeImmutable) {
			return new WP_Error('validation_error', __('The selected booking time is invalid.', 'quickslot'));
		}

		$duration_minutes = max(1, (int) $service['duration']);
		$end_local        = $start_local->modify('+' . $duration_minutes . ' minutes');
		$start_utc        = $start_local->setTimezone($utc_timezone)->format('Y-m-d H:i:s');
		$end_utc          = $end_local->setTimezone($utc_timezone)->format('Y-m-d H:i:s');
		$timezone_value   = '' !== $customer_tz ? $customer_tz : wp_timezone_string();

		try {
			$cancellation_token = bin2hex(random_bytes(32));
		} catch (Exception $exception) {
			return new WP_Error('booking_creation_failed', __('Unable to create the booking right now.', 'quickslot'));
		}

		$bookings_table       = $wpdb->prefix . 'qs_bookings';
		$supports_transactions = $this->table_supports_transactions($bookings_table);

		if ($supports_transactions && false === $wpdb->query('START TRANSACTION')) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$supports_transactions = false;
		}

		if ($supports_transactions) {
			// Lock the service row so concurrent writes for the same service serialize even
			// when no overlapping booking row exists yet.
			$service = $this->get_active_service($service_id, true);

			if (null === $service) {
				$wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				return new WP_Error('validation_error', __('The selected service is unavailable.', 'quickslot'));
			}

			$duration_minutes = max(1, (int) $service['duration']);
			$end_local        = $start_local->modify('+' . $duration_minutes . ' minutes');
			$end_utc          = $end_local->setTimezone($utc_timezone)->format('Y-m-d H:i:s');
		}

		if ($this->has_overlapping_booking($service_id, $start_utc, $end_utc, $supports_transactions)) {
			if ($supports_transactions) {
				$wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			return new WP_Error('slot_unavailable', __('This time slot is no longer available.', 'quickslot'));
		}

		$inserted = $this->insert_booking(
			$bookings_table,
			$service_id,
			$customer_name,
			$customer_email,
			$customer_phone,
			$customer_note,
			$start_utc,
			$end_utc,
			$timezone_value,
			$status,
			$booking_source,
			$cancellation_token
		);

		if (false === $inserted) {
			if ($supports_transactions) {
				$wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			return new WP_Error('booking_creation_failed', __('Unable to create the booking right now.', 'quickslot'));
		}

		if ($supports_transactions && false === $wpdb->query('COMMIT')) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query('ROLLBACK'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			return new WP_Error('booking_creation_failed', __('Unable to create the booking right now.', 'quickslot'));
		}

		$booking_id   = (int) $wpdb->insert_id;
		$month_key    = substr($booking_date, 0, 7);
		$booking_data = array(
			'id'                 => $booking_id,
			'service_id'         => $service_id,
			'customer_name'      => $customer_name,
			'customer_email'     => $customer_email,
			'customer_phone'     => $customer_phone,
			'customer_note'      => $customer_note,
			'booking_date'       => $booking_date,
			'booking_time'       => $booking_time,
			'booking_start'      => $start_utc,
			'booking_end'        => $end_utc,
			'timezone'           => $timezone_value,
			'status'             => $status,
			'payment_status'     => 'unpaid',
			'booking_source'     => $booking_source,
			'cancellation_token' => $cancellation_token,
		);

		delete_transient('qs_slots_' . $service_id . '_' . $booking_date);
		delete_transient('qs_dates_' . $service_id . '_' . $month_key);

		do_action('quickslot_booking_created', $booking_id, $booking_data);

		if ('confirmed' === $status) {
			do_action('quickslot_booking_confirmed', $booking_id);
		}

		return $booking_id;
	}

	/**
	 * Returns whether the bookings table engine supports transactions.
	 */
	private function table_supports_transactions(string $table_name): bool {
		global $wpdb;

		$query = $wpdb->prepare('SHOW TABLE STATUS LIKE %s', $table_name);
		$row   = $wpdb->get_row($query, ARRAY_A);

		if (! is_array($row) || empty($row['Engine'])) {
			return false;
		}

		$engine = strtolower((string) $row['Engine']);

		return in_array($engine, array('innodb', 'ndb', 'ndbcluster'), true);
	}

	/**
	 * Checks for an overlapping pending or confirmed booking.
	 *
	 * When transactions are available, FOR UPDATE helps serialize competing writes
	 * against the overlapping row set. Without transactional engines, this is still
	 * the final immediate server-side check before insert, but cannot guarantee full
	 * atomicity on engines such as MyISAM.
	 */
	private function has_overlapping_booking(int $service_id, string $start_utc, string $end_utc, bool $lock_rows): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'qs_bookings';
		$sql   = "SELECT id FROM {$table} WHERE service_id = %d AND status IN (%s, %s) AND booking_start < %s AND booking_end > %s LIMIT 1";

		if ($lock_rows) {
			$sql .= ' FOR UPDATE';
		}

		$query = $wpdb->prepare(
			$sql,
			$service_id,
			'pending',
			'confirmed',
			$end_utc,
			$start_utc
		);
		$row   = $wpdb->get_var($query);

		return null !== $row;
	}

	/**
	 * Inserts the booking row.
	 */
	private function insert_booking(
		string $table,
		int $service_id,
		string $customer_name,
		string $customer_email,
		string $customer_phone,
		string $customer_note,
		string $start_utc,
		string $end_utc,
		string $timezone_value,
		string $status,
		string $booking_source,
		string $cancellation_token
	): bool {
		global $wpdb;

		return false !== $wpdb->insert(
			$table,
			array(
				'service_id'         => $service_id,
				'customer_name'      => $customer_name,
				'customer_email'     => $customer_email,
				'customer_phone'     => '' !== $customer_phone ? $customer_phone : null,
				'customer_note'      => '' !== $customer_note ? $customer_note : null,
				'booking_start'      => $start_utc,
				'booking_end'        => $end_utc,
				'timezone'           => $timezone_value,
				'status'             => $status,
				'payment_status'     => 'unpaid',
				'booking_source'     => $booking_source,
				'cancellation_token' => $cancellation_token,
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}

	/**
	 * Returns an active service row.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_active_service(int $service_id, bool $lock_row = false): ?array {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb) || $service_id < 1) {
			return null;
		}

		$table = $wpdb->prefix . 'qs_services';
		$sql   = "SELECT id, duration, status FROM {$table} WHERE id = %d AND status = %s LIMIT 1";

		if ($lock_row) {
			$sql .= ' FOR UPDATE';
		}

		$query = $wpdb->prepare(
			$sql,
			$service_id,
			'active'
		);
		$row   = $wpdb->get_row($query, ARRAY_A);

		return is_array($row) ? $row : null;
	}
}
