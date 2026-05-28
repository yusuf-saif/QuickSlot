<?php
/**
 * Availability admin management.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Availability_Admin {
	/**
	 * Runtime notices.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $messages = array();

	/**
	 * Sticky weekly form data after validation errors.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private ?array $weekly_form_data = null;

	/**
	 * Sticky exception form data after validation errors.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $exception_form_data = null;

	/**
	 * Renders the availability page.
	 */
	public function render_page(): void {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'quickslot'));
		}

		$this->handle_actions();

		?>
		<div class="wrap qs-admin-page qs-availability-page">
			<h1><?php echo esc_html__('Availability', 'quickslot'); ?></h1>
			<?php $this->render_notices(); ?>
			<?php $this->render_weekly_availability_section(); ?>
			<?php $this->render_exceptions_section(); ?>
		</div>
		<?php
	}

	/**
	 * Handles page actions.
	 */
	private function handle_actions(): void {
		if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
			if (isset($_POST['qs_weekly_availability_submit'])) {
				$this->handle_weekly_save();
				return;
			}

			if (isset($_POST['qs_exception_submit'])) {
				$this->handle_exception_save();
				return;
			}
		}

		$action = isset($_GET['action']) ? QS_Sanitizer::text(wp_unslash($_GET['action'])) : '';

		if ('delete_exception' === $action) {
			$this->handle_exception_delete();
		}
	}

	/**
	 * Saves weekly availability.
	 */
	private function handle_weekly_save(): void {
		check_admin_referer('qs_save_availability');

		$submitted = isset($_POST['availability']) && is_array($_POST['availability']) ? wp_unslash($_POST['availability']) : array();
		$data      = array();
		$errors    = array();

		foreach ($this->get_days() as $day_of_week => $label) {
			$day_input = isset($submitted[ $day_of_week ]) && is_array($submitted[ $day_of_week ]) ? $submitted[ $day_of_week ] : array();

			$is_available   = isset($day_input['is_available']) ? 1 : 0;
			$start_time     = isset($day_input['start_time']) ? QS_Sanitizer::text((string) $day_input['start_time']) : '';
			$end_time       = isset($day_input['end_time']) ? QS_Sanitizer::text((string) $day_input['end_time']) : '';
			$buffer_minutes = isset($day_input['buffer_minutes']) ? absint((string) $day_input['buffer_minutes']) : 0;
			$max_bookings   = isset($day_input['max_bookings']) && '' !== (string) $day_input['max_bookings'] ? absint((string) $day_input['max_bookings']) : 0;
			$breaks         = isset($day_input['breaks']) && is_array($day_input['breaks']) ? $day_input['breaks'] : array();

			$validated_breaks = array();

			if ($is_available && (! $this->is_valid_time($start_time) || ! $this->is_valid_time($end_time))) {
				$errors[] = sprintf(
					/* translators: %s: day label. */
					esc_html__('%s requires valid start and end times.', 'quickslot'),
					esc_html($label)
				);
			} elseif ($is_available && ! $this->is_end_after_start($start_time, $end_time)) {
				$errors[] = sprintf(
					/* translators: %s: day label. */
					esc_html__('%s end time must be after the start time.', 'quickslot'),
					esc_html($label)
				);
			}

			foreach ($breaks as $break) {
				if (! is_array($break)) {
					continue;
				}

				$break_start = isset($break['start']) ? QS_Sanitizer::text((string) $break['start']) : '';
				$break_end   = isset($break['end']) ? QS_Sanitizer::text((string) $break['end']) : '';

				if ('' === $break_start && '' === $break_end) {
					continue;
				}

				if (! $this->is_valid_time($break_start) || ! $this->is_valid_time($break_end)) {
					$errors[] = sprintf(
						/* translators: %s: day label. */
						esc_html__('%s contains a break with an invalid time.', 'quickslot'),
						esc_html($label)
					);
					continue;
				}

				if (! $this->is_end_after_start($break_start, $break_end)) {
					$errors[] = sprintf(
						/* translators: %s: day label. */
						esc_html__('%s contains a break where the end time is not after the start time.', 'quickslot'),
						esc_html($label)
					);
					continue;
				}

				$validated_breaks[] = array(
					'start' => $break_start,
					'end'   => $break_end,
				);
			}

			$data[ $day_of_week ] = array(
				'day_of_week'    => $day_of_week,
				'label'          => $label,
				'is_available'   => $is_available,
				'start_time'     => $is_available ? $start_time : '',
				'end_time'       => $is_available ? $end_time : '',
				'breaks'         => $is_available ? $validated_breaks : array(),
				'buffer_minutes' => $buffer_minutes,
				'max_bookings'   => $max_bookings,
			);
		}

		if (! empty($errors)) {
			$this->weekly_form_data = $data;
			foreach ($errors as $error) {
				$this->messages[] = array(
					'type'    => 'error',
					'message' => $error,
				);
			}
			return;
		}

		global $wpdb;

		$table   = $wpdb->prefix . 'qs_availability';
		$now     = current_time('mysql');
		$success = true;

		foreach ($data as $day_data) {
			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE day_of_week = %d ORDER BY id ASC LIMIT 1",
					(int) $day_data['day_of_week']
				)
			);

			$breaks_json = wp_json_encode($day_data['breaks']);

			if (false === $breaks_json) {
				$breaks_json = '[]';
			}

			$has_hours = '' !== (string) $day_data['start_time'] && '' !== (string) $day_data['end_time'];

			if (null !== $existing_id) {
				if ($has_hours) {
					$sql = $wpdb->prepare(
						"UPDATE {$table} SET is_available = %d, start_time = %s, end_time = %s, breaks = %s, buffer_minutes = %d, max_bookings = %d, updated_at = %s WHERE id = %d",
						(int) $day_data['is_available'],
						(string) $day_data['start_time'],
						(string) $day_data['end_time'],
						$breaks_json,
						(int) $day_data['buffer_minutes'],
						(int) $day_data['max_bookings'],
						$now,
						(int) $existing_id
					);
				} else {
					$sql = $wpdb->prepare(
						"UPDATE {$table} SET is_available = %d, start_time = NULL, end_time = NULL, breaks = %s, buffer_minutes = %d, max_bookings = %d, updated_at = %s WHERE id = %d",
						(int) $day_data['is_available'],
						$breaks_json,
						(int) $day_data['buffer_minutes'],
						(int) $day_data['max_bookings'],
						$now,
						(int) $existing_id
					);
				}
			} else {
				if ($has_hours) {
					$sql = $wpdb->prepare(
						"INSERT INTO {$table} (day_of_week, is_available, start_time, end_time, breaks, buffer_minutes, max_bookings, updated_at) VALUES (%d, %d, %s, %s, %s, %d, %d, %s)",
						(int) $day_data['day_of_week'],
						(int) $day_data['is_available'],
						(string) $day_data['start_time'],
						(string) $day_data['end_time'],
						$breaks_json,
						(int) $day_data['buffer_minutes'],
						(int) $day_data['max_bookings'],
						$now
					);
				} else {
					$sql = $wpdb->prepare(
						"INSERT INTO {$table} (day_of_week, is_available, start_time, end_time, breaks, buffer_minutes, max_bookings, updated_at) VALUES (%d, %d, NULL, NULL, %s, %d, %d, %s)",
						(int) $day_data['day_of_week'],
						(int) $day_data['is_available'],
						$breaks_json,
						(int) $day_data['buffer_minutes'],
						(int) $day_data['max_bookings'],
						$now
					);
				}
			}

			$result = $wpdb->query($sql);

			if (false === $result) {
				$success = false;
				break;
			}
		}

		wp_safe_redirect(
			$this->get_availability_url(
				array(
					'qs_notice' => $success ? 'availability_saved' : 'availability_error',
				)
			)
		);
		exit;
	}

	/**
	 * Saves an availability exception.
	 */
	private function handle_exception_save(): void {
		check_admin_referer('qs_save_availability_exception');

		$date            = isset($_POST['exception_date']) ? QS_Sanitizer::text(wp_unslash($_POST['exception_date'])) : '';
		$type            = isset($_POST['exception_type']) ? QS_Sanitizer::text(wp_unslash($_POST['exception_type'])) : 'blocked';
		$start_time      = isset($_POST['start_time']) ? QS_Sanitizer::text(wp_unslash($_POST['start_time'])) : '';
		$end_time        = isset($_POST['end_time']) ? QS_Sanitizer::text(wp_unslash($_POST['end_time'])) : '';
		$reason          = isset($_POST['reason']) ? QS_Sanitizer::text(wp_unslash($_POST['reason'])) : '';
		$this->exception_form_data = array(
			'exception_date' => $date,
			'exception_type' => $type,
			'start_time'     => $start_time,
			'end_time'       => $end_time,
			'reason'         => $reason,
		);

		$errors = array();

		if (! $this->is_valid_date($date)) {
			$errors[] = esc_html__('Exception date must use the YYYY-MM-DD format.', 'quickslot');
		}

		if (! in_array($type, array('blocked', 'custom'), true)) {
			$errors[] = esc_html__('Exception type is invalid.', 'quickslot');
		}

		if ('custom' === $type) {
			if (! $this->is_valid_time($start_time) || ! $this->is_valid_time($end_time)) {
				$errors[] = esc_html__('Custom hours require valid start and end times.', 'quickslot');
			} elseif (! $this->is_end_after_start($start_time, $end_time)) {
				$errors[] = esc_html__('Custom hours require the end time to be after the start time.', 'quickslot');
			}
		}

		if (! empty($errors)) {
			foreach ($errors as $error) {
				$this->messages[] = array(
					'type'    => 'error',
					'message' => $error,
				);
			}
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'qs_availability_exceptions';
		if ('custom' === $type) {
			$sql = $wpdb->prepare(
				"INSERT INTO {$table} (exception_date, is_available, start_time, end_time, reason, created_at) VALUES (%s, %d, %s, %s, %s, %s)",
				$date,
				1,
				$start_time,
				$end_time,
				$reason,
				current_time('mysql')
			);
		} else {
			$sql = $wpdb->prepare(
				"INSERT INTO {$table} (exception_date, is_available, start_time, end_time, reason, created_at) VALUES (%s, %d, NULL, NULL, %s, %s)",
				$date,
				0,
				$reason,
				current_time('mysql')
			);
		}

		$result = $wpdb->query($sql);

		wp_safe_redirect(
			$this->get_availability_url(
				array(
					'qs_notice' => false === $result ? 'exception_error' : 'exception_saved',
				)
			)
		);
		exit;
	}

	/**
	 * Deletes an availability exception.
	 */
	private function handle_exception_delete(): void {
		$exception_id = isset($_GET['exception_id']) ? absint(wp_unslash($_GET['exception_id'])) : 0;

		if ($exception_id < 1) {
			wp_safe_redirect($this->get_availability_url(array('qs_notice' => 'exception_invalid')));
			exit;
		}

		check_admin_referer('qs_delete_availability_exception_' . $exception_id);

		global $wpdb;

		$table  = $wpdb->prefix . 'qs_availability_exceptions';
		$sql    = $wpdb->prepare("DELETE FROM {$table} WHERE id = %d", $exception_id);
		$result = $wpdb->query($sql);

		wp_safe_redirect(
			$this->get_availability_url(
				array(
					'qs_notice' => false === $result ? 'exception_error' : 'exception_deleted',
				)
			)
		);
		exit;
	}

	/**
	 * Renders weekly availability settings.
	 */
	private function render_weekly_availability_section(): void {
		$days = null !== $this->weekly_form_data ? $this->weekly_form_data : $this->get_weekly_availability_rows();
		?>
		<div class="qs-admin-section">
			<h2><?php echo esc_html__('Weekly Availability', 'quickslot'); ?></h2>
			<form method="post" action="<?php echo esc_url($this->get_availability_url()); ?>">
				<?php wp_nonce_field('qs_save_availability'); ?>
				<table class="widefat striped qs-availability-table">
					<thead>
						<tr>
							<th><?php echo esc_html__('Day', 'quickslot'); ?></th>
							<th><?php echo esc_html__('Enabled', 'quickslot'); ?></th>
							<th><?php echo esc_html__('Start Time', 'quickslot'); ?></th>
							<th><?php echo esc_html__('End Time', 'quickslot'); ?></th>
							<th><?php echo esc_html__('Breaks', 'quickslot'); ?></th>
							<th><?php echo esc_html__('Buffer Minutes', 'quickslot'); ?></th>
							<th><?php echo esc_html__('Max Bookings Per Day', 'quickslot'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($days as $day_of_week => $day) : ?>
							<tr>
								<th scope="row"><?php echo esc_html((string) $day['label']); ?></th>
								<td>
									<label>
										<input type="checkbox" name="availability[<?php echo esc_attr((string) $day_of_week); ?>][is_available]" value="1" <?php checked((int) $day['is_available'], 1); ?>>
										<span class="screen-reader-text"><?php echo esc_html(sprintf(__('Enable %s', 'quickslot'), (string) $day['label'])); ?></span>
									</label>
								</td>
								<td><input type="time" name="availability[<?php echo esc_attr((string) $day_of_week); ?>][start_time]" value="<?php echo esc_attr((string) $day['start_time']); ?>"></td>
								<td><input type="time" name="availability[<?php echo esc_attr((string) $day_of_week); ?>][end_time]" value="<?php echo esc_attr((string) $day['end_time']); ?>"></td>
								<td>
									<div class="qs-breaks" data-day="<?php echo esc_attr((string) $day_of_week); ?>">
										<?php foreach ($day['breaks'] as $break) : ?>
											<div class="qs-break-row">
												<input type="time" name="availability[<?php echo esc_attr((string) $day_of_week); ?>][breaks][][start]" value="<?php echo esc_attr((string) $break['start']); ?>">
												<input type="time" name="availability[<?php echo esc_attr((string) $day_of_week); ?>][breaks][][end]" value="<?php echo esc_attr((string) $break['end']); ?>">
												<button type="button" class="button-link-delete qs-remove-break"><?php echo esc_html__('Remove', 'quickslot'); ?></button>
											</div>
										<?php endforeach; ?>
									</div>
									<button type="button" class="button qs-add-break" data-day="<?php echo esc_attr((string) $day_of_week); ?>"><?php echo esc_html__('Add Break', 'quickslot'); ?></button>
								</td>
								<td><input type="number" min="0" step="1" class="small-text" name="availability[<?php echo esc_attr((string) $day_of_week); ?>][buffer_minutes]" value="<?php echo esc_attr((string) $day['buffer_minutes']); ?>"></td>
								<td><input type="number" min="0" step="1" class="small-text" name="availability[<?php echo esc_attr((string) $day_of_week); ?>][max_bookings]" value="<?php echo esc_attr((string) $day['max_bookings']); ?>"></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="submit"><button type="submit" name="qs_weekly_availability_submit" class="button button-primary"><?php echo esc_html__('Save Weekly Availability', 'quickslot'); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders availability exceptions.
	 */
	private function render_exceptions_section(): void {
		$form_data  = null !== $this->exception_form_data ? $this->exception_form_data : $this->get_default_exception_form_data();
		$exceptions = $this->get_exceptions();
		?>
		<div class="qs-admin-section">
			<h2><?php echo esc_html__('Blocked Dates and Exceptions', 'quickslot'); ?></h2>
			<form method="post" action="<?php echo esc_url($this->get_availability_url()); ?>" class="qs-exception-form">
				<?php wp_nonce_field('qs_save_availability_exception'); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="qs-exception-date"><?php echo esc_html__('Exception Date', 'quickslot'); ?></label></th>
							<td><input type="date" id="qs-exception-date" name="exception_date" value="<?php echo esc_attr($form_data['exception_date']); ?>" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-exception-type"><?php echo esc_html__('Type', 'quickslot'); ?></label></th>
							<td>
								<select id="qs-exception-type" name="exception_type" class="qs-exception-type">
									<option value="blocked" <?php selected($form_data['exception_type'], 'blocked'); ?>><?php echo esc_html__('Block all day', 'quickslot'); ?></option>
									<option value="custom" <?php selected($form_data['exception_type'], 'custom'); ?>><?php echo esc_html__('Custom hours', 'quickslot'); ?></option>
								</select>
							</td>
						</tr>
						<tr class="qs-exception-hours-row">
							<th scope="row"><?php echo esc_html__('Hours', 'quickslot'); ?></th>
							<td>
								<input type="time" name="start_time" value="<?php echo esc_attr($form_data['start_time']); ?>">
								<input type="time" name="end_time" value="<?php echo esc_attr($form_data['end_time']); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-exception-reason"><?php echo esc_html__('Reason', 'quickslot'); ?></label></th>
							<td><input type="text" class="regular-text" id="qs-exception-reason" name="reason" value="<?php echo esc_attr($form_data['reason']); ?>"></td>
						</tr>
					</tbody>
				</table>
				<p class="submit"><button type="submit" name="qs_exception_submit" class="button button-primary"><?php echo esc_html__('Add Exception', 'quickslot'); ?></button></p>
			</form>

			<h3><?php echo esc_html__('Existing Exceptions', 'quickslot'); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__('Date', 'quickslot'); ?></th>
						<th><?php echo esc_html__('Type', 'quickslot'); ?></th>
						<th><?php echo esc_html__('Hours', 'quickslot'); ?></th>
						<th><?php echo esc_html__('Reason', 'quickslot'); ?></th>
						<th><?php echo esc_html__('Actions', 'quickslot'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($exceptions)) : ?>
						<tr>
							<td colspan="5"><?php echo esc_html__('No exceptions found.', 'quickslot'); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ($exceptions as $exception) : ?>
							<tr>
								<td><?php echo esc_html((string) $exception->exception_date); ?></td>
								<td><?php echo esc_html((int) $exception->is_available === 1 ? __('Custom hours', 'quickslot') : __('Block all day', 'quickslot')); ?></td>
								<td><?php echo esc_html($this->format_exception_hours($exception)); ?></td>
								<td><?php echo esc_html((string) $exception->reason); ?></td>
								<td><a href="<?php echo esc_url($this->get_delete_exception_url((int) $exception->id)); ?>" onclick="return window.confirm('<?php echo esc_js(__('Are you sure you want to delete this exception?', 'quickslot')); ?>');"><?php echo esc_html__('Delete', 'quickslot'); ?></a></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Renders page notices.
	 */
	private function render_notices(): void {
		$notice_key = isset($_GET['qs_notice']) ? QS_Sanitizer::text(wp_unslash($_GET['qs_notice'])) : '';
		$notices    = array(
			'availability_saved' => array('success', __('Weekly availability saved.', 'quickslot')),
			'availability_error' => array('error', __('Weekly availability could not be saved.', 'quickslot')),
			'exception_saved'    => array('success', __('Exception added.', 'quickslot')),
			'exception_deleted'  => array('success', __('Exception deleted.', 'quickslot')),
			'exception_error'    => array('error', __('Exception could not be saved.', 'quickslot')),
			'exception_invalid'  => array('error', __('Invalid exception request.', 'quickslot')),
		);

		if (isset($notices[ $notice_key ])) {
			$this->messages[] = array(
				'type'    => $notices[ $notice_key ][0],
				'message' => $notices[ $notice_key ][1],
			);
		}

		foreach ($this->messages as $message) {
			?>
			<div class="notice notice-<?php echo esc_attr($message['type']); ?> is-dismissible"><p><?php echo esc_html($message['message']); ?></p></div>
			<?php
		}
	}

	/**
	 * Returns weekly availability rows keyed by day.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_weekly_availability_rows(): array {
		global $wpdb;

		$table    = $wpdb->prefix . 'qs_availability';
		$rows     = $wpdb->get_results("SELECT day_of_week, is_available, start_time, end_time, breaks, buffer_minutes, max_bookings FROM {$table} ORDER BY id ASC");
		$days     = array();
		$labels   = $this->get_days();

		foreach ($labels as $day_of_week => $label) {
			$days[ $day_of_week ] = array(
				'day_of_week'    => $day_of_week,
				'label'          => $label,
				'is_available'   => 0,
				'start_time'     => '',
				'end_time'       => '',
				'breaks'         => array(),
				'buffer_minutes' => 0,
				'max_bookings'   => 0,
			);
		}

		if (! is_array($rows)) {
			return $days;
		}

		foreach ($rows as $row) {
			$day_of_week = (int) $row->day_of_week;

			if (! isset($days[ $day_of_week ])) {
				continue;
			}

			$breaks = json_decode((string) $row->breaks, true);

			$days[ $day_of_week ] = array(
				'day_of_week'    => $day_of_week,
				'label'          => $labels[ $day_of_week ],
				'is_available'   => (int) $row->is_available,
				'start_time'     => $this->format_time_for_input($row->start_time),
				'end_time'       => $this->format_time_for_input($row->end_time),
				'breaks'         => is_array($breaks) ? $this->normalize_breaks_for_display($breaks) : array(),
				'buffer_minutes' => (int) $row->buffer_minutes,
				'max_bookings'   => (int) $row->max_bookings,
			);
		}

		return $days;
	}

	/**
	 * Returns saved exceptions.
	 *
	 * @return array<int, object>
	 */
	private function get_exceptions(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'qs_availability_exceptions';
		$rows  = $wpdb->get_results("SELECT id, exception_date, is_available, start_time, end_time, reason FROM {$table} ORDER BY exception_date DESC, id DESC");

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Returns default exception form values.
	 *
	 * @return array<string, string>
	 */
	private function get_default_exception_form_data(): array {
		return array(
			'exception_date' => '',
			'exception_type' => 'blocked',
			'start_time'     => '',
			'end_time'       => '',
			'reason'         => '',
		);
	}

	/**
	 * Returns day labels in storage order.
	 *
	 * @return array<int, string>
	 */
	private function get_days(): array {
		return array(
			1 => __('Monday', 'quickslot'),
			2 => __('Tuesday', 'quickslot'),
			3 => __('Wednesday', 'quickslot'),
			4 => __('Thursday', 'quickslot'),
			5 => __('Friday', 'quickslot'),
			6 => __('Saturday', 'quickslot'),
			0 => __('Sunday', 'quickslot'),
		);
	}

	/**
	 * Validates HH:MM time values.
	 */
	private function is_valid_time(string $time): bool {
		return 1 === preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time);
	}

	/**
	 * Validates YYYY-MM-DD date values.
	 */
	private function is_valid_date(string $date): bool {
		$parsed = DateTime::createFromFormat('Y-m-d', $date);

		return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $date;
	}

	/**
	 * Confirms an end time occurs after a start time.
	 */
	private function is_end_after_start(string $start_time, string $end_time): bool {
		return $this->time_to_minutes($end_time) > $this->time_to_minutes($start_time);
	}

	/**
	 * Converts a time string to minutes.
	 */
	private function time_to_minutes(string $time): int {
		list($hours, $minutes) = array_map('intval', explode(':', $time));

		return ($hours * 60) + $minutes;
	}

	/**
	 * Formats stored time values for HTML time inputs.
	 *
	 * @param string|null $time Stored time value.
	 */
	private function format_time_for_input(?string $time): string {
		if (null === $time || '' === $time) {
			return '';
		}

		return substr($time, 0, 5);
	}

	/**
	 * Normalizes saved breaks for display.
	 *
	 * @param array<int, array<string, string>> $breaks Saved break data.
	 * @return array<int, array<string, string>>
	 */
	private function normalize_breaks_for_display(array $breaks): array {
		$normalized = array();

		foreach ($breaks as $break) {
			if (! isset($break['start'], $break['end'])) {
				continue;
			}

			$normalized[] = array(
				'start' => $this->format_time_for_input((string) $break['start']),
				'end'   => $this->format_time_for_input((string) $break['end']),
			);
		}

		return $normalized;
	}

	/**
	 * Formats exception hours for list display.
	 *
	 * @param object $exception Exception row.
	 */
	private function format_exception_hours(object $exception): string {
		if ((int) $exception->is_available !== 1) {
			return __('All day blocked', 'quickslot');
		}

		$start_time = $this->format_time_for_input($exception->start_time);
		$end_time   = $this->format_time_for_input($exception->end_time);

		return sprintf(
			/* translators: 1: start time, 2: end time. */
			__('From %1$s to %2$s', 'quickslot'),
			$start_time,
			$end_time
		);
	}

	/**
	 * Returns the page URL.
	 *
	 * @param array<string, string> $args Optional query args.
	 */
	private function get_availability_url(array $args = array()): string {
		return admin_url('admin.php?' . http_build_query(array_merge(array('page' => 'quickslot-availability'), $args), '', '&'));
	}

	/**
	 * Builds the delete URL for an exception.
	 */
	private function get_delete_exception_url(int $exception_id): string {
		return wp_nonce_url(
			$this->get_availability_url(
				array(
					'action'       => 'delete_exception',
					'exception_id' => (string) $exception_id,
				)
			),
			'qs_delete_availability_exception_' . $exception_id
		);
	}
}
