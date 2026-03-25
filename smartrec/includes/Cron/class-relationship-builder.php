<?php
/**
 * Builds product relationship scores via cron.
 *
 * @package SmartRec\Cron
 */

namespace SmartRec\Cron;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class RelationshipBuilder
 *
 * Computes co-purchase and co-view relationships, trending scores, and user profiles.
 */
class RelationshipBuilder {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Max runtime in seconds.
	 *
	 * @var int
	 */
	private $max_runtime;

	/**
	 * Start time.
	 *
	 * @var float
	 */
	private $start_time;

	/**
	 * Batch size.
	 *
	 * @var int
	 */
	private $batch_size;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings    = $settings;
		$this->max_runtime = (int) $settings->get( 'cron_max_runtime', 120 );
		$this->batch_size  = (int) $settings->get( 'cron_batch_size', 500 );
	}

	/**
	 * Build all relationships.
	 *
	 * @return array Summary of processed records.
	 */
	public function build_all(): array {
		$this->start_time = microtime( true );

		$results = array(
			'bought_together' => 0,
			'viewed_together' => 0,
			'trending_scores' => 0,
			'user_profiles'   => 0,
		);

		// 1. Co-purchase relationships.
		if ( ! $this->is_time_exceeded() ) {
			$results['bought_together'] = $this->build_co_purchase_relationships();
		}

		// 2. Co-view relationships.
		if ( ! $this->is_time_exceeded() ) {
			$results['viewed_together'] = $this->build_co_view_relationships();
		}

		// 3. Trending scores.
		if ( ! $this->is_time_exceeded() ) {
			$results['trending_scores'] = $this->build_trending_scores();
		}

		// 4. User profiles.
		if ( ! $this->is_time_exceeded() ) {
			$results['user_profiles'] = $this->build_user_profiles();
		}

		return $results;
	}

	/**
	 * Build co-purchase relationships from order data.
	 *
	 * @return int Number of relationships created/updated.
	 */
	public function build_co_purchase_relationships(): int {
		global $wpdb;

		$lookback_days = (int) $this->settings->get( 'engine_bought_together_lookback', 90 );
		$min_count     = (int) $this->settings->get( 'engine_bought_together_min_count', 2 );
		$since         = gmdate( 'Y-m-d H:i:s', strtotime( "-{$lookback_days} days" ) );

		$offset    = (int) $this->settings->get( 'cron_copurchase_offset', 0 );
		$processed = 0;

		// Get purchase events grouped by order (using session + short time window as proxy).
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT session_id
				FROM {$wpdb->prefix}smartrec_events
				WHERE event_type = 'purchase' AND created_at >= %s
				ORDER BY session_id
				LIMIT %d OFFSET %d",
				$since,
				$this->batch_size,
				$offset
			),
			ARRAY_A
		);

		if ( empty( $sessions ) ) {
			// Reset offset for next run.
			$this->settings->set( 'cron_copurchase_offset', 0 );
			return 0;
		}

		$pairs = array();

		foreach ( $sessions as $session ) {
			if ( $this->is_time_exceeded() ) {
				break;
			}

			$products = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT product_id
					FROM {$wpdb->prefix}smartrec_events
					WHERE session_id = %s AND event_type = 'purchase' AND created_at >= %s",
					$session['session_id'],
					$since
				)
			);

			if ( count( $products ) < 2 ) {
				continue;
			}

			// Create all product pairs.
			$count = count( $products );
			for ( $i = 0; $i < $count; $i++ ) {
				for ( $j = $i + 1; $j < $count; $j++ ) {
					$a = (int) $products[ $i ];
					$b = (int) $products[ $j ];

					$key = min( $a, $b ) . '_' . max( $a, $b );
					if ( ! isset( $pairs[ $key ] ) ) {
						$pairs[ $key ] = array(
							'a'     => min( $a, $b ),
							'b'     => max( $a, $b ),
							'count' => 0,
						);
					}
					++$pairs[ $key ]['count'];
				}
			}
		}

		// Store relationships.
		$total_sessions = count( $sessions );
		foreach ( $pairs as $pair ) {
			if ( $pair['count'] < $min_count ) {
				continue;
			}

			$support    = $pair['count'] / max( 1, $total_sessions );
			$confidence = min( 1.0, $pair['count'] / max( 1, $total_sessions ) );
			$score      = round( 0.4 * $confidence + 0.4 * $support + 0.2 * min( 1.0, $pair['count'] / 10 ), 6 );

			$this->upsert_relationship( $pair['a'], $pair['b'], 'bought_together', $score, $pair['count'] );
			$this->upsert_relationship( $pair['b'], $pair['a'], 'bought_together', $score, $pair['count'] );
			$processed += 2;
		}

		// Update offset.
		$this->settings->set( 'cron_copurchase_offset', $offset + $this->batch_size );

		do_action( 'smartrec_relationships_built', 'bought_together', $processed );

		return $processed;
	}

	/**
	 * Build co-view relationships from session data.
	 *
	 * @return int Number of relationships created/updated.
	 */
	public function build_co_view_relationships(): int {
		global $wpdb;

		$lookback_days = (int) $this->settings->get( 'engine_viewed_together_lookback', 30 );
		$min_count     = (int) $this->settings->get( 'engine_viewed_together_min_count', 3 );
		$since         = gmdate( 'Y-m-d H:i:s', strtotime( "-{$lookback_days} days" ) );

		$offset    = (int) $this->settings->get( 'cron_coview_offset', 0 );
		$processed = 0;

		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT session_id
				FROM {$wpdb->prefix}smartrec_events
				WHERE event_type = 'view' AND created_at >= %s
				ORDER BY session_id
				LIMIT %d OFFSET %d",
				$since,
				$this->batch_size,
				$offset
			),
			ARRAY_A
		);

		if ( empty( $sessions ) ) {
			$this->settings->set( 'cron_coview_offset', 0 );
			return 0;
		}

		$pairs = array();

		foreach ( $sessions as $session ) {
			if ( $this->is_time_exceeded() ) {
				break;
			}

			// Get views within 10-minute windows.
			$products = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT product_id
					FROM {$wpdb->prefix}smartrec_events
					WHERE session_id = %s AND event_type = 'view' AND created_at >= %s",
					$session['session_id'],
					$since
				)
			);

			if ( count( $products ) < 2 ) {
				continue;
			}

			$count = count( $products );
			for ( $i = 0; $i < $count; $i++ ) {
				for ( $j = $i + 1; $j < $count; $j++ ) {
					$a   = (int) $products[ $i ];
					$b   = (int) $products[ $j ];
					$key = min( $a, $b ) . '_' . max( $a, $b );

					if ( ! isset( $pairs[ $key ] ) ) {
						$pairs[ $key ] = array(
							'a'     => min( $a, $b ),
							'b'     => max( $a, $b ),
							'count' => 0,
						);
					}
					++$pairs[ $key ]['count'];
				}
			}
		}

		foreach ( $pairs as $pair ) {
			if ( $pair['count'] < $min_count ) {
				continue;
			}

			$score = round( min( 1.0, $pair['count'] / 20 ), 6 );

			$this->upsert_relationship( $pair['a'], $pair['b'], 'viewed_together', $score, $pair['count'] );
			$this->upsert_relationship( $pair['b'], $pair['a'], 'viewed_together', $score, $pair['count'] );
			$processed += 2;
		}

		$this->settings->set( 'cron_coview_offset', $offset + $this->batch_size );

		do_action( 'smartrec_relationships_built', 'viewed_together', $processed );

		return $processed;
	}

	/**
	 * Build trending scores.
	 *
	 * @return int Number of products scored.
	 */
	public function build_trending_scores(): int {
		global $wpdb;

		$weight_24h = (int) $this->settings->get( 'engine_trending_weight_24h', 10 );
		$weight_7d  = (int) $this->settings->get( 'engine_trending_weight_7d', 3 );
		$weight_30d = (int) $this->settings->get( 'engine_trending_weight_30d', 1 );

		$now    = current_time( 'mysql' );
		$ago24h = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
		$ago7d  = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$ago30d = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		// Get all products with events in last 30 days.
		$products = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT product_id
				FROM {$wpdb->prefix}smartrec_events
				WHERE created_at >= %s
				LIMIT %d",
				$ago30d,
				$this->batch_size
			)
		);

		$processed = 0;

		foreach ( $products as $product_id ) {
			if ( $this->is_time_exceeded() ) {
				break;
			}

			$stats = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						SUM(CASE WHEN event_type = 'view' AND created_at >= %s THEN 1 ELSE 0 END) as views_24h,
						SUM(CASE WHEN event_type = 'view' AND created_at >= %s THEN 1 ELSE 0 END) as views_7d,
						SUM(CASE WHEN event_type = 'view' AND created_at >= %s THEN 1 ELSE 0 END) as views_30d,
						SUM(CASE WHEN event_type = 'purchase' AND created_at >= %s THEN 1 ELSE 0 END) as purchases_24h,
						SUM(CASE WHEN event_type = 'purchase' AND created_at >= %s THEN 1 ELSE 0 END) as purchases_7d,
						SUM(CASE WHEN event_type = 'purchase' AND created_at >= %s THEN 1 ELSE 0 END) as purchases_30d,
						SUM(CASE WHEN event_type = 'cart_add' AND created_at >= %s THEN 1 ELSE 0 END) as cart_adds_7d
					FROM {$wpdb->prefix}smartrec_events
					WHERE product_id = %d AND created_at >= %s",
					$ago24h,
					$ago7d,
					$ago30d,
					$ago24h,
					$ago7d,
					$ago30d,
					$ago7d,
					$product_id,
					$ago30d
				),
				ARRAY_A
			);

			if ( ! $stats ) {
				continue;
			}

			$trending_score = ( (int) $stats['views_24h'] * $weight_24h )
				+ ( (int) $stats['views_7d'] * $weight_7d )
				+ ( (int) $stats['views_30d'] * $weight_30d )
				+ ( (int) $stats['purchases_24h'] * 50 )
				+ ( (int) $stats['purchases_7d'] * 15 )
				+ ( (int) $stats['purchases_30d'] * 5 )
				+ ( (int) $stats['cart_adds_7d'] * 8 );

			$trending_score = apply_filters( 'smartrec_trending_formula', $trending_score, $stats );

			$views_30d      = max( 1, (int) $stats['views_30d'] );
			$conversion_rate = (int) $stats['views_30d'] >= 10
				? round( (int) $stats['purchases_30d'] / $views_30d, 4 )
				: 0.0;

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}smartrec_product_scores
					(product_id, views_24h, views_7d, views_30d, purchases_24h, purchases_7d, purchases_30d, cart_adds_7d, trending_score, conversion_rate, last_updated)
					VALUES (%d, %d, %d, %d, %d, %d, %d, %d, %f, %f, %s)
					ON DUPLICATE KEY UPDATE
					views_24h = VALUES(views_24h), views_7d = VALUES(views_7d), views_30d = VALUES(views_30d),
					purchases_24h = VALUES(purchases_24h), purchases_7d = VALUES(purchases_7d), purchases_30d = VALUES(purchases_30d),
					cart_adds_7d = VALUES(cart_adds_7d), trending_score = VALUES(trending_score),
					conversion_rate = VALUES(conversion_rate), last_updated = VALUES(last_updated)",
					$product_id,
					(int) $stats['views_24h'],
					(int) $stats['views_7d'],
					(int) $stats['views_30d'],
					(int) $stats['purchases_24h'],
					(int) $stats['purchases_7d'],
					(int) $stats['purchases_30d'],
					(int) $stats['cart_adds_7d'],
					$trending_score,
					$conversion_rate,
					$now
				)
			);

			++$processed;
		}

		// Normalize trending scores to 0-1 range.
		if ( $processed > 0 ) {
			$max_score = (float) $wpdb->get_var(
				"SELECT MAX(trending_score) FROM {$wpdb->prefix}smartrec_product_scores"
			);

			if ( $max_score > 0 ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}smartrec_product_scores SET trending_score = trending_score / %f",
						$max_score
					)
				);
			}
		}

		return $processed;
	}

	/**
	 * Build user profiles from event history.
	 *
	 * @return int Number of profiles updated.
	 */
	public function build_user_profiles(): int {
		global $wpdb;

		$processed = 0;

		// Get active users/sessions from last 30 days.
		$users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, session_id, COUNT(*) as event_count
				FROM {$wpdb->prefix}smartrec_events
				WHERE created_at >= %s AND user_id > 0
				GROUP BY user_id
				ORDER BY event_count DESC
				LIMIT %d",
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
				$this->batch_size
			),
			ARRAY_A
		);

		foreach ( $users as $user_data ) {
			if ( $this->is_time_exceeded() ) {
				break;
			}

			$user_id    = (int) $user_data['user_id'];
			$session_id = $user_data['session_id'];

			// Get viewed products.
			$viewed = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT product_id FROM {$wpdb->prefix}smartrec_events
					WHERE user_id = %d AND event_type = 'view'
					ORDER BY created_at DESC LIMIT 50",
					$user_id
				)
			);

			// Get purchased products.
			$purchased = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT product_id FROM {$wpdb->prefix}smartrec_events
					WHERE user_id = %d AND event_type = 'purchase'
					ORDER BY created_at DESC LIMIT 100",
					$user_id
				)
			);

			// Calculate preferred categories.
			$all_products    = array_unique( array_merge( $viewed, $purchased ) );
			$category_counts = array();
			foreach ( $all_products as $pid ) {
				$product = wc_get_product( (int) $pid );
				if ( ! $product ) {
					continue;
				}
				foreach ( $product->get_category_ids() as $cat_id ) {
					$weight = in_array( $pid, $purchased, true ) ? 2 : 1;
					$category_counts[ $cat_id ] = ( $category_counts[ $cat_id ] ?? 0 ) + $weight;
				}
			}

			// Calculate preferred price range.
			$prices = array();
			foreach ( array_slice( $all_products, 0, 20 ) as $pid ) {
				$product = wc_get_product( (int) $pid );
				if ( $product ) {
					$price = (float) $product->get_price();
					if ( $price > 0 ) {
						$prices[] = $price;
					}
				}
			}

			$price_range = '';
			if ( ! empty( $prices ) ) {
				sort( $prices );
				$price_range = round( $prices[0], 2 ) . '-' . round( end( $prices ), 2 );
			}

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}smartrec_user_profiles
					(user_id, session_id, preferred_categories, viewed_products, purchased_products, preferred_price_range, last_active)
					VALUES (%d, %s, %s, %s, %s, %s, %s)
					ON DUPLICATE KEY UPDATE
					session_id = VALUES(session_id),
					preferred_categories = VALUES(preferred_categories),
					viewed_products = VALUES(viewed_products),
					purchased_products = VALUES(purchased_products),
					preferred_price_range = VALUES(preferred_price_range),
					last_active = VALUES(last_active)",
					$user_id,
					$session_id,
					wp_json_encode( $category_counts ),
					wp_json_encode( array_map( 'intval', $viewed ) ),
					wp_json_encode( array_map( 'intval', $purchased ) ),
					$price_range,
					current_time( 'mysql' )
				)
			);

			++$processed;
		}

		return $processed;
	}

	/**
	 * Upsert a product relationship.
	 *
	 * @param int    $product_id         Product ID.
	 * @param int    $related_product_id Related product ID.
	 * @param string $type               Relationship type.
	 * @param float  $score              Score.
	 * @param int    $occurrences        Occurrence count.
	 * @return void
	 */
	private function upsert_relationship( int $product_id, int $related_product_id, string $type, float $score, int $occurrences ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}smartrec_product_relationships
				(product_id, related_product_id, relationship_type, score, occurrences, last_updated)
				VALUES (%d, %d, %s, %f, %d, %s)
				ON DUPLICATE KEY UPDATE
				score = VALUES(score), occurrences = VALUES(occurrences), last_updated = VALUES(last_updated)",
				$product_id,
				$related_product_id,
				$type,
				$score,
				$occurrences,
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Check if max runtime has been exceeded.
	 *
	 * @return bool
	 */
	private function is_time_exceeded(): bool {
		return ( microtime( true ) - $this->start_time ) > $this->max_runtime;
	}
}
