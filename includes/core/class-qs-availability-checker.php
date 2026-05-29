<?php
/**
 * Server-side slot availability checks.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Availability_Checker {
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
	 * Confirms whether a requested time is still available.
	 */
	public function is_available(int $service_id, string $date_ymd, string $time_hm): bool {
		if ($service_id < 1 || ! QS_Sanitizer::is_date($date_ymd) || ! QS_Sanitizer::is_time($time_hm)) {
			return false;
		}

		$slots = $this->slot_generator->generate($service_id, $date_ymd);

		return in_array($time_hm, $slots, true);
	}
}
