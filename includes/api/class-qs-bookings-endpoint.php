<?php
/**
 * Public bookings REST endpoint.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Bookings_Endpoint {
	/**
	 * Booking handler.
	 *
	 * @var QS_Booking_Handler
	 */
	private QS_Booking_Handler $booking_handler;

	/**
	 * Availability checker.
	 *
	 * @var QS_Availability_Checker
	 */
	private QS_Availability_Checker $availability_checker;

	/**
	 * ICS generator.
	 *
	 * @var QS_ICS_Generator
	 */
	private QS_ICS_Generator $ics_generator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->booking_handler      = new QS_Booking_Handler();
		$this->availability_checker = new QS_Availability_Checker();
		$this->ics_generator        = QS_ICS_Generator::instance();
	}

	/**
	 * Registers routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'quickslot/v1',
			'/bookings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_booking'),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Creates a booking.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function create_booking(WP_REST_Request $request): WP_REST_Response {
		$payload = $request->get_json_params();

		if (! is_array($payload)) {
			$payload = array();
		}

		$data = array(
			'service_id'     => QS_Sanitizer::absint((string) ($payload['service_id'] ?? '')),
			'booking_date'   => QS_Sanitizer::text((string) ($payload['booking_date'] ?? '')),
			'booking_time'   => QS_Sanitizer::text((string) ($payload['booking_time'] ?? '')),
			'customer_name'  => QS_Sanitizer::text((string) ($payload['customer_name'] ?? '')),
			'customer_email' => QS_Sanitizer::email((string) ($payload['customer_email'] ?? '')),
			'customer_phone' => QS_Sanitizer::text((string) ($payload['customer_phone'] ?? '')),
			'customer_note'  => QS_Sanitizer::textarea((string) ($payload['customer_note'] ?? '')),
			'timezone'       => QS_Sanitizer::timezone((string) ($payload['timezone'] ?? '')),
			'website'        => QS_Sanitizer::text((string) ($payload['website'] ?? '')),
		);

		if ('' !== $data['website']) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array(
						'spam_ignored' => true,
					),
				),
				200
			);
		}

		if ($this->passes_basic_rate_limit_validation($data) && $this->is_rate_limited($request)) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'rate_limited',
					'message' => __('Too many booking attempts. Please try again later.', 'quickslot'),
				),
				429
			);
		}

		$errors = $this->validate($data);

		if (! empty($errors)) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'validation_error',
					'message' => __('Please review the highlighted fields and try again.', 'quickslot'),
					'errors'  => $errors,
				),
				422
			);
		}

		if (! $this->availability_checker->is_available($data['service_id'], $data['booking_date'], $data['booking_time'])) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'slot_unavailable',
					'message' => __('This time slot is no longer available.', 'quickslot'),
				),
				409
			);
		}

		$booking_id = $this->booking_handler->create($data);

		if ($booking_id instanceof WP_Error) {
			if ('slot_unavailable' === $booking_id->get_error_code()) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'code'    => 'slot_unavailable',
						'message' => __('This time slot is no longer available.', 'quickslot'),
					),
					409
				);
			}

			if ('validation_error' === $booking_id->get_error_code()) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'code'    => 'validation_error',
						'message' => __('Please review the highlighted fields and try again.', 'quickslot'),
						'errors'  => array(),
					),
					422
				);
			}

			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'booking_creation_failed',
					'message' => __('Unable to create the booking right now.', 'quickslot'),
				),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'booking_id' => (int) $booking_id,
					'ics_url'    => $this->ics_generator->get_download_url((int) $booking_id),
				),
			),
			201
		);
	}

	/**
	 * Validates sanitized request data.
	 *
	 * @param array<string, mixed> $data Request data.
	 * @return array<string, string>
	 */
	private function validate(array $data): array {
		$errors = array();
		$today  = current_datetime()->format('Y-m-d');

		if ((int) $data['service_id'] < 1 || ! $this->service_is_active((int) $data['service_id'])) {
			$errors['service_id'] = __('Please choose an available service.', 'quickslot');
		}

		if (! QS_Sanitizer::is_date((string) $data['booking_date'])) {
			$errors['booking_date'] = __('Please choose a valid booking date.', 'quickslot');
		} elseif ((string) $data['booking_date'] < $today) {
			$errors['booking_date'] = __('Please choose today or a future date.', 'quickslot');
		}

		if (! QS_Sanitizer::is_time((string) $data['booking_time'])) {
			$errors['booking_time'] = __('Please choose a valid booking time.', 'quickslot');
		}

		if ('' === (string) $data['customer_name']) {
			$errors['customer_name'] = __('Please enter your name.', 'quickslot');
		}

		if ('' === (string) $data['customer_email']) {
			$errors['customer_email'] = __('Please enter your email address.', 'quickslot');
		} elseif (! is_email((string) $data['customer_email'])) {
			$errors['customer_email'] = __('Please enter a valid email address.', 'quickslot');
		}

		return $errors;
	}

	/**
	 * Checks whether a service is active.
	 */
	private function service_is_active(int $service_id): bool {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb) || $service_id < 1) {
			return false;
		}

		$table = $wpdb->prefix . 'qs_services';
		$query = $wpdb->prepare(
			"SELECT id FROM {$table} WHERE id = %d AND status = %s LIMIT 1",
			$service_id,
			'active'
		);
		$row   = $wpdb->get_var($query);

		return null !== $row;
	}

	/**
	 * Limits counting to attempts that at least include the required booking fields.
	 */
	private function passes_basic_rate_limit_validation(array $data): bool {
		return (int) $data['service_id'] > 0
			&& '' !== (string) $data['booking_date']
			&& '' !== (string) $data['booking_time']
			&& '' !== (string) $data['customer_name']
			&& '' !== (string) $data['customer_email'];
	}

	/**
	 * Limits repeated anonymous booking attempts for cached/public pages.
	 */
	private function is_rate_limited(WP_REST_Request $request): bool {
		$ip_address = $this->get_client_ip($request);
		$cache_key  = 'qs_booking_rate_' . hash('sha256', $ip_address . wp_salt('auth'));
		$attempts   = get_transient($cache_key);

		if (! is_int($attempts)) {
			$attempts = 0;
		}

		if ($attempts >= 10) {
			return true;
		}

		set_transient($cache_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);

		return false;
	}

	/**
	 * Returns the best available client IP for coarse rate limiting.
	 */
	private function get_client_ip(WP_REST_Request $request): string {
		$forwarded_ip = $request->get_header('cf-connecting-ip');

		if ('' === $forwarded_ip) {
			$forwarded_ip = $request->get_header('x-forwarded-for');
		}

		if ('' !== $forwarded_ip) {
			$parts = array_map('trim', explode(',', $forwarded_ip));
			$ip    = $parts[0] ?? '';

			if ('' !== $ip) {
				return sanitize_text_field($ip);
			}
		}

		$remote_addr = $request->get_header('x-real-ip');

		if ('' === $remote_addr) {
			$server_params = $request->get_server_params();
			$remote_addr   = isset($server_params['REMOTE_ADDR']) ? (string) $server_params['REMOTE_ADDR'] : '';
		}

		return '' !== $remote_addr ? sanitize_text_field($remote_addr) : 'unknown';
	}
}
