<?php
/**
 * Frontend bootstrap.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Frontend {
	/**
	 * Booking form renderer.
	 *
	 * @var QS_Booking_Form
	 */
	private QS_Booking_Form $booking_form;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->booking_form = new QS_Booking_Form();
	}

	/**
	 * Registers frontend hooks.
	 */
	public function init(): void {
		add_action('init', array($this, 'register_shortcode'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	/**
	 * Registers the booking shortcode.
	 */
	public function register_shortcode(): void {
		add_shortcode('quickslot_booking', array($this, 'render_shortcode'));
	}

	/**
	 * Renders the booking shortcode.
	 */
	public function render_shortcode(): string {
		return $this->booking_form->render();
	}

	/**
	 * Enqueues frontend assets when the current page contains the shortcode.
	 */
	public function enqueue_assets(): void {
		if (! $this->current_page_has_shortcode()) {
			return;
		}

		wp_enqueue_style(
			'quickslot-design-tokens',
			QUICKSLOT_URL . 'assets/css/quickslot-design-tokens.css',
			array(),
			QUICKSLOT_VERSION
		);

		wp_enqueue_style(
			'quickslot-frontend',
			QUICKSLOT_URL . 'assets/css/quickslot-frontend.css',
			array('quickslot-design-tokens'),
			QUICKSLOT_VERSION
		);

		wp_enqueue_script(
			'quickslot-booking',
			QUICKSLOT_URL . 'assets/js/quickslot-booking.js',
			array(),
			QUICKSLOT_VERSION,
			true
		);

		wp_localize_script(
			'quickslot-booking',
			'QuickSlotData',
			array(
				'restUrl'   => esc_url_raw(rest_url('quickslot/v1/')),
				'nonce'     => wp_create_nonce('wp_rest'),
				'timezone'  => wp_timezone_string(),
				'i18n'      => array(
					'service'     => __('Service', 'quickslot'),
					'date'        => __('Date', 'quickslot'),
					'time'        => __('Time', 'quickslot'),
					'details'     => __('Details', 'quickslot'),
					'review'      => __('Review', 'quickslot'),
					'success'     => __('Success', 'quickslot'),
					'next'        => __('Next', 'quickslot'),
					'back'        => __('Back', 'quickslot'),
					'placeholder' => __('This step is a placeholder for a later phase.', 'quickslot'),
					'loading'     => __('Loading...', 'quickslot'),
					'error'       => __('Something went wrong. Please try again.', 'quickslot'),
					'noServices'  => __('No services available', 'quickslot'),
					'noDates'     => __('No dates available', 'quickslot'),
					'noSlots'     => __('No slots available', 'quickslot'),
					'free'        => __('Free', 'quickslot'),
					'price'       => __('Price', 'quickslot'),
					'duration'    => __('Duration', 'quickslot'),
					'minutes'     => __('minutes', 'quickslot'),
					'previousMonth' => __('Previous month', 'quickslot'),
					'nextMonth'     => __('Next month', 'quickslot'),
					'selectService' => __('Select a service to continue.', 'quickslot'),
					'selectDate'    => __('Select an available date to continue.', 'quickslot'),
					'selectTime'    => __('Select an available time to continue.', 'quickslot'),
					'calendar'      => __('Booking calendar', 'quickslot'),
					'availableDates' => __('Available dates', 'quickslot'),
					'availableTimes' => __('Available times', 'quickslot'),
					'monthNames'    => array(
						__('January', 'quickslot'),
						__('February', 'quickslot'),
						__('March', 'quickslot'),
						__('April', 'quickslot'),
						__('May', 'quickslot'),
						__('June', 'quickslot'),
						__('July', 'quickslot'),
						__('August', 'quickslot'),
						__('September', 'quickslot'),
						__('October', 'quickslot'),
						__('November', 'quickslot'),
						__('December', 'quickslot'),
					),
					'weekdayNames'  => array(
						__('Sun', 'quickslot'),
						__('Mon', 'quickslot'),
						__('Tue', 'quickslot'),
						__('Wed', 'quickslot'),
						__('Thu', 'quickslot'),
						__('Fri', 'quickslot'),
						__('Sat', 'quickslot'),
					),
				),
			)
		);
	}

	/**
	 * Checks whether the current queried content contains the shortcode.
	 */
	private function current_page_has_shortcode(): bool {
		if (! is_singular()) {
			return false;
		}

		$post = get_queried_object();

		if (! ($post instanceof WP_Post)) {
			return false;
		}

		return has_shortcode((string) $post->post_content, 'quickslot_booking');
	}
}
