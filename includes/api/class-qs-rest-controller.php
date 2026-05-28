<?php
/**
 * REST API bootstrap.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_REST_Controller {
	/**
	 * Services endpoint.
	 *
	 * @var QS_Services_Endpoint
	 */
	private QS_Services_Endpoint $services_endpoint;

	/**
	 * Availability endpoint.
	 *
	 * @var QS_Availability_Endpoint
	 */
	private QS_Availability_Endpoint $availability_endpoint;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->services_endpoint     = new QS_Services_Endpoint();
		$this->availability_endpoint = new QS_Availability_Endpoint();
	}

	/**
	 * Registers REST bootstrap hooks.
	 */
	public function init(): void {
		add_action('rest_api_init', array($this, 'register_routes'));
	}

	/**
	 * Registers all QuickSlot REST routes.
	 */
	public function register_routes(): void {
		$this->services_endpoint->register_routes();
		$this->availability_endpoint->register_routes();
	}
}
