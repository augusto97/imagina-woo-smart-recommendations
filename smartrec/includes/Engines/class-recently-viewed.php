<?php
/**
 * Recently Viewed engine — session-based recent views.
 *
 * @package SmartRec\Engines
 */

namespace SmartRec\Engines;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class RecentlyViewed
 *
 * Returns products recently viewed by the current visitor.
 */
class RecentlyViewed implements RecommendationEngineInterface {

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
		return 'recently_viewed';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return __( 'Recently Viewed', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDescription(): string {
		return __( 'Shows products the visitor has recently viewed.', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable(): bool {
		return (bool) $this->settings->get( 'engine_recently_viewed_enabled', true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecommendations( int $productId, int $userId, string $sessionId, array $args = array() ): array {
		$limit   = $args['limit'] ?? $this->getDefaultLimit();
		$exclude = $args['exclude'] ?? array();

		if ( $productId > 0 ) {
			$exclude[] = $productId;
		}

		global $wpdb;

		// Get views for current session or user.
		$where_clause = '';
		$where_args   = array();

		if ( $userId > 0 ) {
			$where_clause = 'WHERE (user_id = %d OR session_id = %s) AND event_type = %s';
			$where_args   = array( $userId, $sessionId, 'view' );
		} elseif ( ! empty( $sessionId ) ) {
			$where_clause = 'WHERE session_id = %s AND event_type = %s';
			$where_args   = array( $sessionId, 'view' );
		} else {
			return array();
		}

		$where_args[] = $limit * 3;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT product_id, MAX(created_at) AS last_viewed
				FROM {$wpdb->prefix}smartrec_events
				{$where_clause}
				GROUP BY product_id
				ORDER BY last_viewed DESC
				LIMIT %d",
				$where_args
			),
			ARRAY_A
		);

		$recommendations = array();
		$position        = 0;

		foreach ( $results as $row ) {
			$pid = (int) $row['product_id'];
			if ( in_array( $pid, $exclude, true ) ) {
				continue;
			}

			$product = wc_get_product( $pid );
			if ( ! $product || ! $product->is_in_stock() || 'publish' !== $product->get_status() ) {
				continue;
			}

			++$position;
			$recommendations[] = array(
				'product_id' => $pid,
				'score'      => max( 0.0, 1.0 - ( $position * 0.05 ) ),
				'reason'     => __( 'Recently viewed', 'smartrec' ),
			);

			if ( count( $recommendations ) >= $limit ) {
				break;
			}
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
		return (int) $this->settings->get( 'engine_recently_viewed_priority', 3 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMinimumDataRequirements(): array {
		return array(
			'description' => __( 'At least 1 product view in the current session.', 'smartrec' ),
			'min_views'   => 1,
		);
	}
}
