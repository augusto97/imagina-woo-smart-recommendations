<?php
/**
 * Cache warmer — pre-computes recommendations via cron.
 *
 * @package SmartRec\Cache
 */

namespace SmartRec\Cache;

use SmartRec\Core\Settings;
use SmartRec\Engines\RecommendationManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class CacheWarmer
 *
 * Pre-computes and caches recommendations for top products.
 */
class CacheWarmer {

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
	 * Run cache warming process.
	 *
	 * @return array Summary of warmed entries.
	 */
	public function warm(): array {
		if ( ! $this->settings->get( 'cache_warmer_enabled', true ) ) {
			return array( 'status' => 'disabled' );
		}

		$start_time = microtime( true );
		$warmed     = 0;

		// Get top viewed products.
		$product_ids = $this->get_top_products( 100 );

		$manager = new RecommendationManager( $this->settings, new CacheManager( $this->settings ) );

		$locations = array( 'single_product_below', 'single_product_tabs' );

		foreach ( $product_ids as $product_id ) {
			foreach ( $locations as $location ) {
				try {
					$manager->getRecommendations( $location, $product_id );
					++$warmed;
				} catch ( \Exception $e ) {
					if ( $this->settings->get( 'debug_mode', false ) ) {
						error_log( 'SmartRec CacheWarmer: ' . $e->getMessage() );
					}
				}
			}

			// Check runtime limit.
			$elapsed = microtime( true ) - $start_time;
			if ( $elapsed > 60 ) {
				break;
			}
		}

		return array(
			'status' => 'completed',
			'warmed' => $warmed,
			'time'   => round( microtime( true ) - $start_time, 2 ),
		);
	}

	/**
	 * Get top viewed product IDs.
	 *
	 * @param int $limit Max products.
	 * @return array
	 */
	private function get_top_products( int $limit ): array {
		global $wpdb;

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT product_id
				FROM {$wpdb->prefix}smartrec_product_scores
				ORDER BY trending_score DESC
				LIMIT %d",
				$limit
			)
		);

		// Fallback: get popular WC products.
		if ( empty( $results ) ) {
			$results = wc_get_products(
				array(
					'status'  => 'publish',
					'limit'   => $limit,
					'orderby' => 'popularity',
					'order'   => 'DESC',
					'return'  => 'ids',
				)
			);
		}

		return array_map( 'absint', $results );
	}
}
