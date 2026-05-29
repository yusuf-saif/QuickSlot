<?php
/**
 * Reminder scheduling and sending.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Reminder_Scheduler {
	/**
	 * Singleton instance.
	 *
	 * @var QS_Reminder_Scheduler|null
	 */
	private static ?QS_Reminder_Scheduler $instance = null;

	/**
	 * Returns the singleton instance.
	 */
	public static function instance(): QS_Reminder_Scheduler {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action('quickslot_booking_created', array($this, 'schedule_for_booking'), 20, 2);
		add_action('quickslot_booking_cancelled', array($this, 'unschedule_for_booking'), 10, 2);
		add_action('quickslot_send_reminder', array($this, 'send_reminder'), 10, 2);
	}

	/**
	 * Schedules reminders for a booking.
	 *
	 * @param array<string, mixed> $booking_data Booking action data.
	 */
	public function schedule_for_booking(int $booking_id, array $booking_data): void {
		$booking_start = isset($booking_data['booking_start']) ? (string) $booking_data['booking_start'] : '';

		if ($booking_id < 1 || '' === $booking_start) {
			return;
		}

		$start_timestamp = strtotime($booking_start . ' UTC');

		if (false === $start_timestamp) {
			return;
		}

		foreach ($this->get_offsets() as $offset) {
			$fire_at = $start_timestamp - $offset;

			if ($fire_at <= time()) {
				continue;
			}

			$this->schedule_single($fire_at, $booking_id, $offset);
		}
	}

	/**
	 * Unschedules pending reminders for a booking.
	 */
	public function unschedule_for_booking(int $booking_id): void {
		if ($booking_id < 1) {
			return;
		}

		foreach ($this->get_offsets() as $offset) {
			if (function_exists('as_unschedule_action')) {
				as_unschedule_action('quickslot_send_reminder', array('booking_id' => $booking_id, 'offset' => $offset), 'quickslot');
			}

			while (false !== wp_next_scheduled('quickslot_send_reminder', array($booking_id, $offset))) {
				wp_unschedule_event((int) wp_next_scheduled('quickslot_send_reminder', array($booking_id, $offset)), 'quickslot_send_reminder', array($booking_id, $offset));
			}
		}
	}

	/**
	 * Sends a reminder if the booking is still eligible.
	 */
	public function send_reminder($booking_id = 0, $offset = 0): void {
		global $wpdb;

		$booking_id = (int) $booking_id;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb) || $booking_id < 1) {
			return;
		}

		$bookings = $wpdb->prefix . 'qs_bookings';
		$query    = $wpdb->prepare(
			"SELECT id, customer_email, status, reminder_sent FROM {$bookings} WHERE id = %d LIMIT 1",
			$booking_id
		);
		$row      = $wpdb->get_row($query, ARRAY_A);

		if (! is_array($row)) {
			return;
		}

		if (in_array((string) $row['status'], array('cancelled', 'completed', 'no-show'), true)) {
			return;
		}

		// Current schema only stores a single reminder_sent flag, so only one successful reminder is tracked.
		if (! empty($row['reminder_sent'])) {
			return;
		}

		$recipient = sanitize_email((string) $row['customer_email']);

		if ('' === $recipient || ! is_email($recipient)) {
			return;
		}

		$sent = QS_Mailer::instance()->send('reminder', $recipient, $booking_id, array('offset' => (int) $offset));

		if (! $sent) {
			return;
		}

		$wpdb->update(
			$bookings,
			array(
				'reminder_sent' => 1,
				'updated_at'    => current_time('mysql'),
			),
			array('id' => $booking_id),
			array('%d', '%s'),
			array('%d')
		);

		do_action('quickslot_reminder_sent', $booking_id);
	}

	/**
	 * Returns configured reminder offsets.
	 *
	 * @return array<int, int>
	 */
	public function get_offsets(): array {
		$stored = get_option('qs_reminder_offsets', array(86400, 7200));

		if (! is_array($stored) || empty($stored)) {
			$stored = array(86400, 7200);
		}

		$offsets = array();

		foreach ($stored as $offset) {
			$seconds = absint((string) $offset);

			if ($seconds > 0) {
				$offsets[] = $seconds;
			}
		}

		return array_values(array_unique($offsets));
	}

	/**
	 * Schedules a single reminder action or event.
	 */
	private function schedule_single(int $fire_at, int $booking_id, int $offset): void {
		if (function_exists('as_schedule_single_action')) {
			as_schedule_single_action($fire_at, 'quickslot_send_reminder', array('booking_id' => $booking_id, 'offset' => $offset), 'quickslot');
			return;
		}

		if (! wp_next_scheduled('quickslot_send_reminder', array($booking_id, $offset))) {
			wp_schedule_single_event($fire_at, 'quickslot_send_reminder', array($booking_id, $offset));
		}
	}
}
