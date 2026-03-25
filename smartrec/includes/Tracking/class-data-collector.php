<?php
/**
 * WooCommerce hooks for server-side data collection.
 *
 * @package SmartRec\Tracking
 */

namespace SmartRec\Tracking;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class DataCollector
 *
 * Hooks into WooCommerce actions for server-side event tracking.
 */
class DataCollector {

	/**
	 * Event store instance.
	 *
	 * @var EventStore
	 */
	private $event_store;

	/**
	 * Session manager instance.
	 *
	 * @var SessionManager
	 */
	private $session_manager;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param EventStore     $event_store     Event store instance.
	 * @param SessionManager $session_manager Session manager instance.
	 * @param Settings       $settings        Settings instance.
	 */
	public function __construct( EventStore $event_store, SessionManager $session_manager, Settings $settings ) {
		$this->event_store     = $event_store;
		$this->session_manager = $session_manager;
		$this->settings        = $settings;

		$tracking_method = $this->settings->get( 'tracking_method', 'both' );
		if ( 'js_only' === $tracking_method ) {
			return;
		}

		$this->register_hooks();
	}

	/**
	 * Register WooCommerce hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Product view.
		add_action( 'woocommerce_after_single_product', array( $this, 'track_product_view' ) );

		// Add to cart.
		add_action( 'woocommerce_add_to_cart', array( $this, 'track_add_to_cart' ), 10, 6 );

		// Remove from cart.
		add_action( 'woocommerce_cart_item_removed', array( $this, 'track_remove_from_cart' ), 10, 2 );

		// Purchase completed.
		add_action( 'woocommerce_order_status_completed', array( $this, 'track_purchase' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'track_purchase' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'track_purchase' ) );

		// Block checkout support.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'track_block_checkout_purchase' ) );
	}

	/**
	 * Track product view.
	 *
	 * @return void
	 */
	public function track_product_view() {
		if ( ! $this->session_manager->is_tracking_allowed() ) {
			return;
		}

		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		// Always track parent product for variations.
		$product_id = $product->get_parent_id() ?: $product->get_id();

		do_action( 'smartrec_before_track_event', 'view', $product_id, $this->session_manager->get_session_id() );

		$event_id = $this->event_store->add_event(
			array(
				'session_id' => $this->session_manager->get_session_id(),
				'user_id'    => $this->session_manager->get_user_id(),
				'event_type' => 'view',
				'product_id' => $product_id,
				'context'    => 'product',
			)
		);

		do_action( 'smartrec_after_track_event', 'view', $product_id, $this->session_manager->get_session_id(), $event_id );
	}

	/**
	 * Track add to cart.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id    Product ID.
	 * @param int    $quantity      Quantity.
	 * @param int    $variation_id  Variation ID.
	 * @param array  $variation     Variation data.
	 * @param array  $cart_item_data Cart item data.
	 * @return void
	 */
	public function track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		if ( ! $this->session_manager->is_tracking_allowed() ) {
			return;
		}

		// Use parent product ID.
		$track_product_id = $product_id;

		do_action( 'smartrec_before_track_event', 'cart_add', $track_product_id, $this->session_manager->get_session_id() );

		$event_id = $this->event_store->add_event(
			array(
				'session_id' => $this->session_manager->get_session_id(),
				'user_id'    => $this->session_manager->get_user_id(),
				'event_type' => 'cart_add',
				'product_id' => $track_product_id,
				'quantity'   => $quantity,
				'context'    => 'cart',
			)
		);

		do_action( 'smartrec_after_track_event', 'cart_add', $track_product_id, $this->session_manager->get_session_id(), $event_id );
	}

	/**
	 * Track remove from cart.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param object $cart          Cart instance.
	 * @return void
	 */
	public function track_remove_from_cart( $cart_item_key, $cart ) {
		if ( ! $this->session_manager->is_tracking_allowed() ) {
			return;
		}

		$cart_item = $cart->removed_cart_contents[ $cart_item_key ] ?? null;
		if ( ! $cart_item ) {
			return;
		}

		$product_id = $cart_item['product_id'];

		do_action( 'smartrec_before_track_event', 'cart_remove', $product_id, $this->session_manager->get_session_id() );

		$event_id = $this->event_store->add_event(
			array(
				'session_id' => $this->session_manager->get_session_id(),
				'user_id'    => $this->session_manager->get_user_id(),
				'event_type' => 'cart_remove',
				'product_id' => $product_id,
				'quantity'   => $cart_item['quantity'],
				'context'    => 'cart',
			)
		);

		do_action( 'smartrec_after_track_event', 'cart_remove', $product_id, $this->session_manager->get_session_id(), $event_id );
	}

	/**
	 * Track purchase from order.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function track_purchase( $order_id ) {
		// Prevent double tracking.
		$tracked = get_post_meta( $order_id, '_smartrec_tracked', true );
		if ( $tracked ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$session_id = $this->session_manager->get_session_id();
		$user_id    = $order->get_user_id();

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();

			do_action( 'smartrec_before_track_event', 'purchase', $product_id, $session_id );

			$event_id = $this->event_store->record_event(
				array(
					'session_id' => $session_id,
					'user_id'    => $user_id,
					'event_type' => 'purchase',
					'product_id' => $product_id,
					'quantity'   => $item->get_quantity(),
					'context'    => 'order',
				)
			);

			do_action( 'smartrec_after_track_event', 'purchase', $product_id, $session_id, $event_id );
		}

		update_post_meta( $order_id, '_smartrec_tracked', 1 );
	}

	/**
	 * Track purchase from block checkout.
	 *
	 * @param \WC_Order $order Order object.
	 * @return void
	 */
	public function track_block_checkout_purchase( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$this->track_purchase( $order->get_id() );
	}
}
