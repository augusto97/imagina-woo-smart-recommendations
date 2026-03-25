<?php
/**
 * Main plugin orchestrator (singleton).
 *
 * @package SmartRec\Core
 */

namespace SmartRec\Core;

use SmartRec\Tracking\Tracker;
use SmartRec\Engines\RecommendationManager;
use SmartRec\Display\Renderer;
use SmartRec\Display\WooCommerceHooks;
use SmartRec\Display\Shortcode;
use SmartRec\Display\Widget;
use SmartRec\Cache\CacheManager;
use SmartRec\API\RestAPI;
use SmartRec\Admin\AdminPage;
use SmartRec\Cron\CronManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 *
 * Singleton orchestrator that boots all plugin components.
 */
class Plugin {

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Tracker instance.
	 *
	 * @var Tracker
	 */
	private $tracker;

	/**
	 * Recommendation manager instance.
	 *
	 * @var RecommendationManager
	 */
	private $recommendation_manager;

	/**
	 * Cache manager instance.
	 *
	 * @var CacheManager
	 */
	private $cache_manager;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — boots all components.
	 */
	private function __construct() {
		$this->settings = new Settings();

		if ( ! $this->settings->get( 'enabled', true ) ) {
			return;
		}

		$this->cache_manager          = new CacheManager( $this->settings );
		$this->recommendation_manager = new RecommendationManager( $this->settings, $this->cache_manager );
		$this->tracker                = new Tracker( $this->settings );

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init_components' ) );
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'smartrec', false, dirname( SMARTREC_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Initialize all plugin components.
	 *
	 * @return void
	 */
	public function init_components() {
		// Display components.
		new Renderer( $this->recommendation_manager, $this->settings );
		new WooCommerceHooks( $this->recommendation_manager, $this->settings );
		new Shortcode( $this->recommendation_manager, $this->settings );

		// REST API.
		new RestAPI( $this->recommendation_manager, $this->tracker, $this->settings );

		// Cron.
		new CronManager( $this->settings );

		// Admin.
		if ( is_admin() ) {
			new AdminPage( $this->settings );
		}
	}

	/**
	 * Register WordPress widgets.
	 *
	 * @return void
	 */
	public function register_widgets() {
		register_widget( Widget::class );
	}

	/**
	 * Get settings instance.
	 *
	 * @return Settings
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Get recommendation manager instance.
	 *
	 * @return RecommendationManager
	 */
	public function get_recommendation_manager() {
		return $this->recommendation_manager;
	}

	/**
	 * Get tracker instance.
	 *
	 * @return Tracker
	 */
	public function get_tracker() {
		return $this->tracker;
	}

	/**
	 * Get cache manager instance.
	 *
	 * @return CacheManager
	 */
	public function get_cache_manager() {
		return $this->cache_manager;
	}
}
