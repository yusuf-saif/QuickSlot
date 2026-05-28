<?php
/**
 * Public services REST endpoint.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Services_Endpoint {
	/**
	 * Registers routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'quickslot/v1',
			'/services',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_services'),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Returns active services.
	 */
	public function get_services(): WP_REST_Response {
		$cached = get_transient('qs_services_list');

		if (is_array($cached)) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => $cached,
				),
				200
			);
		}

		global $wpdb;

		$table = $wpdb->prefix . 'qs_services';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, description, duration, price, is_paid FROM {$table} WHERE status = %s ORDER BY id ASC",
				'active'
			),
			ARRAY_A
		);

		$data = array();

		if (is_array($rows)) {
			foreach ($rows as $row) {
				$data[] = array(
					'id'          => (int) $row['id'],
					'name'        => (string) $row['name'],
					'description' => (string) $row['description'],
					'duration'    => (int) $row['duration'],
					'price'       => null === $row['price'] ? null : (float) $row['price'],
					'is_paid'     => (bool) $row['is_paid'],
				);
			}
		}

		set_transient('qs_services_list', $data, 300);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}
}
