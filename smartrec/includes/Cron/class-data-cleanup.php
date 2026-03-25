<?php
/**
 * Data cleanup cron job.
 *
 * @package SmartRec\Cron
 */

namespace SmartRec\Cron;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class DataCleanup
 *
 * Purges old tracking data, orphaned relationships, and expired profiles.
 */
class DataCleanup {

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
	 * Run all cleanup tasks.
	 *
	 * @return array Summary of cleanup operations.
	 */
	public function cleanup(): array {
		$results = array(
			'events_deleted'       => $this->cleanup_old_events(),
			'relationships_cleaned' => $this->cleanup_orphaned_relationships(),
			'profiles_cleaned'     => $this->cleanup_expired_profiles(),
		);

		return $results;
	}

	/**
	 * Delete events older than retention period.
	 *
	 * @return int Number of deleted events.
	 */
	private function cleanup_old_events(): int {
		global $wpdb;

		$retention_days = (int) $this->settings->get( 'data_retention_days', 90 );
		$cutoff_date    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}smartrec_events WHERE created_at < %s LIMIT 10000",
				$cutoff_date
			)
		);

		return (int) $deleted;
	}

	/**
	 * Delete relationships for products that no longer exist.
	 *
	 * @return int Number of deleted relationships.
	 */
	private function cleanup_orphaned_relationships(): int {
		global $wpdb;

		$deleted = $wpdb->query(
			"DELETE pr FROM {$wpdb->prefix}smartrec_product_relationships pr
			LEFT JOIN {$wpdb->posts} p1 ON p1.ID = pr.product_id AND p1.post_type = 'product'
			LEFT JOIN {$wpdb->posts} p2 ON p2.ID = pr.related_product_id AND p2.post_type = 'product'
			WHERE p1.ID IS NULL OR p2.ID IS NULL
			LIMIT 5000"
		);

		return (int) $deleted;
	}

	/**
	 * Delete user profiles with no activity in 90 days.
	 *
	 * @return int Number of deleted profiles.
	 */
	private function cleanup_expired_profiles(): int {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}smartrec_user_profiles WHERE last_active < %s LIMIT 5000",
				$cutoff
			)
		);

		return (int) $deleted;
	}
}
