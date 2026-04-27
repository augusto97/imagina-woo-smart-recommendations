<?php
/**
 * Shortcode handler.
 *
 * @package SmartRec\Display
 */

namespace SmartRec\Display;

use SmartRec\Core\Settings;
use SmartRec\Engines\RecommendationManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Shortcode
 *
 * Registers and handles the [smartrec] shortcode.
 */
class Shortcode {

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

		add_shortcode( 'smartrec', array( $this, 'render_shortcode' ) );

		// Themed homepage shortcodes.
		add_shortcode( 'smartrec_recently_viewed', array( $this, 'render_recently_viewed' ) );
		add_shortcode( 'smartrec_for_you', array( $this, 'render_for_you' ) );
		add_shortcode( 'smartrec_trending', array( $this, 'render_trending' ) );
		add_shortcode( 'smartrec_similar_to_viewed', array( $this, 'render_similar_to_viewed' ) );
		add_shortcode( 'smartrec_bought_together', array( $this, 'render_bought_together' ) );
		add_shortcode( 'smartrec_new_arrivals', array( $this, 'render_new_arrivals' ) );
	}

	/**
	 * Render the [smartrec] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'engine'         => '',
				'limit'          => $this->settings->get( 'default_limit', 8 ),
				'layout'         => $this->settings->get( 'default_layout', 'grid' ),
				'title'          => __( 'Recommended for you', 'smartrec' ),
				'columns'        => 4,
				'product_id'     => '',
				'category'       => '',
				'exclude'        => '',
				'show_price'     => 'yes',
				'show_rating'    => 'yes',
				'show_add_to_cart' => 'yes',
				'show_reason'    => 'yes',
				'order'          => 'score',
				'load_more_text' => '',
				'css_class'      => '',
			),
			$atts,
			'smartrec'
		);

		// Auto-detect product ID.
		$product_id = (int) $atts['product_id'];
		if ( $product_id <= 0 && is_product() ) {
			global $product;
			if ( $product instanceof \WC_Product ) {
				$product_id = $product->get_id();
			}
		}

		// Build args.
		$args = array(
			'limit'          => (int) $atts['limit'],
			'layout'         => sanitize_text_field( $atts['layout'] ),
			'title'          => sanitize_text_field( $atts['title'] ),
			'columns'        => (int) $atts['columns'],
			'show_price'     => 'yes' === $atts['show_price'],
			'show_rating'    => 'yes' === $atts['show_rating'],
			'show_add_to_cart' => 'yes' === $atts['show_add_to_cart'],
			'show_reason'    => 'yes' === $atts['show_reason'],
			'order'          => in_array( $atts['order'], array( 'score', 'random' ), true ) ? $atts['order'] : 'score',
			'css_class'      => sanitize_html_class( $atts['css_class'] ),
		);

		if ( ! empty( $atts['engine'] ) ) {
			$args['engine'] = sanitize_text_field( $atts['engine'] );
		}

		if ( ! empty( $atts['exclude'] ) ) {
			$args['exclude'] = array_map( 'absint', explode( ',', $atts['exclude'] ) );
		}

		if ( ! empty( $atts['category'] ) ) {
			$args['category_id'] = (int) $atts['category'];
		}

		return $this->renderer->render( 'custom_shortcode', $product_id, $args );
	}

	/**
	 * [smartrec_recently_viewed] — Products the visitor recently viewed.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_recently_viewed( $atts ) {
		return $this->render_themed_shortcode( $atts, array(
			'engine' => 'recently_viewed',
			'title'  => __( 'Recently viewed', 'smartrec' ),
		) );
	}

	/**
	 * [smartrec_for_you] — Personalized recommendations based on user profile.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_for_you( $atts ) {
		return $this->render_themed_shortcode( $atts, array(
			'engine' => 'personalized_mix',
			'title'  => __( 'Recommended for you', 'smartrec' ),
		) );
	}

	/**
	 * [smartrec_trending] — Popular/trending products.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_trending( $atts ) {
		return $this->render_themed_shortcode( $atts, array(
			'engine' => 'trending',
			'title'  => __( 'Trending now', 'smartrec' ),
		) );
	}

	/**
	 * [smartrec_similar_to_viewed] — Products similar to what the user viewed.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_similar_to_viewed( $atts ) {
		return $this->render_themed_shortcode( $atts, array(
			'engine' => 'similar',
			'title'  => __( 'Related to what you viewed', 'smartrec' ),
		) );
	}

	/**
	 * [smartrec_bought_together] — Products others also bought.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_bought_together( $atts ) {
		return $this->render_themed_shortcode( $atts, array(
			'engine' => 'bought_together',
			'title'  => __( 'Customers also bought', 'smartrec' ),
		) );
	}

	/**
	 * [smartrec_new_arrivals] — Newest products (uses trending with recency bias).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_new_arrivals( $atts ) {
		return $this->render_themed_shortcode( $atts, array(
			'engine' => 'trending',
			'title'  => __( 'New arrivals', 'smartrec' ),
		) );
	}

	/**
	 * Render a themed shortcode with pre-set defaults.
	 *
	 * @param array $atts      User-provided attributes.
	 * @param array $defaults  Theme-specific defaults (engine, title).
	 * @return string
	 */
	private function render_themed_shortcode( $atts, array $defaults ): string {
		$atts = shortcode_atts(
			array(
				'limit'            => $this->settings->get( 'default_limit', 8 ),
				'layout'           => $this->settings->get( 'default_layout', 'grid' ),
				'title'            => $defaults['title'],
				'columns'          => 4,
				'columns_tablet'   => 2,
				'columns_mobile'   => 1,
				'product_id'       => '',
				'category'         => '',
				'exclude'          => '',
				'show_price'       => 'yes',
				'show_rating'      => 'yes',
				'show_add_to_cart' => 'yes',
				'show_reason'      => 'no',
				'load_more'        => 0,
				'load_more_text'   => '',
				'order'            => 'score',
				'css_class'        => '',
			),
			$atts,
			'smartrec_' . str_replace( array( 'personalized_mix', 'recently_viewed' ), array( 'for_you', 'recently_viewed' ), $defaults['engine'] )
		);

		$product_id = (int) $atts['product_id'];
		if ( $product_id <= 0 && is_product() ) {
			global $product;
			if ( $product instanceof \WC_Product ) {
				$product_id = $product->get_id();
			}
		}

		$args = array(
			'engine'           => $defaults['engine'],
			'limit'            => (int) $atts['limit'],
			'layout'           => sanitize_text_field( $atts['layout'] ),
			'title'            => sanitize_text_field( $atts['title'] ),
			'columns'          => (int) $atts['columns'],
			'columns_tablet'   => (int) $atts['columns_tablet'],
			'columns_mobile'   => (int) $atts['columns_mobile'],
			'show_price'       => 'yes' === $atts['show_price'],
			'show_rating'      => 'yes' === $atts['show_rating'],
			'show_add_to_cart' => 'yes' === $atts['show_add_to_cart'],
			'show_reason'      => 'yes' === $atts['show_reason'],
			'css_class'        => sanitize_html_class( $atts['css_class'] ),
			'order'            => in_array( $atts['order'], array( 'score', 'random' ), true ) ? $atts['order'] : 'score',
		);

		$load_more = (int) $atts['load_more'];
		if ( $load_more > 0 ) {
			$args['load_more']       = true;
			$args['load_more_count'] = $load_more;
			if ( ! empty( $atts['load_more_text'] ) ) {
				$args['load_more_text'] = sanitize_text_field( $atts['load_more_text'] );
			}
		}

		if ( ! empty( $atts['exclude'] ) ) {
			$args['exclude'] = array_map( 'absint', explode( ',', $atts['exclude'] ) );
		}

		if ( ! empty( $atts['category'] ) ) {
			$args['category_id'] = (int) $atts['category'];
		}

		return $this->renderer->render( 'custom_shortcode', $product_id, $args );
	}
}
