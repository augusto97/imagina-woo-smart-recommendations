<?php
/**
 * Template rendering engine.
 *
 * @package SmartRec\Display
 */

namespace SmartRec\Display;

use SmartRec\Core\Settings;
use SmartRec\Engines\RecommendationManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Renderer
 *
 * Renders recommendation widgets using templates.
 */
class Renderer {

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

		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'smartrec-frontend',
			SMARTREC_PLUGIN_URL . 'assets/css/smartrec-frontend.css',
			array(),
			SMARTREC_VERSION
		);

		if ( $this->settings->get( 'ajax_loading', false ) ) {
			wp_enqueue_script(
				'smartrec-display',
				SMARTREC_PLUGIN_URL . 'assets/js/smartrec-display.js',
				array(),
				SMARTREC_VERSION,
				true
			);

			wp_localize_script(
				'smartrec-display',
				'smartrecDisplay',
				array(
					'ajaxUrl' => rest_url( 'smartrec/v1/recommendations' ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
	}

	/**
	 * Render recommendations for a location.
	 *
	 * @param string $location  Location ID.
	 * @param int    $productId Product ID.
	 * @param array  $args      Display arguments.
	 * @return string HTML output.
	 */
	public function render( string $location, int $productId = 0, array $args = array() ): string {
		$location_settings = $this->settings->get_location_settings( $location );

		$args = wp_parse_args(
			$args,
			array(
				'limit'         => $location_settings['limit'],
				'layout'        => $location_settings['layout'],
				'title'         => $location_settings['title'],
				'engine'        => $location_settings['engine'],
				'columns'       => 4,
				'show_price'    => $this->settings->get( 'show_price', true ),
				'show_rating'   => $this->settings->get( 'show_rating', true ),
				'show_add_to_cart' => $this->settings->get( 'show_add_to_cart', true ),
				'show_reason'   => $this->settings->get( 'show_reason', true ),
				'css_class'     => '',
			)
		);

		// AJAX loading placeholder.
		if ( $this->settings->get( 'ajax_loading', false ) ) {
			return $this->render_placeholder( $location, $productId, $args );
		}

		// Get recommendations.
		$recommendations = $this->manager->getRecommendations( $location, $productId, $args );

		if ( empty( $recommendations ) ) {
			return '';
		}

		// Load product objects.
		$products = $this->load_products( $recommendations );

		if ( empty( $products ) ) {
			return '';
		}

		return $this->render_template( $products, $recommendations, $location, $args );
	}

	/**
	 * Render a template with products.
	 *
	 * @param array  $products        WC_Product objects.
	 * @param array  $recommendations Raw recommendation data.
	 * @param string $location        Location ID.
	 * @param array  $args            Display arguments.
	 * @return string
	 */
	public function render_template( array $products, array $recommendations, string $location, array $args ): string {
		$layout   = $args['layout'] ?? 'grid';
		$template = $this->locate_template( $layout );

		if ( ! $template ) {
			return '';
		}

		// Build reason map.
		$reasons = array();
		foreach ( $recommendations as $rec ) {
			$reasons[ $rec['product_id'] ] = $rec['reason'] ?? '';
		}

		$title    = apply_filters( 'smartrec_widget_title', $args['title'], $location, $args['engine'] ?? '' );
		$settings = $args;

		do_action( 'smartrec_before_render', $location, $products, $template );

		ob_start();
		include $template;
		$html = ob_get_clean();

		do_action( 'smartrec_after_render', $location, $products, $template );

		$html = apply_filters( 'smartrec_product_card_html', $html, $products, $location );

		return $html;
	}

	/**
	 * Render AJAX loading placeholder.
	 *
	 * @param string $location  Location.
	 * @param int    $productId Product ID.
	 * @param array  $args      Arguments.
	 * @return string
	 */
	private function render_placeholder( string $location, int $productId, array $args ): string {
		$css_class = ! empty( $args['css_class'] ) ? ' ' . esc_attr( $args['css_class'] ) : '';

		return sprintf(
			'<div class="smartrec-widget smartrec-loading%s" data-location="%s" data-product-id="%d" data-limit="%d" data-layout="%s" data-engine="%s">
				<div class="smartrec-widget__skeleton">
					%s
				</div>
			</div>',
			$css_class,
			esc_attr( $location ),
			$productId,
			(int) $args['limit'],
			esc_attr( $args['layout'] ),
			esc_attr( $args['engine'] ?? '' ),
			str_repeat( '<div class="smartrec-widget__skeleton-item"></div>', min( (int) $args['limit'], 4 ) )
		);
	}

	/**
	 * Load WC_Product objects from recommendations.
	 *
	 * @param array $recommendations Recommendation data.
	 * @return array WC_Product objects.
	 */
	private function load_products( array $recommendations ): array {
		$products = array();
		foreach ( $recommendations as $rec ) {
			$product = wc_get_product( $rec['product_id'] );
			if ( $product ) {
				$products[] = $product;
			}
		}
		return $products;
	}

	/**
	 * Locate template file (theme override or plugin default).
	 *
	 * @param string $layout Layout name.
	 * @return string|false Template path or false.
	 */
	private function locate_template( string $layout ) {
		$template_name = 'recommendation-' . sanitize_file_name( $layout ) . '.php';
		$template      = apply_filters( 'smartrec_template', '', '', $layout );

		if ( ! empty( $template ) && file_exists( $template ) ) {
			return $template;
		}

		// Check theme override.
		$theme_template = locate_template( 'smartrec/' . $template_name );
		if ( $theme_template ) {
			return $theme_template;
		}

		// Default template.
		$default = SMARTREC_PLUGIN_DIR . 'templates/' . $template_name;
		if ( file_exists( $default ) ) {
			return $default;
		}

		// Fallback to grid.
		$fallback = SMARTREC_PLUGIN_DIR . 'templates/recommendation-grid.php';
		return file_exists( $fallback ) ? $fallback : false;
	}
}
