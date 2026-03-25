<?php
/**
 * Plugin deactivation handler.
 *
 * @package SmartRec\Core
 */

namespace SmartRec\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivator
 *
 * Cleans up scheduled events on deactivation.
 */
class Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'smartrec_build_relationships' );
		wp_clear_scheduled_hook( 'smartrec_cache_warmer' );
		wp_clear_scheduled_hook( 'smartrec_data_cleanup' );

		do_action( 'smartrec_plugin_deactivated' );

		flush_rewrite_rules();
	}
}
