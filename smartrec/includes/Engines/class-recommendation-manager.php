<?php
/**
 * Recommendation Manager — orchestrates all engines.
 *
 * @package SmartRec\Engines
 */

namespace SmartRec\Engines;

use SmartRec\Core\Settings;
use SmartRec\Cache\CacheManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class RecommendationManager
 *
 * Registers, orchestrates and merges results from all recommendation engines.
 */
class RecommendationManager {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Cache manager instance.
	 *
	 * @var CacheManager
	 */
	private $cache;

	/**
	 * Registered engines.
	 *
	 * @var RecommendationEngineInterface[]
	 */
	private $engines = array();

	/**
	 * Constructor.
	 *
	 * @param Settings     $settings Settings instance.
	 * @param CacheManager $cache    Cache manager instance.
	 */
	public function __construct( Settings $settings, CacheManager $cache ) {
		$this->settings = $settings;
		$this->cache    = $cache;

		$this->register_default_engines();
	}

	/**
	 * Register all default engines.
	 *
	 * @return void
	 */
	private function register_default_engines() {
		$this->engines = array(
			'similar_products'  => new SimilarProducts( $this->settings ),
			'bought_together'   => new BoughtTogether( $this->settings ),
			'viewed_together'   => new ViewedTogether( $this->settings ),
			'recently_viewed'   => new RecentlyViewed( $this->settings ),
			'trending'          => new TrendingProducts( $this->settings ),
			'complementary'     => new ComplementaryProducts( $this->settings ),
		);

		// Personalized Mix needs reference to other engines.
		$personalized = new PersonalizedMix( $this->settings, $this->engines );
		$this->engines['personalized_mix'] = $personalized;

		// Allow third-party engines.
		$this->engines = apply_filters( 'smartrec_registered_engines', $this->engines );
	}

	/**
	 * Get recommendations for a location.
	 *
	 * @param string $location  Location ID.
	 * @param int    $productId Current product ID.
	 * @param array  $args      Additional arguments.
	 * @return array
	 */
	public function getRecommendations( string $location, int $productId, array $args = array() ): array {
		// Determine session and user.
		$userId    = get_current_user_id();
		$sessionId = isset( $_COOKIE['smartrec_session'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['smartrec_session'] ) ) : '';

		// Check cache.
		if ( $this->settings->get( 'cache_enabled', true ) ) {
			$cache_key = $this->build_cache_key( $location, $productId, $args );
			$cache_key = apply_filters( 'smartrec_cache_key', $cache_key, $location, $productId );
			$cached    = $this->cache->get( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Determine engines for this location.
		$engines = $this->get_engines_for_location( $location, $args );

		do_action( 'smartrec_before_recommendations', $location, $productId, $engines );

		// Query each engine.
		$all_results = array();
		foreach ( $engines as $engine ) {
			if ( ! $engine->isAvailable() ) {
				continue;
			}

			try {
				$results = $engine->getRecommendations( $productId, $userId, $sessionId, $args );
				$all_results[ $engine->getId() ] = $results;
			} catch ( \Exception $e ) {
				if ( $this->settings->get( 'debug_mode', false ) ) {
					error_log( 'SmartRec: Engine ' . $engine->getId() . ' error: ' . $e->getMessage() );
				}
				$all_results[ $engine->getId() ] = array();
			}
		}

		// Merge results.
		$merged = $this->merge_results( $all_results, $args );

		// Apply global filters.
		$merged = apply_filters( 'smartrec_filter_results', $merged, $location, $productId );

		// Batch-prime WP object cache for all product IDs at once.
		// This converts N individual wc_get_product() queries into 2-3 batch queries.
		$this->prime_product_cache( $merged );

		// Validate products (now hits in-memory cache, ~0 DB queries).
		$merged = $this->validate_products( $merged );

		// Apply global exclude list.
		$merged = $this->apply_exclusions( $merged, $location );

		// Limit results.
		$limit  = $args['limit'] ?? (int) $this->settings->get( 'default_limit', 8 );
		$merged = array_slice( $merged, 0, $limit );

		do_action( 'smartrec_after_recommendations', $location, $productId, $merged );

		// Cache results. Personalized engines use shorter TTL for freshness.
		if ( $this->settings->get( 'cache_enabled', true ) && ! empty( $merged ) ) {
			$engine_id = $args['engine'] ?? 'default';
			$personalized_engines = array( 'personalized_mix', 'recently_viewed', 'similar', 'bought_together' );
			$is_personalized = in_array( $engine_id, $personalized_engines, true ) || 'default' === $engine_id;

			if ( $is_personalized && ( get_current_user_id() > 0 || ! empty( $sessionId ) ) ) {
				$ttl = 300; // 5 minutes for personalized — keeps results fresh.
			} else {
				$ttl = $this->settings->get_cache_ttl( $location );
			}

			$ttl = apply_filters( 'smartrec_cache_ttl', $ttl, $location );
			$this->cache->set( $cache_key, $merged, $ttl );
		}

		return $merged;
	}

	/**
	 * Get a specific engine by ID.
	 *
	 * @param string $engineId Engine ID.
	 * @return RecommendationEngineInterface|null
	 */
	public function get_engine( string $engineId ) {
		return $this->engines[ $engineId ] ?? null;
	}

	/**
	 * Get all registered engines.
	 *
	 * @return RecommendationEngineInterface[]
	 */
	public function get_all_engines(): array {
		return $this->engines;
	}

	/**
	 * Get engines configured for a specific location.
	 *
	 * @param string $location Location ID.
	 * @param array  $args     Arguments (may contain 'engine' override).
	 * @return RecommendationEngineInterface[]
	 */
	private function get_engines_for_location( string $location, array $args = array() ): array {
		// Engine override from args.
		if ( ! empty( $args['engine'] ) && isset( $this->engines[ $args['engine'] ] ) ) {
			return array( $this->engines[ $args['engine'] ] );
		}

		// Get configured engine for location.
		$location_engines = $this->settings->get( 'location_engines', array() );
		$engine_id        = $location_engines[ $location ] ?? 'personalized_mix';

		if ( isset( $this->engines[ $engine_id ] ) ) {
			return array( $this->engines[ $engine_id ] );
		}

		// Fallback to personalized mix.
		return array( $this->engines['personalized_mix'] );
	}

	/**
	 * Merge results from multiple engines.
	 *
	 * @param array $allResults Results keyed by engine ID.
	 * @param array $args       Arguments.
	 * @return array
	 */
	private function merge_results( array $allResults, array $args = array() ): array {
		$merged = array();

		foreach ( $allResults as $engine_id => $results ) {
			foreach ( $results as $result ) {
				$pid = $result['product_id'];
				if ( ! isset( $merged[ $pid ] ) || $result['score'] > $merged[ $pid ]['score'] ) {
					$merged[ $pid ] = $result;
				}
			}
		}

		$merged = array_values( $merged );

		usort(
			$merged,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return $merged;
	}

	/**
	 * Batch-prime WP object cache for a list of product IDs.
	 *
	 * Loads all post data, post meta, and term data in 2-3 bulk queries
	 * instead of N individual queries. Subsequent wc_get_product() calls
	 * hit the in-memory cache with zero DB queries.
	 *
	 * @param array $results Recommendation results with 'product_id' keys.
	 * @return void
	 */
	private function prime_product_cache( array $results ) {
		$ids = array_unique( array_column( $results, 'product_id' ) );
		if ( empty( $ids ) ) {
			return;
		}

		// 1 query: Load all post data into WP object cache.
		_prime_post_caches( $ids, true );

		// 1 query: Load all postmeta into cache.
		update_meta_cache( 'post', $ids );

		// 1 query: Load all term relationships into cache.
		if ( function_exists( 'update_object_term_cache' ) ) {
			update_object_term_cache( $ids, 'product' );
		}
	}

	/**
	 * Validate that all recommended products are valid.
	 *
	 * @param array $results Results.
	 * @return array
	 */
	private function validate_products( array $results ): array {
		return array_filter(
			$results,
			function ( $result ) {
				$product = wc_get_product( $result['product_id'] );
				return $product
					&& $product->is_in_stock()
					&& 'publish' === $product->get_status()
					&& $product->is_visible();
			}
		);
	}

	/**
	 * Apply global exclusions.
	 *
	 * @param array  $results  Results.
	 * @param string $location Location.
	 * @return array
	 */
	private function apply_exclusions( array $results, string $location ): array {
		$exclude_ids = $this->settings->get( 'exclude_product_ids', '' );
		if ( ! empty( $exclude_ids ) ) {
			$exclude_ids = array_map( 'absint', explode( ',', $exclude_ids ) );
		} else {
			$exclude_ids = array();
		}

		$exclude_ids = apply_filters( 'smartrec_exclude_products', $exclude_ids, $location );

		if ( empty( $exclude_ids ) ) {
			return $results;
		}

		return array_filter(
			$results,
			function ( $result ) use ( $exclude_ids ) {
				return ! in_array( $result['product_id'], $exclude_ids, true );
			}
		);
	}

	/**
	 * Build cache key for a recommendation request.
	 *
	 * @param string $location  Location.
	 * @param int    $productId Product ID.
	 * @param array  $args      Arguments.
	 * @return string
	 */
	private function build_cache_key( string $location, int $productId, array $args = array() ): string {
		$engine = $args['engine'] ?? 'default';

		// Personalized engines need per-user cache; others are shared.
		$personalized_engines = array( 'personalized_mix', 'recently_viewed' );
		$user_part = '0';
		if ( in_array( $engine, $personalized_engines, true ) || 'default' === $engine ) {
			$user_id = get_current_user_id();
			$user_part = $user_id > 0
				? 'u' . $user_id
				: 's' . substr( md5( $_COOKIE['smartrec_session'] ?? '' ), 0, 8 );
		}

		$parts = array(
			'smartrec',
			$location,
			$productId,
			$engine,
			$args['limit'] ?? $this->settings->get( 'default_limit', 8 ),
			$user_part,
		);

		return implode( '_', $parts );
	}
}
