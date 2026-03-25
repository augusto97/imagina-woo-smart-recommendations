<?php
/**
 * Viewed Together engine — co-view session analysis.
 *
 * @package SmartRec\Engines
 */

namespace SmartRec\Engines;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class ViewedTogether
 *
 * Recommends products frequently viewed in the same session.
 */
class ViewedTogether implements RecommendationEngineInterface {

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
		return 'viewed_together';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return __( 'Viewed Together', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDescription(): string {
		return __( 'Recommends products frequently viewed together in the same session.', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable(): bool {
		return (bool) $this->settings->get( 'engine_viewed_together_enabled', true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecommendations( int $productId, int $userId, string $sessionId, array $args = array() ): array {
		if ( $productId <= 0 ) {
			return array();
		}

		$limit   = $args['limit'] ?? $this->getDefaultLimit();
		$exclude = $args['exclude'] ?? array();
		$exclude[] = $productId;

		global $wpdb;

		$exclude_ids = implode( ',', array_map( 'absint', $exclude ) );
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pr.related_product_id AS product_id, pr.score, pr.occurrences
				FROM {$wpdb->prefix}smartrec_product_relationships pr
				INNER JOIN {$wpdb->posts} p ON p.ID = pr.related_product_id AND p.post_status = 'publish'
				WHERE pr.product_id = %d
				AND pr.relationship_type = 'viewed_together'
				AND pr.related_product_id NOT IN ({$exclude_ids})
				ORDER BY pr.score DESC
				LIMIT %d",
				$productId,
				$limit
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
				'reason'     => __( 'Others also viewed', 'smartrec' ),
			);
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
		return (int) $this->settings->get( 'engine_viewed_together_priority', 4 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMinimumDataRequirements(): array {
		return array(
			'description' => __( 'At least 50 sessions with 2+ product views.', 'smartrec' ),
			'min_sessions' => 50,
			'min_views_per_session' => 2,
		);
	}
}
