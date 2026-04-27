<?php
/**
 * Auto-injection into WooCommerce pages.
 *
 * @package SmartRec\Display
 */

namespace SmartRec\Display;

use SmartRec\Core\Settings;
use SmartRec\Engines\RecommendationManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class WooCommerceHooks
 *
 * Hooks into WooCommerce template locations to auto-display recommendations.
 */
class WooCommerceHooks {

	/**
	 * Recommendation manager.
	 *
	 * @var RecommendationManager
	 */
	private $manager;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Renderer.
	 *
	 * @var Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param RecommendationManager $manager  Recommendation manager.
	 * @param Settings              $settings Settings.
	 */
	public function __construct( RecommendationManager $manager, Settings $settings ) {
		$this->manager  = $manager;
		$this->settings = $settings;
		$this->renderer = new Renderer( $manager, $settings );

		$this->register_hooks();
	}

	/**
	 * Register WooCommerce display hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Single product page — below product summary.
		if ( $this->settings->get( 'location_single_product_below', true ) ) {
			add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_single_product_below' ), 15 );
		}

		// Single product page — as a tab.
		if ( $this->settings->get( 'location_single_product_tabs', true ) ) {
			add_filter( 'woocommerce_product_tabs', array( $this, 'add_recommendations_tab' ) );
		}

		// Cart page — multiple hooks for compatibility with classic and block cart.
		if ( $this->settings->get( 'location_cart_page', true ) ) {
			add_action( 'woocommerce_after_cart_table', array( $this, 'render_cart_page' ) );
			add_action( 'woocommerce_after_cart', array( $this, 'render_cart_page_once' ) );
			add_action( 'wp_footer', array( $this, 'render_cart_page_block_fallback' ) );
		}

		// Replace WC cross-sells.
		if ( $this->settings->get( 'location_cart_page_cross_sells', false ) ) {
			remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
			add_action( 'woocommerce_cart_collaterals', array( $this, 'render_cart_cross_sells' ) );
		}

		// Checkout page — classic + block checkout.
		if ( $this->settings->get( 'location_checkout_page', false ) ) {
			add_action( 'woocommerce_after_checkout_form', array( $this, 'render_checkout_page' ) );
			add_action( 'wp_footer', array( $this, 'render_checkout_page_block_fallback' ) );
		}

		// Category/archive pages.
		if ( $this->settings->get( 'location_category_page', true ) ) {
			add_action( 'woocommerce_after_shop_loop', array( $this, 'render_category_page' ) );
		}

		// Empty cart.
		if ( $this->settings->get( 'location_empty_cart', true ) ) {
			add_action( 'woocommerce_cart_is_empty', array( $this, 'render_empty_cart' ) );
		}

		// Thank you page.
		if ( $this->settings->get( 'location_thank_you_page', true ) ) {
			add_action( 'woocommerce_thankyou', array( $this, 'render_thank_you_page' ) );
		}

		// My Account dashboard.
		if ( $this->settings->get( 'location_my_account', false ) ) {
			add_action( 'woocommerce_account_dashboard', array( $this, 'render_my_account' ) );
		}
	}

	/**
	 * Render recommendations below single product.
	 *
	 * @return void
	 */
	public function render_single_product_below() {
		global $product;
		if ( ! $product ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped in templates.
		echo $this->renderer->render( 'single_product_below', $product->get_id() );
	}

	/**
	 * Add recommendations tab to product page.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_recommendations_tab( $tabs ) {
		$tabs['smartrec_recommendations'] = array(
			'title'    => __( 'Recommended', 'smartrec' ),
			'priority' => 40,
			'callback' => array( $this, 'render_recommendations_tab_content' ),
		);
		return $tabs;
	}

	/**
	 * Render recommendations tab content.
	 *
	 * @return void
	 */
	public function render_recommendations_tab_content() {
		global $product;
		if ( ! $product ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->renderer->render( 'single_product_tabs', $product->get_id() );
	}

	/**
	 * Render recommendations on cart page.
	 *
	 * @return void
	 */
	/**
	 * Track whether cart recommendations have already been rendered.
	 *
	 * @var bool
	 */
	private $cart_rendered = false;

	/**
	 * Render cart page recommendations.
	 *
	 * @return void
	 */
	public function render_cart_page() {
		if ( $this->cart_rendered ) {
			return;
		}
		$this->cart_rendered = true;

		$product_id = $this->get_primary_cart_product_id();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->renderer->render( 'cart_page', $product_id );
	}

	/**
	 * Render cart page recommendations (deduped for woocommerce_after_cart).
	 *
	 * @return void
	 */
	public function render_cart_page_once() {
		$this->render_cart_page();
	}

	/**
	 * Fallback for block-based cart: render in wp_footer if on cart page.
	 *
	 * @return void
	 */
	public function render_cart_page_block_fallback() {
		if ( $this->cart_rendered ) {
			return;
		}

		// Check if we're on the cart page.
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}

		$this->render_cart_page();
	}

	/**
	 * Render cross-sell replacements.
	 *
	 * @return void
	 */
	public function render_cart_cross_sells() {
		$product_id = $this->get_primary_cart_product_id();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->renderer->render( 'cart_page_cross_sells', $product_id );
	}

	/**
	 * Render recommendations on checkout page.
	 *
	 * @return void
	 */
	/**
	 * @var bool
	 */
	private $checkout_rendered = false;

	/**
	 * Render recommendations on checkout page.
	 *
	 * @return void
	 */
	public function render_checkout_page() {
		if ( $this->checkout_rendered ) {
			return;
		}
		$this->checkout_rendered = true;

		$product_id = $this->get_primary_cart_product_id();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->renderer->render( 'checkout_page', $product_id );
	}

	/**
	 * Fallback for block-based checkout.
	 *
	 * @return void
	 */
	public function render_checkout_page_block_fallback() {
		if ( $this->checkout_rendered ) {
			return;
		}
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		$this->render_checkout_page();
	}

	/**
	 * Render recommendations on category pages.
	 *
	 * @return void
	 */
	public function render_category_page() {
		$category_id = 0;
		if ( is_product_category() ) {
			$term = get_queried_object();
			if ( $term ) {
				$category_id = $term->term_id;
			}
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->renderer->render(
			'category_page',
			0,
			array( 'category_id' => $category_id )
		);
	}

	/**
	 * Render recommendations on empty cart.
	 *
	 * @return void
	 */
	public function render_empty_cart() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->renderer->render( 'empty_cart', 0 );
	}

	/**
	 * Render recommendations on thank you page.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function render_thank_you_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$items = $order->get_items();
		$product_id = 0;
		foreach ( $items as $item ) {
			$product_id = $item->get_product_id();
			break;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->renderer->render( 'thank_you_page', $product_id );
	}

	/**
	 * Render recommendations on My Account dashboard.
	 *
	 * @return void
	 */
	public function render_my_account() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->renderer->render( 'my_account', 0 );
	}

	/**
	 * Get the primary product ID from the cart.
	 *
	 * @return int
	 */
	private function get_primary_cart_product_id(): int {
		if ( ! WC()->cart ) {
			return 0;
		}

		$items = WC()->cart->get_cart();
		if ( empty( $items ) ) {
			return 0;
		}

		// Return the most expensive item.
		$max_price  = 0;
		$product_id = 0;
		foreach ( $items as $item ) {
			$product = $item['data'] ?? null;
			if ( $product instanceof \WC_Product ) {
				$price = (float) $product->get_price() * $item['quantity'];
				if ( $price > $max_price ) {
					$max_price  = $price;
					$product_id = $item['product_id'];
				}
			}
		}

		return $product_id;
	}
}
