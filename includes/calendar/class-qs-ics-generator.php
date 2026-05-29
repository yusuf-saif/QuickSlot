<?php
/**
 * ICS generation and download handling.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_ICS_Generator {
	/**
	 * Singleton instance.
	 *
	 * @var QS_ICS_Generator|null
	 */
	private static ?QS_ICS_Generator $instance = null;

	/**
	 * Returns the singleton instance.
	 */
	public static function instance(): QS_ICS_Generator {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_filter('query_vars', array($this, 'register_query_vars'));
		add_action('template_redirect', array($this, 'handle_download_request'));
	}

	/**
	 * Generates an ICS payload.
	 *
	 * @return string|WP_Error
	 */
	public function generate(int $booking_id) {
		$booking = $this->get_booking($booking_id);

		if (null === $booking) {
			return new WP_Error('booking_not_found', __('Booking not found.', 'quickslot'));
		}

		$site_host      = wp_parse_url(home_url('/'), PHP_URL_HOST);
		$business_name  = sanitize_text_field((string) get_option('qs_business_name', ''));
		$business_email = sanitize_email((string) get_option('qs_business_email', ''));

		if ('' === $business_name) {
			$business_name = get_bloginfo('name');
		}

		if ('' === $business_email || ! is_email($business_email)) {
			$business_email = sanitize_email((string) get_option('admin_email', ''));
		}

		$start = new DateTimeImmutable((string) $booking['booking_start'], new DateTimeZone('UTC'));
		$end   = new DateTimeImmutable((string) $booking['booking_end'], new DateTimeZone('UTC'));
		$local = $start->setTimezone(wp_timezone());

		$summary = $this->escape_text(sprintf(__('%1$s with %2$s', 'quickslot'), (string) $booking['service_name'], (string) $booking['customer_name']));
		$description = $this->escape_text(
			sprintf(
				/* translators: 1: service name, 2: date, 3: time, 4: customer name, 5: customer email, 6: customer phone, 7: business name, 8: business email */
				__("Service: %1$s\nDate: %2$s\nTime: %3$s\nCustomer: %4$s\nEmail: %5$s\nPhone: %6$s\nBusiness: %7$s\nBusiness Email: %8$s", 'quickslot'),
				(string) $booking['service_name'],
				$local->format('Y-m-d'),
				$local->format('H:i'),
				(string) $booking['customer_name'],
				(string) $booking['customer_email'],
				(string) $booking['customer_phone'],
				$business_name,
				$business_email
			)
		);

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//QuickSlot//WordPress//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:REQUEST',
			'BEGIN:VEVENT',
			'UID:' . $booking_id . '@' . $site_host,
			'DTSTAMP:' . gmdate('Ymd\THis\Z'),
			'DTSTART:' . $start->format('Ymd\THis\Z'),
			'DTEND:' . $end->format('Ymd\THis\Z'),
			'SUMMARY:' . $summary,
			'DESCRIPTION:' . $description,
			'ORGANIZER;CN=' . $this->escape_text($business_name) . ':mailto:' . $business_email,
			'STATUS:CONFIRMED',
			'END:VEVENT',
			'END:VCALENDAR',
		);

		$content = implode("\r\n", array_map(array($this, 'fold_line'), $lines)) . "\r\n";

		/**
		 * Filters generated QuickSlot ICS content.
		 *
		 * @param string               $content    ICS content.
		 * @param int                  $booking_id Booking ID.
		 * @param array<string, mixed> $booking    Booking data.
		 */
		$content = apply_filters('quickslot_ics_content', $content, $booking_id, $booking);

		return is_string($content) ? $content : '';
	}

	/**
	 * Returns a token-authenticated download URL.
	 */
	public function get_download_url(int $booking_id): string {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb) || $booking_id < 1) {
			return '';
		}

		$table = $wpdb->prefix . 'qs_bookings';
		$query = $wpdb->prepare("SELECT cancellation_token FROM {$table} WHERE id = %d LIMIT 1", $booking_id);
		$token = $wpdb->get_var($query);

		if (! is_string($token) || '' === $token) {
			return '';
		}

		return add_query_arg(
			array(
				'qs_ics_download' => '1',
				'booking_id'      => $booking_id,
				'token'           => $token,
			),
			home_url('/')
		);
	}

	/**
	 * Registers ICS query vars.
	 *
	 * @param array<int, string> $vars Existing vars.
	 * @return array<int, string>
	 */
	public function register_query_vars(array $vars): array {
		$vars[] = 'qs_ics_download';
		$vars[] = 'booking_id';
		$vars[] = 'token';

		return $vars;
	}

	/**
	 * Handles public ICS downloads.
	 */
	public function handle_download_request(): void {
		if ('1' !== get_query_var('qs_ics_download')) {
			return;
		}

		$booking_id = absint((string) get_query_var('booking_id'));
		$token      = sanitize_text_field((string) get_query_var('token'));
		$booking    = $this->get_booking($booking_id);

		if ($booking_id < 1 || '' === $token || null === $booking) {
			wp_die(esc_html__('Not found.', 'quickslot'), esc_html__('Not found.', 'quickslot'), array('response' => 404));
		}

		$stored_token = isset($booking['cancellation_token']) ? (string) $booking['cancellation_token'] : '';

		if ('' === $stored_token || ! hash_equals($stored_token, $token)) {
			wp_die(esc_html__('Not found.', 'quickslot'), esc_html__('Not found.', 'quickslot'), array('response' => 404));
		}

		$content = $this->generate($booking_id);

		if ($content instanceof WP_Error) {
			wp_die(esc_html__('Not found.', 'quickslot'), esc_html__('Not found.', 'quickslot'), array('response' => 404));
		}

		nocache_headers();
		header('Content-Type: text/calendar; charset=utf-8');
		header('Content-Disposition: attachment; filename=appointment-' . $booking_id . '.ics');

		echo $content;
		exit;
	}

	/**
	 * Loads a booking joined with service.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_booking(int $booking_id): ?array {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb) || $booking_id < 1) {
			return null;
		}

		$bookings = $wpdb->prefix . 'qs_bookings';
		$services = $wpdb->prefix . 'qs_services';
		$query    = $wpdb->prepare(
			"SELECT b.id, b.customer_name, b.customer_email, b.customer_phone, b.booking_start, b.booking_end, b.cancellation_token, s.name AS service_name FROM {$bookings} b LEFT JOIN {$services} s ON s.id = b.service_id WHERE b.id = %d LIMIT 1",
			$booking_id
		);
		$row      = $wpdb->get_row($query, ARRAY_A);

		return is_array($row) ? $row : null;
	}

	/**
	 * Escapes ICS text values.
	 */
	private function escape_text(string $value): string {
		$value = str_replace("\r\n", "\n", $value);
		$value = str_replace("\r", "\n", $value);
		$value = str_replace('\\', '\\\\', $value);
		$value = str_replace(';', '\\;', $value);
		$value = str_replace(',', '\\,', $value);
		$value = str_replace("\n", '\\n', $value);

		return $value;
	}

	/**
	 * Folds ICS lines longer than 75 octets.
	 */
	private function fold_line(string $line): string {
		$folded = '';
		$first  = true;

		while (strlen($line) > 75) {
			$chunk   = function_exists('mb_strcut') ? mb_strcut($line, 0, 75, 'UTF-8') : substr($line, 0, 75);
			$folded .= ($first ? '' : ' ') . $chunk . "\r\n";
			$line    = substr($line, strlen($chunk));
			$first   = false;
		}

		return $folded . ($first ? '' : ' ') . $line;
	}
}
