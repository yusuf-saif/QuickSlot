<?php
/**
 * Services admin CRUD.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Services_Admin {
	/**
	 * Items shown per page.
	 */
	private const PER_PAGE = 20;

	/**
	 * Renders the services admin page.
	 */
	public function render_page(): void {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'quickslot'));
		}

		$this->handle_actions();
		$this->render_notice();

		$action = $this->get_action();

		if ('add' === $action || 'edit' === $action) {
			$this->render_form_page();
			return;
		}

		$this->render_list_page();
	}

	/**
	 * Handles services form and row actions.
	 */
	private function handle_actions(): void {
		if ('POST' === $_SERVER['REQUEST_METHOD']) {
			$this->handle_save();
			return;
		}

		$action = $this->get_action();

		if ('deactivate' === $action) {
			$this->handle_deactivate();
			return;
		}

		if ('delete' === $action) {
			$this->handle_delete();
		}
	}

	/**
	 * Saves a service.
	 */
	private function handle_save(): void {
		if (! isset($_POST['qs_service_submit'])) {
			return;
		}

		check_admin_referer('qs_save_service');

		$service_id   = isset($_POST['service_id']) ? absint(wp_unslash($_POST['service_id'])) : 0;
		$service_name = isset($_POST['service_name']) ? QS_Sanitizer::text(wp_unslash($_POST['service_name'])) : '';
		$description  = isset($_POST['description']) ? QS_Sanitizer::textarea(wp_unslash($_POST['description'])) : '';
		$duration     = isset($_POST['duration']) ? absint(wp_unslash($_POST['duration'])) : 0;
		$price_raw    = isset($_POST['price']) ? wp_unslash($_POST['price']) : '';
		$is_paid      = isset($_POST['is_paid']) ? 1 : 0;
		$status       = isset($_POST['status']) ? QS_Sanitizer::text(wp_unslash($_POST['status'])) : 'active';

		$form_data = array(
			'id'          => $service_id,
			'name'        => $service_name,
			'description' => $description,
			'duration'    => $duration,
			'price'       => $this->normalize_price($price_raw),
			'is_paid'     => $is_paid,
			'status'      => in_array($status, array('active', 'inactive'), true) ? $status : 'active',
		);

		$errors = $this->validate_service_data($form_data);

		if (! empty($errors)) {
			$this->render_form_page($form_data, $errors);
			exit;
		}

		$result = $service_id > 0 ? $this->update_service($form_data) : $this->insert_service($form_data);

		if (! $result) {
			$this->render_form_page(
				$form_data,
				array(esc_html__('The service could not be saved. Please try again.', 'quickslot'))
			);
			exit;
		}

		$this->clear_services_cache();

		$notice = $service_id > 0 ? 'updated' : 'created';
		wp_safe_redirect($this->get_services_url(array('qs_notice' => $notice)));
		exit;
	}

	/**
	 * Deactivates a service.
	 */
	private function handle_deactivate(): void {
		$service_id = $this->get_requested_service_id();

		if ($service_id < 1) {
			wp_safe_redirect($this->get_services_url(array('qs_notice' => 'invalid')));
			exit;
		}

		check_admin_referer('qs_deactivate_service_' . $service_id);

		global $wpdb;

		$table   = $wpdb->prefix . 'qs_services';
		$sql     = $wpdb->prepare(
			"UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d",
			'inactive',
			current_time('mysql'),
			$service_id
		);
		$result  = $wpdb->query($sql);
		$notice  = false === $result ? 'error' : 'deactivated';

		if (false !== $result) {
			$this->clear_services_cache();
		}

		wp_safe_redirect($this->get_services_url(array('qs_notice' => $notice)));
		exit;
	}

	/**
	 * Deletes a service.
	 */
	private function handle_delete(): void {
		$service_id = $this->get_requested_service_id();

		if ($service_id < 1) {
			wp_safe_redirect($this->get_services_url(array('qs_notice' => 'invalid')));
			exit;
		}

		check_admin_referer('qs_delete_service_' . $service_id);

		global $wpdb;

		$table  = $wpdb->prefix . 'qs_services';
		$sql    = $wpdb->prepare("DELETE FROM {$table} WHERE id = %d", $service_id);
		$result = $wpdb->query($sql);
		$notice = false === $result ? 'error' : 'deleted';

		if (false !== $result) {
			$this->clear_services_cache();
		}

		wp_safe_redirect($this->get_services_url(array('qs_notice' => $notice)));
		exit;
	}

	/**
	 * Renders the services list page.
	 */
	private function render_list_page(): void {
		$page_data = $this->get_services_page_data();
		$page_url  = $this->get_services_url();

		?>
		<div class="wrap qs-admin-page">
			<h1 class="wp-heading-inline"><?php echo esc_html__('Services', 'quickslot'); ?></h1>
			<a href="<?php echo esc_url($this->get_services_url(array('action' => 'add'))); ?>" class="page-title-action"><?php echo esc_html__('Add New Service', 'quickslot'); ?></a>
			<hr class="wp-header-end">

			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__('ID', 'quickslot'); ?></th>
						<th scope="col"><?php echo esc_html__('Name', 'quickslot'); ?></th>
						<th scope="col"><?php echo esc_html__('Duration', 'quickslot'); ?></th>
						<th scope="col"><?php echo esc_html__('Price', 'quickslot'); ?></th>
						<th scope="col"><?php echo esc_html__('Status', 'quickslot'); ?></th>
						<th scope="col"><?php echo esc_html__('Actions', 'quickslot'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($page_data['items'])) : ?>
						<tr>
							<td colspan="6"><?php echo esc_html__('No services found.', 'quickslot'); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ($page_data['items'] as $service) : ?>
							<tr>
								<td><?php echo esc_html((string) $service->id); ?></td>
								<td><?php echo esc_html($service->name); ?></td>
								<td><?php echo esc_html(sprintf(__('%d minutes', 'quickslot'), (int) $service->duration)); ?></td>
								<td><?php echo esc_html($this->format_price((string) $service->price)); ?></td>
								<td><?php echo esc_html(ucfirst((string) $service->status)); ?></td>
								<td>
									<a href="<?php echo esc_url($this->get_services_url(array('action' => 'edit', 'service_id' => (int) $service->id))); ?>"><?php echo esc_html__('Edit', 'quickslot'); ?></a>
									|
									<a href="<?php echo esc_url($this->get_deactivate_url((int) $service->id)); ?>"><?php echo esc_html__('Deactivate', 'quickslot'); ?></a>
									|
									<a href="<?php echo esc_url($this->get_delete_url((int) $service->id)); ?>" onclick="return window.confirm('<?php echo esc_js(__('Are you sure you want to delete this service?', 'quickslot')); ?>');"><?php echo esc_html__('Delete', 'quickslot'); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ($page_data['total_pages'] > 1) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php echo esc_html(sprintf(_n('%d item', '%d items', $page_data['total_items'], 'quickslot'), $page_data['total_items'])); ?>
						</span>
						<span class="pagination-links">
							<?php for ($page = 1; $page <= $page_data['total_pages']; $page++) : ?>
								<?php if ($page === $page_data['current_page']) : ?>
									<span class="tablenav-pages-navspan" aria-current="page"><?php echo esc_html((string) $page); ?></span>
								<?php else : ?>
									<a class="button" href="<?php echo esc_url(add_query_arg('paged', (string) $page, $page_url)); ?>"><?php echo esc_html((string) $page); ?></a>
								<?php endif; ?>
							<?php endfor; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the add/edit form page.
	 *
	 * @param array<string, int|string|null>|null $form_data Current form values.
	 * @param array<int, string>             $errors Validation errors.
	 */
	private function render_form_page(?array $form_data = null, array $errors = array()): void {
		$service_id = $this->get_requested_service_id();

		if (null === $form_data) {
			$form_data = $service_id > 0 ? $this->get_service_for_form($service_id) : $this->get_default_form_data();
		}

		if ($service_id > 0 && empty($form_data['id'])) {
			wp_safe_redirect($this->get_services_url(array('qs_notice' => 'not_found')));
			exit;
		}

		$page_title = ! empty($form_data['id']) ? __('Edit Service', 'quickslot') : __('Add New Service', 'quickslot');
		?>
		<div class="wrap qs-admin-page">
			<h1><?php echo esc_html($page_title); ?></h1>

			<?php if (! empty($errors)) : ?>
				<div class="notice notice-error"><ul>
					<?php foreach ($errors as $error) : ?>
						<li><?php echo esc_html($error); ?></li>
					<?php endforeach; ?>
				</ul></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url($this->get_services_url()); ?>">
				<?php wp_nonce_field('qs_save_service'); ?>
				<input type="hidden" name="service_id" value="<?php echo esc_attr((string) $form_data['id']); ?>">

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="qs-service-name"><?php echo esc_html__('Service Name', 'quickslot'); ?></label></th>
							<td><input name="service_name" type="text" id="qs-service-name" class="regular-text" required value="<?php echo esc_attr((string) $form_data['name']); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-service-description"><?php echo esc_html__('Description', 'quickslot'); ?></label></th>
							<td><textarea name="description" id="qs-service-description" class="large-text" rows="5"><?php echo esc_textarea((string) $form_data['description']); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-service-duration"><?php echo esc_html__('Duration in Minutes', 'quickslot'); ?></label></th>
							<td><input name="duration" type="number" id="qs-service-duration" class="small-text" min="5" max="480" required value="<?php echo esc_attr((string) $form_data['duration']); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-service-price"><?php echo esc_html__('Price', 'quickslot'); ?></label></th>
							<td><input name="price" type="text" id="qs-service-price" class="small-text" value="<?php echo esc_attr((string) $form_data['price']); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__('Is Paid', 'quickslot'); ?></th>
							<td><label for="qs-service-is-paid"><input name="is_paid" type="checkbox" id="qs-service-is-paid" value="1" <?php checked((int) $form_data['is_paid'], 1); ?>> <?php echo esc_html__('Yes', 'quickslot'); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><label for="qs-service-status"><?php echo esc_html__('Status', 'quickslot'); ?></label></th>
							<td>
								<select name="status" id="qs-service-status">
									<option value="active" <?php selected((string) $form_data['status'], 'active'); ?>><?php echo esc_html__('Active', 'quickslot'); ?></option>
									<option value="inactive" <?php selected((string) $form_data['status'], 'inactive'); ?>><?php echo esc_html__('Inactive', 'quickslot'); ?></option>
								</select>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" name="qs_service_submit" class="button button-primary"><?php echo esc_html__('Save Service', 'quickslot'); ?></button>
					<a href="<?php echo esc_url($this->get_services_url()); ?>" class="button"><?php echo esc_html__('Cancel', 'quickslot'); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Gets paginated services data.
	 *
	 * @return array<string, array<int, object>|int>
	 */
	private function get_services_page_data(): array {
		global $wpdb;

		$table        = $wpdb->prefix . 'qs_services';
		$current_page = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
		$offset       = ($current_page - 1) * self::PER_PAGE;

		$total_items = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table}");
		$query       = $wpdb->prepare(
			"SELECT id, name, duration, price, status FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
			self::PER_PAGE,
			$offset
		);
		$items       = $wpdb->get_results($query);
		$total_pages = max(1, (int) ceil($total_items / self::PER_PAGE));

		return array(
			'items'        => is_array($items) ? $items : array(),
			'total_items'  => $total_items,
			'total_pages'  => $total_pages,
			'current_page' => min($current_page, $total_pages),
		);
	}

	/**
	 * Gets a service for editing.
	 *
	 * @param int $service_id Service ID.
	 * @return array<string, int|string|null>
	 */
	private function get_service_for_form(int $service_id): array {
		global $wpdb;

		$table = $wpdb->prefix . 'qs_services';
		$query = $wpdb->prepare(
			"SELECT id, name, description, duration, price, is_paid, status FROM {$table} WHERE id = %d",
			$service_id
		);
		$row   = $wpdb->get_row($query, ARRAY_A);

		if (! is_array($row)) {
			return array();
		}

		return array(
			'id'          => (int) $row['id'],
			'name'        => (string) $row['name'],
			'description' => (string) $row['description'],
			'duration'    => (int) $row['duration'],
			'price'       => null === $row['price'] ? '' : (string) $row['price'],
			'is_paid'     => (int) $row['is_paid'],
			'status'      => (string) $row['status'],
		);
	}

	/**
	 * Inserts a service.
	 *
	 * @param array<string, int|string|null> $data Sanitized service data.
	 */
	private function insert_service(array $data): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'qs_services';
		$price = '' === (string) $data['price'] ? null : (string) $data['price'];

		if (null === $price) {
			$sql = $wpdb->prepare(
				"INSERT INTO {$table} (name, description, duration, price, is_paid, status, created_at, updated_at) VALUES (%s, %s, %d, NULL, %d, %s, %s, %s)",
				(string) $data['name'],
				(string) $data['description'],
				(int) $data['duration'],
				(int) $data['is_paid'],
				(string) $data['status'],
				current_time('mysql'),
				current_time('mysql')
			);
		} else {
			$sql = $wpdb->prepare(
				"INSERT INTO {$table} (name, description, duration, price, is_paid, status, created_at, updated_at) VALUES (%s, %s, %d, %s, %d, %s, %s, %s)",
				(string) $data['name'],
				(string) $data['description'],
				(int) $data['duration'],
				$price,
				(int) $data['is_paid'],
				(string) $data['status'],
				current_time('mysql'),
				current_time('mysql')
			);
		}

		return false !== $wpdb->query($sql);
	}

	/**
	 * Updates a service.
	 *
	 * @param array<string, int|string|null> $data Sanitized service data.
	 */
	private function update_service(array $data): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'qs_services';
		$price = '' === (string) $data['price'] ? null : (string) $data['price'];

		if (null === $price) {
			$sql = $wpdb->prepare(
				"UPDATE {$table} SET name = %s, description = %s, duration = %d, price = NULL, is_paid = %d, status = %s, updated_at = %s WHERE id = %d",
				(string) $data['name'],
				(string) $data['description'],
				(int) $data['duration'],
				(int) $data['is_paid'],
				(string) $data['status'],
				current_time('mysql'),
				(int) $data['id']
			);
		} else {
			$sql = $wpdb->prepare(
				"UPDATE {$table} SET name = %s, description = %s, duration = %d, price = %s, is_paid = %d, status = %s, updated_at = %s WHERE id = %d",
				(string) $data['name'],
				(string) $data['description'],
				(int) $data['duration'],
				$price,
				(int) $data['is_paid'],
				(string) $data['status'],
				current_time('mysql'),
				(int) $data['id']
			);
		}

		return false !== $wpdb->query($sql);
	}

	/**
	 * Validates service data.
	 *
	 * @param array<string, int|string|null> $data Sanitized service data.
	 * @return array<int, string>
	 */
	private function validate_service_data(array $data): array {
		$errors = array();

		if ('' === (string) $data['name']) {
			$errors[] = esc_html__('Service name is required.', 'quickslot');
		}

		if ((int) $data['duration'] < 5 || (int) $data['duration'] > 480) {
			$errors[] = esc_html__('Duration must be between 5 and 480 minutes.', 'quickslot');
		}

		if (! $this->is_valid_price((string) $data['price'])) {
			$errors[] = esc_html__('Price must be a valid non-negative amount.', 'quickslot');
		}

		if (! in_array((string) $data['status'], array('active', 'inactive'), true)) {
			$errors[] = esc_html__('Status must be active or inactive.', 'quickslot');
		}

		return $errors;
	}

	/**
	 * Renders status notices.
	 */
	private function render_notice(): void {
		if (! isset($_GET['qs_notice'])) {
			return;
		}

		$notice_key = QS_Sanitizer::text(wp_unslash($_GET['qs_notice']));
		$notices    = array(
			'created'     => array('success', __('Service created.', 'quickslot')),
			'updated'     => array('success', __('Service updated.', 'quickslot')),
			'deactivated' => array('success', __('Service deactivated.', 'quickslot')),
			'deleted'     => array('success', __('Service deleted.', 'quickslot')),
			'not_found'   => array('error', __('Service not found.', 'quickslot')),
			'invalid'     => array('error', __('Invalid service request.', 'quickslot')),
			'error'       => array('error', __('An error occurred. Please try again.', 'quickslot')),
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
	 * Returns the current services admin action.
	 */
	private function get_action(): string {
		if (! isset($_REQUEST['action'])) {
			return 'list';
		}

		return QS_Sanitizer::text(wp_unslash($_REQUEST['action']));
	}

	/**
	 * Returns the requested service ID.
	 */
	private function get_requested_service_id(): int {
		return isset($_REQUEST['service_id']) ? absint(wp_unslash($_REQUEST['service_id'])) : 0;
	}

	/**
	 * Returns default form data.
	 *
	 * @return array<string, int|string|null>
	 */
	private function get_default_form_data(): array {
		return array(
			'id'          => 0,
			'name'        => '',
			'description' => '',
			'duration'    => 30,
			'price'       => '',
			'is_paid'     => 0,
			'status'      => 'active',
		);
	}

	/**
	 * Builds the services page URL.
	 *
	 * @param array<string, int|string> $args Optional query args.
	 */
	private function get_services_url(array $args = array()): string {
		$base_args = array('page' => 'quickslot-services');

		return admin_url('admin.php?' . http_build_query(array_merge($base_args, $args), '', '&'));
	}

	/**
	 * Builds the deactivate URL.
	 *
	 * @param int $service_id Service ID.
	 */
	private function get_deactivate_url(int $service_id): string {
		return wp_nonce_url(
			$this->get_services_url(
				array(
					'action'     => 'deactivate',
					'service_id' => $service_id,
				)
			),
			'qs_deactivate_service_' . $service_id
		);
	}

	/**
	 * Builds the delete URL.
	 *
	 * @param int $service_id Service ID.
	 */
	private function get_delete_url(int $service_id): string {
		return wp_nonce_url(
			$this->get_services_url(
				array(
					'action'     => 'delete',
					'service_id' => $service_id,
				)
			),
			'qs_delete_service_' . $service_id
		);
	}

	/**
	 * Normalizes a price field while preserving blank values.
	 *
	 * @param string $price Raw submitted price.
	 * @return string|null
	 */
	private function normalize_price(string $price): ?string {
		$price = trim($price);

		if ('' === $price) {
			return null;
		}

		$normalized = preg_replace('/[^0-9.]/', '', $price);

		if (null === $normalized || '' === $normalized) {
			return null;
		}

		return $normalized;
	}

	/**
	 * Validates a price string.
	 *
	 * @param string $price Price string.
	 */
	private function is_valid_price(?string $price): bool {
		if (null === $price || '' === $price) {
			return true;
		}

		if (! preg_match('/^\d+(\.\d{1,2})?$/', $price)) {
			return false;
		}

		return (float) $price >= 0;
	}

	/**
	 * Formats a price for display.
	 *
	 * @param string $price Stored price value.
	 */
	private function format_price(?string $price): string {
		if (null === $price || '' === $price) {
			return esc_html__('Free', 'quickslot');
		}

		return wp_strip_all_tags(wp_kses_post(wp_number_format((float) $price, 2)));
	}

	/**
	 * Clears cached public service responses.
	 */
	private function clear_services_cache(): void {
		delete_transient('qs_services_list');
	}
}
