<?php
/**
 * Public availability REST endpoints.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Availability_Endpoint {
	/**
	 * Slot generator.
	 *
	 * @var QS_Slot_Generator
	 */
	private QS_Slot_Generator $slot_generator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->slot_generator = new QS_Slot_Generator();
	}

	/**
	 * Registers routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'quickslot/v1',
			'/availability/dates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_dates'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'quickslot/v1',
			'/availability/slots',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_slots'),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Returns available dates for a service and month.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_dates(WP_REST_Request $request) {
		$service_id = QS_Sanitizer::absint((string) $request->get_param('service_id'));
		$month      = QS_Sanitizer::text((string) $request->get_param('month'));

		if ($service_id < 1) {
			return new WP_Error('quickslot_invalid_service', __('A valid service is required.', 'quickslot'), array('status' => 400));
		}

		if (! QS_Sanitizer::is_month($month)) {
			return new WP_Error('quickslot_invalid_month', __('The month must use the YYYY-MM format.', 'quickslot'), array('status' => 400));
		}

		if (! $this->service_is_active($service_id)) {
			return new WP_Error('quickslot_service_not_found', __('The requested service is unavailable.', 'quickslot'), array('status' => 404));
		}

		$cache_key = 'qs_dates_' . $service_id . '_' . $month;
		$cached    = get_transient($cache_key);

		if (is_array($cached)) {
			return new WP_REST_Response(array('success' => true, 'data' => $cached), 200);
		}

		$dates     = array();
		$timezone  = wp_timezone();
		$month_obj = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $month . '-01 00:00:00', $timezone);
		$today     = current_datetime()->format('Y-m-d');

		if (! $month_obj instanceof DateTimeImmutable) {
			return new WP_Error('quickslot_invalid_month', __('The month must use the YYYY-MM format.', 'quickslot'), array('status' => 400));
		}

		$days_in_month = (int) $month_obj->format('t');

		for ($day = 1; $day <= $days_in_month; $day++) {
			$date = $month_obj->setDate((int) $month_obj->format('Y'), (int) $month_obj->format('m'), $day)->format('Y-m-d');

			if ($date < $today) {
				continue;
			}

			$slots = $this->slot_generator->generate($service_id, $date);

			if (! empty($slots)) {
				$dates[] = $date;
			}
		}

		/**
		 * Filters available dates returned by the public endpoint.
		 *
		 * @param array<int, string> $dates      Available dates.
		 * @param int                $service_id Service ID.
		 * @param string             $month      Requested month.
		 */
		$dates = apply_filters('quickslot_available_dates', $dates, $service_id, $month);
		$dates = is_array($dates) ? array_values(array_filter($dates, 'is_string')) : array();

		set_transient($cache_key, $dates, 60);

		return new WP_REST_Response(array('success' => true, 'data' => $dates), 200);
	}

	/**
	 * Returns available slots for a service and date.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_slots(WP_REST_Request $request) {
		$service_id = QS_Sanitizer::absint((string) $request->get_param('service_id'));
		$date       = QS_Sanitizer::text((string) $request->get_param('date'));

		if ($service_id < 1) {
			return new WP_Error('quickslot_invalid_service', __('A valid service is required.', 'quickslot'), array('status' => 400));
		}

		if (! QS_Sanitizer::is_date($date)) {
			return new WP_Error('quickslot_invalid_date', __('The date must use the YYYY-MM-DD format.', 'quickslot'), array('status' => 400));
		}

		if (! $this->service_is_active($service_id)) {
			return new WP_Error('quickslot_service_not_found', __('The requested service is unavailable.', 'quickslot'), array('status' => 404));
		}

		$cache_key = 'qs_slots_' . $service_id . '_' . $date;
		$cached    = get_transient($cache_key);

		if (is_array($cached)) {
			return new WP_REST_Response(array('success' => true, 'data' => $cached), 200);
		}

		$slots = $this->slot_generator->generate($service_id, $date);

		set_transient($cache_key, $slots, 30);

		return new WP_REST_Response(array('success' => true, 'data' => $slots), 200);
	}

	/**
	 * Checks whether a service is active.
	 */
	private function service_is_active(int $service_id): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'qs_services';
		$query = $wpdb->prepare(
			"SELECT id FROM {$table} WHERE id = %d AND status = %s LIMIT 1",
			$service_id,
			'active'
		);
		$row   = $wpdb->get_var($query);

		return null !== $row;
	}
}
