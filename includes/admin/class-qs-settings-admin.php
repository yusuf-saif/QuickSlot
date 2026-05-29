<?php
/**
 * Settings admin controller.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Settings_Admin {
	/**
	 * Template helper.
	 *
	 * @var QS_Email_Templates
	 */
	private QS_Email_Templates $templates;

	/**
	 * Reminder scheduler.
	 *
	 * @var QS_Reminder_Scheduler
	 */
	private QS_Reminder_Scheduler $reminders;

	/**
	 * Google Calendar integration.
	 *
	 * @var QS_Google_Calendar
	 */
	private QS_Google_Calendar $google_calendar;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->templates = new QS_Email_Templates();
		$this->reminders = QS_Reminder_Scheduler::instance();
		$this->google_calendar = QS_Google_Calendar::instance();
	}

	/**
	 * Renders the settings page.
	 */
	public function render_page(): void {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'quickslot'));
		}

		$this->handle_actions();
		$this->render_notice();

		$active_tab = $this->get_active_tab();
		?>
		<div class="wrap qs-admin-page qs-settings-admin">
			<h1><?php echo esc_html__('Settings', 'quickslot'); ?></h1>

			<nav class="nav-tab-wrapper qs-settings-tabs">
				<?php foreach ($this->get_tabs() as $tab => $label) : ?>
					<a href="<?php echo esc_url($this->get_settings_url(array('tab' => $tab))); ?>" class="nav-tab<?php echo $active_tab === $tab ? ' nav-tab-active' : ''; ?>"><?php echo esc_html($label); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php if ('general' === $active_tab) : ?>
				<?php $this->render_general_tab(); ?>
			<?php elseif ('email' === $active_tab) : ?>
				<?php $this->render_email_tab(); ?>
			<?php elseif ('calendar' === $active_tab) : ?>
				<?php $this->render_calendar_tab(); ?>
			<?php else : ?>
				<?php $this->render_reminders_tab(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handles settings form submissions.
	 */
	private function handle_actions(): void {
		if ('POST' !== $_SERVER['REQUEST_METHOD']) {
			return;
		}

		if (isset($_POST['qs_save_general_settings'])) {
			$this->handle_general_save();
			return;
		}

		if (isset($_POST['qs_save_email_templates'])) {
			$this->handle_email_templates_save();
			return;
		}

		if (isset($_POST['qs_save_calendar_settings'])) {
			$this->handle_calendar_save();
			return;
		}

		if (isset($_POST['qs_save_reminders'])) {
			$this->handle_reminders_save();
		}
	}

	/**
	 * Renders the General tab.
	 */
	private function render_general_tab(): void {
		$business_name  = (string) get_option('qs_business_name', '');
		$business_email = (string) get_option('qs_business_email', '');
		?>
		<form method="post" action="<?php echo esc_url($this->get_settings_url(array('tab' => 'general'))); ?>" class="qs-admin-form-card qs-settings-card">
			<?php wp_nonce_field('qs_save_general_settings'); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="qs-business-name"><?php echo esc_html__('Business Name', 'quickslot'); ?></label></th>
						<td><input type="text" id="qs-business-name" name="qs_business_name" class="regular-text" value="<?php echo esc_attr($business_name); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="qs-business-email"><?php echo esc_html__('Business Email', 'quickslot'); ?></label></th>
						<td><input type="email" id="qs-business-email" name="qs_business_email" class="regular-text" value="<?php echo esc_attr($business_email); ?>"></td>
					</tr>
				</tbody>
			</table>

			<p class="description qs-settings-note"><?php echo esc_html__('For reliable delivery, consider using a WordPress SMTP plugin configured for your site.', 'quickslot'); ?></p>
			<p class="submit"><button type="submit" name="qs_save_general_settings" class="button button-primary"><?php echo esc_html__('Save Settings', 'quickslot'); ?></button></p>
		</form>
		<?php
	}

	/**
	 * Renders the Email Templates tab.
	 */
	private function render_email_tab(): void {
		?>
		<form method="post" action="<?php echo esc_url($this->get_settings_url(array('tab' => 'email'))); ?>" class="qs-admin-form-card qs-settings-card">
			<?php wp_nonce_field('qs_save_email_templates'); ?>

			<div class="qs-placeholder-chips">
				<strong><?php echo esc_html__('Placeholders', 'quickslot'); ?></strong>
				<?php foreach ($this->get_placeholder_tokens() as $token) : ?>
					<button type="button" class="button button-secondary qs-insert-placeholder" data-placeholder="<?php echo esc_attr($token); ?>"><?php echo esc_html($token); ?></button>
				<?php endforeach; ?>
			</div>

			<?php foreach ($this->get_template_types() as $type => $label) : ?>
				<?php $template = $this->templates->get_template($type); ?>
				<div class="qs-template-editor">
					<h2><?php echo esc_html($label); ?></h2>
					<p>
						<label for="qs-template-<?php echo esc_attr($type); ?>-subject"><strong><?php echo esc_html__('Subject', 'quickslot'); ?></strong></label>
						<input type="text" id="qs-template-<?php echo esc_attr($type); ?>-subject" name="templates[<?php echo esc_attr($type); ?>][subject]" class="large-text" value="<?php echo esc_attr($template['subject']); ?>">
					</p>
					<p>
						<label for="qs-template-<?php echo esc_attr($type); ?>-body"><strong><?php echo esc_html__('Body', 'quickslot'); ?></strong></label>
						<textarea id="qs-template-<?php echo esc_attr($type); ?>-body" name="templates[<?php echo esc_attr($type); ?>][body]" class="large-text code qs-template-body" rows="8"><?php echo esc_textarea($template['body']); ?></textarea>
					</p>
				</div>
			<?php endforeach; ?>

			<p class="submit"><button type="submit" name="qs_save_email_templates" class="button button-primary"><?php echo esc_html__('Save Templates', 'quickslot'); ?></button></p>
		</form>
		<?php
	}

	/**
	 * Renders the Google Calendar tab.
	 */
	private function render_calendar_tab(): void {
		$settings = $this->google_calendar->get_settings();
		$is_connected = $this->google_calendar->is_connected();
		$is_library_available = $this->google_calendar->is_library_available();
		?>
		<form method="post" action="<?php echo esc_url($this->get_settings_url(array('tab' => 'calendar'))); ?>" class="qs-admin-form-card qs-settings-card">
			<?php wp_nonce_field('qs_save_calendar_settings'); ?>

			<?php if (! $is_library_available) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html__('Google API client library not installed. Run composer install to enable Google Calendar integration.', 'quickslot'); ?></p></div>
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="qs-google-client-id"><?php echo esc_html__('Google Client ID', 'quickslot'); ?></label></th>
						<td><input type="text" id="qs-google-client-id" name="qs_google_client_id" class="regular-text" value="<?php echo esc_attr($settings['client_id']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="qs-google-client-secret"><?php echo esc_html__('Google Client Secret', 'quickslot'); ?></label></th>
						<td><input type="text" id="qs-google-client-secret" name="qs_google_client_secret" class="regular-text" value="<?php echo esc_attr($settings['client_secret']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="qs-google-calendar-id"><?php echo esc_html__('Google Calendar ID', 'quickslot'); ?></label></th>
						<td><input type="text" id="qs-google-calendar-id" name="qs_google_calendar_id" class="regular-text" value="<?php echo esc_attr($settings['calendar_id']); ?>"><p class="description"><?php echo esc_html__('Use primary to sync with the primary Google Calendar.', 'quickslot'); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('Connection Status', 'quickslot'); ?></th>
						<td><span class="qs-badge <?php echo $is_connected ? 'qs-badge--confirmed' : 'qs-badge--cancelled'; ?>"><?php echo esc_html($this->google_calendar->get_status_label()); ?></span></td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" name="qs_save_calendar_settings" class="button button-primary"><?php echo esc_html__('Save Calendar Settings', 'quickslot'); ?></button>
			</p>
		</form>

		<div class="qs-admin-form-card qs-settings-card">
			<h2><?php echo esc_html__('Google Calendar Connection', 'quickslot'); ?></h2>
			<?php if ($is_connected) : ?>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<?php wp_nonce_field('qs_google_disconnect'); ?>
					<input type="hidden" name="action" value="quickslot_google_disconnect">
					<p><button type="submit" class="button"><?php echo esc_html__('Disconnect', 'quickslot'); ?></button></p>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<?php wp_nonce_field('qs_google_oauth_start'); ?>
					<input type="hidden" name="action" value="quickslot_google_oauth_start">
					<p><button type="submit" class="button button-primary"<?php disabled(! $is_library_available || '' === $settings['client_id'] || '' === $settings['client_secret']); ?>><?php echo esc_html__('Connect Google Calendar', 'quickslot'); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the Reminders tab.
	 */
	private function render_reminders_tab(): void {
		$offsets = $this->reminders->get_offsets();
		?>
		<form method="post" action="<?php echo esc_url($this->get_settings_url(array('tab' => 'reminders'))); ?>" class="qs-admin-form-card qs-settings-card">
			<?php wp_nonce_field('qs_save_reminders'); ?>

			<div class="qs-reminder-offsets" data-qs-reminder-offsets>
				<?php foreach ($offsets as $offset) : ?>
					<div class="qs-reminder-offset-row">
						<input type="number" min="0.1" step="0.1" name="reminder_hours[]" value="<?php echo esc_attr((string) $this->seconds_to_hours($offset)); ?>">
						<span><?php echo esc_html__('hours before booking', 'quickslot'); ?></span>
						<button type="button" class="button-link-delete qs-remove-reminder-offset"><?php echo esc_html__('Remove', 'quickslot'); ?></button>
					</div>
				<?php endforeach; ?>
			</div>

			<p><button type="button" class="button" data-qs-add-reminder-offset><?php echo esc_html__('Add Reminder Offset', 'quickslot'); ?></button></p>
			<p class="submit"><button type="submit" name="qs_save_reminders" class="button button-primary"><?php echo esc_html__('Save Reminders', 'quickslot'); ?></button></p>
		</form>
		<?php
	}

	/**
	 * Saves general settings.
	 */
	private function handle_general_save(): void {
		check_admin_referer('qs_save_general_settings');

		$business_name      = isset($_POST['qs_business_name']) ? QS_Sanitizer::text(wp_unslash($_POST['qs_business_name'])) : '';
		$business_email_raw = isset($_POST['qs_business_email']) ? QS_Sanitizer::text(wp_unslash($_POST['qs_business_email'])) : '';
		$business_email     = QS_Sanitizer::email($business_email_raw);

		if ('' !== $business_email_raw && ! is_email($business_email_raw)) {
			wp_safe_redirect($this->get_settings_url(array('tab' => 'general', 'qs_notice' => 'invalid_email')));
			exit;
		}

		update_option('qs_business_name', $business_name);
		update_option('qs_business_email', $business_email);

		wp_safe_redirect($this->get_settings_url(array('tab' => 'general', 'qs_notice' => 'saved_general')));
		exit;
	}

	/**
	 * Saves email templates.
	 */
	private function handle_email_templates_save(): void {
		check_admin_referer('qs_save_email_templates');

		$submitted = isset($_POST['templates']) && is_array($_POST['templates']) ? wp_unslash($_POST['templates']) : array();

		foreach ($this->get_template_types() as $type => $label) {
			$template = isset($submitted[ $type ]) && is_array($submitted[ $type ]) ? $submitted[ $type ] : array();
			update_option(
				'qs_email_template_' . $type,
				array(
					'subject' => sanitize_text_field((string) ($template['subject'] ?? '')),
					'body'    => wp_kses_post((string) ($template['body'] ?? '')),
				)
			);
		}

		wp_safe_redirect($this->get_settings_url(array('tab' => 'email', 'qs_notice' => 'saved_templates')));
		exit;
	}

	/**
	 * Saves Google Calendar settings.
	 */
	private function handle_calendar_save(): void {
		check_admin_referer('qs_save_calendar_settings');

		$client_id = isset($_POST['qs_google_client_id']) ? QS_Sanitizer::text(wp_unslash($_POST['qs_google_client_id'])) : '';
		$client_secret = isset($_POST['qs_google_client_secret']) ? QS_Sanitizer::text(wp_unslash($_POST['qs_google_client_secret'])) : '';
		$calendar_id = isset($_POST['qs_google_calendar_id']) ? QS_Sanitizer::text(wp_unslash($_POST['qs_google_calendar_id'])) : 'primary';

		if ('' === $calendar_id) {
			$calendar_id = 'primary';
		}

		update_option('qs_google_client_id', $client_id);
		update_option('qs_google_client_secret', $client_secret);
		update_option('qs_google_calendar_id', $calendar_id);

		wp_safe_redirect($this->get_settings_url(array('tab' => 'calendar', 'qs_notice' => 'saved_calendar')));
		exit;
	}

	/**
	 * Saves reminder offsets.
	 */
	private function handle_reminders_save(): void {
		check_admin_referer('qs_save_reminders');

		$submitted = isset($_POST['reminder_hours']) && is_array($_POST['reminder_hours']) ? wp_unslash($_POST['reminder_hours']) : array();
		$offsets   = array();

		foreach ($submitted as $hours_raw) {
			$hours   = (float) QS_Sanitizer::text((string) $hours_raw);
			$seconds = (int) round($hours * 3600);

			if ($seconds <= 0) {
				continue;
			}

			$offsets[] = $seconds;
		}

		if (empty($offsets)) {
			wp_safe_redirect($this->get_settings_url(array('tab' => 'reminders', 'qs_notice' => 'invalid_offsets')));
			exit;
		}

		update_option('qs_reminder_offsets', array_values(array_unique($offsets)));

		wp_safe_redirect($this->get_settings_url(array('tab' => 'reminders', 'qs_notice' => 'saved_reminders')));
		exit;
	}

	/**
	 * Renders settings notices.
	 */
	private function render_notice(): void {
		if (! isset($_GET['qs_notice'])) {
			return;
		}

		$notice_key = QS_Sanitizer::text(wp_unslash($_GET['qs_notice']));
		$notices    = array(
			'saved_general'   => array('success', __('General settings saved.', 'quickslot')),
			'saved_calendar'  => array('success', __('Google Calendar settings saved.', 'quickslot')),
			'saved_templates' => array('success', __('Email templates saved.', 'quickslot')),
			'saved_reminders' => array('success', __('Reminder settings saved.', 'quickslot')),
			'google_connected' => array('success', __('Google Calendar connected.', 'quickslot')),
			'google_disconnected' => array('success', __('Google Calendar disconnected.', 'quickslot')),
			'google_connect_error' => array('error', __('Google Calendar could not be connected. Please verify your settings and try again.', 'quickslot')),
			'google_library_missing' => array('error', __('Google API client library is missing. Run composer install to enable this integration.', 'quickslot')),
			'invalid_email'   => array('error', __('Please enter a valid business email address.', 'quickslot')),
			'invalid_offsets' => array('error', __('Please enter at least one positive reminder offset.', 'quickslot')),
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
	 * Returns settings tabs.
	 *
	 * @return array<string, string>
	 */
	private function get_tabs(): array {
		return array(
			'general'   => __('General Settings', 'quickslot'),
			'email'     => __('Email Templates', 'quickslot'),
			'calendar'  => __('Calendar', 'quickslot'),
			'reminders' => __('Reminders', 'quickslot'),
		);
	}

	/**
	 * Returns supported template types.
	 *
	 * @return array<string, string>
	 */
	private function get_template_types(): array {
		return array(
			'confirmation' => __('Confirmation Template', 'quickslot'),
			'notification' => __('Notification Template', 'quickslot'),
			'reminder'     => __('Reminder Template', 'quickslot'),
			'cancellation' => __('Cancellation Template', 'quickslot'),
		);
	}

	/**
	 * Returns placeholder tokens.
	 *
	 * @return array<int, string>
	 */
	private function get_placeholder_tokens(): array {
		return array(
			'{customer_name}',
			'{customer_email}',
			'{customer_phone}',
			'{service_name}',
			'{service_duration}',
			'{service_price}',
			'{booking_date}',
			'{booking_time}',
			'{booking_id}',
			'{business_name}',
			'{business_email}',
			'{site_url}',
			'{cancellation_reason}',
		);
	}

	/**
	 * Returns active tab.
	 */
	private function get_active_tab(): string {
		$tab = isset($_GET['tab']) ? QS_Sanitizer::text(wp_unslash($_GET['tab'])) : 'general';

		return array_key_exists($tab, $this->get_tabs()) ? $tab : 'general';
	}

	/**
	 * Converts seconds to display hours.
	 */
	private function seconds_to_hours(int $seconds): string {
		$hours = $seconds / 3600;

		if ((float) (int) $hours === (float) $hours) {
			return (string) (int) $hours;
		}

		return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
	}

	/**
	 * Returns settings URL.
	 *
	 * @param array<string, string> $args Query args.
	 */
	private function get_settings_url(array $args = array()): string {
		return add_query_arg(array_merge(array('page' => 'quickslot-settings'), $args), admin_url('admin.php'));
	}
}
