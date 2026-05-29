<?php
/**
 * Email template storage and placeholder replacement.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Email_Templates {
	/**
	 * Template option map.
	 *
	 * @var array<string, string>
	 */
	private array $template_options = array(
		'confirmation' => 'qs_email_template_confirmation',
		'notification' => 'qs_email_template_notification',
		'reminder'     => 'qs_email_template_reminder',
		'cancellation' => 'qs_email_template_cancellation',
	);

	/**
	 * Returns a template for the requested type.
	 *
	 * @return array<string, string>
	 */
	public function get_template(string $type): array {
		$defaults = $this->get_default_templates();

		if (! isset($this->template_options[ $type ], $defaults[ $type ])) {
			return array('subject' => '', 'body' => '');
		}

		$option_name = $this->template_options[ $type ];
		$stored      = get_option($option_name, null);

		if (! is_array($stored) || ! isset($stored['subject'], $stored['body'])) {
			$stored = $defaults[ $type ];
			update_option($option_name, $stored);
		}

		$template = array(
			'subject' => sanitize_text_field((string) $stored['subject']),
			'body'    => wp_kses_post((string) $stored['body']),
		);

		/**
		 * Filters a QuickSlot email template.
		 *
		 * @param array<string, string> $template Template data.
		 * @param string                $type     Template type.
		 */
		$template = apply_filters('quickslot_email_template', $template, $type);

		return is_array($template)
			? array(
				'subject' => sanitize_text_field((string) ($template['subject'] ?? '')),
				'body'    => wp_kses_post((string) ($template['body'] ?? '')),
			)
			: array('subject' => '', 'body' => '');
	}

	/**
	 * Replaces supported placeholders in a text string.
	 *
	 * @param array<string, mixed> $booking_data Booking/template data.
	 */
	public function replace_placeholders(string $text, array $booking_data): string {
		$replacements = array(
			'{customer_name}'       => (string) ($booking_data['customer_name'] ?? ''),
			'{customer_email}'      => (string) ($booking_data['customer_email'] ?? ''),
			'{customer_phone}'      => (string) ($booking_data['customer_phone'] ?? ''),
			'{service_name}'        => (string) ($booking_data['service_name'] ?? ''),
			'{service_duration}'    => (string) ($booking_data['service_duration'] ?? ''),
			'{service_price}'       => (string) ($booking_data['service_price'] ?? ''),
			'{booking_date}'        => (string) ($booking_data['booking_date'] ?? ''),
			'{booking_time}'        => (string) ($booking_data['booking_time'] ?? ''),
			'{booking_id}'          => (string) ($booking_data['booking_id'] ?? ''),
			'{business_name}'       => (string) ($booking_data['business_name'] ?? ''),
			'{business_email}'      => (string) ($booking_data['business_email'] ?? ''),
			'{site_url}'            => (string) ($booking_data['site_url'] ?? ''),
			'{cancellation_reason}' => (string) ($booking_data['cancellation_reason'] ?? ''),
		);

		return strtr($text, $replacements);
	}

	/**
	 * Returns default templates.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function get_default_templates(): array {
		return array(
			'confirmation' => array(
				'subject' => __('Your booking is received for {service_name}', 'quickslot'),
				'body'    => wp_kses_post('<p>' . __('Hi {customer_name},', 'quickslot') . '</p><p>' . __('Your booking #{booking_id} has been received for {service_name} on {booking_date} at {booking_time}.', 'quickslot') . '</p><p>' . __('Thank you,', 'quickslot') . '<br>{business_name}</p>'),
			),
			'notification' => array(
				'subject' => __('New booking received: {service_name}', 'quickslot'),
				'body'    => wp_kses_post('<p>' . __('A new booking #{booking_id} has been created.', 'quickslot') . '</p><p>{customer_name}<br>{customer_email}<br>{customer_phone}</p><p>{service_name}<br>{booking_date} {booking_time}</p>'),
			),
			'reminder' => array(
				'subject' => __('Reminder: {service_name} on {booking_date}', 'quickslot'),
				'body'    => wp_kses_post('<p>' . __('Hi {customer_name},', 'quickslot') . '</p><p>' . __('This is a reminder for your booking of {service_name} on {booking_date} at {booking_time}.', 'quickslot') . '</p>'),
			),
			'cancellation' => array(
				'subject' => __('Booking update for {service_name}', 'quickslot'),
				'body'    => wp_kses_post('<p>' . __('Hi {customer_name},', 'quickslot') . '</p><p>' . __('Your booking #{booking_id} for {service_name} on {booking_date} at {booking_time} has been cancelled.', 'quickslot') . '</p><p>{cancellation_reason}</p>'),
			),
		);
	}
}
