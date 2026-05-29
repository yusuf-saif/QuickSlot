<?php
/**
 * Booking email sender.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Mailer {
	/**
	 * Singleton instance.
	 *
	 * @var QS_Mailer|null
	 */
	private static ?QS_Mailer $instance = null;

	/**
	 * Template helper.
	 *
	 * @var QS_Email_Templates
	 */
	private QS_Email_Templates $templates;

	/**
	 * Active from email.
	 */
	private string $from_email = '';

	/**
	 * Active from name.
	 */
	private string $from_name = '';

	/**
	 * Returns the singleton instance.
	 */
	public static function instance(): QS_Mailer {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->templates = new QS_Email_Templates();

		add_action('quickslot_booking_created', array($this, 'handle_booking_created'), 10, 2);
		add_action('quickslot_booking_cancelled', array($this, 'handle_booking_cancelled'), 10, 2);
	}

	/**
	 * Sends an email for a booking.
	 *
	 * @param array<string, mixed> $extra Extra placeholder data.
	 */
	public function send(string $type, string $recipient, int $booking_id, array $extra = array()): bool {
		$template  = $this->templates->get_template($type);
		$recipient = sanitize_email($recipient);
		$booking   = $this->get_booking_email_data($booking_id, $extra);
		$headers   = array('Content-Type: text/html; charset=UTF-8');
		$attachments = array();
		$temp_ics_path = '';

		if ('' === $recipient || ! is_email($recipient) || '' === $template['subject'] || '' === $template['body'] || empty($booking)) {
			QS_Email_Logger::log($booking_id, $type, $recipient, $template['subject'], 'failed');
			return false;
		}

		$subject = wp_strip_all_tags($this->templates->replace_placeholders($template['subject'], $booking));
		$body    = wp_kses_post($this->templates->replace_placeholders($template['body'], $booking));

		if ('confirmation' === $type) {
			$ics_path = QS_ICS_Generator::instance()->create_temp_file($booking_id);

			if (is_string($ics_path) && '' !== $ics_path) {
				$temp_ics_path = $ics_path;
				$attachments[] = $ics_path;
			}
		}

		$this->from_name  = (string) $booking['business_name'];
		$this->from_email = (string) $booking['business_email'];

		add_filter('wp_mail_from', array($this, 'filter_from_email'));
		add_filter('wp_mail_from_name', array($this, 'filter_from_name'));

		/**
		 * Fires before QuickSlot sends an email.
		 *
		 * @param string               $type      Email type.
		 * @param string               $recipient Recipient email.
		 * @param int                  $booking_id Booking ID.
		 * @param array<string, mixed> $booking   Booking placeholder data.
		 */
		do_action('quickslot_before_send_email', $type, $recipient, $booking_id, $booking);

		$sent = wp_mail($recipient, $subject, $body, $headers, $attachments);

		remove_filter('wp_mail_from', array($this, 'filter_from_email'));
		remove_filter('wp_mail_from_name', array($this, 'filter_from_name'));

		if ('' !== $temp_ics_path && file_exists($temp_ics_path)) {
			wp_delete_file($temp_ics_path);
		}

		QS_Email_Logger::log($booking_id, $type, $recipient, $subject, $sent ? 'sent' : 'failed');

		/**
		 * Fires after QuickSlot sends an email.
		 *
		 * @param string $type       Email type.
		 * @param string $recipient  Recipient email.
		 * @param int    $booking_id Booking ID.
		 * @param bool   $sent       Whether the email was sent.
		 */
		do_action('quickslot_after_send_email', $type, $recipient, $booking_id, $sent);

		return (bool) $sent;
	}

	/**
	 * Handles booking-created emails.
	 *
	 * @param array<string, mixed> $booking_data Booking action data.
	 */
	public function handle_booking_created(int $booking_id, array $booking_data): void {
		$customer_email = isset($booking_data['customer_email']) ? sanitize_email((string) $booking_data['customer_email']) : '';
		$business_email = sanitize_email((string) get_option('qs_business_email', ''));

		if ('' !== $customer_email) {
			$this->send('confirmation', $customer_email, $booking_id);
		}

		if ('' === $business_email) {
			$business_email = sanitize_email((string) get_option('admin_email', ''));
		}

		if ('' !== $business_email) {
			$this->send('notification', $business_email, $booking_id);
		}
	}

	/**
	 * Handles booking-cancelled emails.
	 */
	public function handle_booking_cancelled(int $booking_id, string $reason): void {
		$booking = $this->get_booking_email_data($booking_id, array('cancellation_reason' => $reason));

		if (empty($booking['customer_email'])) {
			return;
		}

		$this->send('cancellation', (string) $booking['customer_email'], $booking_id, array('cancellation_reason' => $reason));
	}

	/**
	 * Filters outgoing from email.
	 */
	public function filter_from_email(string $email): string {
		return '' !== $this->from_email ? $this->from_email : $email;
	}

	/**
	 * Filters outgoing from name.
	 */
	public function filter_from_name(string $name): string {
		return '' !== $this->from_name ? $this->from_name : $name;
	}

	/**
	 * Loads booking and service data for placeholders.
	 *
	 * @param array<string, mixed> $extra Extra placeholder data.
	 * @return array<string, mixed>
	 */
	private function get_booking_email_data(int $booking_id, array $extra = array()): array {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb) || $booking_id < 1) {
			return array();
		}

		$bookings = $wpdb->prefix . 'qs_bookings';
		$services = $wpdb->prefix . 'qs_services';
		$query    = $wpdb->prepare(
			"SELECT b.id, b.customer_name, b.customer_email, b.customer_phone, b.booking_start, b.timezone, s.name AS service_name, s.duration AS service_duration, s.price AS service_price FROM {$bookings} b LEFT JOIN {$services} s ON s.id = b.service_id WHERE b.id = %d LIMIT 1",
			$booking_id
		);
		$row      = $wpdb->get_row($query, ARRAY_A);

		if (! is_array($row)) {
			return array();
		}

		$business_name  = sanitize_text_field((string) get_option('qs_business_name', ''));
		$business_email = sanitize_email((string) get_option('qs_business_email', ''));

		if ('' === $business_name) {
			$business_name = get_bloginfo('name');
		}

		if ('' === $business_email || ! is_email($business_email)) {
			$business_email = sanitize_email((string) get_option('admin_email', ''));
		}

		$price_value = '';
		if (null !== $row['service_price'] && '' !== (string) $row['service_price']) {
			$price_value = (string) $row['service_price'];
		} else {
			$price_value = __('Free', 'quickslot');
		}

		$start = new DateTimeImmutable((string) $row['booking_start'], new DateTimeZone('UTC'));
		$start = $start->setTimezone(wp_timezone());

		$data = array(
			'booking_id'          => (int) $row['id'],
			'customer_name'       => (string) $row['customer_name'],
			'customer_email'      => (string) $row['customer_email'],
			'customer_phone'      => (string) $row['customer_phone'],
			'service_name'        => (string) $row['service_name'],
			'service_duration'    => sprintf(__('%d minutes', 'quickslot'), (int) $row['service_duration']),
			'service_price'       => $price_value,
			'booking_date'        => $start->format('Y-m-d'),
			'booking_time'        => $start->format('H:i'),
			'business_name'       => $business_name,
			'business_email'      => $business_email,
			'site_url'            => home_url('/'),
			'cancellation_reason' => isset($extra['cancellation_reason']) ? (string) $extra['cancellation_reason'] : '',
		);

		foreach ($extra as $key => $value) {
			$data[ (string) $key ] = $value;
		}

		return $data;
	}
}
