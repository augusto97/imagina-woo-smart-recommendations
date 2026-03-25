<?php
/**
 * Settings page for the admin panel.
 *
 * @package SmartRec\Admin
 */

namespace SmartRec\Admin;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class SettingsPage
 *
 * Renders the settings form with all configuration sections.
 */
class SettingsPage {

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
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<form method="post" class="smartrec-settings">
			<?php wp_nonce_field( 'smartrec_settings', 'smartrec_nonce' ); ?>
			<input type="hidden" name="smartrec_save_settings" value="settings">

			<?php $this->render_general_section(); ?>
			<?php $this->render_tracking_section(); ?>
			<?php $this->render_engines_section(); ?>
			<?php $this->render_locations_section(); ?>
			<?php $this->render_cache_section(); ?>
			<?php $this->render_advanced_section(); ?>

			<?php submit_button( __( 'Save Settings', 'smartrec' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render the General settings section.
	 *
	 * @return void
	 */
	private function render_general_section() {
		?>
		<h2><?php esc_html_e( 'General Settings', 'smartrec' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Plugin', 'smartrec' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smartrec_enabled" value="1" <?php checked( $this->settings->get( 'enabled', true ) ); ?>>
						<?php esc_html_e( 'Enable SmartRec globally', 'smartrec' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_default_limit"><?php esc_html_e( 'Default Product Limit', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="number" id="smartrec_default_limit" name="smartrec_default_limit"
						   value="<?php echo esc_attr( $this->settings->get( 'default_limit', 8 ) ); ?>"
						   min="1" max="20" step="1" class="small-text">
					<p class="description"><?php esc_html_e( 'Number of products to show per recommendation widget (1-20).', 'smartrec' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_default_layout"><?php esc_html_e( 'Default Layout', 'smartrec' ); ?></label>
				</th>
				<td>
					<select id="smartrec_default_layout" name="smartrec_default_layout">
						<option value="grid" <?php selected( $this->settings->get( 'default_layout', 'grid' ), 'grid' ); ?>><?php esc_html_e( 'Grid', 'smartrec' ); ?></option>
						<option value="slider" <?php selected( $this->settings->get( 'default_layout', 'grid' ), 'slider' ); ?>><?php esc_html_e( 'Slider', 'smartrec' ); ?></option>
						<option value="list" <?php selected( $this->settings->get( 'default_layout', 'grid' ), 'list' ); ?>><?php esc_html_e( 'List', 'smartrec' ); ?></option>
						<option value="minimal" <?php selected( $this->settings->get( 'default_layout', 'grid' ), 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'smartrec' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the Tracking settings section.
	 *
	 * @return void
	 */
	private function render_tracking_section() {
		?>
		<h2><?php esc_html_e( 'Tracking Settings', 'smartrec' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Tracking', 'smartrec' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smartrec_tracking_enabled" value="1" <?php checked( $this->settings->get( 'tracking_enabled', true ) ); ?>>
						<?php esc_html_e( 'Track user behavior for recommendations', 'smartrec' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_tracking_method"><?php esc_html_e( 'Tracking Method', 'smartrec' ); ?></label>
				</th>
				<td>
					<select id="smartrec_tracking_method" name="smartrec_tracking_method">
						<option value="both" <?php selected( $this->settings->get( 'tracking_method', 'both' ), 'both' ); ?>><?php esc_html_e( 'Both (JavaScript + Server-side)', 'smartrec' ); ?></option>
						<option value="js_only" <?php selected( $this->settings->get( 'tracking_method', 'both' ), 'js_only' ); ?>><?php esc_html_e( 'JavaScript Only', 'smartrec' ); ?></option>
						<option value="server_only" <?php selected( $this->settings->get( 'tracking_method', 'both' ), 'server_only' ); ?>><?php esc_html_e( 'Server-side Only', 'smartrec' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Respect Do Not Track', 'smartrec' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smartrec_respect_dnt" value="1" <?php checked( $this->settings->get( 'respect_dnt', true ) ); ?>>
						<?php esc_html_e( 'Honor the browser Do Not Track header', 'smartrec' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_data_retention_days"><?php esc_html_e( 'Data Retention Period', 'smartrec' ); ?></label>
				</th>
				<td>
					<select id="smartrec_data_retention_days" name="smartrec_data_retention_days">
						<?php
						$retention_options = array(
							30  => __( '30 days', 'smartrec' ),
							60  => __( '60 days', 'smartrec' ),
							90  => __( '90 days', 'smartrec' ),
							180 => __( '180 days', 'smartrec' ),
							365 => __( '365 days', 'smartrec' ),
						);
						$current_retention = $this->settings->get( 'data_retention_days', 90 );
						foreach ( $retention_options as $value => $label ) :
							?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_retention, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Tracking events older than this will be automatically deleted.', 'smartrec' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the Engines settings section.
	 *
	 * @return void
	 */
	private function render_engines_section() {
		$engines = array(
			'similar'          => __( 'Similar Products', 'smartrec' ),
			'bought_together'  => __( 'Bought Together', 'smartrec' ),
			'viewed_together'  => __( 'Viewed Together', 'smartrec' ),
			'recently_viewed'  => __( 'Recently Viewed', 'smartrec' ),
			'trending'         => __( 'Trending Products', 'smartrec' ),
			'complementary'    => __( 'Complementary Products', 'smartrec' ),
			'personalized'     => __( 'Personalized Mix', 'smartrec' ),
		);
		?>
		<h2><?php esc_html_e( 'Recommendation Engines', 'smartrec' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php foreach ( $engines as $engine_key => $engine_label ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $engine_label ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								   name="smartrec_engine_<?php echo esc_attr( $engine_key ); ?>_enabled"
								   value="1"
								   <?php checked( $this->settings->get( 'engine_' . $engine_key . '_enabled', true ) ); ?>>
							<?php esc_html_e( 'Enable', 'smartrec' ); ?>
						</label>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Render the Display Locations settings section.
	 *
	 * @return void
	 */
	private function render_locations_section() {
		$locations = array(
			'single_product_below' => __( 'Below Single Product', 'smartrec' ),
			'single_product_tabs'  => __( 'Single Product Tab', 'smartrec' ),
			'cart_page'            => __( 'Cart Page', 'smartrec' ),
			'category_page'        => __( 'Category Page', 'smartrec' ),
			'empty_cart'           => __( 'Empty Cart', 'smartrec' ),
			'thank_you_page'       => __( 'Thank You Page', 'smartrec' ),
			'checkout_page'        => __( 'Checkout Page', 'smartrec' ),
			'my_account'           => __( 'My Account', 'smartrec' ),
		);
		?>
		<h2><?php esc_html_e( 'Display Locations', 'smartrec' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php foreach ( $locations as $location_key => $location_label ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $location_label ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								   name="smartrec_location_<?php echo esc_attr( $location_key ); ?>"
								   value="1"
								   <?php checked( $this->settings->get( 'location_' . $location_key, false ) ); ?>>
							<?php esc_html_e( 'Enable', 'smartrec' ); ?>
						</label>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Render the Cache settings section.
	 *
	 * @return void
	 */
	private function render_cache_section() {
		?>
		<h2><?php esc_html_e( 'Cache Settings', 'smartrec' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Cache', 'smartrec' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smartrec_cache_enabled" value="1" <?php checked( $this->settings->get( 'cache_enabled', true ) ); ?>>
						<?php esc_html_e( 'Cache recommendation results', 'smartrec' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_cache_ttl_product"><?php esc_html_e( 'Product Page TTL', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="number" id="smartrec_cache_ttl_product" name="smartrec_cache_ttl_product"
						   value="<?php echo esc_attr( $this->settings->get( 'cache_ttl_product', 3600 ) ); ?>"
						   min="0" step="60" class="small-text">
					<p class="description"><?php esc_html_e( 'Time to live in seconds for product page caches. Default: 3600 (1 hour).', 'smartrec' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_cache_ttl_category"><?php esc_html_e( 'Category Page TTL', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="number" id="smartrec_cache_ttl_category" name="smartrec_cache_ttl_category"
						   value="<?php echo esc_attr( $this->settings->get( 'cache_ttl_category', 1800 ) ); ?>"
						   min="0" step="60" class="small-text">
					<p class="description"><?php esc_html_e( 'Time to live in seconds for category page caches. Default: 1800 (30 minutes).', 'smartrec' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_cache_ttl_cart"><?php esc_html_e( 'Cart Page TTL', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="number" id="smartrec_cache_ttl_cart" name="smartrec_cache_ttl_cart"
						   value="<?php echo esc_attr( $this->settings->get( 'cache_ttl_cart', 900 ) ); ?>"
						   min="0" step="60" class="small-text">
					<p class="description"><?php esc_html_e( 'Time to live in seconds for cart page caches. Default: 900 (15 minutes).', 'smartrec' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the Advanced settings section.
	 *
	 * @return void
	 */
	private function render_advanced_section() {
		?>
		<h2><?php esc_html_e( 'Advanced Settings', 'smartrec' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Debug Mode', 'smartrec' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smartrec_debug_mode" value="1" <?php checked( $this->settings->get( 'debug_mode', false ) ); ?>>
						<?php esc_html_e( 'Log all queries and engine outputs to WooCommerce log', 'smartrec' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Delete Data on Uninstall', 'smartrec' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smartrec_delete_data_on_uninstall" value="1" <?php checked( $this->settings->get( 'delete_data_on_uninstall', false ) ); ?>>
						<?php esc_html_e( 'Remove all plugin data when uninstalling (tables, options, transients)', 'smartrec' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'AJAX Loading', 'smartrec' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smartrec_ajax_loading" value="1" <?php checked( $this->settings->get( 'ajax_loading', false ) ); ?>>
						<?php esc_html_e( 'Load recommendations asynchronously via AJAX', 'smartrec' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show Price', 'smartrec' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smartrec_show_price" value="1" <?php checked( $this->settings->get( 'show_price', true ) ); ?>>
						<?php esc_html_e( 'Display product prices in recommendation widgets', 'smartrec' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show Rating', 'smartrec' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smartrec_show_rating" value="1" <?php checked( $this->settings->get( 'show_rating', true ) ); ?>>
						<?php esc_html_e( 'Display product ratings in recommendation widgets', 'smartrec' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show Add to Cart', 'smartrec' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smartrec_show_add_to_cart" value="1" <?php checked( $this->settings->get( 'show_add_to_cart', true ) ); ?>>
						<?php esc_html_e( 'Display Add to Cart button in recommendation widgets', 'smartrec' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show Reason Badge', 'smartrec' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smartrec_show_reason" value="1" <?php checked( $this->settings->get( 'show_reason', true ) ); ?>>
						<?php esc_html_e( 'Display reason badges (e.g., "Trending", "Others also bought")', 'smartrec' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}
}
