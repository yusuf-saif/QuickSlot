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

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb)) {
			return new WP_Error('booking_creation_failed', __('Unable to create the booking right now.', 'quickslot'));
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

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'qs_bookings',
			array(
				'service_id'          => $service_id,
				'customer_name'       => $customer_name,
				'customer_email'      => $customer_email,
				'customer_phone'      => '' !== $customer_phone ? $customer_phone : null,
				'customer_note'       => '' !== $customer_note ? $customer_note : null,
				'booking_start'       => $start_utc,
				'booking_end'         => $end_utc,
				'timezone'            => $timezone_value,
				'status'              => 'pending',
				'payment_status'      => 'unpaid',
				'booking_source'      => 'frontend',
				'cancellation_token'  => $cancellation_token,
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

		if (false === $inserted) {
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
			'status'             => 'pending',
			'payment_status'     => 'unpaid',
			'booking_source'     => 'frontend',
			'cancellation_token' => $cancellation_token,
		);

		delete_transient('qs_slots_' . $service_id . '_' . $booking_date);
		delete_transient('qs_dates_' . $service_id . '_' . $month_key);

		do_action('quickslot_booking_created', $booking_id, $booking_data);

		return $booking_id;
	}

	/**
	 * Returns an active service row.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_active_service(int $service_id): ?array {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb) || $service_id < 1) {
			return null;
		}

		$table = $wpdb->prefix . 'qs_services';
		$query = $wpdb->prepare(
			"SELECT id, duration, status FROM {$table} WHERE id = %d AND status = %s LIMIT 1",
			$service_id,
			'active'
		);
		$row   = $wpdb->get_row($query, ARRAY_A);

		return is_array($row) ? $row : null;
	}
}
