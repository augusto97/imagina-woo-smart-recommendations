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
}
