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

		$exclude_ids = $args['exclude'] ?? array();
		$requested_limit = $args['limit'] ?? (int) $this->settings->get( 'default_limit', 8 );

		// Ask engines for more products when we'll filter results down.
		$engine_limit = $requested_limit;
		$category_id  = (int) ( $args['category_id'] ?? 0 );

		if ( ! empty( $exclude_ids ) ) {
			// Ask for enough to fill after filtering. Generous multiplier ensures
			// Load More always has products even when many are excluded.
			$engine_limit = max( $requested_limit * 3, $requested_limit + count( $exclude_ids ) + 10 );
		}

		// Category filter will discard many results — ask for 5x more.
		if ( $category_id > 0 ) {
			$engine_limit = max( $engine_limit * 5, 40 );
		}

		// Random order: build a large pool so each page load picks different products.
		$order    = $args['order'] ?? 'score';
		$is_random = ( 'random' === $order );
		if ( $is_random ) {
			$engine_limit = max( $engine_limit * 3, $requested_limit * 3, 24 );
		}

		// Check cache only when NOT excluding (normal page load).
		if ( empty( $exclude_ids ) && $this->settings->get( 'cache_enabled', true ) ) {
			$cache_key = $this->build_cache_key( $location, $productId, $args );
			$cache_key = apply_filters( 'smartrec_cache_key', $cache_key, $location, $productId );
			$cached    = $this->cache->get( $cache_key );
			if ( false !== $cached ) {
				if ( $is_random ) {
					// Cache stores the full pool; pick a random subset each time.
					shuffle( $cached );
					return array_slice( $cached, 0, $requested_limit );
				}
				return $cached;
			}
		}

		// Determine engines for this location.
		$engines = $this->get_engines_for_location( $location, $args );

		do_action( 'smartrec_before_recommendations', $location, $productId, $engines );

		// Build engine args with expanded limit.
		$engine_args = $args;
		$engine_args['limit'] = $engine_limit;
		$engine_args['exclude'] = $exclude_ids;

		// Query each engine.
		$all_results = array();
		foreach ( $engines as $engine ) {
			if ( ! $engine->isAvailable() ) {
				continue;
			}

			try {
				$results = $engine->getRecommendations( $productId, $userId, $sessionId, $engine_args );
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

		// Filter by category if specified (applies to ALL engines).
		$category_id = (int) ( $args['category_id'] ?? 0 );
		if ( $category_id > 0 ) {
			$merged = $this->filter_by_category( $merged, $category_id );
		}

		// Filter out excluded IDs (from Load More).
		if ( ! empty( $exclude_ids ) ) {
			$merged = array_values( array_filter( $merged, function ( $r ) use ( $exclude_ids ) {
				return ! in_array( (int) $r['product_id'], $exclude_ids, true );
			} ) );
		}

		// Batch-prime WP object cache for all product IDs at once.
		$this->prime_product_cache( $merged );

		// Validate products (now hits in-memory cache, ~0 DB queries).
		$merged = $this->validate_products( $merged );

		// Apply global exclude list.
		$merged = $this->apply_exclusions( $merged, $location );

		// For random: cache the full validated pool, then pick a random subset.
		// For score: just slice to requested limit.
		if ( $is_random ) {
			// Cache the full pool before slicing.
			$pool_to_cache = $merged;

			// Pick random subset for this page load.
			shuffle( $merged );
			$merged = array_slice( $merged, 0, $requested_limit );
		} else {
			$merged = array_slice( $merged, 0, $requested_limit );
		}

		do_action( 'smartrec_after_recommendations', $location, $productId, $merged );

		// Cache results (skip cache for Load More requests with exclusions).
		$data_to_cache = $is_random ? ( $pool_to_cache ?? $merged ) : $merged;
		if ( empty( $exclude_ids ) && $this->settings->get( 'cache_enabled', true ) && ! empty( $data_to_cache ) ) {
			$engine_id = $args['engine'] ?? 'default';
			$personalized_engines = array( 'personalized_mix', 'recently_viewed', 'similar', 'bought_together' );
			$is_personalized = in_array( $engine_id, $personalized_engines, true ) || 'default' === $engine_id;

			if ( $is_personalized && ( get_current_user_id() > 0 || ! empty( $sessionId ) ) ) {
				$ttl = 300; // 5 minutes for personalized — keeps results fresh.
			} else {
				$ttl = $this->settings->get_cache_ttl( $location );
			}

			$ttl = apply_filters( 'smartrec_cache_ttl', $ttl, $location );
			$this->cache->set( $cache_key, $data_to_cache, $ttl );
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
	 * Filter results to only include products in a specific category.
	 *
	 * @param array $results     Recommendation results.
	 * @param int   $category_id Category term ID.
	 * @return array Filtered results.
	 */
	private function filter_by_category( array $results, int $category_id ): array {
		if ( empty( $results ) ) {
			return $results;
		}

		// Prime cache first so get_category_ids() doesn't trigger N queries.
		$this->prime_product_cache( $results );

		return array_values( array_filter( $results, function ( $r ) use ( $category_id ) {
			$product = wc_get_product( $r['product_id'] );
			if ( ! $product ) {
				return false;
			}
			return in_array( $category_id, $product->get_category_ids(), true );
		} ) );
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
			$args['category_id'] ?? 0,
			$user_part,
		);

		return implode( '_', $parts );
	}
}
