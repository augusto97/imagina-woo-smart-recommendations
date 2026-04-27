<?php
/**
 * Gutenberg block registration.
 *
 * @package SmartRec\Display
 */

namespace SmartRec\Display;

use SmartRec\Core\Settings;
use SmartRec\Engines\RecommendationManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class GutenbergBlock
 *
 * Registers the SmartRec Gutenberg block with server-side rendering.
 */
class GutenbergBlock {

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
	 * Constructor.
	 *
	 * @param RecommendationManager $manager  Recommendation manager.
	 * @param Settings              $settings Settings.
	 */
	public function __construct( RecommendationManager $manager, Settings $settings ) {
		$this->manager  = $manager;
		$this->settings = $settings;

		// Register immediately — this constructor runs during 'init' hook
		// so adding another 'init' action would be too late.
		$this->register_block();
	}

	/**
	 * Register the Gutenberg block.
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$deps = array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch' );
		// wp-server-side-render was added in WP 5.3.
		if ( wp_script_is( 'wp-server-side-render', 'registered' ) ) {
			$deps[] = 'wp-server-side-render';
		}

		wp_register_script(
			'smartrec-block-editor',
			SMARTREC_PLUGIN_URL . 'assets/js/blocks/smartrec-block.js',
			$deps,
			SMARTREC_VERSION,
			true
		);

		// Load frontend CSS in the editor so preview looks correct.
		wp_register_style(
			'smartrec-block-editor-style',
			false,
			array(),
			SMARTREC_VERSION
		);

		$css_file = SMARTREC_PLUGIN_DIR . 'assets/css/smartrec-frontend.css';
		if ( file_exists( $css_file ) ) {
			wp_add_inline_style( 'smartrec-block-editor-style', file_get_contents( $css_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		register_block_type( 'smartrec/recommendations', array(
			'api_version'     => 2,
			'editor_script'   => 'smartrec-block-editor',
			'editor_style'    => 'smartrec-block-editor-style',
			'render_callback' => array( $this, 'render_block' ),
			'attributes'      => array(
				'blockType'      => array( 'type' => 'string', 'default' => 'for_you' ),
				'title'          => array( 'type' => 'string', 'default' => '' ),
				'limit'          => array( 'type' => 'number', 'default' => 8 ),
				'columns'        => array( 'type' => 'number', 'default' => 4 ),
				'columnsTablet'  => array( 'type' => 'number', 'default' => 2 ),
				'columnsMobile'  => array( 'type' => 'number', 'default' => 1 ),
				'layout'         => array( 'type' => 'string', 'default' => 'grid' ),
				'loadMore'       => array( 'type' => 'number', 'default' => 0 ),
				'showPrice'      => array( 'type' => 'boolean', 'default' => true ),
				'showRating'     => array( 'type' => 'boolean', 'default' => true ),
				'showAddToCart'  => array( 'type' => 'boolean', 'default' => true ),
				'showReason'     => array( 'type' => 'boolean', 'default' => false ),
				'category'       => array( 'type' => 'string', 'default' => '' ),
				'order'          => array( 'type' => 'string', 'default' => 'score' ),
			),
		) );

		// Product Template block — for FSE product templates, Kadence WooTemplates, etc.
		register_block_type( 'smartrec/product-recommendations', array(
			'api_version'     => 2,
			'editor_script'   => 'smartrec-block-editor',
			'editor_style'    => 'smartrec-block-editor-style',
			'render_callback' => array( $this, 'render_product_block' ),
			'uses_context'    => array( 'postId', 'postType' ),
			'attributes'      => array(
				'blockType'      => array( 'type' => 'string', 'default' => 'similar' ),
				'title'          => array( 'type' => 'string', 'default' => '' ),
				'limit'          => array( 'type' => 'number', 'default' => 4 ),
				'columns'        => array( 'type' => 'number', 'default' => 4 ),
				'columnsTablet'  => array( 'type' => 'number', 'default' => 2 ),
				'columnsMobile'  => array( 'type' => 'number', 'default' => 1 ),
				'layout'         => array( 'type' => 'string', 'default' => 'grid' ),
				'loadMore'       => array( 'type' => 'number', 'default' => 0 ),
				'showPrice'      => array( 'type' => 'boolean', 'default' => true ),
				'showRating'     => array( 'type' => 'boolean', 'default' => true ),
				'showAddToCart'  => array( 'type' => 'boolean', 'default' => true ),
				'showReason'     => array( 'type' => 'boolean', 'default' => true ),
				'order'          => array( 'type' => 'string', 'default' => 'score' ),
			),
		) );
	}

	/**
	 * Server-side render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$type_map = array(
			'recently_viewed'  => array( 'engine' => 'recently_viewed', 'title' => __( 'Recently viewed', 'smartrec' ) ),
			'for_you'          => array( 'engine' => 'personalized_mix', 'title' => __( 'Recommended for you', 'smartrec' ) ),
			'trending'         => array( 'engine' => 'trending', 'title' => __( 'Trending now', 'smartrec' ) ),
			'similar_to_viewed' => array( 'engine' => 'similar', 'title' => __( 'Related to what you viewed', 'smartrec' ) ),
			'bought_together'  => array( 'engine' => 'bought_together', 'title' => __( 'Customers also bought', 'smartrec' ) ),
			'new_arrivals'     => array( 'engine' => 'trending', 'title' => __( 'New arrivals', 'smartrec' ) ),
			'custom'           => array( 'engine' => '', 'title' => __( 'Recommended products', 'smartrec' ) ),
		);

		$block_type = $attributes['blockType'] ?? 'for_you';
		$preset     = $type_map[ $block_type ] ?? $type_map['for_you'];

		$title = ! empty( $attributes['title'] ) ? $attributes['title'] : $preset['title'];

		// Build shortcode attributes string.
		$shortcode_tag = 'smartrec';
		if ( 'custom' !== $block_type && isset( $type_map[ $block_type ] ) ) {
			$shortcode_tag = 'smartrec_' . $block_type;
		}

		$atts = array(
			'title'          => $title,
			'limit'          => (int) ( $attributes['limit'] ?? 8 ),
			'columns'        => (int) ( $attributes['columns'] ?? 4 ),
			'columns_tablet' => (int) ( $attributes['columnsTablet'] ?? 2 ),
			'columns_mobile' => (int) ( $attributes['columnsMobile'] ?? 1 ),
			'layout'         => $attributes['layout'] ?? 'grid',
			'load_more'      => (int) ( $attributes['loadMore'] ?? 0 ),
			'show_price'     => ! empty( $attributes['showPrice'] ) ? 'yes' : 'no',
			'show_rating'    => ! empty( $attributes['showRating'] ) ? 'yes' : 'no',
			'show_add_to_cart' => ! empty( $attributes['showAddToCart'] ) ? 'yes' : 'no',
			'show_reason'    => ! empty( $attributes['showReason'] ) ? 'yes' : 'no',
			'order'          => $attributes['order'] ?? 'score',
		);

		if ( ! empty( $attributes['category'] ) ) {
			$atts['category'] = $attributes['category'];
		}

		if ( 'custom' === $block_type && ! empty( $preset['engine'] ) ) {
			$atts['engine'] = $preset['engine'];
		}

		// Build shortcode string.
		$parts = array();
		foreach ( $atts as $key => $value ) {
			$parts[] = $key . '="' . esc_attr( $value ) . '"';
		}

		return do_shortcode( '[' . $shortcode_tag . ' ' . implode( ' ', $parts ) . ']' );
	}

	/**
	 * Server-side render for the product template block.
	 * Automatically detects the current product from multiple sources.
	 *
	 * @param array     $attributes Block attributes.
	 * @param string    $content    Block content.
	 * @param \WP_Block $block      Block instance with context.
	 * @return string
	 */
	public function render_product_block( $attributes, $content, $block ) {
		// Detect product ID from multiple sources (FSE, classic, template builders).
		$product_id = 0;

		// 1. FSE block context (postId from query loop / product template).
		if ( ! empty( $block->context['postId'] ) ) {
			$post_type = get_post_type( $block->context['postId'] );
			if ( 'product' === $post_type ) {
				$product_id = (int) $block->context['postId'];
			}
		}

		// 2. Global product (set by WooCommerce and template builders).
		if ( $product_id <= 0 && ! empty( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof \WC_Product ) {
			$product_id = $GLOBALS['product']->get_id();
		}

		// 3. Global post (classic templates, Kadence, Elementor).
		if ( $product_id <= 0 ) {
			$current_post = get_post();
			if ( $current_post && 'product' === $current_post->post_type ) {
				$product_id = $current_post->ID;
			}
		}

		// 4. WooCommerce is_product() check.
		if ( $product_id <= 0 && function_exists( 'is_product' ) && is_product() ) {
			$product_id = get_queried_object_id();
		}

		if ( $product_id <= 0 ) {
			return '';
		}

		$type_map = array(
			'similar'          => array( 'engine' => 'similar', 'title' => __( 'Similar products', 'smartrec' ) ),
			'bought_together'  => array( 'engine' => 'bought_together', 'title' => __( 'Frequently bought together', 'smartrec' ) ),
			'viewed_together'  => array( 'engine' => 'viewed_together', 'title' => __( 'Others also viewed', 'smartrec' ) ),
			'complementary'    => array( 'engine' => 'complementary', 'title' => __( 'Complete your purchase', 'smartrec' ) ),
			'recently_viewed'  => array( 'engine' => 'recently_viewed', 'title' => __( 'Recently viewed', 'smartrec' ) ),
			'personalized_mix' => array( 'engine' => 'personalized_mix', 'title' => __( 'Recommended for you', 'smartrec' ) ),
			'trending'         => array( 'engine' => 'trending', 'title' => __( 'Trending now', 'smartrec' ) ),
		);

		$block_type = $attributes['blockType'] ?? 'similar';
		$preset     = $type_map[ $block_type ] ?? $type_map['similar'];
		$title      = ! empty( $attributes['title'] ) ? $attributes['title'] : $preset['title'];

		$renderer = new Renderer( $this->manager, $this->settings );

		$args = array(
			'engine'           => $preset['engine'],
			'limit'            => (int) ( $attributes['limit'] ?? 4 ),
			'layout'           => $attributes['layout'] ?? 'grid',
			'title'            => $title,
			'columns'          => (int) ( $attributes['columns'] ?? 4 ),
			'columns_tablet'   => (int) ( $attributes['columnsTablet'] ?? 2 ),
			'columns_mobile'   => (int) ( $attributes['columnsMobile'] ?? 1 ),
			'show_price'       => ! empty( $attributes['showPrice'] ),
			'show_rating'      => ! empty( $attributes['showRating'] ),
			'show_add_to_cart' => ! empty( $attributes['showAddToCart'] ),
			'show_reason'      => ! empty( $attributes['showReason'] ),
			'order'            => $attributes['order'] ?? 'score',
			'use_wc_template'  => $this->settings->get( 'use_wc_template', false ),
		);

		$load_more = (int) ( $attributes['loadMore'] ?? 0 );
		if ( $load_more > 0 ) {
			$args['load_more']       = true;
			$args['load_more_count'] = $load_more;
			$args['load_more_text']  = $this->settings->get( 'load_more_text', __( 'Load more', 'smartrec' ) );
		}

		return $renderer->render( 'single_product_below', $product_id, $args );
	}
}
