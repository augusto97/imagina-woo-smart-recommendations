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
		// Load CSS inline to avoid browser/server caching of stale file.
		$css_file = SMARTREC_PLUGIN_DIR . 'assets/css/smartrec-frontend.css';
		$css      = '';

		if ( file_exists( $css_file ) ) {
			$css .= file_get_contents( $css_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		// Append dynamic inline styles from admin customization.
		$css .= "\n" . $this->build_dynamic_css();

		if ( ! empty( $css ) ) {
			// Register an empty handle so wp_add_inline_style works, then print inline.
			wp_register_style( 'smartrec-frontend', false, array(), SMARTREC_VERSION );
			wp_enqueue_style( 'smartrec-frontend' );
			wp_add_inline_style( 'smartrec-frontend', $css );
		}

		// Load More JS — always register so the inline script can use the REST URL.
		wp_register_script( 'smartrec-load-more', false, array(), SMARTREC_VERSION, true );
		wp_enqueue_script( 'smartrec-load-more' );

		$load_more_js = SMARTREC_PLUGIN_DIR . 'assets/js/smartrec-load-more.js';
		if ( file_exists( $load_more_js ) ) {
			wp_add_inline_script(
				'smartrec-load-more',
				'var smartrecLoadMore=' . wp_json_encode( array(
					'restUrl' => rest_url( 'smartrec/v1/recommendations' ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				) ) . ';' . file_get_contents( $load_more_js ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			);
		}

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
	 * Build dynamic CSS from admin style settings.
	 *
	 * @return string CSS string.
	 */
	private function build_dynamic_css(): string {
		// All CSS variables with their defaults. Admin values override these.
		$defaults = array(
			'--smartrec-columns'      => '4',
			'--smartrec-gap'          => '16px',
			'--smartrec-card-padding' => '12px',
			'--smartrec-card-radius'  => '8px',
			'--smartrec-card-shadow'  => '0 1px 3px rgba(0,0,0,0.08)',
			'--smartrec-title-size'   => '18px',
			'--smartrec-badge-bg'     => '#f0f0f0',
			'--smartrec-badge-color'  => '#333',
			'--smartrec-accent'       => '#7f54b3',
			'--smartrec-card-bg'      => '#fff',
			'--smartrec-card-text'    => '#333',
			'--smartrec-title-color'  => 'inherit',
			'--smartrec-btn-bg'       => '#7f54b3',
			'--smartrec-btn-text'     => '#fff',
		);

		// Map admin settings to CSS variable names.
		$overrides = array(
			'style_accent_color' => '--smartrec-accent',
			'style_card_bg'      => '--smartrec-card-bg',
			'style_card_text'    => '--smartrec-card-text',
			'style_title_color'  => '--smartrec-title-color',
			'style_badge_bg'     => '--smartrec-badge-bg',
			'style_badge_text'   => '--smartrec-badge-color',
			'style_btn_bg'       => '--smartrec-btn-bg',
			'style_btn_text'     => '--smartrec-btn-text',
			'style_card_radius'  => '--smartrec-card-radius',
			'style_gap'          => '--smartrec-gap',
			'style_title_size'   => '--smartrec-title-size',
		);

		// Apply admin overrides.
		foreach ( $overrides as $setting => $var ) {
			$value = $this->settings->get( $setting, '' );
			if ( ! empty( $value ) ) {
				$defaults[ $var ] = $value;
			}
		}

		// If accent is set but btn_bg is not, button inherits accent.
		$accent_val = $this->settings->get( 'style_accent_color', '' );
		if ( ! empty( $accent_val ) && empty( $this->settings->get( 'style_btn_bg', '' ) ) ) {
			$defaults['--smartrec-btn-bg'] = $accent_val;
		}

		// Shadow presets.
		$shadow = $this->settings->get( 'style_card_shadow', '' );
		if ( ! empty( $shadow ) ) {
			$shadow_map = array(
				'none'   => 'none',
				'small'  => '0 1px 2px rgba(0,0,0,0.06)',
				'medium' => '0 2px 8px rgba(0,0,0,0.12)',
				'large'  => '0 4px 16px rgba(0,0,0,0.16)',
			);
			if ( isset( $shadow_map[ $shadow ] ) ) {
				$defaults['--smartrec-card-shadow'] = $shadow_map[ $shadow ];
			}
		}

		// Build :root block.
		$vars = array();
		foreach ( $defaults as $var => $value ) {
			$vars[] = $var . ':' . $value;
		}
		$css = ':root{' . implode( ';', $vars ) . '}';

		// Inherit theme fonts.
		if ( $this->settings->get( 'inherit_theme_fonts', true ) ) {
			$css .= "\n" . '.smartrec-widget,.smartrec-widget__title,.smartrec-widget__item-title{font-family:inherit}';
		}

		// Custom CSS from admin.
		$custom = $this->settings->get( 'custom_css', '' );
		if ( ! empty( $custom ) ) {
			$css .= "\n" . $custom;
		}

		return $css;
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
				'columns'       => $location_settings['columns'] ?? 4,
				'show_price'    => $this->settings->get( 'show_price', true ),
				'show_rating'   => $this->settings->get( 'show_rating', true ),
				'show_add_to_cart' => $this->settings->get( 'show_add_to_cart', true ),
				'show_reason'   => $this->settings->get( 'show_reason', true ),
				'use_wc_template' => $this->settings->get( 'use_wc_template', false ),
				'load_more'       => $location_settings['load_more'] ?? false,
				'load_more_count' => $location_settings['load_more_count'] ?? 4,
				'load_more_text'  => $this->settings->get( 'load_more_text', __( 'Load more', 'smartrec' ) ),
				'location'        => $location,
				'product_id'      => $productId,
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

	/**
	 * Render only the product item cards (no wrapper, title, or load-more button).
	 * Used by the REST API for partial/load-more responses.
	 *
	 * @param array  $products        WC_Product objects.
	 * @param array  $recommendations Raw recommendation data.
	 * @param string $location        Location ID.
	 * @param array  $args            Display arguments.
	 * @return string HTML of product items only.
	 */
	public function render_product_items( array $products, array $recommendations, string $location, array $args ): string {
		if ( empty( $products ) ) {
			return '';
		}

		$use_wc = ! empty( $args['use_wc_template'] );
		$layout = $args['layout'] ?? 'grid';

		// Build reason map.
		$reasons = array();
		foreach ( $recommendations as $rec ) {
			$reasons[ $rec['product_id'] ] = $rec['reason'] ?? '';
		}

		$settings = wp_parse_args( $args, array(
			'show_price'       => $this->settings->get( 'show_price', true ),
			'show_rating'      => $this->settings->get( 'show_rating', true ),
			'show_add_to_cart' => $this->settings->get( 'show_add_to_cart', true ),
			'show_reason'      => $this->settings->get( 'show_reason', true ),
			'use_wc_template'  => $use_wc,
		) );

		ob_start();

		if ( $use_wc ) {
			// Render WC product cards.
			foreach ( $products as $product ) {
				$GLOBALS['post']    = get_post( $product->get_id() );
				$GLOBALS['product'] = $product;
				setup_postdata( $GLOBALS['post'] );
				wc_get_template_part( 'content', 'product' );
			}
			wp_reset_postdata();
		} else {
			// Render SmartRec product cards using the partial template.
			$partial_template = SMARTREC_PLUGIN_DIR . 'templates/partials/product-card.php';
			if ( file_exists( $partial_template ) ) {
				foreach ( $products as $product ) {
					// Set WC globals so woocommerce_template_loop_add_to_cart() works.
					$GLOBALS['post']    = get_post( $product->get_id() );
					$GLOBALS['product'] = $product;
					setup_postdata( $GLOBALS['post'] );

					$product_id = $product->get_id();
					$reason     = $reasons[ $product_id ] ?? '';
					include $partial_template;
				}
				wp_reset_postdata();
			}
		}

		return ob_get_clean();
	}

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
