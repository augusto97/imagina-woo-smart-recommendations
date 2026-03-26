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
 * Renders the settings form with sub-tabs and styled sections.
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
	 * Render the settings page with sub-tabs.
	 *
	 * @return void
	 */
	public function render() {
		$sub_tabs = array(
			'general'    => __( 'General', 'smartrec' ),
			'locations'  => __( 'Display Locations', 'smartrec' ),
			'appearance' => __( 'Appearance', 'smartrec' ),
			'engines'    => __( 'Engines', 'smartrec' ),
			'tracking'   => __( 'Tracking', 'smartrec' ),
			'cache'      => __( 'Cache', 'smartrec' ),
			'advanced'   => __( 'Advanced', 'smartrec' ),
		);
		?>
		<form method="post" class="smartrec-settings">
			<?php wp_nonce_field( 'smartrec_settings', 'smartrec_nonce' ); ?>
			<input type="hidden" name="smartrec_save_settings" value="settings">

			<div class="smartrec-subtabs">
				<nav class="smartrec-subtabs__nav">
					<?php $first = true; ?>
					<?php foreach ( $sub_tabs as $tab_id => $tab_label ) : ?>
						<a href="#smartrec-tab-<?php echo esc_attr( $tab_id ); ?>"
						   class="smartrec-subtabs__link <?php echo $first ? 'smartrec-subtabs__link--active' : ''; ?>"
						   data-tab="<?php echo esc_attr( $tab_id ); ?>">
							<?php echo esc_html( $tab_label ); ?>
						</a>
						<?php $first = false; ?>
					<?php endforeach; ?>
				</nav>

				<div id="smartrec-tab-general" class="smartrec-subtabs__panel smartrec-subtabs__panel--active">
					<?php $this->render_general_section(); ?>
				</div>

				<div id="smartrec-tab-locations" class="smartrec-subtabs__panel">
					<?php $this->render_locations_section(); ?>
				</div>

				<div id="smartrec-tab-appearance" class="smartrec-subtabs__panel">
					<?php $this->render_appearance_section(); ?>
				</div>

				<div id="smartrec-tab-engines" class="smartrec-subtabs__panel">
					<?php $this->render_engines_section(); ?>
				</div>

				<div id="smartrec-tab-tracking" class="smartrec-subtabs__panel">
					<?php $this->render_tracking_section(); ?>
				</div>

				<div id="smartrec-tab-cache" class="smartrec-subtabs__panel">
					<?php $this->render_cache_section(); ?>
				</div>

				<div id="smartrec-tab-advanced" class="smartrec-subtabs__panel">
					<?php $this->render_advanced_section(); ?>
				</div>
			</div>

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
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'General Settings', 'smartrec' ); ?></h3>
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
						<p class="description"><?php esc_html_e( 'Number of products to show per widget (1-20).', 'smartrec' ); ?></p>
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
		</div>

		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Product Card Style', 'smartrec' ); ?></h3>
			<p class="smartrec-section__desc"><?php esc_html_e( 'Choose how recommended products are displayed on your store.', 'smartrec' ); ?></p>

			<div class="smartrec-card-style-options">
				<label class="smartrec-card-style-option <?php echo ! $this->settings->get( 'use_wc_template', false ) ? 'smartrec-card-style-option--selected' : ''; ?>">
					<input type="radio" name="smartrec_use_wc_template" value="0" <?php checked( $this->settings->get( 'use_wc_template', false ), false ); ?>>
					<div class="smartrec-card-style-option__content">
						<strong><?php esc_html_e( 'SmartRec Cards', 'smartrec' ); ?></strong>
						<span><?php esc_html_e( 'Custom cards with full color and style customization via the Appearance tab.', 'smartrec' ); ?></span>
					</div>
				</label>
				<label class="smartrec-card-style-option <?php echo $this->settings->get( 'use_wc_template', false ) ? 'smartrec-card-style-option--selected' : ''; ?>">
					<input type="radio" name="smartrec_use_wc_template" value="1" <?php checked( $this->settings->get( 'use_wc_template', false ), true ); ?>>
					<div class="smartrec-card-style-option__content">
						<strong><?php esc_html_e( 'WooCommerce Template', 'smartrec' ); ?></strong>
						<span><?php esc_html_e( 'Uses your theme\'s product template. Products look identical to the rest of your store. Recommended for best theme compatibility.', 'smartrec' ); ?></span>
					</div>
				</label>
			</div>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Inherit Theme Fonts', 'smartrec' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="smartrec_inherit_theme_fonts" value="1" <?php checked( $this->settings->get( 'inherit_theme_fonts', true ) ); ?>>
							<?php esc_html_e( 'Use your theme\'s font family for all SmartRec text (recommended)', 'smartrec' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Product Card Elements', 'smartrec' ); ?></h3>
			<p class="smartrec-section__desc"><?php esc_html_e( 'Choose which elements to show inside each product card.', 'smartrec' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Show Price', 'smartrec' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="smartrec_show_price" value="1" <?php checked( $this->settings->get( 'show_price', true ) ); ?>>
							<?php esc_html_e( 'Display product prices', 'smartrec' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Show Rating', 'smartrec' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="smartrec_show_rating" value="1" <?php checked( $this->settings->get( 'show_rating', true ) ); ?>>
							<?php esc_html_e( 'Display star ratings', 'smartrec' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Show Add to Cart', 'smartrec' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="smartrec_show_add_to_cart" value="1" <?php checked( $this->settings->get( 'show_add_to_cart', true ) ); ?>>
							<?php esc_html_e( 'Display "Add to Cart" button', 'smartrec' ); ?>
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
		</div>
		<?php
	}

	/**
	 * Render the Tracking settings section.
	 *
	 * @return void
	 */
	private function render_tracking_section() {
		?>
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Tracking Settings', 'smartrec' ); ?></h3>
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
							<option value="both" <?php selected( $this->settings->get( 'tracking_method', 'both' ), 'both' ); ?>><?php esc_html_e( 'Both (JS + Server-side)', 'smartrec' ); ?></option>
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
						<label for="smartrec_data_retention_days"><?php esc_html_e( 'Data Retention', 'smartrec' ); ?></label>
					</th>
					<td>
						<select id="smartrec_data_retention_days" name="smartrec_data_retention_days">
							<?php
							$options = array( 30 => '30', 60 => '60', 90 => '90', 180 => '180', 365 => '365' );
							$current = $this->settings->get( 'data_retention_days', 90 );
							foreach ( $options as $val => $label ) :
								?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current, $val ); ?>>
									<?php /* translators: %s: number of days */ printf( esc_html__( '%s days', 'smartrec' ), esc_html( $label ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Events older than this are automatically deleted.', 'smartrec' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the Engines settings section.
	 *
	 * @return void
	 */
	private function render_engines_section() {
		$engines = array(
			'similar'          => array( __( 'Similar Products', 'smartrec' ), __( 'Content-based: recommends products with same categories, tags, and attributes.', 'smartrec' ) ),
			'bought_together'  => array( __( 'Bought Together', 'smartrec' ), __( 'Co-purchase analysis: "Customers who bought X also bought Y".', 'smartrec' ) ),
			'viewed_together'  => array( __( 'Viewed Together', 'smartrec' ), __( 'Session analysis: products frequently viewed in the same browsing session.', 'smartrec' ) ),
			'recently_viewed'  => array( __( 'Recently Viewed', 'smartrec' ), __( 'Shows products the current visitor has recently viewed.', 'smartrec' ) ),
			'trending'         => array( __( 'Trending Products', 'smartrec' ), __( 'Time-weighted popularity scoring based on views and purchases.', 'smartrec' ) ),
			'complementary'    => array( __( 'Complementary Products', 'smartrec' ), __( 'Cross-sell: uses your category rules from the Complementary Rules tab.', 'smartrec' ) ),
			'personalized'     => array( __( 'Personalized Mix', 'smartrec' ), __( 'Smart hybrid: combines all engines weighted by user profile. Best default.', 'smartrec' ) ),
		);
		?>
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Recommendation Engines', 'smartrec' ); ?></h3>
			<p class="smartrec-section__desc"><?php esc_html_e( 'Enable or disable individual recommendation algorithms.', 'smartrec' ); ?></p>

			<div class="smartrec-engines-list">
				<?php foreach ( $engines as $key => $info ) : ?>
					<label class="smartrec-engine-item">
						<input type="checkbox"
							   name="smartrec_engine_<?php echo esc_attr( $key ); ?>_enabled"
							   value="1"
							   <?php checked( $this->settings->get( 'engine_' . $key . '_enabled', true ) ); ?>>
						<div class="smartrec-engine-item__text">
							<strong><?php echo esc_html( $info[0] ); ?></strong>
							<span><?php echo esc_html( $info[1] ); ?></span>
						</div>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Display Locations settings section.
	 *
	 * @return void
	 */
	private function render_locations_section() {
		$locations = array(
			'single_product_below'  => array( __( 'Below Single Product', 'smartrec' ), __( 'After product summary on single product pages', 'smartrec' ), 'woocommerce_after_single_product_summary', 'personalized_mix' ),
			'single_product_tabs'   => array( __( 'Product Tab', 'smartrec' ), __( 'New "Recommended" tab in product data tabs', 'smartrec' ), 'woocommerce_product_tabs', 'bought_together' ),
			'cart_page'             => array( __( 'Cart Page', 'smartrec' ), __( 'Below the cart table', 'smartrec' ), 'woocommerce_after_cart_table', 'bought_together' ),
			'cart_page_cross_sells' => array( __( 'Cart Cross-Sells', 'smartrec' ), __( 'Replaces WooCommerce native cross-sells', 'smartrec' ), 'woocommerce_cross_sell_display', 'complementary' ),
			'checkout_page'         => array( __( 'Checkout Page', 'smartrec' ), __( 'After the checkout form', 'smartrec' ), 'woocommerce_after_checkout_form', 'bought_together' ),
			'category_page'         => array( __( 'Category / Archive', 'smartrec' ), __( 'After the product loop on category pages', 'smartrec' ), 'woocommerce_after_shop_loop', 'trending' ),
			'empty_cart'            => array( __( 'Empty Cart', 'smartrec' ), __( 'When cart is empty', 'smartrec' ), 'woocommerce_cart_is_empty', 'trending' ),
			'thank_you_page'        => array( __( 'Thank You Page', 'smartrec' ), __( 'Order confirmation page', 'smartrec' ), 'woocommerce_thankyou', 'complementary' ),
			'my_account'            => array( __( 'My Account', 'smartrec' ), __( 'Customer account dashboard', 'smartrec' ), 'woocommerce_account_dashboard', 'personalized_mix' ),
		);

		$engine_labels = array(
			'personalized_mix' => __( 'Personalized Mix', 'smartrec' ),
			'similar'          => __( 'Similar Products', 'smartrec' ),
			'bought_together'  => __( 'Bought Together', 'smartrec' ),
			'viewed_together'  => __( 'Viewed Together', 'smartrec' ),
			'recently_viewed'  => __( 'Recently Viewed', 'smartrec' ),
			'trending'         => __( 'Trending', 'smartrec' ),
			'complementary'    => __( 'Complementary', 'smartrec' ),
		);

		$layouts = array( '' => __( 'Default', 'smartrec' ), 'grid' => __( 'Grid', 'smartrec' ), 'slider' => __( 'Slider', 'smartrec' ), 'list' => __( 'List', 'smartrec' ), 'minimal' => __( 'Minimal', 'smartrec' ) );

		$loc_engines   = $this->settings->get( 'location_engines', array() );
		$loc_titles    = $this->settings->get( 'location_titles', array() );
		$loc_limits    = $this->settings->get( 'location_limits', array() );
		$loc_layouts   = $this->settings->get( 'location_layouts', array() );
		$loc_columns   = $this->settings->get( 'location_columns', array() );
		$loc_cols_tablet = $this->settings->get( 'location_columns_tablet', array() );
		$loc_cols_mobile = $this->settings->get( 'location_columns_mobile', array() );
		$loc_load_more = $this->settings->get( 'location_load_more', array() );
		?>
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Display Locations', 'smartrec' ); ?></h3>
			<p class="smartrec-section__desc"><?php esc_html_e( 'Choose where recommendations appear. Click a row to expand its settings.', 'smartrec' ); ?></p>

			<div class="smartrec-locations">
				<?php foreach ( $locations as $key => $loc ) :
					$enabled    = $this->settings->get( 'location_' . $key, false );
					$cur_engine = $loc_engines[ $key ] ?? $loc[3];
					$cur_title  = $loc_titles[ $key ] ?? '';
					$cur_limit  = $loc_limits[ $key ] ?? '';
					$cur_layout = $loc_layouts[ $key ] ?? '';
					$cur_cols        = $loc_columns[ $key ] ?? '';
					$cur_cols_tablet = $loc_cols_tablet[ $key ] ?? '';
					$cur_cols_mobile = $loc_cols_mobile[ $key ] ?? '';
					$cur_load_more   = $loc_load_more[ $key ] ?? '';
					?>
					<div class="smartrec-loc <?php echo $enabled ? 'smartrec-loc--active' : ''; ?>">
						<div class="smartrec-loc__header" tabindex="0" role="button">
							<label class="smartrec-loc__toggle" onclick="event.stopPropagation();">
								<input type="checkbox" name="smartrec_location_<?php echo esc_attr( $key ); ?>" value="1" class="smartrec-location-toggle" <?php checked( $enabled ); ?>>
							</label>
							<div class="smartrec-loc__info">
								<strong><?php echo esc_html( $loc[0] ); ?></strong>
								<span class="smartrec-loc__desc"><?php echo esc_html( $loc[1] ); ?></span>
							</div>
							<span class="smartrec-loc__engine-badge"><?php echo esc_html( $engine_labels[ $cur_engine ] ?? $cur_engine ); ?></span>
							<span class="smartrec-loc__arrow dashicons dashicons-arrow-down-alt2"></span>
						</div>
						<div class="smartrec-loc__body">
							<table class="form-table smartrec-loc__table" role="presentation">
								<tr>
									<th><?php esc_html_e( 'Engine', 'smartrec' ); ?></th>
									<td>
										<select name="smartrec_loc_engine[<?php echo esc_attr( $key ); ?>]">
											<?php foreach ( $engine_labels as $ek => $el ) : ?>
												<option value="<?php echo esc_attr( $ek ); ?>" <?php selected( $cur_engine, $ek ); ?>><?php echo esc_html( $el ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Title', 'smartrec' ); ?></th>
									<td>
										<input type="text" name="smartrec_loc_title[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $cur_title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Recommended for you', 'smartrec' ); ?>" class="regular-text">
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Products', 'smartrec' ); ?></th>
									<td>
										<input type="number" name="smartrec_loc_limit[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $cur_limit ); ?>" min="1" max="20" placeholder="<?php echo esc_attr( $this->settings->get( 'default_limit', 8 ) ); ?>" class="small-text">
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Columns', 'smartrec' ); ?></th>
									<td class="smartrec-columns-responsive">
										<label>
											<span><?php esc_html_e( 'Desktop', 'smartrec' ); ?></span>
											<input type="number" name="smartrec_loc_columns[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $cur_cols ); ?>" min="1" max="6" placeholder="4" class="small-text">
										</label>
										<label>
											<span><?php esc_html_e( 'Tablet', 'smartrec' ); ?></span>
											<input type="number" name="smartrec_loc_columns_tablet[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $cur_cols_tablet ); ?>" min="1" max="6" placeholder="2" class="small-text">
										</label>
										<label>
											<span><?php esc_html_e( 'Mobile', 'smartrec' ); ?></span>
											<input type="number" name="smartrec_loc_columns_mobile[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $cur_cols_mobile ); ?>" min="1" max="6" placeholder="1" class="small-text">
										</label>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Layout', 'smartrec' ); ?></th>
									<td>
										<select name="smartrec_loc_layout[<?php echo esc_attr( $key ); ?>]">
											<?php foreach ( $layouts as $lk => $ll ) : ?>
												<option value="<?php echo esc_attr( $lk ); ?>" <?php selected( $cur_layout, $lk ); ?>><?php echo esc_html( $ll ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Load More', 'smartrec' ); ?></th>
									<td>
										<input type="number" name="smartrec_loc_load_more[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $cur_load_more ); ?>" min="0" max="20" placeholder="0" class="small-text">
										<p class="description"><?php esc_html_e( 'Products per click. 0 or empty = disabled. Not available for slider.', 'smartrec' ); ?></p>
									</td>
								</tr>
							</table>
							<p class="smartrec-loc__hook-info">
								<?php /* translators: %s: hook name */ printf( esc_html__( 'WooCommerce hook: %s', 'smartrec' ), '<code>' . esc_html( $loc[2] ) . '</code>' ); ?>
							</p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Appearance settings section.
	 *
	 * @return void
	 */
	private function render_appearance_section() {
		?>
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Colors', 'smartrec' ); ?></h3>
			<p class="smartrec-section__desc"><?php esc_html_e( 'Customize colors to match your theme. Leave blank to use defaults.', 'smartrec' ); ?></p>
			<table class="form-table" role="presentation">
				<?php
				$color_fields = array(
					'style_accent_color' => array( __( 'Accent / Price Color', 'smartrec' ), '#7f54b3', __( 'Used for prices and accents. Defaults to WooCommerce primary.', 'smartrec' ) ),
					'style_card_bg'      => array( __( 'Card Background', 'smartrec' ), '#ffffff', '' ),
					'style_card_text'    => array( __( 'Card Text', 'smartrec' ), '#333333', '' ),
					'style_title_color'  => array( __( 'Section Title', 'smartrec' ), '#1d2327', '' ),
					'style_btn_bg'       => array( __( 'Button Background', 'smartrec' ), '#7f54b3', '' ),
					'style_btn_text'     => array( __( 'Button Text', 'smartrec' ), '#ffffff', '' ),
					'style_badge_bg'     => array( __( 'Badge Background', 'smartrec' ), '#f0f0f0', '' ),
					'style_badge_text'   => array( __( 'Badge Text', 'smartrec' ), '#333333', '' ),
				);
				foreach ( $color_fields as $field_key => $field_info ) : ?>
					<tr>
						<th scope="row"><label for="smartrec_<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $field_info[0] ); ?></label></th>
						<td>
							<input type="text"
								   id="smartrec_<?php echo esc_attr( $field_key ); ?>"
								   name="smartrec_<?php echo esc_attr( $field_key ); ?>"
								   value="<?php echo esc_attr( $this->settings->get( $field_key, '' ) ); ?>"
								   class="smartrec-color-picker"
								   data-default-color="<?php echo esc_attr( $field_info[1] ); ?>"
								   placeholder="<?php echo esc_attr( $field_info[1] ); ?>">
							<?php if ( ! empty( $field_info[2] ) ) : ?>
								<p class="description"><?php echo esc_html( $field_info[2] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Card Styling', 'smartrec' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="smartrec_style_card_radius"><?php esc_html_e( 'Border Radius', 'smartrec' ); ?></label></th>
					<td>
						<input type="text" id="smartrec_style_card_radius" name="smartrec_style_card_radius" value="<?php echo esc_attr( $this->settings->get( 'style_card_radius', '' ) ); ?>" placeholder="8px" class="small-text">
						<p class="description"><?php esc_html_e( 'CSS value: 8px, 0, 12px, etc.', 'smartrec' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smartrec_style_card_shadow"><?php esc_html_e( 'Shadow', 'smartrec' ); ?></label></th>
					<td>
						<select id="smartrec_style_card_shadow" name="smartrec_style_card_shadow">
							<option value="" <?php selected( $this->settings->get( 'style_card_shadow', '' ), '' ); ?>><?php esc_html_e( 'Default (subtle)', 'smartrec' ); ?></option>
							<option value="none" <?php selected( $this->settings->get( 'style_card_shadow', '' ), 'none' ); ?>><?php esc_html_e( 'None', 'smartrec' ); ?></option>
							<option value="small" <?php selected( $this->settings->get( 'style_card_shadow', '' ), 'small' ); ?>><?php esc_html_e( 'Small', 'smartrec' ); ?></option>
							<option value="medium" <?php selected( $this->settings->get( 'style_card_shadow', '' ), 'medium' ); ?>><?php esc_html_e( 'Medium', 'smartrec' ); ?></option>
							<option value="large" <?php selected( $this->settings->get( 'style_card_shadow', '' ), 'large' ); ?>><?php esc_html_e( 'Large', 'smartrec' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smartrec_style_gap"><?php esc_html_e( 'Grid Gap', 'smartrec' ); ?></label></th>
					<td>
						<input type="text" id="smartrec_style_gap" name="smartrec_style_gap" value="<?php echo esc_attr( $this->settings->get( 'style_gap', '' ) ); ?>" placeholder="16px" class="small-text">
						<p class="description"><?php esc_html_e( 'Space between cards: 16px, 1rem, 20px, etc.', 'smartrec' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smartrec_style_title_size"><?php esc_html_e( 'Title Font Size', 'smartrec' ); ?></label></th>
					<td>
						<input type="text" id="smartrec_style_title_size" name="smartrec_style_title_size" value="<?php echo esc_attr( $this->settings->get( 'style_title_size', '' ) ); ?>" placeholder="18px" class="small-text">
					</td>
				</tr>
			</table>
		</div>

		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Custom CSS', 'smartrec' ); ?></h3>
			<p class="smartrec-section__desc"><?php esc_html_e( 'Add custom CSS to fine-tune the appearance. All rules apply inside .smartrec-widget.', 'smartrec' ); ?></p>
			<textarea id="smartrec_custom_css" name="smartrec_custom_css" rows="8" class="large-text code" placeholder="<?php esc_attr_e( "/* Example: */\n.smartrec-widget__item-title {\n    font-size: 15px;\n}", 'smartrec' ); ?>"><?php echo esc_textarea( $this->settings->get( 'custom_css', '' ) ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Render the Cache settings section.
	 *
	 * @return void
	 */
	private function render_cache_section() {
		?>
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Cache Settings', 'smartrec' ); ?></h3>
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
					<th scope="row"><label for="smartrec_cache_ttl_product"><?php esc_html_e( 'Product Page TTL', 'smartrec' ); ?></label></th>
					<td>
						<input type="number" id="smartrec_cache_ttl_product" name="smartrec_cache_ttl_product" value="<?php echo esc_attr( $this->settings->get( 'cache_ttl_product', 3600 ) ); ?>" min="0" step="60" class="small-text">
						<p class="description"><?php esc_html_e( 'Seconds. Default: 3600 (1 hour).', 'smartrec' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smartrec_cache_ttl_category"><?php esc_html_e( 'Category Page TTL', 'smartrec' ); ?></label></th>
					<td>
						<input type="number" id="smartrec_cache_ttl_category" name="smartrec_cache_ttl_category" value="<?php echo esc_attr( $this->settings->get( 'cache_ttl_category', 1800 ) ); ?>" min="0" step="60" class="small-text">
						<p class="description"><?php esc_html_e( 'Seconds. Default: 1800 (30 min).', 'smartrec' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smartrec_cache_ttl_cart"><?php esc_html_e( 'Cart Page TTL', 'smartrec' ); ?></label></th>
					<td>
						<input type="number" id="smartrec_cache_ttl_cart" name="smartrec_cache_ttl_cart" value="<?php echo esc_attr( $this->settings->get( 'cache_ttl_cart', 900 ) ); ?>" min="0" step="60" class="small-text">
						<p class="description"><?php esc_html_e( 'Seconds. Default: 900 (15 min).', 'smartrec' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the Advanced settings section.
	 *
	 * @return void
	 */
	private function render_advanced_section() {
		?>
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Advanced Settings', 'smartrec' ); ?></h3>
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
					<th scope="row"><?php esc_html_e( 'AJAX Loading', 'smartrec' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="smartrec_ajax_loading" value="1" <?php checked( $this->settings->get( 'ajax_loading', false ) ); ?>>
							<?php esc_html_e( 'Load recommendations asynchronously (lazy load)', 'smartrec' ); ?>
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
						<p class="description" style="color:#b32d2e;"><?php esc_html_e( 'Warning: This cannot be undone.', 'smartrec' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}
