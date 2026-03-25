<?php
/**
 * Cache orchestration.
 *
 * @package SmartRec\Cache
 */

namespace SmartRec\Cache;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class CacheManager
 *
 * Manages caching of recommendation results using transients or object cache.
 */
class CacheManager {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Cache prefix.
	 *
	 * @var string
	 */
	const PREFIX = 'smartrec_cache_';

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached value or false if not found.
	 */
	public function get( string $key ) {
		if ( ! $this->settings->get( 'cache_enabled', true ) ) {
			return false;
		}

		$full_key = self::PREFIX . $key;

		// Try object cache first if available.
		if ( wp_using_ext_object_cache() ) {
			$value = wp_cache_get( $full_key, 'smartrec' );
			if ( false !== $value ) {
				return $value;
			}
		}

		return get_transient( $full_key );
	}

	/**
	 * Set cached value.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl   Time to live in seconds.
	 * @return bool
	 */
	public function set( string $key, $value, int $ttl = 3600 ): bool {
		if ( ! $this->settings->get( 'cache_enabled', true ) ) {
			return false;
		}

		$full_key = self::PREFIX . $key;

		// Use object cache if available.
		if ( wp_using_ext_object_cache() ) {
			wp_cache_set( $full_key, $value, 'smartrec', $ttl );
		}

		return set_transient( $full_key, $value, $ttl );
	}

	/**
	 * Delete cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function delete( string $key ): bool {
		$full_key = self::PREFIX . $key;

		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $full_key, 'smartrec' );
		}

		return delete_transient( $full_key );
	}

	/**
	 * Clear all SmartRec caches.
	 *
	 * @return int Number of entries cleared.
	 */
	public function clear_all(): int {
		global $wpdb;

		// Delete all transients with our prefix.
		$count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . self::PREFIX . '%',
				'_transient_timeout_' . self::PREFIX . '%'
			)
		);

		// Flush object cache group if possible.
		if ( wp_using_ext_object_cache() && function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'smartrec' );
		}

		do_action( 'smartrec_cache_cleared' );

		return (int) $count;
	}

	/**
	 * Invalidate cache for a specific product.
	 *
	 * @param int $productId Product ID.
	 * @return int Number of entries cleared.
	 */
	public function invalidate_product( int $productId ): int {
		global $wpdb;

		$pattern = self::PREFIX . '%_' . $productId . '_%';

		$count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . $pattern,
				'_transient_timeout_' . $pattern
			)
		);

		return (int) $count;
	}
}
