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
			<?php $this->render_appearance_section(); ?>
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
			<tr>
				<th scope="row"><?php esc_html_e( 'Product Card Style', 'smartrec' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="smartrec_use_wc_template" value="0" <?php checked( $this->settings->get( 'use_wc_template', false ), false ); ?>>
							<?php esc_html_e( 'SmartRec cards (customizable via Appearance settings below)', 'smartrec' ); ?>
						</label>
						<br>
						<label>
							<input type="radio" name="smartrec_use_wc_template" value="1" <?php checked( $this->settings->get( 'use_wc_template', false ), true ); ?>>
							<strong><?php esc_html_e( 'Use WooCommerce product template', 'smartrec' ); ?></strong>
							&mdash; <span class="description"><?php esc_html_e( 'Products will look exactly like the rest of your store (recommended for best theme compatibility)', 'smartrec' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Inherit Theme Fonts', 'smartrec' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="smartrec_inherit_theme_fonts" value="1" <?php checked( $this->settings->get( 'inherit_theme_fonts', true ) ); ?>>
						<?php esc_html_e( 'Use your theme\'s font family for titles and product names (recommended)', 'smartrec' ); ?>
					</label>
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
			'single_product_below' => array(
				'label'   => __( 'Below Single Product', 'smartrec' ),
				'desc'    => __( 'After product summary on single product pages', 'smartrec' ),
				'hook'    => 'woocommerce_after_single_product_summary',
				'default' => 'personalized_mix',
			),
			'single_product_tabs'  => array(
				'label'   => __( 'Product Tab', 'smartrec' ),
				'desc'    => __( 'New "Recommended" tab in product data tabs', 'smartrec' ),
				'hook'    => 'woocommerce_product_tabs',
				'default' => 'bought_together',
			),
			'cart_page'            => array(
				'label'   => __( 'Cart Page', 'smartrec' ),
				'desc'    => __( 'Below the cart table', 'smartrec' ),
				'hook'    => 'woocommerce_after_cart_table',
				'default' => 'bought_together',
			),
			'cart_page_cross_sells' => array(
				'label'   => __( 'Cart Cross-Sells', 'smartrec' ),
				'desc'    => __( 'Replaces WooCommerce native cross-sells', 'smartrec' ),
				'hook'    => 'woocommerce_cross_sell_display',
				'default' => 'complementary',
			),
			'checkout_page'        => array(
				'label'   => __( 'Checkout Page', 'smartrec' ),
				'desc'    => __( 'After the checkout form', 'smartrec' ),
				'hook'    => 'woocommerce_after_checkout_form',
				'default' => 'bought_together',
			),
			'category_page'        => array(
				'label'   => __( 'Category / Archive', 'smartrec' ),
				'desc'    => __( 'After the product loop on category pages', 'smartrec' ),
				'hook'    => 'woocommerce_after_shop_loop',
				'default' => 'trending',
			),
			'empty_cart'           => array(
				'label'   => __( 'Empty Cart', 'smartrec' ),
				'desc'    => __( 'When cart is empty', 'smartrec' ),
				'hook'    => 'woocommerce_cart_is_empty',
				'default' => 'trending',
			),
			'thank_you_page'       => array(
				'label'   => __( 'Thank You Page', 'smartrec' ),
				'desc'    => __( 'Order confirmation page', 'smartrec' ),
				'hook'    => 'woocommerce_thankyou',
				'default' => 'complementary',
			),
			'my_account'           => array(
				'label'   => __( 'My Account', 'smartrec' ),
				'desc'    => __( 'Customer account dashboard', 'smartrec' ),
				'hook'    => 'woocommerce_account_dashboard',
				'default' => 'personalized_mix',
			),
		);

		$engines = array(
			'personalized_mix' => __( 'Personalized Mix', 'smartrec' ),
			'similar'          => __( 'Similar Products', 'smartrec' ),
			'bought_together'  => __( 'Bought Together', 'smartrec' ),
			'viewed_together'  => __( 'Viewed Together', 'smartrec' ),
			'recently_viewed'  => __( 'Recently Viewed', 'smartrec' ),
			'trending'         => __( 'Trending', 'smartrec' ),
			'complementary'    => __( 'Complementary', 'smartrec' ),
		);

		$layouts = array(
			''        => __( 'Default', 'smartrec' ),
			'grid'    => __( 'Grid', 'smartrec' ),
			'slider'  => __( 'Slider', 'smartrec' ),
			'list'    => __( 'List', 'smartrec' ),
			'minimal' => __( 'Minimal', 'smartrec' ),
		);

		$location_engines = $this->settings->get( 'location_engines', array() );
		$location_titles  = $this->settings->get( 'location_titles', array() );
		$location_limits  = $this->settings->get( 'location_limits', array() );
		$location_layouts = $this->settings->get( 'location_layouts', array() );
		$location_columns = $this->settings->get( 'location_columns', array() );
		?>
		<h2><?php esc_html_e( 'Display Locations', 'smartrec' ); ?></h2>
		<p class="description" style="margin-bottom:12px;"><?php esc_html_e( 'Choose where recommendations appear on your store. Click any row to expand its settings.', 'smartrec' ); ?></p>

		<div class="smartrec-locations">
			<?php foreach ( $locations as $loc_key => $loc_info ) :
				$is_enabled  = $this->settings->get( 'location_' . $loc_key, false );
				$cur_engine  = $location_engines[ $loc_key ] ?? $loc_info['default'];
				$cur_title   = $location_titles[ $loc_key ] ?? '';
				$cur_limit   = $location_limits[ $loc_key ] ?? '';
				$cur_layout  = $location_layouts[ $loc_key ] ?? '';
				$cur_columns = $location_columns[ $loc_key ] ?? '';
				?>
				<div class="smartrec-loc <?php echo $is_enabled ? 'smartrec-loc--active' : ''; ?>">
					<div class="smartrec-loc__header" tabindex="0" role="button">
						<label class="smartrec-loc__toggle" onclick="event.stopPropagation();">
							<input type="checkbox"
								   name="smartrec_location_<?php echo esc_attr( $loc_key ); ?>"
								   value="1"
								   class="smartrec-location-toggle"
								   <?php checked( $is_enabled ); ?>>
						</label>
						<div class="smartrec-loc__info">
							<strong><?php echo esc_html( $loc_info['label'] ); ?></strong>
							<span class="smartrec-loc__desc"><?php echo esc_html( $loc_info['desc'] ); ?></span>
						</div>
						<span class="smartrec-loc__engine-badge"><?php echo esc_html( $engines[ $cur_engine ] ?? $cur_engine ); ?></span>
						<span class="smartrec-loc__arrow dashicons dashicons-arrow-down-alt2"></span>
					</div>
					<div class="smartrec-loc__body">
						<div class="smartrec-loc__fields">
							<div class="smartrec-loc__field">
								<label><?php esc_html_e( 'Recommendation Engine', 'smartrec' ); ?></label>
								<select name="smartrec_loc_engine[<?php echo esc_attr( $loc_key ); ?>]">
									<?php foreach ( $engines as $eng_key => $eng_label ) : ?>
										<option value="<?php echo esc_attr( $eng_key ); ?>" <?php selected( $cur_engine, $eng_key ); ?>>
											<?php echo esc_html( $eng_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="smartrec-loc__field">
								<label><?php esc_html_e( 'Section Title', 'smartrec' ); ?></label>
								<input type="text"
									   name="smartrec_loc_title[<?php echo esc_attr( $loc_key ); ?>]"
									   value="<?php echo esc_attr( $cur_title ); ?>"
									   placeholder="<?php esc_attr_e( 'e.g. Recommended for you', 'smartrec' ); ?>">
							</div>
						</div>
						<div class="smartrec-loc__fields smartrec-loc__fields--row">
							<div class="smartrec-loc__field">
								<label><?php esc_html_e( 'Products', 'smartrec' ); ?></label>
								<input type="number"
									   name="smartrec_loc_limit[<?php echo esc_attr( $loc_key ); ?>]"
									   value="<?php echo esc_attr( $cur_limit ); ?>"
									   min="1" max="20" step="1"
									   placeholder="<?php echo esc_attr( $this->settings->get( 'default_limit', 8 ) ); ?>"
									   class="small-text">
							</div>
							<div class="smartrec-loc__field">
								<label><?php esc_html_e( 'Columns', 'smartrec' ); ?></label>
								<input type="number"
									   name="smartrec_loc_columns[<?php echo esc_attr( $loc_key ); ?>]"
									   value="<?php echo esc_attr( $cur_columns ); ?>"
									   min="1" max="6" step="1"
									   placeholder="4"
									   class="small-text">
							</div>
							<div class="smartrec-loc__field">
								<label><?php esc_html_e( 'Layout', 'smartrec' ); ?></label>
								<select name="smartrec_loc_layout[<?php echo esc_attr( $loc_key ); ?>]">
									<?php foreach ( $layouts as $lay_key => $lay_label ) : ?>
										<option value="<?php echo esc_attr( $lay_key ); ?>" <?php selected( $cur_layout, $lay_key ); ?>>
											<?php echo esc_html( $lay_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<p class="smartrec-loc__hook-info">
							<?php
							/* translators: %s: WooCommerce hook name */
							printf( esc_html__( 'Hook: %s', 'smartrec' ), '<code>' . esc_html( $loc_info['hook'] ) . '</code>' );
							?>
						</p>
					</div>
				</div>
			<?php endforeach; ?>
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
		<h2><?php esc_html_e( 'Appearance & Styling', 'smartrec' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Customize colors and styling to match your theme. Leave blank to use defaults.', 'smartrec' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="smartrec_style_accent_color"><?php esc_html_e( 'Accent Color', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="smartrec_style_accent_color"
						   name="smartrec_style_accent_color"
						   value="<?php echo esc_attr( $this->settings->get( 'style_accent_color', '' ) ); ?>"
						   class="smartrec-color-picker"
						   data-default-color="#7f54b3"
						   placeholder="#7f54b3">
					<p class="description"><?php esc_html_e( 'Used for buttons and price. Defaults to WooCommerce primary color.', 'smartrec' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_style_card_bg"><?php esc_html_e( 'Card Background', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="smartrec_style_card_bg"
						   name="smartrec_style_card_bg"
						   value="<?php echo esc_attr( $this->settings->get( 'style_card_bg', '' ) ); ?>"
						   class="smartrec-color-picker"
						   data-default-color="#ffffff"
						   placeholder="#ffffff">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_style_card_text"><?php esc_html_e( 'Card Text Color', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="smartrec_style_card_text"
						   name="smartrec_style_card_text"
						   value="<?php echo esc_attr( $this->settings->get( 'style_card_text', '' ) ); ?>"
						   class="smartrec-color-picker"
						   data-default-color="#333333"
						   placeholder="#333333">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_style_title_color"><?php esc_html_e( 'Widget Title Color', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="smartrec_style_title_color"
						   name="smartrec_style_title_color"
						   value="<?php echo esc_attr( $this->settings->get( 'style_title_color', '' ) ); ?>"
						   class="smartrec-color-picker"
						   data-default-color="#1d2327"
						   placeholder="#1d2327">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_style_badge_bg"><?php esc_html_e( 'Badge Background', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="smartrec_style_badge_bg"
						   name="smartrec_style_badge_bg"
						   value="<?php echo esc_attr( $this->settings->get( 'style_badge_bg', '' ) ); ?>"
						   class="smartrec-color-picker"
						   data-default-color="#f0f0f0"
						   placeholder="#f0f0f0">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_style_badge_text"><?php esc_html_e( 'Badge Text Color', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="smartrec_style_badge_text"
						   name="smartrec_style_badge_text"
						   value="<?php echo esc_attr( $this->settings->get( 'style_badge_text', '' ) ); ?>"
						   class="smartrec-color-picker"
						   data-default-color="#333333"
						   placeholder="#333333">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_style_btn_bg"><?php esc_html_e( 'Button Background', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="smartrec_style_btn_bg"
						   name="smartrec_style_btn_bg"
						   value="<?php echo esc_attr( $this->settings->get( 'style_btn_bg', '' ) ); ?>"
						   class="smartrec-color-picker"
						   data-default-color="#7f54b3"
						   placeholder="#7f54b3">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_style_btn_text"><?php esc_html_e( 'Button Text Color', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="smartrec_style_btn_text"
						   name="smartrec_style_btn_text"
						   value="<?php echo esc_attr( $this->settings->get( 'style_btn_text', '' ) ); ?>"
						   class="smartrec-color-picker"
						   data-default-color="#ffffff"
						   placeholder="#ffffff">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_style_card_radius"><?php esc_html_e( 'Card Border Radius', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="smartrec_style_card_radius"
						   name="smartrec_style_card_radius"
						   value="<?php echo esc_attr( $this->settings->get( 'style_card_radius', '' ) ); ?>"
						   placeholder="8px"
						   class="small-text">
					<p class="description"><?php esc_html_e( 'CSS value, e.g. 8px, 0, 12px.', 'smartrec' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_style_card_shadow"><?php esc_html_e( 'Card Shadow', 'smartrec' ); ?></label>
				</th>
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
				<th scope="row">
					<label for="smartrec_style_gap"><?php esc_html_e( 'Grid Gap', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="smartrec_style_gap"
						   name="smartrec_style_gap"
						   value="<?php echo esc_attr( $this->settings->get( 'style_gap', '' ) ); ?>"
						   placeholder="16px"
						   class="small-text">
					<p class="description"><?php esc_html_e( 'Space between product cards. CSS value, e.g. 16px, 1rem, 20px.', 'smartrec' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="smartrec_style_title_size"><?php esc_html_e( 'Widget Title Font Size', 'smartrec' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="smartrec_style_title_size"
						   name="smartrec_style_title_size"
						   value="<?php echo esc_attr( $this->settings->get( 'style_title_size', '' ) ); ?>"
						   placeholder="18px"
						   class="small-text">
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Custom CSS', 'smartrec' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Add custom CSS rules. These will be scoped to .smartrec-widget elements. Use this to fine-tune the appearance to match your theme.', 'smartrec' ); ?></p>
		<textarea id="smartrec_custom_css"
				  name="smartrec_custom_css"
				  rows="10"
				  class="large-text code"
				  placeholder="<?php esc_attr_e( "/* Example: */\n.smartrec-widget__item-title {\n    font-family: inherit;\n    font-size: 15px;\n}\n.smartrec-widget__item-btn {\n    border-radius: 4px;\n}", 'smartrec' ); ?>"
		><?php echo esc_textarea( $this->settings->get( 'custom_css', '' ) ); ?></textarea>
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
