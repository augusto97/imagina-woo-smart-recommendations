<?php
/**
 * Plugin Name: SmartRec — Intelligent Product Recommendations for WooCommerce
 * Plugin URI:  https://github.com/augusto97/imagina-woo-smart-recommendations
 * Description: Advanced, behavior-driven product recommendation engine running entirely within WordPress.
 * Version:     1.6.0
 * Author:      SmartRec
 * Author URI:  https://github.com/augusto97
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smartrec
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 *
 * @package SmartRec
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'SMARTREC_VERSION', '1.6.0' );
define( 'SMARTREC_PLUGIN_FILE', __FILE__ );
define( 'SMARTREC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMARTREC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMARTREC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once SMARTREC_PLUGIN_DIR . 'includes/class-autoloader.php';

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function smartrec_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active.
 *
 * @return void
 */
function smartrec_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: WooCommerce plugin URL */
					__( '<strong>SmartRec</strong> requires <a href="%s" target="_blank">WooCommerce</a> to be installed and active.', 'smartrec' ),
					'https://wordpress.org/plugins/woocommerce/'
				)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function smartrec_init() {
	if ( ! smartrec_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'smartrec_woocommerce_missing_notice' );
		return;
	}

	\SmartRec\Core\Plugin::get_instance();
}
add_action( 'plugins_loaded', 'smartrec_init' );

// Activation hook.
register_activation_hook( __FILE__, array( '\\SmartRec\\Core\\Activator', 'activate' ) );

// Deactivation hook.
register_deactivation_hook( __FILE__, array( '\\SmartRec\\Core\\Deactivator', 'deactivate' ) );

// Declare HPOS compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
