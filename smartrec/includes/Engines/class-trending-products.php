<?php
/**
 * Trending Products engine — time-weighted popularity scoring.
 *
 * @package SmartRec\Engines
 */

namespace SmartRec\Engines;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class TrendingProducts
 *
 * Recommends products based on trending popularity scores.
 */
class TrendingProducts implements RecommendationEngineInterface {

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
		return 'trending';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return __( 'Trending Products', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDescription(): string {
		return __( 'Recommends currently trending and popular products.', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable(): bool {
		return (bool) $this->settings->get( 'engine_trending_enabled', true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecommendations( int $productId, int $userId, string $sessionId, array $args = array() ): array {
		$limit      = $args['limit'] ?? $this->getDefaultLimit();
		$exclude    = $args['exclude'] ?? array();
		$categoryId = $args['category_id'] ?? 0;

		if ( $productId > 0 ) {
			$exclude[] = $productId;
		}

		global $wpdb;

		$where   = "WHERE p.post_status = 'publish' AND p.post_type = 'product'";
		$params  = array();

		if ( ! empty( $exclude ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $exclude ), '%d' ) );
			$where       .= " AND ps.product_id NOT IN ({$placeholders})";
			$params       = array_merge( $params, $exclude );
		}

		// Filter by category if specified.
		$join = '';
		if ( $categoryId > 0 ) {
			$join   = "INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = ps.product_id
					   INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'";
			$where .= ' AND tt.term_id = %d';
			$params[] = $categoryId;
		}

		$params[] = $limit * 2;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ps.product_id, ps.trending_score, ps.conversion_rate
				FROM {$wpdb->prefix}smartrec_product_scores ps
				INNER JOIN {$wpdb->posts} p ON p.ID = ps.product_id
				{$join}
				{$where}
				ORDER BY ps.trending_score DESC
				LIMIT %d",
				$params
			),
			ARRAY_A
		);

		$recommendations = array();
		foreach ( $results as $row ) {
			$pid     = (int) $row['product_id'];
			$product = wc_get_product( $pid );
			if ( ! $product || ! $product->is_in_stock() ) {
				continue;
			}

			$recommendations[] = array(
				'product_id' => $pid,
				'score'      => (float) $row['trending_score'],
				'reason'     => __( 'Trending now', 'smartrec' ),
			);

			if ( count( $recommendations ) >= $limit ) {
				break;
			}
		}

		// Fallback: if no scored products, get best-selling products.
		if ( empty( $recommendations ) ) {
			$recommendations = $this->get_fallback_popular( $exclude, $limit );
		}

		return apply_filters( 'smartrec_engine_results', $recommendations, $this->getId(), $productId );
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
		return (int) $this->settings->get( 'engine_trending_priority', 6 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMinimumDataRequirements(): array {
		return array(
			'description' => __( 'At least 100 tracked events across products.', 'smartrec' ),
			'min_events'  => 100,
		);
	}

	/**
	 * Fallback: get popular products when no trending scores exist.
	 *
	 * @param array $exclude Exclude IDs.
	 * @param int   $limit   Limit.
	 * @return array
	 */
	private function get_fallback_popular( array $exclude, int $limit ): array {
		// Use direct WP_Query to avoid any wc_get_products limit overrides.
		$query = new \WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => max( $limit * 3, 30 ),
			'post__not_in'   => $exclude,
			'fields'         => 'ids',
			'orderby'        => 'rand',
			'meta_query'     => array(
				array(
					'key'     => '_stock_status',
					'value'   => 'instock',
					'compare' => '=',
				),
			),
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );

		$products = $query->posts;

		if ( count( $products ) > $limit ) {
			$products = array_slice( $products, 0, $limit );
		}

		$recommendations = array();
		foreach ( $products as $pid ) {
			$recommendations[] = array(
				'product_id' => $pid,
				'score'      => 0.5,
				'reason'     => __( 'Popular product', 'smartrec' ),
			);
		}

		return $recommendations;
	}
}
