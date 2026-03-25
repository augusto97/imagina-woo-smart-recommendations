<?php
/**
 * Cron job orchestrator.
 *
 * @package SmartRec\Cron
 */

namespace SmartRec\Cron;

use SmartRec\Core\Settings;
use SmartRec\Cache\CacheWarmer;

defined( 'ABSPATH' ) || exit;

/**
 * Class CronManager
 *
 * Registers and manages all scheduled cron jobs.
 */
class CronManager {

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

		// Register custom cron intervals.
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		// Register cron event handlers.
		add_action( 'smartrec_build_relationships', array( $this, 'run_relationship_builder' ) );
		add_action( 'smartrec_cache_warmer', array( $this, 'run_cache_warmer' ) );
		add_action( 'smartrec_data_cleanup', array( $this, 'run_data_cleanup' ) );
	}

	/**
	 * Add custom cron schedule intervals.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['six_hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 Hours', 'smartrec' ),
		);

		$schedules['twelve_hours'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 12 Hours', 'smartrec' ),
		);

		return $schedules;
	}

	/**
	 * Run relationship builder cron job.
	 *
	 * @return void
	 */
	public function run_relationship_builder() {
		$builder = new RelationshipBuilder( $this->settings );

		$start_time = microtime( true );

		if ( $this->settings->get( 'debug_mode', false ) ) {
			error_log( 'SmartRec: Starting relationship builder cron.' );
		}

		$results = $builder->build_all();

		$elapsed = round( microtime( true ) - $start_time, 2 );

		$this->settings->set( 'last_relationship_build', current_time( 'mysql' ) );
		$this->settings->set( 'last_relationship_build_stats', $results );

		if ( $this->settings->get( 'debug_mode', false ) ) {
			error_log( 'SmartRec: Relationship builder completed in ' . $elapsed . 's. Results: ' . wp_json_encode( $results ) );
		}
	}

	/**
	 * Run cache warmer cron job.
	 *
	 * @return void
	 */
	public function run_cache_warmer() {
		$warmer = new CacheWarmer( $this->settings );
		$result = $warmer->warm();

		$this->settings->set( 'last_cache_warm', current_time( 'mysql' ) );

		if ( $this->settings->get( 'debug_mode', false ) ) {
			error_log( 'SmartRec: Cache warmer completed. Results: ' . wp_json_encode( $result ) );
		}
	}

	/**
	 * Run data cleanup cron job.
	 *
	 * @return void
	 */
	public function run_data_cleanup() {
		$cleanup = new DataCleanup( $this->settings );
		$result  = $cleanup->cleanup();

		$this->settings->set( 'last_data_cleanup', current_time( 'mysql' ) );

		if ( $this->settings->get( 'debug_mode', false ) ) {
			error_log( 'SmartRec: Data cleanup completed. Results: ' . wp_json_encode( $result ) );
		}
	}
}
