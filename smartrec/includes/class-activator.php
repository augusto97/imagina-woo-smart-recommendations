<?php
/**
 * Plugin activation handler.
 *
 * @package SmartRec\Core
 */

namespace SmartRec\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 *
 * Creates database tables and sets default options on activation.
 */
class Activator {

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( SMARTREC_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'SmartRec requires WooCommerce to be installed and active.', 'smartrec' ),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		self::create_tables();
		self::set_default_options();
		self::schedule_cron_events();

		update_option( 'smartrec_version', SMARTREC_VERSION );
		update_option( 'smartrec_installed_at', current_time( 'mysql' ) );

		do_action( 'smartrec_plugin_activated' );

		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$tables = array();

		// Events table.
		$tables[] = "CREATE TABLE {$wpdb->prefix}smartrec_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED DEFAULT 0,
			event_type ENUM('view', 'cart_add', 'cart_remove', 'purchase', 'click', 'wishlist') NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			source_product_id BIGINT UNSIGNED DEFAULT 0 COMMENT 'Product that led to this event',
			quantity INT UNSIGNED DEFAULT 1,
			context VARCHAR(50) DEFAULT '' COMMENT 'page_type: product, category, cart, search',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_session (session_id, event_type),
			INDEX idx_product (product_id, event_type),
			INDEX idx_user (user_id, event_type),
			INDEX idx_created (created_at),
			INDEX idx_source_product (source_product_id, event_type)
		) ENGINE=InnoDB {$charset_collate};";

		// Product relationships table.
		$tables[] = "CREATE TABLE {$wpdb->prefix}smartrec_product_relationships (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			related_product_id BIGINT UNSIGNED NOT NULL,
			relationship_type ENUM('bought_together', 'viewed_together', 'similar', 'complementary') NOT NULL,
			score DECIMAL(10,6) NOT NULL DEFAULT 0.000000 COMMENT 'Relationship strength 0-1',
			occurrences INT UNSIGNED DEFAULT 0,
			last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uk_relationship (product_id, related_product_id, relationship_type),
			INDEX idx_product_type (product_id, relationship_type, score),
			INDEX idx_updated (last_updated)
		) ENGINE=InnoDB {$charset_collate};";

		// Product scores table.
		$tables[] = "CREATE TABLE {$wpdb->prefix}smartrec_product_scores (
			product_id BIGINT UNSIGNED NOT NULL,
			views_24h INT UNSIGNED DEFAULT 0,
			views_7d INT UNSIGNED DEFAULT 0,
			views_30d INT UNSIGNED DEFAULT 0,
			purchases_24h INT UNSIGNED DEFAULT 0,
			purchases_7d INT UNSIGNED DEFAULT 0,
			purchases_30d INT UNSIGNED DEFAULT 0,
			cart_adds_7d INT UNSIGNED DEFAULT 0,
			trending_score DECIMAL(10,6) DEFAULT 0.000000,
			conversion_rate DECIMAL(5,4) DEFAULT 0.0000,
			last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (product_id),
			INDEX idx_trending (trending_score),
			INDEX idx_updated (last_updated)
		) ENGINE=InnoDB {$charset_collate};";

		// User profiles table.
		$tables[] = "CREATE TABLE {$wpdb->prefix}smartrec_user_profiles (
			user_id BIGINT UNSIGNED NOT NULL,
			session_id VARCHAR(64) NOT NULL DEFAULT '',
			preferred_categories TEXT COMMENT 'JSON: {cat_id: weight}',
			preferred_attributes TEXT COMMENT 'JSON: {attr_name: {value: weight}}',
			preferred_price_range VARCHAR(50) DEFAULT '' COMMENT 'min-max',
			viewed_products TEXT COMMENT 'JSON: [product_id] last 50',
			purchased_products TEXT COMMENT 'JSON: [product_id] last 100',
			profile_vector TEXT COMMENT 'JSON: computed preference vector',
			last_active DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (user_id),
			INDEX idx_session (session_id),
			INDEX idx_active (last_active)
		) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private static function set_default_options() {
		$settings = new Settings();
		$defaults = array(
			'enabled'          => true,
			'tracking_enabled' => true,
			'cache_enabled'    => true,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( Settings::OPTION_PREFIX . $key ) ) {
				update_option( Settings::OPTION_PREFIX . $key, $value );
			}
		}
	}

	/**
	 * Schedule cron events.
	 *
	 * @return void
	 */
	private static function schedule_cron_events() {
		if ( ! wp_next_scheduled( 'smartrec_build_relationships' ) ) {
			wp_schedule_event( time(), 'six_hours', 'smartrec_build_relationships' );
		}
		if ( ! wp_next_scheduled( 'smartrec_cache_warmer' ) ) {
			wp_schedule_event( time(), 'hourly', 'smartrec_cache_warmer' );
		}
		if ( ! wp_next_scheduled( 'smartrec_data_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'smartrec_data_cleanup' );
		}
	}
}
