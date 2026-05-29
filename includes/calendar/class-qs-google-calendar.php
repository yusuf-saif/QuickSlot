<?php
/**
 * Google Calendar OAuth and event sync.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Google_Calendar {
	/**
	 * OAuth scope.
	 */
	private const SCOPE = 'https://www.googleapis.com/auth/calendar.events';

	/**
	 * Singleton instance.
	 *
	 * @var QS_Google_Calendar|null
	 */
	private static ?QS_Google_Calendar $instance = null;

	/**
	 * Returns the singleton instance.
	 */
	public static function instance(): QS_Google_Calendar {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->maybe_load_vendor();

		add_action('admin_post_quickslot_google_oauth_start', array($this, 'handle_oauth_start'));
		add_action('admin_post_quickslot_google_oauth_callback', array($this, 'handle_oauth_callback'));
		add_action('admin_post_quickslot_google_disconnect', array($this, 'handle_disconnect'));
		add_action('admin_post_quickslot_google_test_connection', array($this, 'handle_test_connection'));
		add_action('quickslot_booking_confirmed', array($this, 'handle_booking_confirmed'));
		add_action('quickslot_booking_cancelled', array($this, 'handle_booking_cancelled'), 20, 2);
	}

	/**
	 * Returns whether the Google client library is available.
	 */
	public function is_library_available(): bool {
		return class_exists('Google\\Client') && class_exists('Google\\Service\\Calendar');
	}

	/**
	 * Returns whether Google Calendar is connected.
	 */
	public function is_connected(): bool {
		$connection = $this->get_connection();

		return is_array($connection) && ! empty($connection['is_connected']);
	}

	/**
	 * Returns current calendar settings for the admin UI.
	 *
	 * @return array<string, string>
	 */
	public function get_settings(): array {
		$calendar_id = (string) get_option('qs_google_calendar_id', 'primary');

		if ('' === $calendar_id) {
			$calendar_id = 'primary';
		}

		return array(
			'client_id'     => (string) get_option('qs_google_client_id', ''),
			'client_secret' => (string) get_option('qs_google_client_secret', ''),
			'calendar_id'   => $calendar_id,
		);
	}

	/**
	 * Returns a connection status label.
	 */
	public function get_status_label(): string {
		return $this->is_connected() ? __('Connected', 'quickslot') : __('Not connected', 'quickslot');
	}

	/**
	 * Returns connection details for admin display.
	 *
	 * @return array<string, string>
	 */
	public function get_connection_details(): array {
		$connection  = $this->get_connection();
		$settings    = $this->get_settings();
		$calendar_id = is_array($connection) && ! empty($connection['calendar_id'])
			? (string) $connection['calendar_id']
			: $settings['calendar_id'];

		$connected_on = '';

		if (is_array($connection) && ! empty($connection['connected_at'])) {
			try {
				$connected_on = (new DateTimeImmutable((string) $connection['connected_at'], wp_timezone()))->format('Y-m-d H:i');
			} catch (Exception $exception) {
				$connected_on = '';
			}
		}

		return array(
			'account_email' => '',
			'calendar_name' => $calendar_id,
			'connected_on'  => $connected_on,
		);
	}

	/**
	 * Returns the OAuth redirect URI for setup.
	 */
	public function get_oauth_redirect_uri(): string {
		return $this->get_redirect_uri();
	}

	/**
	 * Builds an OAuth authorization URL.
	 *
	 * @return string|WP_Error
	 */
	public function get_oauth_url() {
		$client = $this->build_client();

		if ($client instanceof WP_Error) {
			return $client;
		}

		$state = wp_create_nonce('qs_google_oauth_state_' . get_current_user_id());

		$client->setClientId($this->get_settings()['client_id']);
		$client->setClientSecret($this->get_settings()['client_secret']);
		$client->setRedirectUri($this->get_redirect_uri());
		$client->setAccessType('offline');
		$client->setPrompt('consent');
		$client->setScopes(array(self::SCOPE));
		$client->setState($state);

		return (string) $client->createAuthUrl();
	}

	/**
	 * Returns an authenticated Google client.
	 *
	 * @return Google\Client|WP_Error
	 */
	public function get_authenticated_client() {
		$client = $this->build_client();

		if ($client instanceof WP_Error) {
			return $client;
		}

		$settings = $this->get_settings();
		$client->setClientId($settings['client_id']);
		$client->setClientSecret($settings['client_secret']);
		$client->setRedirectUri($this->get_redirect_uri());
		$client->setAccessType('offline');
		$client->setScopes(array(self::SCOPE));

		$connection = $this->get_connection();

		if (! is_array($connection) || empty($connection['is_connected'])) {
			return new WP_Error('google_not_connected', __('Google Calendar is not connected.', 'quickslot'));
		}

		$access_token = $this->decrypt_token((string) $connection['access_token']);
		$refresh_token = $this->decrypt_token((string) $connection['refresh_token']);

		if ($access_token instanceof WP_Error || $refresh_token instanceof WP_Error) {
			return new WP_Error('google_token_error', __('Google Calendar token data is invalid.', 'quickslot'));
		}

		$expires_timestamp = ! empty($connection['token_expires_at']) ? strtotime((string) $connection['token_expires_at'] . ' UTC') : false;

		if (false !== $expires_timestamp && $expires_timestamp <= time()) {
			if ('' === $refresh_token) {
				return new WP_Error('google_token_expired', __('Google Calendar authorization has expired.', 'quickslot'));
			}

			try {
				$client->refreshToken($refresh_token);
				$token_data = $client->getAccessToken();
			} catch (Throwable $throwable) {
				$this->log_error('refresh token', $throwable->getMessage());
				return new WP_Error('google_refresh_failed', __('Google Calendar authorization could not be refreshed.', 'quickslot'));
			}

			if (! is_array($token_data) || empty($token_data['access_token'])) {
				return new WP_Error('google_refresh_failed', __('Google Calendar authorization could not be refreshed.', 'quickslot'));
			}

			$access_token  = (string) $token_data['access_token'];
			$refresh_token = ! empty($token_data['refresh_token']) ? (string) $token_data['refresh_token'] : $refresh_token;
			$this->save_connection_tokens($settings['calendar_id'], $access_token, $refresh_token, isset($token_data['expires_in']) ? (int) $token_data['expires_in'] : 3600);
		}

		$client->setAccessToken(
			array(
				'access_token'  => $access_token,
				'refresh_token' => $refresh_token,
			)
		);

		return $client;
	}

	/**
	 * Handles OAuth start.
	 */
	public function handle_oauth_start(): void {
		$this->assert_manage_options();
		check_admin_referer('qs_google_oauth_start');

		$url = $this->get_oauth_url();

		if ($url instanceof WP_Error) {
			wp_safe_redirect($this->get_settings_url(array('tab' => 'calendar', 'qs_notice' => 'google_connect_error')));
			exit;
		}

		wp_safe_redirect($url);
		exit;
	}

	/**
	 * Handles OAuth callback.
	 */
	public function handle_oauth_callback(): void {
		$this->assert_manage_options();

		$state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
		$code  = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';

		if ('' === $state || ! wp_verify_nonce($state, 'qs_google_oauth_state_' . get_current_user_id()) || '' === $code) {
			wp_safe_redirect($this->get_settings_url(array('tab' => 'calendar', 'qs_notice' => 'google_connect_error')));
			exit;
		}

		$client = $this->build_client();

		if ($client instanceof WP_Error) {
			wp_safe_redirect($this->get_settings_url(array('tab' => 'calendar', 'qs_notice' => 'google_library_missing')));
			exit;
		}

		$settings = $this->get_settings();
		$client->setClientId($settings['client_id']);
		$client->setClientSecret($settings['client_secret']);
		$client->setRedirectUri($this->get_redirect_uri());

		try {
			$token_data = $client->fetchAccessTokenWithAuthCode($code);
		} catch (Throwable $throwable) {
			$this->log_error('oauth callback', $throwable->getMessage());
			wp_safe_redirect($this->get_settings_url(array('tab' => 'calendar', 'qs_notice' => 'google_connect_error')));
			exit;
		}

		if (! is_array($token_data) || empty($token_data['access_token']) || ! empty($token_data['error'])) {
			wp_safe_redirect($this->get_settings_url(array('tab' => 'calendar', 'qs_notice' => 'google_connect_error')));
			exit;
		}

		$access_token  = (string) $token_data['access_token'];
		$refresh_token = ! empty($token_data['refresh_token']) ? (string) $token_data['refresh_token'] : '';

		if (! $this->save_connection_tokens($settings['calendar_id'], $access_token, $refresh_token, isset($token_data['expires_in']) ? (int) $token_data['expires_in'] : 3600)) {
			wp_safe_redirect($this->get_settings_url(array('tab' => 'calendar', 'qs_notice' => 'google_connect_error')));
			exit;
		}

		wp_safe_redirect($this->get_settings_url(array('tab' => 'calendar', 'qs_notice' => 'google_connected')));
		exit;
	}

	/**
	 * Handles disconnect.
	 */
	public function handle_disconnect(): void {
		$this->assert_manage_options();
		check_admin_referer('qs_google_disconnect');

		$this->clear_connection();

		wp_safe_redirect($this->get_settings_url(array('tab' => 'calendar', 'qs_notice' => 'google_disconnected')));
		exit;
	}

	/**
	 * Handles connection test requests.
	 */
	public function handle_test_connection(): void {
		$this->assert_manage_options();
		check_admin_referer('qs_test_google_connection');

		if (! $this->is_connected()) {
			wp_safe_redirect($this->get_settings_url(array('tab' => 'calendar', 'qs_notice' => 'google_test_failure')));
			exit;
		}

		$result = $this->test_connection();

		wp_safe_redirect(
			$this->get_settings_url(
				array(
					'tab'       => 'calendar',
					'qs_notice' => $result ? 'google_test_success' : 'google_test_failure',
				)
			)
		);
		exit;
	}

	/**
	 * Syncs a confirmed booking to Google Calendar.
	 */
	public function handle_booking_confirmed(int $booking_id): void {
		$client = $this->get_authenticated_client();

		if ($client instanceof WP_Error) {
			return;
		}

		$booking = $this->get_booking_sync_data($booking_id);

		if (null === $booking || ! empty($booking['calendar_event_id'])) {
			return;
		}

		$calendar_id = $this->get_settings()['calendar_id'];

		try {
			$service = new Google\Service\Calendar($client);
			$event   = new Google\Service\Calendar\Event(
				array(
					'summary'     => sprintf('%1$s — %2$s', (string) $booking['service_name'], (string) $booking['customer_name']),
					'description' => sprintf(
						/* translators: 1: booking id, 2: customer email, 3: customer phone, 4: customer note */
						__("Booking ID: %1$d\nEmail: %2$s\nPhone: %3$s\nNote: %4$s", 'quickslot'),
						(int) $booking['id'],
						(string) $booking['customer_email'],
						(string) $booking['customer_phone'],
						(string) $booking['customer_note']
					),
					'start'       => array(
						'dateTime' => gmdate('c', strtotime((string) $booking['booking_start'] . ' UTC')),
						'timeZone' => 'UTC',
					),
					'end'         => array(
						'dateTime' => gmdate('c', strtotime((string) $booking['booking_end'] . ' UTC')),
						'timeZone' => 'UTC',
					),
				)
			);

			$created = $service->events->insert($calendar_id, $event);
		} catch (Throwable $throwable) {
			$this->log_error('create Google event', $throwable->getMessage());
			return;
		}

		$event_id = method_exists($created, 'getId') ? (string) $created->getId() : '';

		if ('' === $event_id) {
			return;
		}

		$this->update_booking_calendar_data($booking_id, 'google', $event_id);
	}

	/**
	 * Deletes a Google event for a cancelled booking.
	 */
	public function handle_booking_cancelled(int $booking_id): void {
		$booking = $this->get_booking_sync_data($booking_id);

		if (null === $booking || 'google' !== (string) $booking['calendar_provider'] || '' === (string) $booking['calendar_event_id']) {
			return;
		}

		$client = $this->get_authenticated_client();

		if ($client instanceof WP_Error) {
			return;
		}

		try {
			$service = new Google\Service\Calendar($client);
			$service->events->delete($this->get_settings()['calendar_id'], (string) $booking['calendar_event_id']);
		} catch (Throwable $throwable) {
			$this->log_error('delete Google event', $throwable->getMessage());
		}
	}

	/**
	 * Returns the provider connection row.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_connection(): ?array {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb)) {
			return null;
		}

		$table = $wpdb->prefix . 'qs_calendar_connections';
		$query = $wpdb->prepare("SELECT * FROM {$table} WHERE provider = %s ORDER BY id DESC LIMIT 1", 'google');
		$row   = $wpdb->get_row($query, ARRAY_A);

		return is_array($row) ? $row : null;
	}

	/**
	 * Saves encrypted token data.
	 */
	private function save_connection_tokens(string $calendar_id, string $access_token, string $refresh_token, int $expires_in): bool {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb)) {
			return false;
		}

		$table      = $wpdb->prefix . 'qs_calendar_connections';
		$connection = $this->get_connection();

		if (is_array($connection) && '' === $refresh_token) {
			$existing_refresh = $this->decrypt_token((string) ($connection['refresh_token'] ?? ''));
			$refresh_token    = is_string($existing_refresh) ? $existing_refresh : '';
		}

		$encrypted_access  = $this->encrypt_token($access_token);
		$encrypted_refresh = $this->encrypt_token($refresh_token);

		if ($encrypted_access instanceof WP_Error || $encrypted_refresh instanceof WP_Error) {
			return false;
		}

		$expires_at  = gmdate('Y-m-d H:i:s', time() + max(1, $expires_in));
		$data        = array(
			'provider'         => 'google',
			'calendar_id'      => '' !== $calendar_id ? $calendar_id : 'primary',
			'access_token'     => $encrypted_access,
			'refresh_token'    => $encrypted_refresh,
			'token_expires_at' => $expires_at,
			'is_connected'     => 1,
			'connected_at'     => empty($connection['connected_at']) || empty($connection['is_connected']) ? current_time('mysql') : (string) $connection['connected_at'],
			'updated_at'       => current_time('mysql'),
		);

		if (is_array($connection) && ! empty($connection['id'])) {
			$result = $wpdb->update(
				$table,
				$data,
				array('id' => (int) $connection['id']),
				array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'),
				array('%d')
			);

			return false !== $result;
		}

		$result = $wpdb->insert(
			$table,
			$data,
			array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
		);

		return false !== $result;
	}

	/**
	 * Clears the stored connection.
	 */
	private function clear_connection(): void {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb)) {
			return;
		}

		$table      = $wpdb->prefix . 'qs_calendar_connections';
		$connection = $this->get_connection();

		if (! is_array($connection) || empty($connection['id'])) {
			return;
		}

		$wpdb->update(
			$table,
			array(
				'access_token'     => null,
				'refresh_token'    => null,
				'token_expires_at' => null,
				'is_connected'     => 0,
				'updated_at'       => current_time('mysql'),
			),
			array('id' => (int) $connection['id']),
			array('%s', '%s', '%s', '%d', '%s'),
			array('%d')
		);
	}

	/**
	 * Encrypts a token string.
	 *
	 * @return string|WP_Error
	 */
	private function encrypt_token(string $token) {
		if ('' === $token) {
			return '';
		}

		if (! function_exists('openssl_encrypt')) {
			return new WP_Error('openssl_unavailable', __('OpenSSL is required for token encryption.', 'quickslot'));
		}

		$key       = hash('sha256', $this->get_secret_material(), true);
		$iv_length = openssl_cipher_iv_length('aes-256-cbc');

		try {
			$iv = random_bytes($iv_length);
		} catch (Throwable $throwable) {
			return new WP_Error('token_encrypt_failed', __('Token encryption failed.', 'quickslot'));
		}

		$cipher    = openssl_encrypt($token, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

		if (false === $cipher) {
			return new WP_Error('token_encrypt_failed', __('Token encryption failed.', 'quickslot'));
		}

		return base64_encode($iv) . ':' . base64_encode($cipher);
	}

	/**
	 * Decrypts a token string.
	 *
	 * @return string|WP_Error
	 */
	private function decrypt_token(string $payload) {
		if ('' === $payload) {
			return '';
		}

		if (! function_exists('openssl_decrypt')) {
			return new WP_Error('openssl_unavailable', __('OpenSSL is required for token decryption.', 'quickslot'));
		}

		$parts = explode(':', $payload, 2);

		if (2 !== count($parts)) {
			return new WP_Error('token_decrypt_failed', __('Token decryption failed.', 'quickslot'));
		}

		$iv     = base64_decode($parts[0], true);
		$cipher = base64_decode($parts[1], true);

		if (false === $iv || false === $cipher) {
			return new WP_Error('token_decrypt_failed', __('Token decryption failed.', 'quickslot'));
		}

		$key   = hash('sha256', $this->get_secret_material(), true);
		$plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

		return false === $plain ? new WP_Error('token_decrypt_failed', __('Token decryption failed.', 'quickslot')) : $plain;
	}

	/**
	 * Returns booking data for sync operations.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_booking_sync_data(int $booking_id): ?array {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb) || $booking_id < 1) {
			return null;
		}

		$bookings = $wpdb->prefix . 'qs_bookings';
		$services = $wpdb->prefix . 'qs_services';
		$query    = $wpdb->prepare(
			"SELECT b.id, b.customer_name, b.customer_email, b.customer_phone, b.customer_note, b.booking_start, b.booking_end, b.calendar_provider, b.calendar_event_id, s.name AS service_name FROM {$bookings} b LEFT JOIN {$services} s ON s.id = b.service_id WHERE b.id = %d LIMIT 1",
			$booking_id
		);
		$row      = $wpdb->get_row($query, ARRAY_A);

		return is_array($row) ? $row : null;
	}

	/**
	 * Updates booking calendar sync fields.
	 */
	private function update_booking_calendar_data(int $booking_id, string $provider, string $event_id): void {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb) || $booking_id < 1) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'qs_bookings',
			array(
				'calendar_provider' => $provider,
				'calendar_event_id' => $event_id,
				'updated_at'        => current_time('mysql'),
			),
			array('id' => $booking_id),
			array('%s', '%s', '%s'),
			array('%d')
		);
	}

	/**
	 * Builds a Google client or returns a safe error.
	 *
	 * @return Google\Client|WP_Error
	 */
	private function build_client() {
		if (! $this->is_library_available()) {
			return new WP_Error('google_library_missing', __('The Google API client library is not installed.', 'quickslot'));
		}

		$settings = $this->get_settings();

		if ('' === $settings['client_id'] || '' === $settings['client_secret']) {
			return new WP_Error('google_settings_missing', __('Google Calendar client settings are incomplete.', 'quickslot'));
		}

		return new Google\Client();
	}

	/**
	 * Tests whether the configured connection can access the calendar.
	 */
	private function test_connection(): bool {
		$client = $this->get_authenticated_client();

		if ($client instanceof WP_Error) {
			$this->log_safe_error('Google Calendar test connection failed.');
			return false;
		}

		try {
			$service     = new Google\Service\Calendar($client);
			$calendar_id = $this->get_settings()['calendar_id'];
			$service->calendars->get($calendar_id);
		} catch (Throwable $throwable) {
			$this->log_safe_error('Google Calendar test connection failed.');
			return false;
		}

		return true;
	}

	/**
	 * Loads Composer autoload if available.
	 */
	private function maybe_load_vendor(): void {
		$autoload = QUICKSLOT_PATH . 'vendor/autoload.php';

		if (file_exists($autoload)) {
			require_once $autoload;
		}
	}

	/**
	 * Returns redirect URI.
	 */
	private function get_redirect_uri(): string {
		return admin_url('admin-post.php?action=quickslot_google_oauth_callback');
	}

	/**
	 * Returns settings page URL.
	 *
	 * @param array<string, string> $args Query args.
	 */
	private function get_settings_url(array $args = array()): string {
		return add_query_arg(array_merge(array('page' => 'quickslot-settings'), $args), admin_url('admin.php'));
	}

	/**
	 * Returns encryption secret material.
	 */
	private function get_secret_material(): string {
		if (defined('LOGGED_IN_KEY') && defined('LOGGED_IN_SALT')) {
			return LOGGED_IN_KEY . LOGGED_IN_SALT;
		}

		if (defined('AUTH_KEY') && defined('AUTH_SALT')) {
			return AUTH_KEY . AUTH_SALT;
		}

		return wp_salt('auth');
	}

	/**
	 * Ensures the current user can manage options.
	 */
	private function assert_manage_options(): void {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'quickslot'));
		}
	}

	/**
	 * Logs a safe Google Calendar error.
	 */
	private function log_error(string $context, string $message): void {
		error_log('[QuickSlot] Google Calendar ' . $context . ' failed: ' . $message);
	}

	/**
	 * Logs a safe generic Google Calendar error.
	 */
	private function log_safe_error(string $message): void {
		error_log('[QuickSlot] ' . $message);
	}
}
