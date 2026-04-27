<?php
/**
 * Bought Together engine — co-purchase frequency analysis.
 *
 * @package SmartRec\Engines
 */

namespace SmartRec\Engines;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class BoughtTogether
 *
 * Recommends products frequently purchased together based on pre-computed relationships.
 */
class BoughtTogether implements RecommendationEngineInterface {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getId(): string {
		return 'bought_together';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return __( 'Frequently Bought Together', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDescription(): string {
		return __( 'Recommends products that are frequently purchased together.', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable(): bool {
		return (bool) $this->settings->get( 'engine_bought_together_enabled', true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecommendations( int $productId, int $userId, string $sessionId, array $args = array() ): array {
		// If no product specified (e.g. homepage), use last viewed product.
		if ( $productId <= 0 && ! empty( $sessionId ) ) {
			$productId = $this->get_last_viewed_product( $userId, $sessionId );
		}

		// Collect product IDs: single product or all cart products.
		$seed_ids = array();
		if ( $productId > 0 ) {
			$seed_ids[] = $productId;
		}

		// If on cart/checkout, include all cart product IDs for better results.
		if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( ! empty( $item['product_id'] ) ) {
					$seed_ids[] = (int) $item['product_id'];
				}
			}
		}

		$seed_ids = array_unique( array_filter( $seed_ids ) );

		if ( empty( $seed_ids ) ) {
			return array();
		}

		$limit   = $args['limit'] ?? $this->getDefaultLimit();
		$exclude = $args['exclude'] ?? array();
		$exclude = array_merge( $exclude, $seed_ids );

		global $wpdb;

		// Query pre-computed relationships for ALL seed products.
		$seed_placeholders = implode( ',', array_fill( 0, count( $seed_ids ), '%d' ) );
		$exclude_ids = array_unique( array_map( 'absint', $exclude ) );
		$exclude_placeholders = implode( ',', array_fill( 0, count( $exclude_ids ), '%d' ) );

		$query_args = array_merge( $seed_ids, $exclude_ids );
		$query_args[] = $limit * 2;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pr.related_product_id AS product_id, MAX(pr.score) AS score, SUM(pr.occurrences) AS occurrences
				FROM {$wpdb->prefix}smartrec_product_relationships pr
				INNER JOIN {$wpdb->posts} p ON p.ID = pr.related_product_id AND p.post_status = 'publish'
				WHERE pr.product_id IN ({$seed_placeholders})
				AND pr.relationship_type = 'bought_together'
				AND pr.related_product_id NOT IN ({$exclude_placeholders})
				GROUP BY pr.related_product_id
				ORDER BY score DESC
				LIMIT %d",
				$query_args
			)
		);

		if ( ! empty( $results ) ) {
			$rec_ids = array_map( function ( $r ) { return (int) $r->product_id; }, $results );
			_prime_post_caches( $rec_ids, true );
			update_meta_cache( 'post', $rec_ids );
		}

		$recommendations = array();
		foreach ( $results as $row ) {
			$product = wc_get_product( (int) $row->product_id );
			if ( ! $product || ! $product->is_in_stock() ) {
				continue;
			}

			$recommendations[] = array(
				'product_id' => (int) $row->product_id,
				'score'      => (float) $row->score,
				'reason'     => __( 'Frequently bought together', 'smartrec' ),
			);

			if ( count( $recommendations ) >= $limit ) {
				break;
			}
		}

		// Fallback: if insufficient results, get popular products in same category.
		$primary_id = $seed_ids[0] ?? 0;
		if ( count( $recommendations ) < $limit && $primary_id > 0 ) {
			$recommendations = $this->supplement_with_fallback( $primary_id, $recommendations, $exclude, $limit );
		}

		return apply_filters( 'smartrec_engine_results', $recommendations, $this->getId(), $primary_id );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDefaultLimit(): int {
		return 6;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPriority(): int {
		return (int) $this->settings->get( 'engine_bought_together_priority', 8 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMinimumDataRequirements(): array {
		return array(
			'description'     => __( 'At least 10 orders with 2+ items each.', 'smartrec' ),
			'min_orders'      => 10,
			'min_items_per_order' => 2,
		);
	}

	/**
	 * Supplement results with popular products from the same categories.
	 *
	 * @param int   $productId       Source product ID.
	 * @param array $recommendations Current recommendations.
	 * @param array $exclude         Product IDs to exclude.
	 * @param int   $limit           Target limit.
	 * @return array
	 */
	/**
	 * Get the last product viewed by this user/session.
	 *
	 * @param int    $userId    User ID.
	 * @param string $sessionId Session ID.
	 * @return int Product ID or 0.
	 */
	private function get_last_viewed_product( int $userId, string $sessionId ): int {
		global $wpdb;

		$where = '';
		$args  = array();

		if ( $userId > 0 ) {
			$where = 'WHERE (user_id = %d OR session_id = %s) AND event_type = %s';
			$args  = array( $userId, $sessionId, 'view' );
		} else {
			$where = 'WHERE session_id = %s AND event_type = %s';
			$args  = array( $sessionId, 'view' );
		}

		$args[] = 1;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT product_id FROM {$wpdb->prefix}smartrec_events {$where} ORDER BY created_at DESC LIMIT %d",
				$args
			)
		);
	}

	private function supplement_with_fallback( int $productId, array $recommendations, array $exclude, int $limit ): array {
		$existing_ids = array_column( $recommendations, 'product_id' );
		$all_exclude  = array_unique( array_merge( $exclude, $existing_ids ) );
		$needed       = $limit - count( $recommendations );

		if ( $needed <= 0 ) {
			return $recommendations;
		}

		// Try same-category products first.
		$product    = wc_get_product( $productId );
		$categories = $product ? $product->get_category_ids() : array();

		if ( ! empty( $categories ) ) {
			$fallbacks = wc_get_products(
				array(
					'status'       => 'publish',
					'limit'        => $needed * 3,
					'category'     => array_map( 'strval', $categories ),
					'exclude'      => $all_exclude,
					'orderby'      => 'rand',
					'stock_status' => 'instock',
					'return'       => 'ids',
				)
			);

			if ( count( $fallbacks ) > $needed ) {
				shuffle( $fallbacks );
				$fallbacks = array_slice( $fallbacks, 0, $needed );
			}

			foreach ( $fallbacks as $fid ) {
				$recommendations[] = array(
					'product_id' => $fid,
					'score'      => 0.1,
					'reason'     => __( 'Popular in this category', 'smartrec' ),
				);
			}

			$all_exclude = array_merge( $all_exclude, $fallbacks );
			$needed      = $limit - count( $recommendations );
		}

		// If still not enough, get from ANY category.
		if ( $needed > 0 ) {
			$any_products = wc_get_products(
				array(
					'status'       => 'publish',
					'limit'        => $needed * 2,
					'exclude'      => $all_exclude,
					'orderby'      => 'rand',
					'stock_status' => 'instock',
					'return'       => 'ids',
				)
			);

			if ( count( $any_products ) > $needed ) {
				$any_products = array_slice( $any_products, 0, $needed );
			}

			foreach ( $any_products as $fid ) {
				$recommendations[] = array(
					'product_id' => $fid,
					'score'      => 0.05,
					'reason'     => __( 'You might also like', 'smartrec' ),
				);
			}
		}

		return $recommendations;
	}
}
