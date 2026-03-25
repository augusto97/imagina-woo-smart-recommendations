<?php
/**
 * Main tracking orchestrator.
 *
 * @package SmartRec\Tracking
 */

namespace SmartRec\Tracking;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Tracker
 *
 * Orchestrates all tracking functionality including frontend JS and server-side hooks.
 */
class Tracker {

	/**
	 * Session manager.
	 *
	 * @var SessionManager
	 */
	private $session_manager;

	/**
	 * Event store.
	 *
	 * @var EventStore
	 */
	private $event_store;

	/**
	 * Data collector.
	 *
	 * @var DataCollector
	 */
	private $data_collector;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings        = $settings;
		$this->session_manager = new SessionManager( $settings );
		$this->event_store     = new EventStore();
		$this->data_collector  = new DataCollector( $this->event_store, $this->session_manager, $settings );

		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker_script' ) );
		}
	}

	/**
	 * Enqueue the frontend tracking script.
	 *
	 * @return void
	 */
	public function enqueue_tracker_script() {
		if ( ! $this->session_manager->is_tracking_allowed() ) {
			return;
		}

		$tracking_method = $this->settings->get( 'tracking_method', 'both' );
		if ( 'server_only' === $tracking_method ) {
			return;
		}

		wp_enqueue_script(
			'smartrec-tracker',
			SMARTREC_PLUGIN_URL . 'assets/js/smartrec-tracker.js',
			array(),
			SMARTREC_VERSION,
			true
		);

		wp_localize_script(
			'smartrec-tracker',
			'smartrecTracker',
			array(
				'ajaxUrl'   => rest_url( 'smartrec/v1/events' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'sessionId' => $this->session_manager->get_session_id(),
				'productId' => $this->get_current_product_id(),
				'context'   => $this->get_current_context(),
				'debug'     => (bool) $this->settings->get( 'debug_mode', false ),
			)
		);
	}

	/**
	 * Get the current product ID if on a product page.
	 *
	 * @return int
	 */
	private function get_current_product_id() {
		if ( is_product() ) {
			global $product;
			if ( $product instanceof \WC_Product ) {
				return $product->get_parent_id() ?: $product->get_id();
			}
		}
		return 0;
	}

	/**
	 * Get the current page context.
	 *
	 * @return string
	 */
	private function get_current_context() {
		if ( is_product() ) {
			return 'product';
		}
		if ( is_product_category() || is_shop() ) {
			return 'category';
		}
		if ( is_cart() ) {
			return 'cart';
		}
		if ( is_checkout() ) {
			return 'checkout';
		}
		if ( is_search() ) {
			return 'search';
		}
		return 'other';
	}

	/**
	 * Get session manager.
	 *
	 * @return SessionManager
	 */
	public function get_session_manager() {
		return $this->session_manager;
	}

	/**
	 * Get event store.
	 *
	 * @return EventStore
	 */
	public function get_event_store() {
		return $this->event_store;
	}
}
