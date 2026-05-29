<?php
/**
 * Bookings admin management.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Bookings_Admin {
	/**
	 * Items shown per page.
	 */
	private const PER_PAGE = 20;

	/**
	 * Allowed status values.
	 *
	 * @var array<int, string>
	 */
	private array $allowed_statuses = array('pending', 'confirmed', 'cancelled', 'completed', 'no-show');

	/**
	 * Allowed admin-created statuses.
	 *
	 * @var array<int, string>
	 */
	private array $admin_create_statuses = array('pending', 'confirmed');

	/**
	 * Renders the bookings admin page.
	 */
	public function render_page(): void {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'quickslot'));
		}

		$this->handle_actions();

		if ('export' === $this->get_action()) {
			$this->handle_export();
		}

		$this->render_notice();

		if ('view' === $this->get_action()) {
			$this->render_detail_page();
			return;
		}

		if ('add' === $this->get_action()) {
			$this->render_add_page();
			return;
		}

		$this->render_list_page();
	}

	/**
	 * Handles bookings admin actions.
	 */
	private function handle_actions(): void {
		if ('POST' !== $_SERVER['REQUEST_METHOD']) {
			return;
		}

		if (isset($_POST['qs_booking_status_submit'])) {
			$this->handle_status_update();
			return;
		}

		if (isset($_POST['qs_admin_booking_submit'])) {
			$this->handle_admin_booking_create();
		}
	}

	/**
	 * Renders the bookings list page.
	 */
	private function render_list_page(): void {
		$page_data = $this->get_bookings_page_data();
		$filters   = $page_data['filters'];

		?>
		<div class="wrap qs-admin-page qs-bookings-admin">
			<h1 class="wp-heading-inline"><?php echo esc_html__('Bookings', 'quickslot'); ?></h1>
			<a href="<?php echo esc_url($this->get_bookings_url(array('action' => 'add'))); ?>" class="page-title-action"><?php echo esc_html__('Add Booking', 'quickslot'); ?></a>
			<a href="<?php echo esc_url($this->get_export_url()); ?>" class="page-title-action"><?php echo esc_html__('Export CSV', 'quickslot'); ?></a>
			<hr class="wp-header-end">

			<form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="qs-admin-filters">
				<input type="hidden" name="page" value="quickslot-bookings">

				<div class="qs-admin-filters__grid">
					<div>
						<label for="qs-date-from"><?php echo esc_html__('Date From', 'quickslot'); ?></label>
						<input type="date" id="qs-date-from" name="date_from" value="<?php echo esc_attr((string) $filters['date_from']); ?>">
					</div>
					<div>
						<label for="qs-date-to"><?php echo esc_html__('Date To', 'quickslot'); ?></label>
						<input type="date" id="qs-date-to" name="date_to" value="<?php echo esc_attr((string) $filters['date_to']); ?>">
					</div>
					<div>
						<label for="qs-service-filter"><?php echo esc_html__('Service', 'quickslot'); ?></label>
						<select id="qs-service-filter" name="service_id">
							<option value="0"><?php echo esc_html__('All services', 'quickslot'); ?></option>
							<?php foreach ($this->get_services_for_filter() as $service) : ?>
								<option value="<?php echo esc_attr((string) $service->id); ?>" <?php selected((int) $filters['service_id'], (int) $service->id); ?>><?php echo esc_html($service->name); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label for="qs-status-filter"><?php echo esc_html__('Status', 'quickslot'); ?></label>
						<select id="qs-status-filter" name="status">
							<option value=""><?php echo esc_html__('All statuses', 'quickslot'); ?></option>
							<?php foreach ($this->allowed_statuses as $status) : ?>
								<option value="<?php echo esc_attr($status); ?>" <?php selected((string) $filters['status'], $status); ?>><?php echo esc_html($this->get_status_label($status)); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="qs-admin-filters__search">
						<label for="qs-customer-search"><?php echo esc_html__('Customer Search', 'quickslot'); ?></label>
						<input type="search" id="qs-customer-search" name="customer_search" value="<?php echo esc_attr((string) $filters['customer_search']); ?>" placeholder="<?php echo esc_attr__('Name or email', 'quickslot'); ?>">
					</div>
				</div>

				<p>
					<button type="submit" class="button button-primary"><?php echo esc_html__('Apply Filters', 'quickslot'); ?></button>
				</p>
			</form>

			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__('Booking ID', 'quickslot'); ?></th>
						<th scope="col"><?php echo esc_html__('Customer Name', 'quickslot'); ?></th>
						<th scope="col"><?php echo esc_html__('Service', 'quickslot'); ?></th>
						<th scope="col"><?php echo esc_html__('Date & Time', 'quickslot'); ?></th>
						<th scope="col"><?php echo esc_html__('Status', 'quickslot'); ?></th>
						<th scope="col"><?php echo esc_html__('Source', 'quickslot'); ?></th>
						<th scope="col"><?php echo esc_html__('Created', 'quickslot'); ?></th>
						<th scope="col"><?php echo esc_html__('Actions', 'quickslot'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($page_data['items'])) : ?>
						<tr>
							<td colspan="8"><?php echo esc_html__('No bookings found.', 'quickslot'); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ($page_data['items'] as $booking) : ?>
							<tr>
								<td><?php echo esc_html((string) $booking->id); ?></td>
								<td><?php echo esc_html((string) $booking->customer_name); ?></td>
								<td><?php echo esc_html((string) $booking->service_name); ?></td>
								<td><?php echo esc_html($this->format_booking_range((string) $booking->booking_start, (string) $booking->booking_end)); ?></td>
								<td><span class="qs-badge qs-badge--<?php echo esc_attr($this->get_status_class((string) $booking->status)); ?>"><?php echo esc_html($this->get_status_label((string) $booking->status)); ?></span></td>
								<td><?php echo esc_html((string) $booking->booking_source); ?></td>
								<td><?php echo esc_html($this->format_local_datetime_for_display((string) $booking->created_at)); ?></td>
								<td><a href="<?php echo esc_url($this->get_view_url((int) $booking->id)); ?>"><?php echo esc_html__('View', 'quickslot'); ?></a></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php $this->render_pagination((int) $page_data['total_items'], (int) $page_data['current_page'], (int) $page_data['total_pages']); ?>
		</div>
		<?php
	}

	/**
	 * Renders the add booking page.
	 *
	 * @param array<string, string|int>|null $form_data Form data.
	 * @param array<int, string>             $errors    Validation errors.
	 */
	private function render_add_page(?array $form_data = null, array $errors = array()): void {
		if (null === $form_data) {
			$form_data = $this->get_default_booking_form_data();
		}

		?>
		<div class="wrap qs-admin-page qs-bookings-admin">
			<h1><?php echo esc_html__('Add Booking', 'quickslot'); ?></h1>
			<p><a href="<?php echo esc_url($this->get_bookings_url()); ?>">&larr; <?php echo esc_html__('Back to bookings', 'quickslot'); ?></a></p>

			<?php if (! empty($errors)) : ?>
				<div class="notice notice-error"><ul>
					<?php foreach ($errors as $error) : ?>
						<li><?php echo esc_html($error); ?></li>
					<?php endforeach; ?>
				</ul></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url($this->get_bookings_url(array('action' => 'add'))); ?>" class="qs-admin-form-card">
				<?php wp_nonce_field('qs_create_admin_booking'); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="qs-admin-service-id"><?php echo esc_html__('Service', 'quickslot'); ?></label></th>
							<td>
								<select id="qs-admin-service-id" name="service_id" required>
									<option value="0"><?php echo esc_html__('Select a service', 'quickslot'); ?></option>
									<?php foreach ($this->get_active_services() as $service) : ?>
										<option value="<?php echo esc_attr((string) $service->id); ?>" <?php selected((int) $form_data['service_id'], (int) $service->id); ?>><?php echo esc_html($service->name); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-admin-booking-date"><?php echo esc_html__('Booking Date', 'quickslot'); ?></label></th>
							<td><input type="date" id="qs-admin-booking-date" name="booking_date" required value="<?php echo esc_attr((string) $form_data['booking_date']); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-admin-booking-time"><?php echo esc_html__('Booking Time', 'quickslot'); ?></label></th>
							<td><input type="time" id="qs-admin-booking-time" name="booking_time" required value="<?php echo esc_attr((string) $form_data['booking_time']); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-admin-customer-name"><?php echo esc_html__('Customer Name', 'quickslot'); ?></label></th>
							<td><input type="text" id="qs-admin-customer-name" name="customer_name" class="regular-text" required value="<?php echo esc_attr((string) $form_data['customer_name']); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-admin-customer-email"><?php echo esc_html__('Customer Email', 'quickslot'); ?></label></th>
							<td><input type="email" id="qs-admin-customer-email" name="customer_email" class="regular-text" required value="<?php echo esc_attr((string) $form_data['customer_email']); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-admin-customer-phone"><?php echo esc_html__('Customer Phone', 'quickslot'); ?></label></th>
							<td><input type="text" id="qs-admin-customer-phone" name="customer_phone" class="regular-text" value="<?php echo esc_attr((string) $form_data['customer_phone']); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-admin-customer-note"><?php echo esc_html__('Customer Note', 'quickslot'); ?></label></th>
							<td><textarea id="qs-admin-customer-note" name="customer_note" class="large-text" rows="5"><?php echo esc_textarea((string) $form_data['customer_note']); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-admin-booking-status"><?php echo esc_html__('Status', 'quickslot'); ?></label></th>
							<td>
								<select id="qs-admin-booking-status" name="status">
									<?php foreach ($this->admin_create_statuses as $status) : ?>
										<option value="<?php echo esc_attr($status); ?>" <?php selected((string) $form_data['status'], $status); ?>><?php echo esc_html($this->get_status_label($status)); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" name="qs_admin_booking_submit" class="button button-primary"><?php echo esc_html__('Create Booking', 'quickslot'); ?></button>
					<a href="<?php echo esc_url($this->get_bookings_url()); ?>" class="button"><?php echo esc_html__('Cancel', 'quickslot'); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the booking detail page.
	 */
	private function render_detail_page(): void {
		$booking_id = $this->get_requested_booking_id();
		$booking    = $this->get_booking($booking_id);

		if (null === $booking) {
			wp_safe_redirect($this->get_bookings_url(array('qs_notice' => 'not_found')));
			exit;
		}

		?>
		<div class="wrap qs-admin-page qs-bookings-admin">
			<h1><?php echo esc_html(sprintf(__('Booking #%d', 'quickslot'), (int) $booking['id'])); ?></h1>
			<p><a href="<?php echo esc_url($this->get_bookings_url()); ?>">&larr; <?php echo esc_html__('Back to bookings', 'quickslot'); ?></a></p>

			<div class="qs-booking-detail-grid">
				<div class="qs-booking-detail-card">
					<h2><?php echo esc_html__('Booking Details', 'quickslot'); ?></h2>
					<table class="widefat striped qs-booking-detail-table">
						<tbody>
							<?php foreach ($this->get_detail_rows($booking) as $label => $value) : ?>
								<tr>
									<th scope="row"><?php echo esc_html($label); ?></th>
									<td><?php echo esc_html($value); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="qs-booking-detail-card">
					<h2><?php echo esc_html__('Update Status', 'quickslot'); ?></h2>
					<form method="post" action="<?php echo esc_url($this->get_view_url((int) $booking['id'])); ?>">
						<?php wp_nonce_field('qs_update_booking_status_' . (int) $booking['id']); ?>
						<input type="hidden" name="booking_id" value="<?php echo esc_attr((string) $booking['id']); ?>">
						<p>
							<label for="qs-booking-status"><strong><?php echo esc_html__('Status', 'quickslot'); ?></strong></label>
						</p>
						<p>
							<select id="qs-booking-status" name="booking_status">
								<?php foreach ($this->allowed_statuses as $status) : ?>
									<option value="<?php echo esc_attr($status); ?>" <?php selected((string) $booking['status'], $status); ?>><?php echo esc_html($this->get_status_label($status)); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p>
							<button type="submit" name="qs_booking_status_submit" class="button button-primary"><?php echo esc_html__('Save Status', 'quickslot'); ?></button>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handles a status update.
	 */
	private function handle_status_update(): void {
		$booking_id = isset($_POST['booking_id']) ? absint(wp_unslash($_POST['booking_id'])) : 0;
		$new_status = isset($_POST['booking_status']) ? QS_Sanitizer::text(wp_unslash($_POST['booking_status'])) : '';

		if ($booking_id < 1) {
			wp_safe_redirect($this->get_bookings_url(array('qs_notice' => 'invalid')));
			exit;
		}

		check_admin_referer('qs_update_booking_status_' . $booking_id);

		if (! in_array($new_status, $this->allowed_statuses, true)) {
			wp_safe_redirect($this->get_view_url($booking_id, array('qs_notice' => 'invalid')));
			exit;
		}

		$booking = $this->get_booking($booking_id);

		if (null === $booking) {
			wp_safe_redirect($this->get_bookings_url(array('qs_notice' => 'not_found')));
			exit;
		}

		$old_status = (string) $booking['status'];

		if ($old_status === $new_status) {
			wp_safe_redirect($this->get_view_url($booking_id, array('qs_notice' => 'updated')));
			exit;
		}

		global $wpdb;

		$table  = $wpdb->prefix . 'qs_bookings';
		$query  = $wpdb->prepare(
			"UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d",
			$new_status,
			current_time('mysql'),
			$booking_id
		);
		$result = $wpdb->query($query);

		if (false === $result) {
			wp_safe_redirect($this->get_view_url($booking_id, array('qs_notice' => 'error')));
			exit;
		}

		do_action('quickslot_booking_status_changed', $booking_id, $old_status, $new_status);

		if ('confirmed' === $new_status) {
			do_action('quickslot_booking_confirmed', $booking_id);
		}

		if ('cancelled' === $new_status) {
			do_action('quickslot_booking_cancelled', $booking_id, '');
		}

		wp_safe_redirect($this->get_view_url($booking_id, array('qs_notice' => 'updated')));
		exit;
	}

	/**
	 * Handles CSV export.
	 */
	private function handle_export(): void {
		check_admin_referer('qs_export_bookings_csv');

		$rows = $this->get_bookings_for_export();

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="quickslot-bookings-' . gmdate('Y-m-d') . '.csv"');

		$output = fopen('php://output', 'w');

		if (false === $output) {
			exit;
		}

		fputcsv($output, array('ID', 'Customer Name', 'Email', 'Phone', 'Service', 'Start', 'End', 'Timezone', 'Status', 'Source', 'Reminder Sent', 'Created'));

		foreach ($rows as $row) {
			fputcsv(
				$output,
				array(
					(string) $row->id,
					(string) $row->customer_name,
					(string) $row->customer_email,
					(string) $row->customer_phone,
					(string) $row->service_name,
					$this->format_utc_datetime_for_display((string) $row->booking_start),
					$this->format_utc_datetime_for_display((string) $row->booking_end),
					(string) $row->timezone,
					$this->get_status_label((string) $row->status),
					(string) $row->booking_source,
					! empty($row->reminder_sent) ? '1' : '0',
					$this->format_local_datetime_for_display((string) $row->created_at),
				)
			);
		}

		fclose($output);
		exit;
	}

	/**
	 * Handles admin booking creation.
	 */
	private function handle_admin_booking_create(): void {
		check_admin_referer('qs_create_admin_booking');

		$form_data = array(
			'service_id'     => isset($_POST['service_id']) ? absint(wp_unslash($_POST['service_id'])) : 0,
			'booking_date'   => isset($_POST['booking_date']) ? QS_Sanitizer::text(wp_unslash($_POST['booking_date'])) : '',
			'booking_time'   => isset($_POST['booking_time']) ? QS_Sanitizer::text(wp_unslash($_POST['booking_time'])) : '',
			'customer_name'  => isset($_POST['customer_name']) ? QS_Sanitizer::text(wp_unslash($_POST['customer_name'])) : '',
			'customer_email' => isset($_POST['customer_email']) ? QS_Sanitizer::email(wp_unslash($_POST['customer_email'])) : '',
			'customer_phone' => isset($_POST['customer_phone']) ? QS_Sanitizer::text(wp_unslash($_POST['customer_phone'])) : '',
			'customer_note'  => isset($_POST['customer_note']) ? QS_Sanitizer::textarea(wp_unslash($_POST['customer_note'])) : '',
			'status'         => isset($_POST['status']) ? QS_Sanitizer::text(wp_unslash($_POST['status'])) : 'pending',
		);

		$errors = $this->validate_admin_booking_data($form_data);

		if (! empty($errors)) {
			$this->render_add_page($form_data, $errors);
			exit;
		}

		$this->ensure_booking_dependencies_loaded();

		$handler = new QS_Booking_Handler();
		$result  = $handler->create(
			array(
				'service_id'     => (int) $form_data['service_id'],
				'booking_date'   => (string) $form_data['booking_date'],
				'booking_time'   => (string) $form_data['booking_time'],
				'customer_name'  => (string) $form_data['customer_name'],
				'customer_email' => (string) $form_data['customer_email'],
				'customer_phone' => (string) $form_data['customer_phone'],
				'customer_note'  => (string) $form_data['customer_note'],
				'timezone'       => wp_timezone_string(),
				'booking_source' => 'admin',
				'status'         => (string) $form_data['status'],
			)
		);

		if ($result instanceof WP_Error) {
			$errors = array($result->get_error_message());
			$this->render_add_page($form_data, $errors);
			exit;
		}

		$booking_id = (int) $result;

		wp_safe_redirect($this->get_view_url($booking_id, array('qs_notice' => 'created')));
		exit;
	}

	/**
	 * Gets paginated bookings data.
	 *
	 * @return array<string, mixed>
	 */
	private function get_bookings_page_data(): array {
		global $wpdb;

		$query_data   = $this->build_bookings_query_parts();
		$filters      = $query_data['filters'];
		$current_page = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
		$offset       = ($current_page - 1) * self::PER_PAGE;
		$bookings     = $wpdb->prefix . 'qs_bookings';
		$services     = $wpdb->prefix . 'qs_services';
		$query_args   = $query_data['query_args'];
		$where_sql    = $query_data['where_sql'];
		$count_query = "SELECT COUNT(b.id) FROM {$bookings} b LEFT JOIN {$services} s ON s.id = b.service_id {$where_sql}";
		$list_query  = "SELECT b.id, b.customer_name, b.booking_start, b.booking_end, b.status, b.booking_source, b.created_at, COALESCE(s.name, '') AS service_name FROM {$bookings} b LEFT JOIN {$services} s ON s.id = b.service_id {$where_sql} ORDER BY b.booking_start DESC, b.id DESC LIMIT %d OFFSET %d";

		$total_items = empty($query_args)
			? (int) $wpdb->get_var($count_query)
			: (int) $wpdb->get_var($wpdb->prepare($count_query, $query_args));

		$list_args = $query_args;
		$list_args[] = self::PER_PAGE;
		$list_args[] = $offset;

		$items = $wpdb->get_results(empty($query_args) ? $wpdb->prepare($list_query, self::PER_PAGE, $offset) : $wpdb->prepare($list_query, $list_args));
		$total_pages = max(1, (int) ceil($total_items / self::PER_PAGE));

		return array(
			'items'        => is_array($items) ? $items : array(),
			'total_items'  => $total_items,
			'total_pages'  => $total_pages,
			'current_page' => min($current_page, $total_pages),
			'filters'      => $filters,
		);
	}

	/**
	 * Returns bookings for export.
	 *
	 * @return array<int, object>
	 */
	private function get_bookings_for_export(): array {
		global $wpdb;

		$query_data = $this->build_bookings_query_parts();
		$bookings   = $wpdb->prefix . 'qs_bookings';
		$services   = $wpdb->prefix . 'qs_services';
		$sql        = "SELECT b.id, b.customer_name, b.customer_email, b.customer_phone, b.booking_start, b.booking_end, b.timezone, b.status, b.booking_source, b.reminder_sent, b.created_at, COALESCE(s.name, '') AS service_name FROM {$bookings} b LEFT JOIN {$services} s ON s.id = b.service_id {$query_data['where_sql']} ORDER BY b.booking_start DESC, b.id DESC";

		$results = empty($query_data['query_args'])
			? $wpdb->get_results($sql)
			: $wpdb->get_results($wpdb->prepare($sql, $query_data['query_args']));

		return is_array($results) ? $results : array();
	}

	/**
	 * Returns current filter values.
	 *
	 * @return array<string, int|string>
	 */
	private function get_filters(): array {
		$date_from       = isset($_GET['date_from']) ? QS_Sanitizer::text(wp_unslash($_GET['date_from'])) : '';
		$date_to         = isset($_GET['date_to']) ? QS_Sanitizer::text(wp_unslash($_GET['date_to'])) : '';
		$service_id      = isset($_GET['service_id']) ? absint(wp_unslash($_GET['service_id'])) : 0;
		$status          = isset($_GET['status']) ? QS_Sanitizer::text(wp_unslash($_GET['status'])) : '';
		$customer_search = isset($_GET['customer_search']) ? QS_Sanitizer::text(wp_unslash($_GET['customer_search'])) : '';

		return array(
			'date_from'       => QS_Sanitizer::is_date($date_from) ? $date_from : '',
			'date_to'         => QS_Sanitizer::is_date($date_to) ? $date_to : '',
			'service_id'      => $service_id,
			'status'          => in_array($status, $this->allowed_statuses, true) ? $status : '',
			'customer_search' => $customer_search,
		);
	}

	/**
	 * Returns default add-booking form values.
	 *
	 * @return array<string, string|int>
	 */
	private function get_default_booking_form_data(): array {
		return array(
			'service_id'     => 0,
			'booking_date'   => current_datetime()->format('Y-m-d'),
			'booking_time'   => '',
			'customer_name'  => '',
			'customer_email' => '',
			'customer_phone' => '',
			'customer_note'  => '',
			'status'         => 'pending',
		);
	}

	/**
	 * Returns a booking row.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_booking(int $booking_id): ?array {
		global $wpdb;

		if ($booking_id < 1) {
			return null;
		}

		$bookings = $wpdb->prefix . 'qs_bookings';
		$services = $wpdb->prefix . 'qs_services';
		$query    = $wpdb->prepare(
			"SELECT b.*, COALESCE(s.name, '') AS service_name FROM {$bookings} b LEFT JOIN {$services} s ON s.id = b.service_id WHERE b.id = %d LIMIT 1",
			$booking_id
		);
		$row      = $wpdb->get_row($query, ARRAY_A);

		return is_array($row) ? $row : null;
	}

	/**
	 * Returns service options for filters.
	 *
	 * @return array<int, object>
	 */
	private function get_services_for_filter(): array {
		global $wpdb;

		$services = $wpdb->prefix . 'qs_services';
		$query    = "SELECT id, name FROM {$services} ORDER BY name ASC";
		$rows     = $wpdb->get_results($query);

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Returns active services for manual booking.
	 *
	 * @return array<int, object>
	 */
	private function get_active_services(): array {
		global $wpdb;

		$services = $wpdb->prefix . 'qs_services';
		$sql      = $wpdb->prepare("SELECT id, name FROM {$services} WHERE status = %s ORDER BY name ASC", 'active');
		$rows     = $wpdb->get_results($sql);

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Renders admin notices.
	 */
	private function render_notice(): void {
		if (! isset($_GET['qs_notice'])) {
			return;
		}

		$notice_key = QS_Sanitizer::text(wp_unslash($_GET['qs_notice']));
		$notices    = array(
			'created'   => array('success', __('Booking created.', 'quickslot')),
			'updated'   => array('success', __('Booking status updated.', 'quickslot')),
			'not_found' => array('error', __('Booking not found.', 'quickslot')),
			'invalid'   => array('error', __('Invalid booking request.', 'quickslot')),
			'error'     => array('error', __('An error occurred. Please try again.', 'quickslot')),
		);

		if (! isset($notices[ $notice_key ])) {
			return;
		}

		$notice = $notices[ $notice_key ];
		?>
		<div class="notice notice-<?php echo esc_attr($notice[0]); ?> is-dismissible"><p><?php echo esc_html($notice[1]); ?></p></div>
		<?php
	}

	/**
	 * Renders pagination.
	 */
	private function render_pagination(int $total_items, int $current_page, int $total_pages): void {
		if ($total_pages <= 1) {
			return;
		}

		?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<span class="displaying-num"><?php echo esc_html(sprintf(_n('%d item', '%d items', $total_items, 'quickslot'), $total_items)); ?></span>
				<span class="pagination-links">
					<?php for ($page = 1; $page <= $total_pages; $page++) : ?>
						<?php if ($page === $current_page) : ?>
							<span class="tablenav-pages-navspan" aria-current="page"><?php echo esc_html((string) $page); ?></span>
						<?php else : ?>
							<a class="button" href="<?php echo esc_url($this->get_bookings_url(array_merge($this->get_filters_for_query_args(), array('paged' => $page)))); ?>"><?php echo esc_html((string) $page); ?></a>
						<?php endif; ?>
					<?php endfor; ?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Returns detail view rows.
	 *
	 * @param array<string, mixed> $booking Booking data.
	 * @return array<string, string>
	 */
	private function get_detail_rows(array $booking): array {
		return array(
			__('Customer Name', 'quickslot')    => (string) $booking['customer_name'],
			__('Customer Email', 'quickslot')   => (string) $booking['customer_email'],
			__('Customer Phone', 'quickslot')   => (string) $booking['customer_phone'],
			__('Customer Note', 'quickslot')    => (string) $booking['customer_note'],
			__('Service', 'quickslot')          => (string) $booking['service_name'],
			__('Booking Start', 'quickslot')    => $this->format_utc_datetime_for_display((string) $booking['booking_start']),
			__('Booking End', 'quickslot')      => $this->format_utc_datetime_for_display((string) $booking['booking_end']),
			__('Timezone', 'quickslot')         => (string) $booking['timezone'],
			__('Status', 'quickslot')           => $this->get_status_label((string) $booking['status']),
			__('Payment Status', 'quickslot')   => (string) $booking['payment_status'],
			__('Booking Source', 'quickslot')   => (string) $booking['booking_source'],
			__('Calendar Provider', 'quickslot') => (string) $booking['calendar_provider'],
			__('Calendar Event ID', 'quickslot') => (string) $booking['calendar_event_id'],
			__('Reminder Sent', 'quickslot')    => ! empty($booking['reminder_sent']) ? __('Yes', 'quickslot') : __('No', 'quickslot'),
			__('Created', 'quickslot')          => $this->format_local_datetime_for_display((string) $booking['created_at']),
			__('Updated', 'quickslot')          => $this->format_local_datetime_for_display((string) $booking['updated_at']),
		);
	}

	/**
	 * Formats a UTC datetime for site timezone display.
	 */
	private function format_utc_datetime_for_display(string $value): string {
		if ('' === $value) {
			return '';
		}

		$utc = new DateTimeZone('UTC');
		$date = new DateTimeImmutable($value, $utc);

		return $date->setTimezone(wp_timezone())->format('Y-m-d H:i');
	}

	/**
	 * Formats a stored site-local datetime.
	 */
	private function format_local_datetime_for_display(string $value): string {
		if ('' === $value) {
			return '';
		}

		$date = new DateTimeImmutable($value, wp_timezone());

		return $date->format('Y-m-d H:i');
	}

	/**
	 * Formats booking range for the list table.
	 */
	private function format_booking_range(string $start, string $end): string {
		return sprintf(
			/* translators: 1: start datetime, 2: end datetime */
			__('%1$s to %2$s', 'quickslot'),
			$this->format_utc_datetime_for_display($start),
			$this->format_utc_datetime_for_display($end)
		);
	}

	/**
	 * Returns a human label for a status.
	 */
	private function get_status_label(string $status): string {
		$labels = array(
			'pending'   => __('Pending', 'quickslot'),
			'confirmed' => __('Confirmed', 'quickslot'),
			'cancelled' => __('Cancelled', 'quickslot'),
			'completed' => __('Completed', 'quickslot'),
			'no-show'   => __('No-show', 'quickslot'),
		);

		return $labels[ $status ] ?? $status;
	}

	/**
	 * Returns CSS status class.
	 */
	private function get_status_class(string $status): string {
		return sanitize_html_class($status);
	}

	/**
	 * Returns the export URL.
	 */
	private function get_export_url(): string {
		return wp_nonce_url(
			$this->get_bookings_url(array_merge($this->get_filters_for_query_args(), array('action' => 'export'))),
			'qs_export_bookings_csv'
		);
	}

	/**
	 * Returns the requested action.
	 */
	private function get_action(): string {
		return isset($_GET['action']) ? QS_Sanitizer::text(wp_unslash($_GET['action'])) : '';
	}

	/**
	 * Returns the requested booking ID.
	 */
	private function get_requested_booking_id(): int {
		return isset($_GET['booking_id']) ? absint(wp_unslash($_GET['booking_id'])) : 0;
	}

	/**
	 * Returns the bookings page URL.
	 *
	 * @param array<string, int|string> $args Query args.
	 */
	private function get_bookings_url(array $args = array()): string {
		return add_query_arg(array_merge(array('page' => 'quickslot-bookings'), $args), admin_url('admin.php'));
	}

	/**
	 * Returns the detail view URL.
	 *
	 * @param array<string, int|string> $args Extra query args.
	 */
	private function get_view_url(int $booking_id, array $args = array()): string {
		return $this->get_bookings_url(array_merge(array('action' => 'view', 'booking_id' => $booking_id), $args));
	}

	/**
	 * Returns current filters as query args.
	 *
	 * @return array<string, int|string>
	 */
	private function get_filters_for_query_args(): array {
		$filters = $this->get_filters();

		return array_filter(
			$filters,
			static function ($value): bool {
				return '' !== $value && 0 !== $value;
			}
		);
	}

	/**
	 * Returns query SQL and args for the current filters.
	 *
	 * @return array<string, array<int, int|string>|string>
	 */
	private function build_bookings_query_parts(): array {
		global $wpdb;

		$filters     = $this->get_filters();
		$where_parts = array('1=1');
		$query_args  = array();

		if ('' !== $filters['date_from']) {
			$where_parts[] = 'b.booking_start >= %s';
			$query_args[]  = $this->convert_date_to_utc_boundary($filters['date_from'], 'start');
		}

		if ('' !== $filters['date_to']) {
			$where_parts[] = 'b.booking_start <= %s';
			$query_args[]  = $this->convert_date_to_utc_boundary($filters['date_to'], 'end');
		}

		if ((int) $filters['service_id'] > 0) {
			$where_parts[] = 'b.service_id = %d';
			$query_args[]  = (int) $filters['service_id'];
		}

		if ('' !== $filters['status']) {
			$where_parts[] = 'b.status = %s';
			$query_args[]  = $filters['status'];
		}

		if ('' !== $filters['customer_search']) {
			$like          = '%' . $wpdb->esc_like($filters['customer_search']) . '%';
			$where_parts[] = '(b.customer_name LIKE %s OR b.customer_email LIKE %s)';
			$query_args[]  = $like;
			$query_args[]  = $like;
		}

		return array(
			'filters'    => $filters,
			'where_sql'  => 'WHERE ' . implode(' AND ', $where_parts),
			'query_args' => $query_args,
		);
	}

	/**
	 * Validates admin manual booking input.
	 *
	 * @param array<string, string|int> $data Form data.
	 * @return array<int, string>
	 */
	private function validate_admin_booking_data(array $data): array {
		$errors = array();
		$today  = current_datetime()->format('Y-m-d');

		if ((int) $data['service_id'] < 1 || ! $this->service_is_active((int) $data['service_id'])) {
			$errors[] = __('Please choose an active service.', 'quickslot');
		}

		if (! QS_Sanitizer::is_date((string) $data['booking_date'])) {
			$errors[] = __('Please enter a valid booking date.', 'quickslot');
		} elseif ((string) $data['booking_date'] < $today) {
			$errors[] = __('Booking date must be today or later.', 'quickslot');
		}

		if (! QS_Sanitizer::is_time((string) $data['booking_time'])) {
			$errors[] = __('Please enter a valid booking time.', 'quickslot');
		}

		if ('' === (string) $data['customer_name']) {
			$errors[] = __('Customer name is required.', 'quickslot');
		}

		if ('' === (string) $data['customer_email'] || ! is_email((string) $data['customer_email'])) {
			$errors[] = __('Please enter a valid customer email.', 'quickslot');
		}

		if (! in_array((string) $data['status'], $this->admin_create_statuses, true)) {
			$errors[] = __('Please choose a valid booking status.', 'quickslot');
		}

		if (empty($errors)) {
			$this->ensure_booking_dependencies_loaded();
			$checker = new QS_Availability_Checker();

			if (! $checker->is_available((int) $data['service_id'], (string) $data['booking_date'], (string) $data['booking_time'])) {
				$errors[] = __('This time slot is no longer available.', 'quickslot');
			}
		}

		return $errors;
	}

	/**
	 * Checks whether a service is active.
	 */
	private function service_is_active(int $service_id): bool {
		global $wpdb;

		if ($service_id < 1) {
			return false;
		}

		$table = $wpdb->prefix . 'qs_services';
		$query = $wpdb->prepare("SELECT id FROM {$table} WHERE id = %d AND status = %s LIMIT 1", $service_id, 'active');
		$row   = $wpdb->get_var($query);

		return null !== $row;
	}

	/**
	 * Ensures shared booking classes are available in admin requests.
	 */
	private function ensure_booking_dependencies_loaded(): void {
		if (! class_exists('QS_Slot_Generator', false)) {
			require_once QUICKSLOT_PATH . 'includes/core/class-qs-slot-generator.php';
		}

		if (! class_exists('QS_Availability_Checker', false)) {
			require_once QUICKSLOT_PATH . 'includes/core/class-qs-availability-checker.php';
		}

		if (! class_exists('QS_Booking_Handler', false)) {
			require_once QUICKSLOT_PATH . 'includes/core/class-qs-booking-handler.php';
		}
	}

	/**
	 * Converts a site-local date into a UTC boundary string.
	 */
	private function convert_date_to_utc_boundary(string $date_ymd, string $boundary): string {
		$time = 'start' === $boundary ? '00:00:00' : '23:59:59';
		$date = new DateTimeImmutable($date_ymd . ' ' . $time, wp_timezone());

		return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	}
}
