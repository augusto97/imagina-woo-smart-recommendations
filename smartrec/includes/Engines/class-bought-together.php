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

		if ( $productId <= 0 ) {
			return array();
		}

		$limit   = $args['limit'] ?? $this->getDefaultLimit();
		$exclude = $args['exclude'] ?? array();
		$exclude[] = $productId;

		global $wpdb;

		// Query pre-computed relationships.
		$exclude_ids = implode( ',', array_map( 'absint', $exclude ) );
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pr.related_product_id AS product_id, pr.score, pr.occurrences
				FROM {$wpdb->prefix}smartrec_product_relationships pr
				INNER JOIN {$wpdb->posts} p ON p.ID = pr.related_product_id AND p.post_status = 'publish'
				WHERE pr.product_id = %d
				AND pr.relationship_type = 'bought_together'
				AND pr.related_product_id NOT IN ({$exclude_ids})
				ORDER BY pr.score DESC
				LIMIT %d",
				$productId,
				$limit * 2
			),
			ARRAY_A
		);

		$recommendations = array();
		foreach ( $results as $row ) {
			$product = wc_get_product( (int) $row['product_id'] );
			if ( ! $product || ! $product->is_in_stock() ) {
				continue;
			}

			$recommendations[] = array(
				'product_id' => (int) $row['product_id'],
				'score'      => (float) $row['score'],
				'reason'     => __( 'Frequently bought together', 'smartrec' ),
			);

			if ( count( $recommendations ) >= $limit ) {
				break;
			}
		}

		// Fallback: if insufficient results, get popular products in same category.
		if ( count( $recommendations ) < $limit ) {
			$recommendations = $this->supplement_with_fallback( $productId, $recommendations, $exclude, $limit );
		}

		return apply_filters( 'smartrec_engine_results', $recommendations, $this->getId(), $productId );
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
		$product = wc_get_product( $productId );
		if ( ! $product ) {
			return $recommendations;
		}

		$existing_ids = array_column( $recommendations, 'product_id' );
		$all_exclude  = array_merge( $exclude, $existing_ids );

		$categories = $product->get_category_ids();
		if ( empty( $categories ) ) {
			return $recommendations;
		}

		$needed    = $limit - count( $recommendations );
		$fallbacks = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => $needed,
				'category' => array_map( 'strval', $categories ),
				'exclude'  => $all_exclude,
				'orderby'  => 'popularity',
				'order'    => 'DESC',
				'stock_status' => 'instock',
				'return'   => 'ids',
			)
		);

		foreach ( $fallbacks as $fallback_id ) {
			$recommendations[] = array(
				'product_id' => $fallback_id,
				'score'      => 0.1,
				'reason'     => __( 'Popular in this category', 'smartrec' ),
			);
		}

		return $recommendations;
	}
}
