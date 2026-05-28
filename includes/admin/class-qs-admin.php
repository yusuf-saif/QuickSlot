<?php
/**
 * Admin functionality.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Admin {
	/**
	 * Services admin controller.
	 *
	 * @var QS_Services_Admin|null
	 */
	private ?QS_Services_Admin $services_admin = null;

	/**
	 * Availability admin controller.
	 *
	 * @var QS_Availability_Admin|null
	 */
	private ?QS_Availability_Admin $availability_admin = null;

	/**
	 * Registers admin hooks.
	 */
	public function init(): void {
		require_once QUICKSLOT_PATH . 'includes/admin/class-qs-services-admin.php';
		require_once QUICKSLOT_PATH . 'includes/admin/class-qs-availability-admin.php';

		$this->services_admin = new QS_Services_Admin();
		$this->availability_admin = new QS_Availability_Admin();

		add_action('admin_menu', array($this, 'register_menu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_filter('plugin_action_links_' . QUICKSLOT_BASENAME, array($this, 'add_action_links'));
	}

	/**
	 * Registers QuickSlot admin menu pages.
	 */
	public function register_menu(): void {
		add_menu_page(
			esc_html__('QuickSlot', 'quickslot'),
			esc_html__('QuickSlot', 'quickslot'),
			'manage_options',
			'quickslot',
			array($this, 'render_dashboard_page'),
			'dashicons-calendar-alt',
			30
		);

		add_submenu_page(
			'quickslot',
			esc_html__('Dashboard', 'quickslot'),
			esc_html__('Dashboard', 'quickslot'),
			'manage_options',
			'quickslot',
			array($this, 'render_dashboard_page')
		);

		add_submenu_page(
			'quickslot',
			esc_html__('Services', 'quickslot'),
			esc_html__('Services', 'quickslot'),
			'manage_options',
			'quickslot-services',
			array($this, 'render_services_page')
		);

		add_submenu_page(
			'quickslot',
			esc_html__('Availability', 'quickslot'),
			esc_html__('Availability', 'quickslot'),
			'manage_options',
			'quickslot-availability',
			array($this, 'render_availability_page')
		);

		add_submenu_page(
			'quickslot',
			esc_html__('Bookings', 'quickslot'),
			esc_html__('Bookings', 'quickslot'),
			'manage_options',
			'quickslot-bookings',
			array($this, 'render_bookings_page')
		);

		add_submenu_page(
			'quickslot',
			esc_html__('Settings', 'quickslot'),
			esc_html__('Settings', 'quickslot'),
			'manage_options',
			'quickslot-settings',
			array($this, 'render_settings_page')
		);
	}

	/**
	 * Enqueues admin assets for QuickSlot pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets(string $hook_suffix): void {
		$quickslot_hooks = array(
			'toplevel_page_quickslot',
			'quickslot_page_quickslot-services',
			'quickslot_page_quickslot-availability',
			'quickslot_page_quickslot-bookings',
			'quickslot_page_quickslot-settings',
		);

		if (! in_array($hook_suffix, $quickslot_hooks, true)) {
			return;
		}

		wp_enqueue_style(
			'quickslot-admin',
			QUICKSLOT_URL . 'assets/css/quickslot-admin.css',
			array(),
			QUICKSLOT_VERSION
		);

		if ('quickslot_page_quickslot-availability' !== $hook_suffix) {
			return;
		}

		wp_enqueue_script(
			'quickslot-admin',
			QUICKSLOT_URL . 'assets/js/quickslot-admin.js',
			array('jquery'),
			QUICKSLOT_VERSION,
			true
		);

		wp_localize_script(
			'quickslot-admin',
			'quickslotAdmin',
			array(
				'removeBreakLabel' => esc_html__('Remove', 'quickslot'),
			)
		);
	}

	/**
	 * Adds the settings action link on the plugins screen.
	 *
	 * @param array<int, string> $links Existing action links.
	 * @return array<int, string>
	 */
	public function add_action_links(array $links): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url(admin_url('admin.php?page=quickslot-settings')),
			esc_html__('Settings', 'quickslot')
		);

		array_unshift($links, $settings_link);

		return $links;
	}

	/**
	 * Renders the dashboard placeholder page.
	 */
	public function render_dashboard_page(): void {
		$this->render_placeholder_page(esc_html__('Dashboard', 'quickslot'));
	}

	/**
	 * Renders the services placeholder page.
	 */
	public function render_services_page(): void {
		if ($this->services_admin instanceof QS_Services_Admin) {
			$this->services_admin->render_page();
		}
	}

	/**
	 * Renders the availability placeholder page.
	 */
	public function render_availability_page(): void {
		if ($this->availability_admin instanceof QS_Availability_Admin) {
			$this->availability_admin->render_page();
		}
	}

	/**
	 * Renders the bookings placeholder page.
	 */
	public function render_bookings_page(): void {
		$this->render_placeholder_page(esc_html__('Bookings', 'quickslot'));
	}

	/**
	 * Renders the settings placeholder page.
	 */
	public function render_settings_page(): void {
		$this->render_placeholder_page(esc_html__('Settings', 'quickslot'));
	}

	/**
	 * Renders a placeholder page.
	 *
	 * @param string $title Page title.
	 */
	private function render_placeholder_page(string $title): void {
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'quickslot'));
		}

		?>
		<div class="wrap qs-admin-page">
			<h1><?php echo esc_html($title); ?></h1>
			<p><?php echo esc_html__('This section will be available in the next build phase.', 'quickslot'); ?></p>
		</div>
		<?php
	}
}
