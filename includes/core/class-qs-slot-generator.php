<?php
/**
 * Slot generation logic.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Slot_Generator {
	/**
	 * Generates available slots for a service and date.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date_ymd   Date in Y-m-d format.
	 * @return array<int, string>
	 */
	public function generate(int $service_id, string $date_ymd): array {
		if ($service_id < 1 || ! QS_Sanitizer::is_date($date_ymd)) {
			return array();
		}

		$service = $this->get_service($service_id);

		if (null === $service) {
			return array();
		}

		$day_of_week = $this->get_day_of_week($date_ymd);
		$availability = $this->get_weekly_availability($day_of_week);

		if (null === $availability || 1 !== (int) $availability['is_available']) {
			return array();
		}

		$exception = $this->get_exception($date_ymd);

		if (null !== $exception && 0 === (int) $exception['is_available']) {
			return array();
		}

		$start_time = (string) $availability['start_time'];
		$end_time   = (string) $availability['end_time'];

		if (null !== $exception && 1 === (int) $exception['is_available']) {
			$start_time = (string) $exception['start_time'];
			$end_time   = (string) $exception['end_time'];
		}

		if (! QS_Sanitizer::is_time($start_time) || ! QS_Sanitizer::is_time($end_time)) {
			return array();
		}

		$max_bookings = null;

		if (null !== $availability['max_bookings'] && '' !== (string) $availability['max_bookings']) {
			$max_bookings = (int) $availability['max_bookings'];
		}

		$bookings = $this->get_existing_bookings($service_id, $date_ymd);

		if (null !== $max_bookings && count($bookings) >= $max_bookings) {
			return array();
		}

		$buffer_minutes   = max(0, (int) $availability['buffer_minutes']);
		$duration_minutes = max(1, (int) $service['duration']);
		$breaks           = $this->normalize_breaks($availability['breaks']);
		$timezone         = wp_timezone();
		$date             = new DateTimeImmutable($date_ymd, $timezone);
		$start_datetime   = new DateTimeImmutable($date_ymd . ' ' . $start_time, $timezone);
		$end_datetime     = new DateTimeImmutable($date_ymd . ' ' . $end_time, $timezone);
		$now              = current_datetime();
		$step_minutes     = $duration_minutes + $buffer_minutes;
		$slots            = array();

		if ($end_datetime <= $start_datetime || $step_minutes < 1) {
			return array();
		}

		$current = $start_datetime;

		while ($current->modify('+' . $duration_minutes . ' minutes') <= $end_datetime) {
			$slot_end = $current->modify('+' . $duration_minutes . ' minutes');

			if ($date->format('Y-m-d') === $now->format('Y-m-d') && $current <= $now) {
				$current = $current->modify('+' . $step_minutes . ' minutes');
				continue;
			}

			if ($this->overlaps_breaks($current, $slot_end, $date_ymd, $breaks, $timezone)) {
				$current = $current->modify('+' . $step_minutes . ' minutes');
				continue;
			}

			if ($this->overlaps_bookings($current, $slot_end, $bookings)) {
				$current = $current->modify('+' . $step_minutes . ' minutes');
				continue;
			}

			$slots[] = $current->format('H:i');
			$current = $current->modify('+' . $step_minutes . ' minutes');
		}

		/**
		 * Filters the generated available slot list.
		 *
		 * @param array<int, string> $slots      Available slots.
		 * @param int                $service_id Service ID.
		 * @param string             $date_ymd   Requested date.
		 */
		$slots = apply_filters('quickslot_available_slots', $slots, $service_id, $date_ymd);

		return is_array($slots) ? array_values(array_filter($slots, 'is_string')) : array();
	}

	/**
	 * Returns an active service row.
	 *
	 * @param int $service_id Service ID.
	 * @return array<string, mixed>|null
	 */
	private function get_service(int $service_id): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'qs_services';
		$query = $wpdb->prepare(
			"SELECT id, duration, status FROM {$table} WHERE id = %d AND status = %s LIMIT 1",
			$service_id,
			'active'
		);
		$row   = $wpdb->get_row($query, ARRAY_A);

		return is_array($row) ? $row : null;
	}

	/**
	 * Returns weekly availability for a day.
	 *
	 * @param int $day_of_week Day of week mapping.
	 * @return array<string, mixed>|null
	 */
	private function get_weekly_availability(int $day_of_week): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'qs_availability';
		$query = $wpdb->prepare(
			"SELECT is_available, start_time, end_time, breaks, buffer_minutes, max_bookings FROM {$table} WHERE day_of_week = %d LIMIT 1",
			$day_of_week
		);
		$row   = $wpdb->get_row($query, ARRAY_A);

		return is_array($row) ? $row : null;
	}

	/**
	 * Returns a matching exception row, prioritizing the latest created entry.
	 *
	 * @param string $date_ymd Requested date.
	 * @return array<string, mixed>|null
	 */
	private function get_exception(string $date_ymd): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'qs_availability_exceptions';
		$query = $wpdb->prepare(
			"SELECT id, is_available, start_time, end_time FROM {$table} WHERE exception_date = %s ORDER BY id DESC LIMIT 1",
			$date_ymd
		);
		$row   = $wpdb->get_row($query, ARRAY_A);

		return is_array($row) ? $row : null;
	}

	/**
	 * Returns bookings for a service on a date.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date_ymd   Requested date.
	 * @return array<int, array<string, string>>
	 */
	private function get_existing_bookings(int $service_id, string $date_ymd): array {
		global $wpdb;

		$table      = $wpdb->prefix . 'qs_bookings';
		$start_of_day = $date_ymd . ' 00:00:00';
		$end_of_day   = $date_ymd . ' 23:59:59';
		$query      = $wpdb->prepare(
			"SELECT booking_start, booking_end FROM {$table} WHERE service_id = %d AND status IN (%s, %s) AND booking_start <= %s AND booking_end >= %s",
			$service_id,
			'pending',
			'confirmed',
			$end_of_day,
			$start_of_day
		);
		$rows       = $wpdb->get_results($query, ARRAY_A);

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Converts the project day mapping from a date.
	 */
	private function get_day_of_week(string $date_ymd): int {
		$day = (int) wp_date('N', strtotime($date_ymd));

		return 7 === $day ? 0 : $day;
	}

	/**
	 * Normalizes break JSON.
	 *
	 * @param string|null $breaks_json Stored breaks JSON.
	 * @return array<int, array<string, string>>
	 */
	private function normalize_breaks(?string $breaks_json): array {
		if (null === $breaks_json || '' === $breaks_json) {
			return array();
		}

		$decoded = json_decode($breaks_json, true);

		if (! is_array($decoded)) {
			return array();
		}

		$breaks = array();

		foreach ($decoded as $break) {
			if (! is_array($break) || ! isset($break['start'], $break['end'])) {
				continue;
			}

			$start = QS_Sanitizer::text((string) $break['start']);
			$end   = QS_Sanitizer::text((string) $break['end']);

			if (! QS_Sanitizer::is_time($start) || ! QS_Sanitizer::is_time($end)) {
				continue;
			}

			$breaks[] = array(
				'start' => $start,
				'end'   => $end,
			);
		}

		return $breaks;
	}

	/**
	 * Checks slot overlap with breaks.
	 *
	 * @param array<int, array<string, string>> $breaks Break windows.
	 */
	private function overlaps_breaks(DateTimeImmutable $slot_start, DateTimeImmutable $slot_end, string $date_ymd, array $breaks, DateTimeZone $timezone): bool {
		foreach ($breaks as $break) {
			$break_start = new DateTimeImmutable($date_ymd . ' ' . $break['start'], $timezone);
			$break_end   = new DateTimeImmutable($date_ymd . ' ' . $break['end'], $timezone);

			if ($slot_start < $break_end && $slot_end > $break_start) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks slot overlap with existing bookings.
	 *
	 * @param array<int, array<string, string>> $bookings Existing booking windows.
	 */
	private function overlaps_bookings(DateTimeImmutable $slot_start, DateTimeImmutable $slot_end, array $bookings): bool {
		$timezone = wp_timezone();

		foreach ($bookings as $booking) {
			if (! isset($booking['booking_start'], $booking['booking_end'])) {
				continue;
			}

			$booking_start = new DateTimeImmutable((string) $booking['booking_start'], $timezone);
			$booking_end   = new DateTimeImmutable((string) $booking['booking_end'], $timezone);

			if ($slot_start < $booking_end && $slot_end > $booking_start) {
				return true;
			}
		}

		return false;
	}
}
