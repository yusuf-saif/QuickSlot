<?php
/**
 * Frontend booking form renderer.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Booking_Form {
	/**
	 * Returns the booking form scaffold markup.
	 */
	public function render(): string {
		$steps = $this->get_steps();

		ob_start();
		?>
		<div id="qs-booking-form" class="qs-booking-form" data-active-step="service">
			<div class="qs-booking-form__inner">
				<div class="qs-booking-form__header">
					<p class="qs-booking-form__eyebrow"><?php echo esc_html__('QuickSlot Booking', 'quickslot'); ?></p>
					<h2 class="qs-booking-form__title"><?php echo esc_html__('Book Your Appointment', 'quickslot'); ?></h2>
					<p class="qs-booking-form__subtitle"><?php echo esc_html__('Choose a service and follow the steps to complete your booking.', 'quickslot'); ?></p>
				</div>

				<ol class="qs-booking-progress" aria-label="<?php echo esc_attr__('Booking progress', 'quickslot'); ?>">
					<?php foreach ($steps as $index => $step) : ?>
						<li class="qs-booking-progress__step<?php echo 0 === $index ? ' is-active' : ''; ?>" data-step="<?php echo esc_attr($step['slug']); ?>">
							<span class="qs-booking-progress__index"><?php echo esc_html((string) ($index + 1)); ?></span>
							<span class="qs-booking-progress__label"><?php echo esc_html($step['label']); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>

				<div class="qs-booking-steps">
					<?php foreach ($steps as $index => $step) : ?>
						<section class="qs-booking-step<?php echo 0 === $index ? ' is-active' : ''; ?>" data-step-panel="<?php echo esc_attr($step['slug']); ?>"<?php echo 0 === $index ? '' : ' hidden'; ?>>
							<div class="qs-booking-step__content">
								<h3 class="qs-booking-step__title"><?php echo esc_html($step['label']); ?></h3>
								<p class="qs-booking-step__text"><?php echo esc_html($step['description']); ?></p>

								<?php if ('service' === $step['slug']) : ?>
									<div class="qs-step-feedback" data-qs-feedback="service" aria-live="polite"></div>
									<div class="qs-service-options" data-qs-services role="group" aria-label="<?php echo esc_attr__('Available services', 'quickslot'); ?>">
									</div>
								<?php elseif ('date' === $step['slug']) : ?>
									<div class="qs-step-feedback" data-qs-feedback="date" aria-live="polite"></div>
									<div class="qs-calendar" data-qs-calendar>
										<div class="qs-calendar__header">
											<button type="button" class="qs-button qs-button--secondary" data-qs-calendar-nav="prev"><?php echo esc_html__('Previous', 'quickslot'); ?></button>
											<h4 class="qs-calendar__title" data-qs-calendar-title><?php echo esc_html__('Loading...', 'quickslot'); ?></h4>
											<button type="button" class="qs-button qs-button--secondary" data-qs-calendar-nav="next"><?php echo esc_html__('Next', 'quickslot'); ?></button>
										</div>
										<div class="qs-calendar__grid" data-qs-calendar-grid aria-label="<?php echo esc_attr__('Booking calendar', 'quickslot'); ?>"></div>
									</div>
								<?php elseif ('time' === $step['slug']) : ?>
									<div class="qs-step-feedback" data-qs-feedback="time" aria-live="polite"></div>
									<div class="qs-slot-options" data-qs-slots role="group" aria-label="<?php echo esc_attr__('Available times', 'quickslot'); ?>"></div>
								<?php elseif ('details' === $step['slug']) : ?>
									<div class="qs-step-feedback" data-qs-feedback="details" aria-live="polite"></div>
									<div class="qs-form-grid">
										<div class="qs-form-field">
											<label for="qs-name"><?php echo esc_html__('Name', 'quickslot'); ?></label>
											<input id="qs-name" type="text" autocomplete="name" data-qs-field="customer_name" placeholder="<?php echo esc_attr__('Your name', 'quickslot'); ?>">
											<p class="qs-field-error" data-qs-field-error="customer_name" hidden></p>
										</div>
										<div class="qs-form-field">
											<label for="qs-email"><?php echo esc_html__('Email', 'quickslot'); ?></label>
											<input id="qs-email" type="email" autocomplete="email" data-qs-field="customer_email" placeholder="<?php echo esc_attr__('you@example.com', 'quickslot'); ?>">
											<p class="qs-field-error" data-qs-field-error="customer_email" hidden></p>
										</div>
										<div class="qs-form-field">
											<label for="qs-phone"><?php echo esc_html__('Phone', 'quickslot'); ?></label>
											<input id="qs-phone" type="text" autocomplete="tel" data-qs-field="customer_phone" placeholder="<?php echo esc_attr__('Optional phone number', 'quickslot'); ?>">
											<p class="qs-field-error" data-qs-field-error="customer_phone" hidden></p>
										</div>
										<div class="qs-form-field qs-honeypot-field" aria-hidden="true">
											<label for="qs-website"><?php echo esc_html__('Website', 'quickslot'); ?></label>
											<input id="qs-website" type="text" tabindex="-1" autocomplete="off" data-qs-field="website" class="qs-honeypot" aria-hidden="true">
										</div>
										<div class="qs-form-field qs-form-field--full">
											<label for="qs-note"><?php echo esc_html__('Note', 'quickslot'); ?></label>
											<textarea id="qs-note" rows="4" data-qs-field="customer_note" placeholder="<?php echo esc_attr__('Optional note for your booking', 'quickslot'); ?>"></textarea>
											<p class="qs-field-error" data-qs-field-error="customer_note" hidden></p>
										</div>
									</div>
								<?php elseif ('review' === $step['slug']) : ?>
									<div class="qs-step-feedback" data-qs-feedback="review" aria-live="polite"></div>
									<div class="qs-review-card">
										<div data-qs-review-summary>
											<p><?php echo esc_html__('Your service, date, time, and details summary will appear here before confirmation.', 'quickslot'); ?></p>
										</div>
									</div>
								<?php elseif ('success' === $step['slug']) : ?>
									<div class="qs-success-state">
										<h4 class="qs-success-state__title" data-qs-success-title><?php echo esc_html__('Booking Placeholder', 'quickslot'); ?></h4>
										<p data-qs-success-message><?php echo esc_html__('A real confirmation message will appear here once booking submission is implemented.', 'quickslot'); ?></p>
									</div>
								<?php else : ?>
									<div class="qs-placeholder-panel">
										<p><?php echo esc_html__('This step will be connected in a later phase.', 'quickslot'); ?></p>
									</div>
								<?php endif; ?>
							</div>

							<div class="qs-booking-step__actions">
								<?php if ($index > 0 && 'success' !== $step['slug']) : ?>
									<button type="button" class="qs-button qs-button--secondary" data-qs-nav="back"><?php echo esc_html__('Back', 'quickslot'); ?></button>
								<?php endif; ?>
								<?php if ('review' === $step['slug']) : ?>
									<button type="button" class="qs-button" data-qs-confirm><?php echo esc_html__('Confirm Booking', 'quickslot'); ?></button>
								<?php elseif ($index < count($steps) - 1) : ?>
									<button type="button" class="qs-button" data-qs-nav="next"><?php echo esc_html__('Next', 'quickslot'); ?></button>
								<?php endif; ?>
							</div>
						</section>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Returns the scaffold step definitions.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_steps(): array {
		return array(
			array(
				'slug'        => 'service',
				'label'       => __('Service', 'quickslot'),
				'description' => __('Select the appointment type you want to book.', 'quickslot'),
			),
			array(
				'slug'        => 'date',
				'label'       => __('Date', 'quickslot'),
				'description' => __('Available booking dates will appear in this step.', 'quickslot'),
			),
			array(
				'slug'        => 'time',
				'label'       => __('Time', 'quickslot'),
				'description' => __('Available time slots will appear in this step.', 'quickslot'),
			),
			array(
				'slug'        => 'details',
				'label'       => __('Details', 'quickslot'),
				'description' => __('Your contact details will be collected in this step.', 'quickslot'),
			),
			array(
				'slug'        => 'review',
				'label'       => __('Review', 'quickslot'),
				'description' => __('Review your booking before final confirmation.', 'quickslot'),
			),
			array(
				'slug'        => 'success',
				'label'       => __('Success', 'quickslot'),
				'description' => __('Your confirmation state will appear here.', 'quickslot'),
			),
		);
	}
}
