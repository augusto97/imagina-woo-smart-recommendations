<?php
/**
 * Personalized Mix engine — hybrid engine combining all others.
 *
 * @package SmartRec\Engines
 */

namespace SmartRec\Engines;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class PersonalizedMix
 *
 * Combines results from all engines weighted by user profile preferences.
 */
class PersonalizedMix implements RecommendationEngineInterface {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Available engines.
	 *
	 * @var RecommendationEngineInterface[]
	 */
	private $engines = array();

	/**
	 * Constructor.
	 *
	 * @param Settings                        $settings Settings instance.
	 * @param RecommendationEngineInterface[] $engines  Available engines.
	 */
	public function __construct( Settings $settings, array $engines = array() ) {
		$this->settings = $settings;
		$this->engines  = $engines;
	}

	/**
	 * Set available engines.
	 *
	 * @param RecommendationEngineInterface[] $engines Engines.
	 * @return void
	 */
	public function setEngines( array $engines ) {
		$this->engines = $engines;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getId(): string {
		return 'personalized_mix';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return __( 'Personalized Mix', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDescription(): string {
		return __( 'Smart blend of all recommendation engines personalized for each visitor.', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable(): bool {
		return (bool) $this->settings->get( 'engine_personalized_enabled', true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecommendations( int $productId, int $userId, string $sessionId, array $args = array() ): array {
		$limit   = $args['limit'] ?? $this->getDefaultLimit();
		$exclude = $args['exclude'] ?? array();

		// Get user profile for personalization.
		$profile = $this->get_user_profile( $userId, $sessionId );

		// Collect results from all available engines.
		$all_results = array();
		foreach ( $this->engines as $engine ) {
			if ( $engine->getId() === $this->getId() ) {
				continue; // Skip self.
			}
			if ( ! $engine->isAvailable() ) {
				continue;
			}

			try {
				$engine_results = $engine->getRecommendations( $productId, $userId, $sessionId, $args );
				foreach ( $engine_results as $result ) {
					$all_results[] = array_merge( $result, array( 'engine' => $engine->getId() ) );
				}
			} catch ( \Exception $e ) {
				if ( $this->settings->get( 'debug_mode', false ) ) {
					error_log( 'SmartRec: Engine ' . $engine->getId() . ' failed: ' . $e->getMessage() );
				}
			}
		}

		// Batch-prime all product IDs from all engines at once.
		$all_ids = array_unique( array_column( $all_results, 'product_id' ) );
		if ( ! empty( $all_ids ) ) {
			_prime_post_caches( $all_ids, true );
			update_meta_cache( 'post', $all_ids );
		}

		// Re-score based on user profile (wc_get_product now hits memory cache).
		$scored = $this->personalize_scores( $all_results, $profile );

		// Merge and deduplicate (keep highest score).
		$merged = $this->merge_results( $scored );

		// Apply diversity filter.
		$max_per_category = (int) $this->settings->get( 'engine_personalized_diversity', 3 );
		$diverse          = $this->apply_diversity_filter( $merged, $max_per_category );

		$results = array_slice( $diverse, 0, $limit );

		return apply_filters( 'smartrec_engine_results', $results, $this->getId(), $productId );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDefaultLimit(): int {
		return 8;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPriority(): int {
		return (int) $this->settings->get( 'engine_personalized_priority', 9 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMinimumDataRequirements(): array {
		return array(
			'description' => __( 'Works best with at least 5 tracked events for the current user.', 'smartrec' ),
			'min_events'  => 5,
		);
	}

	/**
	 * Get user profile data — uses real-time session events when available,
	 * falls back to pre-computed cron profile.
	 *
	 * @param int    $userId    User ID.
	 * @param string $sessionId Session ID.
	 * @return array
	 */
	private function get_user_profile( int $userId, string $sessionId ): array {
		global $wpdb;

		$profile = array(
			'preferred_categories' => array(),
			'preferred_price_range' => array( 'min' => 0, 'max' => 0 ),
			'viewed_products'      => array(),
			'purchased_products'   => array(),
		);

		// 1. Try real-time session data first (most recent behavior).
		$realtime = $this->get_realtime_profile( $userId, $sessionId );

		// 2. Load pre-computed cron profile as base.
		$where_clause = '';
		$where_args   = array();

		if ( $userId > 0 ) {
			$where_clause = 'WHERE user_id = %d';
			$where_args   = array( $userId );
		} elseif ( ! empty( $sessionId ) ) {
			$where_clause = 'WHERE session_id = %s';
			$where_args   = array( $sessionId );
		}

		if ( ! empty( $where_args ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}smartrec_user_profiles {$where_clause} LIMIT 1",
					$where_args
				),
				ARRAY_A
			);

			if ( $row ) {
				$profile['preferred_categories'] = ! empty( $row['preferred_categories'] ) ? json_decode( $row['preferred_categories'], true ) : array();
				$profile['viewed_products']      = ! empty( $row['viewed_products'] ) ? json_decode( $row['viewed_products'], true ) : array();
				$profile['purchased_products']   = ! empty( $row['purchased_products'] ) ? json_decode( $row['purchased_products'], true ) : array();

				if ( ! empty( $row['preferred_price_range'] ) ) {
					$parts = explode( '-', $row['preferred_price_range'] );
					if ( count( $parts ) === 2 ) {
						$profile['preferred_price_range'] = array(
							'min' => (float) $parts[0],
							'max' => (float) $parts[1],
						);
					}
				}
			}
		}

		// 3. Merge real-time data on top (real-time takes priority).
		if ( ! empty( $realtime['viewed_products'] ) ) {
			// Prepend fresh views (most recent first), dedup.
			$profile['viewed_products'] = array_values( array_unique(
				array_merge( $realtime['viewed_products'], $profile['viewed_products'] )
			) );
			$profile['viewed_products'] = array_slice( $profile['viewed_products'], 0, 50 );
		}

		if ( ! empty( $realtime['preferred_categories'] ) ) {
			// Merge category weights — real-time gets 2x boost.
			foreach ( $realtime['preferred_categories'] as $cat_id => $weight ) {
				$existing = $profile['preferred_categories'][ $cat_id ] ?? 0;
				$profile['preferred_categories'][ $cat_id ] = $existing + ( $weight * 2 );
			}
		}

		return $profile;
	}

	/**
	 * Build a real-time profile from the current session's recent events.
	 * This reads directly from the events table — no cron needed.
	 *
	 * @param int    $userId    User ID.
	 * @param string $sessionId Session ID.
	 * @return array
	 */
	private function get_realtime_profile( int $userId, string $sessionId ): array {
		global $wpdb;

		$result = array(
			'viewed_products'      => array(),
			'preferred_categories' => array(),
		);

		// Get recent views from this session (last 2 hours, max 20).
		$where = '';
		$args  = array();
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-2 hours' ) );

		if ( $userId > 0 ) {
			$where = 'WHERE (user_id = %d OR session_id = %s) AND event_type = %s AND created_at >= %s';
			$args  = array( $userId, $sessionId, 'view', $since );
		} elseif ( ! empty( $sessionId ) ) {
			$where = 'WHERE session_id = %s AND event_type = %s AND created_at >= %s';
			$args  = array( $sessionId, 'view', $since );
		} else {
			return $result;
		}

		$args[] = 20;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$recent_views = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT product_id FROM {$wpdb->prefix}smartrec_events {$where} ORDER BY created_at DESC LIMIT %d",
				$args
			)
		);

		if ( empty( $recent_views ) ) {
			return $result;
		}

		$result['viewed_products'] = array_map( 'intval', $recent_views );

		// Extract categories from recently viewed products.
		_prime_post_caches( $result['viewed_products'], true );
		update_meta_cache( 'post', $result['viewed_products'] );

		foreach ( $result['viewed_products'] as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			foreach ( $product->get_category_ids() as $cat_id ) {
				$result['preferred_categories'][ $cat_id ] = ( $result['preferred_categories'][ $cat_id ] ?? 0 ) + 1;
			}
		}

		return $result;
	}

	/**
	 * Re-score results based on user profile.
	 *
	 * @param array $results Results from engines.
	 * @param array $profile User profile.
	 * @return array
	 */
	private function personalize_scores( array $results, array $profile ): array {
		$preferred_cats = $profile['preferred_categories'] ?? array();
		$price_range    = $profile['preferred_price_range'] ?? array( 'min' => 0, 'max' => 0 );
		$viewed         = $profile['viewed_products'] ?? array();
		$purchased      = $profile['purchased_products'] ?? array();

		foreach ( $results as &$result ) {
			$pid     = $result['product_id'];
			$product = wc_get_product( $pid );

			if ( ! $product ) {
				$result['score'] = 0;
				continue;
			}

			$score = $result['score'];

			// Boost: product in preferred category.
			if ( ! empty( $preferred_cats ) ) {
				$product_cats = $product->get_category_ids();
				foreach ( $product_cats as $cat_id ) {
					if ( isset( $preferred_cats[ $cat_id ] ) ) {
						$score *= 1.3;
						break;
					}
				}
			}

			// Boost: product in preferred price range.
			if ( $price_range['min'] > 0 || $price_range['max'] > 0 ) {
				$product_price = (float) $product->get_price();
				if ( $product_price >= $price_range['min'] && $product_price <= $price_range['max'] ) {
					$score *= 1.2;
				}
			}

			// Penalty: already viewed but not purchased (variety boost).
			if ( in_array( $pid, $viewed, true ) && ! in_array( $pid, $purchased, true ) ) {
				$score *= 0.7;
			}

			// Strong penalty: already purchased.
			if ( in_array( $pid, $purchased, true ) ) {
				$score *= 0.3;
			}

			$result['score'] = apply_filters( 'smartrec_product_score', $score, $pid, $this->getId() );
		}

		return $results;
	}

	/**
	 * Merge results, keeping highest score per product.
	 *
	 * @param array $results All results.
	 * @return array
	 */
	private function merge_results( array $results ): array {
		$merged = array();

		foreach ( $results as $result ) {
			$pid = $result['product_id'];
			if ( ! isset( $merged[ $pid ] ) || $result['score'] > $merged[ $pid ]['score'] ) {
				$merged[ $pid ] = $result;
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
	 * Apply diversity filter — max N products per category.
	 *
	 * @param array $results         Sorted results.
	 * @param int   $maxPerCategory Max products per category.
	 * @return array
	 */
	private function apply_diversity_filter( array $results, int $maxPerCategory ): array {
		$category_counts = array();
		$filtered        = array();

		foreach ( $results as $result ) {
			$product = wc_get_product( $result['product_id'] );
			if ( ! $product ) {
				continue;
			}

			$categories = $product->get_category_ids();
			$allowed    = true;

			foreach ( $categories as $cat_id ) {
				if ( isset( $category_counts[ $cat_id ] ) && $category_counts[ $cat_id ] >= $maxPerCategory ) {
					$allowed = false;
					break;
				}
			}

			if ( $allowed ) {
				$filtered[] = $result;
				foreach ( $categories as $cat_id ) {
					$category_counts[ $cat_id ] = ( $category_counts[ $cat_id ] ?? 0 ) + 1;
				}
			}
		}

		return $filtered;
	}
}
