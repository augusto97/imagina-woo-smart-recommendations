<?php
/**
 * Centralized settings management.
 *
 * @package SmartRec\Core
 */

namespace SmartRec\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 *
 * Manages all plugin settings via WordPress Options API.
 */
class Settings {

	/**
	 * Option name prefix.
	 *
	 * @var string
	 */
	const OPTION_PREFIX = 'smartrec_';

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private $defaults;

	/**
	 * Cached settings.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->defaults = $this->get_defaults();
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not set.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$value = get_option( self::OPTION_PREFIX . $key, $default ?? ( $this->defaults[ $key ] ?? null ) );
		$this->cache[ $key ] = $value;

		return $value;
	}

	/**
	 * Set a setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool
	 */
	public function set( $key, $value ) {
		$this->cache[ $key ] = $value;
		return update_option( self::OPTION_PREFIX . $key, $value );
	}

	/**
	 * Delete a setting.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public function delete( $key ) {
		unset( $this->cache[ $key ] );
		return delete_option( self::OPTION_PREFIX . $key );
	}

	/**
	 * Get all settings with defaults merged.
	 *
	 * @return array
	 */
	public function get_all() {
		$settings = array();
		foreach ( $this->defaults as $key => $default ) {
			$settings[ $key ] = $this->get( $key );
		}
		return $settings;
	}

	/**
	 * Reset all settings to defaults.
	 *
	 * @return void
	 */
	public function reset() {
		foreach ( $this->defaults as $key => $default ) {
			$this->set( $key, $default );
		}
		$this->cache = array();
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	private function get_defaults() {
		return array(
			// General.
			'enabled'                   => true,
			'default_limit'             => 8,
			'default_layout'            => 'grid',
			'use_wc_template'           => false,
			'inherit_theme_fonts'       => true,
			'show_powered_by'           => false,

			// Tracking.
			'tracking_enabled'          => true,
			'tracking_method'           => 'both',
			'respect_dnt'              => true,
			'cookie_consent'            => 'auto',
			'data_retention_days'       => 90,
			'event_buffer_mode'         => 'direct',

			// Engines.
			'engine_similar_enabled'        => true,
			'engine_similar_priority'       => 5,
			'engine_bought_together_enabled' => true,
			'engine_bought_together_priority' => 8,
			'engine_bought_together_lookback' => 90,
			'engine_bought_together_min_count' => 2,
			'engine_viewed_together_enabled' => true,
			'engine_viewed_together_priority' => 4,
			'engine_viewed_together_lookback' => 30,
			'engine_viewed_together_min_count' => 3,
			'engine_recently_viewed_enabled' => true,
			'engine_recently_viewed_priority' => 3,
			'engine_trending_enabled'       => true,
			'engine_trending_priority'      => 6,
			'engine_trending_weight_24h'    => 10,
			'engine_trending_weight_7d'     => 3,
			'engine_trending_weight_30d'    => 1,
			'engine_complementary_enabled'  => true,
			'engine_complementary_priority' => 7,
			'engine_personalized_enabled'   => true,
			'engine_personalized_priority'  => 9,
			'engine_personalized_diversity' => 3,

			// Display locations.
			'location_single_product_below'     => true,
			'location_single_product_tabs'      => true,
			'location_cart_page'                => true,
			'location_cart_page_cross_sells'    => false,
			'location_checkout_page'            => false,
			'location_category_page'            => true,
			'location_empty_cart'               => true,
			'location_thank_you_page'           => true,
			'location_my_account'               => false,

			// Display settings.
			'ajax_loading'              => false,
			'show_price'                => true,
			'show_rating'               => true,
			'show_add_to_cart'          => true,
			'show_reason'               => true,

			// Load more.
			'load_more_enabled'         => false,
			'load_more_count'           => 4,
			'load_more_text'            => 'Load more',

			// Per-location load more counts.
			'location_load_more'        => array(),

			// Cache.
			'cache_enabled'             => true,
			'cache_ttl_product'         => 3600,
			'cache_ttl_category'        => 1800,
			'cache_ttl_cart'            => 900,
			'cache_ttl_general'         => 3600,
			'cache_warmer_enabled'      => true,
			'cache_warmer_frequency'    => 'hourly',

			// Cron.
			'cron_relationships_interval' => 'six_hours',
			'cron_cleanup_interval'       => 'daily',
			'cron_max_runtime'            => 120,
			'cron_batch_size'             => 500,

			// Advanced.
			'debug_mode'                => false,
			'max_queries_per_request'   => 10,
			'exclude_product_ids'       => '',
			'exclude_categories'        => array(),
			'rest_api_public'           => true,
			'delete_data_on_uninstall'  => false,

			// Appearance / styling.
			'style_accent_color'        => '',
			'style_card_bg'             => '',
			'style_card_text'           => '',
			'style_title_color'         => '',
			'style_badge_bg'            => '',
			'style_badge_text'          => '',
			'style_btn_bg'              => '',
			'style_btn_text'            => '',
			'style_card_radius'         => '',
			'style_card_shadow'         => '',
			'style_gap'                 => '',
			'style_title_size'          => '',
			'custom_css'                => '',

			// Complementary rules.
			'complementary_rules'       => array(),

			// Location engine mapping.
			'location_engines'          => array(
				'single_product_below'  => 'personalized_mix',
				'single_product_tabs'   => 'bought_together',
				'cart_page'             => 'bought_together',
				'cart_page_cross_sells' => 'complementary',
				'checkout_page'         => 'bought_together',
				'category_page'         => 'trending',
				'empty_cart'            => 'trending',
				'thank_you_page'        => 'complementary',
				'my_account'            => 'personalized_mix',
			),

			// Location titles.
			'location_titles'           => array(
				'single_product_below'  => 'Recommended for you',
				'single_product_tabs'   => 'Frequently Bought Together',
				'cart_page'             => 'You might also like',
				'cart_page_cross_sells' => 'Complete your order',
				'checkout_page'         => 'Last chance to add',
				'category_page'         => 'Trending in this category',
				'empty_cart'            => 'Popular products',
				'thank_you_page'        => 'Customers also bought',
				'my_account'            => 'Recommended for you',
			),

			// Per-location limits.
			'location_limits'           => array(),

			// Per-location layouts.
			'location_layouts'          => array(),

			// Per-location columns.
			'location_columns'          => array(),

			// Per-location order (score or random).
			'location_order'            => array(),
		);
	}

	/**
	 * Get location-specific settings.
	 *
	 * @param string $location Location ID.
	 * @return array
	 */
	public function get_location_settings( $location ) {
		$engines  = $this->get( 'location_engines' );
		$titles   = $this->get( 'location_titles' );
		$limits   = $this->get( 'location_limits' );
		$layouts  = $this->get( 'location_layouts' );
		$columns  = $this->get( 'location_columns' );

		$default_limit = (int) $this->get( 'default_limit', 8 );
		$default_layout = $this->get( 'default_layout', 'grid' );

		$cols_tablet  = $this->get( 'location_columns_tablet', array() );
		$cols_mobile  = $this->get( 'location_columns_mobile', array() );
		$load_more_map = $this->get( 'location_load_more', array() );
		$load_more_global = $this->get( 'load_more_enabled', false );
		$load_more_count  = (int) $this->get( 'load_more_count', 4 );
		$order_map        = $this->get( 'location_order', array() );

		return array(
			'enabled'         => $this->get( 'location_' . $location, false ),
			'engine'          => $engines[ $location ] ?? 'personalized_mix',
			'title'           => $titles[ $location ] ?? __( 'Recommended products', 'smartrec' ),
			'limit'           => ! empty( $limits[ $location ] ) ? (int) $limits[ $location ] : $default_limit,
			'layout'          => ! empty( $layouts[ $location ] ) ? $layouts[ $location ] : $default_layout,
			'columns'         => ! empty( $columns[ $location ] ) ? (int) $columns[ $location ] : 4,
			'columns_tablet'  => ! empty( $cols_tablet[ $location ] ) ? (int) $cols_tablet[ $location ] : 2,
			'columns_mobile'  => ! empty( $cols_mobile[ $location ] ) ? (int) $cols_mobile[ $location ] : 1,
			'load_more'       => ! empty( $load_more_map[ $location ] ) ? true : $load_more_global,
			'load_more_count' => ! empty( $load_more_map[ $location ] ) ? (int) $load_more_map[ $location ] : $load_more_count,
			'order'           => $order_map[ $location ] ?? 'score',
		);
	}

	/**
	 * Get cache TTL for a location type.
	 *
	 * @param string $location Location identifier.
	 * @return int TTL in seconds.
	 */
	public function get_cache_ttl( $location ) {
		$cart_locations = array( 'cart_page', 'cart_page_cross_sells', 'checkout_page' );
		$category_locations = array( 'category_page' );

		if ( in_array( $location, $cart_locations, true ) ) {
			return (int) $this->get( 'cache_ttl_cart', 900 );
		}

		if ( in_array( $location, $category_locations, true ) ) {
			return (int) $this->get( 'cache_ttl_category', 1800 );
		}

		if ( strpos( $location, 'single_product' ) !== false ) {
			return (int) $this->get( 'cache_ttl_product', 3600 );
		}

		return (int) $this->get( 'cache_ttl_general', 3600 );
	}
}
