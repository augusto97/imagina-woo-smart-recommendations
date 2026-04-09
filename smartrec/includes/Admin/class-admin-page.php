<?php
/**
 * Main admin page and menu registration.
 *
 * @package SmartRec\Admin
 */

namespace SmartRec\Admin;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminPage
 *
 * Registers the admin menu and renders the admin pages.
 */
class AdminPage {

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

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Product meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_product_meta_box' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
	}

	/**
	 * Register admin menu under WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'SmartRec', 'smartrec' ),
			__( 'SmartRec', 'smartrec' ),
			'manage_woocommerce',
			'smartrec',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on product edit pages (for meta box). SmartRec page uses inline styles/scripts.
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'smartrec-admin',
			SMARTREC_PLUGIN_URL . 'assets/css/smartrec-admin.css',
			array(),
			SMARTREC_VERSION
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		// Handle settings save.
		if ( isset( $_POST['smartrec_save_settings'] ) && check_admin_referer( 'smartrec_settings', 'smartrec_nonce' ) ) {
			$this->save_settings();
		}

		// Handle admin actions.
		if ( isset( $_POST['smartrec_action'] ) && check_admin_referer( 'smartrec_action', 'smartrec_action_nonce' ) ) {
			$this->handle_admin_action( sanitize_text_field( wp_unslash( $_POST['smartrec_action'] ) ) );
		}
	}

	/**
	 * Render the main admin page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		$tabs = array(
			'dashboard'  => __( 'Dashboard', 'smartrec' ),
			'settings'   => __( 'Settings', 'smartrec' ),
			'shortcodes' => __( 'Shortcode Builder', 'smartrec' ),
			'rules'      => __( 'Complementary Rules', 'smartrec' ),
			'analytics'  => __( 'Analytics', 'smartrec' ),
			'tools'      => __( 'Tools', 'smartrec' ),
		);

		// Enqueue color picker assets.
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Inline CSS — guarantees styles load regardless of enqueue path issues.
		$css_file = SMARTREC_PLUGIN_DIR . 'assets/css/smartrec-admin.css';
		if ( file_exists( $css_file ) ) {
			echo '<style>' . file_get_contents( $css_file ) . '</style>' . "\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		?>
		<div class="wrap smartrec-admin">
			<h1><?php esc_html_e( 'SmartRec — Intelligent Product Recommendations', 'smartrec' ); ?>
				<span style="font-size:12px;font-weight:normal;color:#8c8f94;margin-left:8px;">v<?php echo esc_html( SMARTREC_VERSION ); ?></span>
			</h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=smartrec&tab=' . $tab_id ) ); ?>"
					   class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_name ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="smartrec-admin__content">
				<?php
				switch ( $active_tab ) {
					case 'dashboard':
						$this->render_dashboard();
						break;
					case 'settings':
						$this->render_settings();
						break;
					case 'shortcodes':
						$this->render_shortcode_builder();
						break;
					case 'rules':
						$this->render_rules();
						break;
					case 'analytics':
						$this->render_analytics();
						break;
					case 'tools':
						$this->render_tools();
						break;
				}
				?>
			</div>
		</div>
		<?php
		// Inline JS — guarantees scripts work regardless of enqueue issues.
		$js_file = SMARTREC_PLUGIN_DIR . 'assets/js/smartrec-admin.js';
		if ( file_exists( $js_file ) ) {
			echo '<script>' . file_get_contents( $js_file ) . '</script>' . "\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
	}

	/**
	 * Render dashboard tab.
	 *
	 * @return void
	 */
	private function render_dashboard() {
		global $wpdb;

		$events_24h = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}smartrec_events WHERE created_at >= %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
			)
		);

		$events_7d = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}smartrec_events WHERE created_at >= %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
			)
		);

		$clicks_7d = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}smartrec_events WHERE event_type = 'click' AND created_at >= %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
			)
		);

		$last_build   = $this->settings->get( 'last_relationship_build', __( 'Never', 'smartrec' ) );
		$last_cleanup = $this->settings->get( 'last_data_cleanup', __( 'Never', 'smartrec' ) );
		?>
		<div class="smartrec-dashboard">
			<div class="smartrec-dashboard__cards">
				<div class="smartrec-card">
					<h3><?php esc_html_e( 'Events (24h)', 'smartrec' ); ?></h3>
					<p class="smartrec-card__number"><?php echo esc_html( number_format_i18n( $events_24h ) ); ?></p>
				</div>
				<div class="smartrec-card">
					<h3><?php esc_html_e( 'Events (7d)', 'smartrec' ); ?></h3>
					<p class="smartrec-card__number"><?php echo esc_html( number_format_i18n( $events_7d ) ); ?></p>
				</div>
				<div class="smartrec-card">
					<h3><?php esc_html_e( 'Rec. Clicks (7d)', 'smartrec' ); ?></h3>
					<p class="smartrec-card__number"><?php echo esc_html( number_format_i18n( $clicks_7d ) ); ?></p>
				</div>
				<div class="smartrec-card">
					<h3><?php esc_html_e( 'Click Rate', 'smartrec' ); ?></h3>
					<p class="smartrec-card__number">
						<?php echo $events_7d > 0 ? esc_html( round( $clicks_7d / $events_7d * 100, 1 ) . '%' ) : '0%'; ?>
					</p>
				</div>
			</div>

			<div class="smartrec-dashboard__status">
				<h3><?php esc_html_e( 'System Status', 'smartrec' ); ?></h3>
				<table class="widefat striped">
					<tr>
						<td><?php esc_html_e( 'Plugin Status', 'smartrec' ); ?></td>
						<td><span class="smartrec-status smartrec-status--active"><?php esc_html_e( 'Active', 'smartrec' ); ?></span></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Tracking', 'smartrec' ); ?></td>
						<td><?php echo $this->settings->get( 'tracking_enabled', true ) ? '<span class="smartrec-status smartrec-status--active">' . esc_html__( 'Enabled', 'smartrec' ) . '</span>' : '<span class="smartrec-status smartrec-status--inactive">' . esc_html__( 'Disabled', 'smartrec' ) . '</span>'; ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Cache', 'smartrec' ); ?></td>
						<td><?php echo $this->settings->get( 'cache_enabled', true ) ? '<span class="smartrec-status smartrec-status--active">' . esc_html__( 'Enabled', 'smartrec' ) . '</span>' : '<span class="smartrec-status smartrec-status--inactive">' . esc_html__( 'Disabled', 'smartrec' ) . '</span>'; ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Last Relationship Build', 'smartrec' ); ?></td>
						<td><?php echo esc_html( $last_build ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Last Data Cleanup', 'smartrec' ); ?></td>
						<td><?php echo esc_html( $last_cleanup ); ?></td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings tab.
	 *
	 * @return void
	 */
	private function render_settings() {
		$settings_page = new SettingsPage( $this->settings );
		$settings_page->render();
	}

	/**
	 * Render shortcode builder tab.
	 *
	 * @return void
	 */
	private function render_shortcode_builder() {
		$presets = array(
			'smartrec_recently_viewed'  => array(
				'name'    => __( 'Recently Viewed', 'smartrec' ),
				'desc'    => __( 'Products the visitor recently browsed. Shows only to users with history.', 'smartrec' ),
				'engine'  => 'recently_viewed',
				'title'   => __( 'Recently viewed', 'smartrec' ),
				'example' => 'Amazon: "Pick up where you left off"',
			),
			'smartrec_for_you'          => array(
				'name'    => __( 'Recommended For You', 'smartrec' ),
				'desc'    => __( 'Personalized mix based on browsing, purchases, and preferences.', 'smartrec' ),
				'engine'  => 'personalized_mix',
				'title'   => __( 'Recommended for you', 'smartrec' ),
				'example' => 'Amazon: "Inspired by your browsing history"',
			),
			'smartrec_trending'         => array(
				'name'    => __( 'Trending Now', 'smartrec' ),
				'desc'    => __( 'Most popular products based on views and purchases. Works for all visitors.', 'smartrec' ),
				'engine'  => 'trending',
				'title'   => __( 'Trending now', 'smartrec' ),
				'example' => 'Amazon: "Best Sellers" / AliExpress: "Top Ranking"',
			),
			'smartrec_similar_to_viewed' => array(
				'name'    => __( 'Related To Viewed', 'smartrec' ),
				'desc'    => __( 'Products similar to what the visitor has viewed (same category, attributes).', 'smartrec' ),
				'engine'  => 'similar',
				'title'   => __( 'Related to what you viewed', 'smartrec' ),
				'example' => 'Amazon: "Related to items you\'ve viewed"',
			),
			'smartrec_bought_together'  => array(
				'name'    => __( 'Customers Also Bought', 'smartrec' ),
				'desc'    => __( 'Co-purchase analysis: products frequently bought together.', 'smartrec' ),
				'engine'  => 'bought_together',
				'title'   => __( 'Customers also bought', 'smartrec' ),
				'example' => 'Amazon: "Customers who bought this also bought"',
			),
			'smartrec_new_arrivals'     => array(
				'name'    => __( 'New Arrivals', 'smartrec' ),
				'desc'    => __( 'Recently added products with trending momentum.', 'smartrec' ),
				'engine'  => 'trending',
				'title'   => __( 'New arrivals', 'smartrec' ),
				'example' => 'AliExpress: "New Arrivals"',
			),
		);

		$layouts = array( 'grid' => __( 'Grid', 'smartrec' ), 'slider' => __( 'Slider', 'smartrec' ), 'list' => __( 'List', 'smartrec' ), 'minimal' => __( 'Minimal', 'smartrec' ) );
		?>
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Shortcode Builder', 'smartrec' ); ?></h3>
			<p class="smartrec-section__desc"><?php esc_html_e( 'Build shortcodes visually and copy them into any page, post, or widget. Ideal for creating homepage recommendation sections like Amazon, Temu, or AliExpress.', 'smartrec' ); ?></p>

			<!-- Builder form -->
			<div class="smartrec-builder">
				<div class="smartrec-builder__form">
					<div class="smartrec-builder__field">
						<label for="smartrec-builder-type"><?php esc_html_e( 'Block Type', 'smartrec' ); ?></label>
						<select id="smartrec-builder-type">
							<?php foreach ( $presets as $tag => $preset ) : ?>
								<option value="<?php echo esc_attr( $tag ); ?>"
										data-title="<?php echo esc_attr( $preset['title'] ); ?>"
										data-engine="<?php echo esc_attr( $preset['engine'] ); ?>">
									<?php echo esc_html( $preset['name'] ); ?>
								</option>
							<?php endforeach; ?>
							<option value="smartrec" data-title="<?php esc_attr_e( 'Recommended products', 'smartrec' ); ?>" data-engine="">
								<?php esc_html_e( 'Custom (advanced)', 'smartrec' ); ?>
							</option>
						</select>
					</div>
					<div class="smartrec-builder__field">
						<label for="smartrec-builder-title"><?php esc_html_e( 'Title', 'smartrec' ); ?></label>
						<input type="text" id="smartrec-builder-title" placeholder="<?php esc_attr_e( 'Recommended for you', 'smartrec' ); ?>">
					</div>
					<div class="smartrec-builder__row">
						<div class="smartrec-builder__field">
							<label for="smartrec-builder-limit"><?php esc_html_e( 'Products', 'smartrec' ); ?></label>
							<input type="number" id="smartrec-builder-limit" value="8" min="1" max="20" class="small-text">
						</div>
						<div class="smartrec-builder__field">
							<label for="smartrec-builder-columns"><?php esc_html_e( 'Columns', 'smartrec' ); ?></label>
							<input type="number" id="smartrec-builder-columns" value="4" min="1" max="6" class="small-text">
						</div>
						<div class="smartrec-builder__field">
							<label for="smartrec-builder-columns-tablet"><?php esc_html_e( 'Tablet', 'smartrec' ); ?></label>
							<input type="number" id="smartrec-builder-columns-tablet" value="2" min="1" max="6" class="small-text">
						</div>
						<div class="smartrec-builder__field">
							<label for="smartrec-builder-columns-mobile"><?php esc_html_e( 'Mobile', 'smartrec' ); ?></label>
							<input type="number" id="smartrec-builder-columns-mobile" value="1" min="1" max="6" class="small-text">
						</div>
					</div>
					<div class="smartrec-builder__row">
						<div class="smartrec-builder__field">
							<label for="smartrec-builder-layout"><?php esc_html_e( 'Layout', 'smartrec' ); ?></label>
							<select id="smartrec-builder-layout">
								<?php foreach ( $layouts as $lk => $ll ) : ?>
									<option value="<?php echo esc_attr( $lk ); ?>"><?php echo esc_html( $ll ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="smartrec-builder__field">
							<label for="smartrec-builder-loadmore"><?php esc_html_e( 'Load More (0=off)', 'smartrec' ); ?></label>
							<input type="number" id="smartrec-builder-loadmore" value="0" min="0" max="20" class="small-text">
						</div>
					</div>
					<div class="smartrec-builder__row">
						<label><input type="checkbox" id="smartrec-builder-price" checked> <?php esc_html_e( 'Price', 'smartrec' ); ?></label>
						<label><input type="checkbox" id="smartrec-builder-rating" checked> <?php esc_html_e( 'Rating', 'smartrec' ); ?></label>
						<label><input type="checkbox" id="smartrec-builder-cart" checked> <?php esc_html_e( 'Add to Cart', 'smartrec' ); ?></label>
						<label><input type="checkbox" id="smartrec-builder-reason"> <?php esc_html_e( 'Reason badge', 'smartrec' ); ?></label>
					</div>
				</div>

				<!-- Generated shortcode output -->
				<div class="smartrec-builder__output">
					<label><?php esc_html_e( 'Generated Shortcode', 'smartrec' ); ?></label>
					<div class="smartrec-builder__code-wrap">
						<code id="smartrec-builder-result"></code>
						<button type="button" class="button button-small" id="smartrec-builder-copy"><?php esc_html_e( 'Copy', 'smartrec' ); ?></button>
					</div>
					<p class="description" id="smartrec-builder-desc"></p>
				</div>
			</div>
		</div>

		<!-- Preset reference -->
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Quick Reference', 'smartrec' ); ?></h3>
			<p class="smartrec-section__desc"><?php esc_html_e( 'Copy any of these shortcodes directly into your pages. Use the builder above to customize them.', 'smartrec' ); ?></p>

			<div class="smartrec-presets">
				<?php foreach ( $presets as $tag => $preset ) : ?>
					<div class="smartrec-preset">
						<div class="smartrec-preset__info">
							<strong><?php echo esc_html( $preset['name'] ); ?></strong>
							<span class="smartrec-preset__example"><?php echo esc_html( $preset['example'] ); ?></span>
							<span class="smartrec-preset__desc"><?php echo esc_html( $preset['desc'] ); ?></span>
						</div>
						<code class="smartrec-preset__code">[<?php echo esc_html( $tag ); ?>]</code>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Homepage Example', 'smartrec' ); ?></h3>
			<p class="smartrec-section__desc"><?php esc_html_e( 'Paste this combination into your homepage to create an Amazon-style experience:', 'smartrec' ); ?></p>
			<pre class="smartrec-example-code">[smartrec_recently_viewed limit="8" columns="4" columns_tablet="2" columns_mobile="1" layout="slider"]

[smartrec_for_you limit="8" columns="4" columns_tablet="2" columns_mobile="1"]

[smartrec_trending limit="8" columns="4" columns_tablet="2" columns_mobile="2"]

[smartrec_bought_together limit="4" columns="4" columns_tablet="2" columns_mobile="1"]</pre>
		</div>
		<?php
	}

	/**
	 * Render complementary rules tab.
	 *
	 * @return void
	 */
	private function render_rules() {
		$rules = $this->settings->get( 'complementary_rules', array() );
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}

		// Build a template row for JS to use when adding new rules.
		$template_options = '';
		foreach ( $categories as $cat ) {
			$template_options .= '<option value="' . esc_attr( $cat->term_id ) . '">' . esc_html( $cat->name ) . '</option>';
		}
		$template_row = '<div class="smartrec-rule-row">'
			. '<div class="smartrec-rule-row__field"><label>' . esc_html__( 'Source category', 'smartrec' ) . '</label>'
			. '<select name="smartrec_rules[__INDEX__][source_category]">' . $template_options . '</select></div>'
			. '<span class="smartrec-rule-row__arrow">&rarr;</span>'
			. '<div class="smartrec-rule-row__field smartrec-rule-row__field--wide"><label>' . esc_html__( 'Complementary categories', 'smartrec' ) . '</label>'
			. '<select name="smartrec_rules[__INDEX__][complementary_categories][]" multiple size="4">' . $template_options . '</select>'
			. '<p class="description">' . esc_html__( 'Hold Ctrl/Cmd to select multiple.', 'smartrec' ) . '</p></div>'
			. '<div class="smartrec-rule-row__field"><label>' . esc_html__( 'Weight', 'smartrec' ) . '</label>'
			. '<input type="number" name="smartrec_rules[__INDEX__][weight]" value="0.5" min="0.1" max="1.0" step="0.1" class="small-text"></div>'
			. '<button type="button" class="button smartrec-remove-rule" title="' . esc_attr__( 'Remove rule', 'smartrec' ) . '">&times;</button>'
			. '</div>';
		?>
		<div class="smartrec-rules">
			<h2><?php esc_html_e( 'Complementary Category Rules', 'smartrec' ); ?></h2>
			<p><?php esc_html_e( 'Define which product categories complement each other for cross-sell recommendations. For example: "Laptops" → "Laptop Bags, Mouse, Keyboards".', 'smartrec' ); ?></p>

			<?php if ( empty( $categories ) ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'No product categories found. Please create WooCommerce product categories first.', 'smartrec' ); ?></p>
				</div>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'smartrec_settings', 'smartrec_nonce' ); ?>
					<input type="hidden" name="smartrec_save_settings" value="rules">

					<div id="smartrec-rules-container" data-template="<?php echo esc_attr( $template_row ); ?>">
						<?php if ( ! empty( $rules ) ) : ?>
							<?php foreach ( $rules as $index => $rule ) : ?>
								<div class="smartrec-rule-row">
									<div class="smartrec-rule-row__field">
										<label><?php esc_html_e( 'Source category', 'smartrec' ); ?></label>
										<select name="smartrec_rules[<?php echo esc_attr( $index ); ?>][source_category]">
											<?php foreach ( $categories as $cat ) : ?>
												<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $rule['source_category'] ?? '', $cat->term_id ); ?>>
													<?php echo esc_html( $cat->name ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>
									<span class="smartrec-rule-row__arrow">&rarr;</span>
									<div class="smartrec-rule-row__field smartrec-rule-row__field--wide">
										<label><?php esc_html_e( 'Complementary categories', 'smartrec' ); ?></label>
										<select name="smartrec_rules[<?php echo esc_attr( $index ); ?>][complementary_categories][]" multiple size="4">
											<?php foreach ( $categories as $cat ) : ?>
												<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo in_array( (int) $cat->term_id, array_map( 'intval', $rule['complementary_categories'] ?? array() ), true ) ? 'selected' : ''; ?>>
													<?php echo esc_html( $cat->name ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple.', 'smartrec' ); ?></p>
									</div>
									<div class="smartrec-rule-row__field">
										<label><?php esc_html_e( 'Weight', 'smartrec' ); ?></label>
										<input type="number" name="smartrec_rules[<?php echo esc_attr( $index ); ?>][weight]" value="<?php echo esc_attr( $rule['weight'] ?? 0.5 ); ?>" min="0.1" max="1.0" step="0.1" class="small-text">
									</div>
									<button type="button" class="button smartrec-remove-rule" title="<?php esc_attr_e( 'Remove rule', 'smartrec' ); ?>">&times;</button>
								</div>
							<?php endforeach; ?>
						<?php else : ?>
							<div class="smartrec-rules__empty">
								<p><?php esc_html_e( 'No rules defined yet. Click "Add Rule" to create your first complementary category rule.', 'smartrec' ); ?></p>
							</div>
						<?php endif; ?>
					</div>

					<p>
						<button type="button" class="button button-secondary" id="smartrec-add-rule">
							+ <?php esc_html_e( 'Add Rule', 'smartrec' ); ?>
						</button>
					</p>

					<?php submit_button( __( 'Save Rules', 'smartrec' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render analytics tab.
	 *
	 * @return void
	 */
	private function render_analytics() {
		$analytics_page = new AnalyticsPage( $this->settings );
		$analytics_page->render();
	}

	/**
	 * Render tools tab.
	 *
	 * @return void
	 */
	private function render_tools() {
		$tools_page = new ToolsPage( $this->settings );
		$tools_page->render();
	}

	/**
	 * Save settings from POST data.
	 *
	 * @return void
	 */
	private function save_settings() {
		$save_type = sanitize_text_field( wp_unslash( $_POST['smartrec_save_settings'] ) );

		if ( 'rules' === $save_type ) {
			$rules = array();
			if ( isset( $_POST['smartrec_rules'] ) && is_array( $_POST['smartrec_rules'] ) ) {
				foreach ( $_POST['smartrec_rules'] as $rule ) {
					$rules[] = array(
						'source_category'          => absint( $rule['source_category'] ?? 0 ),
						'complementary_categories' => array_map( 'absint', $rule['complementary_categories'] ?? array() ),
						'weight'                   => max( 0.1, min( 1.0, (float) ( $rule['weight'] ?? 0.5 ) ) ),
					);
				}
			}
			$this->settings->set( 'complementary_rules', $rules );
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Rules saved.', 'smartrec' ) . '</p></div>';
				}
			);
			return;
		}

		// Simple settings (bool, int, string).
		$settings_map = array(
			'enabled'              => 'bool',
			'default_limit'        => 'int',
			'default_layout'       => 'string',
			'tracking_enabled'     => 'bool',
			'tracking_method'      => 'string',
			'respect_dnt'          => 'bool',
			'cookie_consent'       => 'string',
			'data_retention_days'  => 'int',
			'cache_enabled'        => 'bool',
			'cache_ttl_product'    => 'int',
			'cache_ttl_category'   => 'int',
			'cache_ttl_cart'       => 'int',
			'use_wc_template'      => 'bool',
			'inherit_theme_fonts'  => 'bool',
			'ajax_loading'         => 'bool',
			'show_price'           => 'bool',
			'show_rating'          => 'bool',
			'show_add_to_cart'     => 'bool',
			'show_reason'          => 'bool',
			'debug_mode'           => 'bool',
			'delete_data_on_uninstall' => 'bool',
			'engine_similar_enabled'        => 'bool',
			'engine_bought_together_enabled' => 'bool',
			'engine_viewed_together_enabled' => 'bool',
			'engine_recently_viewed_enabled' => 'bool',
			'engine_trending_enabled'       => 'bool',
			'engine_complementary_enabled'  => 'bool',
			'engine_personalized_enabled'   => 'bool',
			'location_single_product_below' => 'bool',
			'location_single_product_tabs'  => 'bool',
			'location_cart_page'            => 'bool',
			'location_cart_page_cross_sells' => 'bool',
			'location_checkout_page'        => 'bool',
			'location_category_page'        => 'bool',
			'location_empty_cart'           => 'bool',
			'location_thank_you_page'       => 'bool',
			'location_my_account'           => 'bool',
		);

		foreach ( $settings_map as $key => $type ) {
			$post_key = 'smartrec_' . $key;
			if ( 'bool' === $type ) {
				// Radio buttons send "0" or "1"; checkboxes only send when checked.
				if ( isset( $_POST[ $post_key ] ) && in_array( $_POST[ $post_key ], array( '0', '1' ), true ) ) {
					$this->settings->set( $key, '1' === $_POST[ $post_key ] );
				} else {
					$this->settings->set( $key, ! empty( $_POST[ $post_key ] ) );
				}
			} elseif ( 'int' === $type ) {
				$this->settings->set( $key, absint( $_POST[ $post_key ] ?? 0 ) );
			} else {
				$this->settings->set( $key, sanitize_text_field( wp_unslash( $_POST[ $post_key ] ?? '' ) ) );
			}
		}

		// Per-location settings (engine, title, limit, layout, columns).
		$location_keys = array(
			'single_product_below', 'single_product_tabs', 'cart_page',
			'cart_page_cross_sells', 'checkout_page', 'category_page',
			'empty_cart', 'thank_you_page', 'my_account',
		);

		$valid_engines = array(
			'personalized_mix', 'similar', 'bought_together', 'viewed_together',
			'recently_viewed', 'trending', 'complementary',
		);
		$valid_layouts = array( '', 'grid', 'slider', 'list', 'minimal' );

		$loc_engines  = array();
		$loc_titles   = array();
		$loc_limits   = array();
		$loc_layouts  = array();
		$loc_columns  = array();

		foreach ( $location_keys as $loc ) {
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput
			$engine  = isset( $_POST['smartrec_loc_engine'][ $loc ] ) ? sanitize_text_field( wp_unslash( $_POST['smartrec_loc_engine'][ $loc ] ) ) : '';
			$title   = isset( $_POST['smartrec_loc_title'][ $loc ] ) ? sanitize_text_field( wp_unslash( $_POST['smartrec_loc_title'][ $loc ] ) ) : '';
			$limit   = isset( $_POST['smartrec_loc_limit'][ $loc ] ) ? absint( $_POST['smartrec_loc_limit'][ $loc ] ) : 0;
			$layout  = isset( $_POST['smartrec_loc_layout'][ $loc ] ) ? sanitize_text_field( wp_unslash( $_POST['smartrec_loc_layout'][ $loc ] ) ) : '';
			$columns = isset( $_POST['smartrec_loc_columns'][ $loc ] ) ? absint( $_POST['smartrec_loc_columns'][ $loc ] ) : 0;
			// phpcs:enable

			$loc_engines[ $loc ] = in_array( $engine, $valid_engines, true ) ? $engine : 'personalized_mix';
			$loc_titles[ $loc ]  = $title;
			if ( $limit > 0 && $limit <= 20 ) {
				$loc_limits[ $loc ] = $limit;
			}
			if ( in_array( $layout, $valid_layouts, true ) ) {
				$loc_layouts[ $loc ] = $layout;
			}
			if ( $columns > 0 && $columns <= 6 ) {
				$loc_columns[ $loc ] = $columns;
			}
		}

		// Responsive columns (tablet, mobile) per location.
		$loc_cols_tablet = array();
		$loc_cols_mobile = array();
		foreach ( $location_keys as $loc ) {
			$tablet = isset( $_POST['smartrec_loc_columns_tablet'][ $loc ] ) ? absint( $_POST['smartrec_loc_columns_tablet'][ $loc ] ) : 0;
			$mobile = isset( $_POST['smartrec_loc_columns_mobile'][ $loc ] ) ? absint( $_POST['smartrec_loc_columns_mobile'][ $loc ] ) : 0;
			if ( $tablet > 0 && $tablet <= 6 ) {
				$loc_cols_tablet[ $loc ] = $tablet;
			}
			if ( $mobile > 0 && $mobile <= 6 ) {
				$loc_cols_mobile[ $loc ] = $mobile;
			}
		}

		// Load more and order per location.
		$loc_load_more = array();
		$loc_order     = array();
		foreach ( $location_keys as $loc ) {
			$lm_val = isset( $_POST['smartrec_loc_load_more'][ $loc ] ) ? absint( $_POST['smartrec_loc_load_more'][ $loc ] ) : 0;
			if ( $lm_val > 0 && $lm_val <= 20 ) {
				$loc_load_more[ $loc ] = $lm_val;
			}

			$order_val = isset( $_POST['smartrec_loc_order'][ $loc ] ) ? sanitize_text_field( wp_unslash( $_POST['smartrec_loc_order'][ $loc ] ) ) : 'score';
			if ( in_array( $order_val, array( 'score', 'random' ), true ) ) {
				$loc_order[ $loc ] = $order_val;
			}
		}

		$this->settings->set( 'location_engines', $loc_engines );
		$this->settings->set( 'location_titles', $loc_titles );
		$this->settings->set( 'location_limits', $loc_limits );
		$this->settings->set( 'location_layouts', $loc_layouts );
		$this->settings->set( 'location_columns', $loc_columns );
		$this->settings->set( 'location_columns_tablet', $loc_cols_tablet );
		$this->settings->set( 'location_columns_mobile', $loc_cols_mobile );
		$this->settings->set( 'location_load_more', $loc_load_more );
		$this->settings->set( 'location_order', $loc_order );

		// Appearance / style settings.
		$style_fields = array(
			'style_accent_color', 'style_card_bg', 'style_card_text',
			'style_title_color', 'style_badge_bg', 'style_badge_text',
			'style_btn_bg', 'style_btn_text', 'style_card_radius',
			'style_card_shadow', 'style_gap', 'style_title_size',
		);

		foreach ( $style_fields as $field ) {
			$value = isset( $_POST[ 'smartrec_' . $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'smartrec_' . $field ] ) ) : '';
			$this->settings->set( $field, $value );
		}

		// Custom CSS - use wp_strip_all_tags to remove any HTML but keep CSS.
		$custom_css = isset( $_POST['smartrec_custom_css'] ) ? wp_strip_all_tags( wp_unslash( $_POST['smartrec_custom_css'] ) ) : '';
		$this->settings->set( 'custom_css', $custom_css );

		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'smartrec' ) . '</p></div>';
			}
		);
	}

	/**
	 * Handle admin actions (tools tab).
	 *
	 * @param string $action Action to perform.
	 * @return void
	 */
	private function handle_admin_action( string $action ) {
		switch ( $action ) {
			case 'clear_cache':
				$cache = new \SmartRec\Cache\CacheManager( $this->settings );
				$count = $cache->clear_all();
				add_action(
					'admin_notices',
					function () use ( $count ) {
						/* translators: %d: number of cache entries cleared */
						echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Cache cleared. %d entries removed.', 'smartrec' ), $count ) ) . '</p></div>';
					}
				);
				break;

			case 'rebuild_relationships':
				$builder = new \SmartRec\Cron\RelationshipBuilder( $this->settings );
				$results = $builder->build_all();
				$this->settings->set( 'last_relationship_build', current_time( 'mysql' ) );
				add_action(
					'admin_notices',
					function () {
						echo '<div class="notice notice-success"><p>' . esc_html__( 'Relationships rebuilt successfully.', 'smartrec' ) . '</p></div>';
					}
				);
				break;

			case 'recount_scores':
				$builder = new \SmartRec\Cron\RelationshipBuilder( $this->settings );
				$count   = $builder->build_trending_scores();
				add_action(
					'admin_notices',
					function () use ( $count ) {
						/* translators: %d: number of products scored */
						echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Scores recounted. %d products updated.', 'smartrec' ), $count ) ) . '</p></div>';
					}
				);
				break;
		}
	}

	/**
	 * Add product meta box.
	 *
	 * @return void
	 */
	public function add_product_meta_box() {
		add_meta_box(
			'smartrec_product_settings',
			__( 'SmartRec Settings', 'smartrec' ),
			array( $this, 'render_product_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render product meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_product_meta_box( $post ) {
		wp_nonce_field( 'smartrec_product_meta', 'smartrec_product_nonce' );

		$exclude = get_post_meta( $post->ID, '_smartrec_exclude', true );
		$manual_related = get_post_meta( $post->ID, '_smartrec_related_products', true );
		$manual_complementary = get_post_meta( $post->ID, '_smartrec_complementary_products', true );
		?>
		<p>
			<label>
				<input type="checkbox" name="smartrec_exclude" value="1" <?php checked( $exclude, '1' ); ?>>
				<?php esc_html_e( 'Exclude from recommendations', 'smartrec' ); ?>
			</label>
		</p>
		<p>
			<label for="smartrec_related"><?php esc_html_e( 'Manual Related Products (IDs, comma-separated):', 'smartrec' ); ?></label>
			<input type="text" id="smartrec_related" name="smartrec_related_products" value="<?php echo esc_attr( is_array( $manual_related ) ? implode( ',', $manual_related ) : '' ); ?>" class="widefat">
		</p>
		<p>
			<label for="smartrec_complementary"><?php esc_html_e( 'Manual Complementary Products (IDs, comma-separated):', 'smartrec' ); ?></label>
			<input type="text" id="smartrec_complementary" name="smartrec_complementary_products" value="<?php echo esc_attr( is_array( $manual_complementary ) ? implode( ',', $manual_complementary ) : '' ); ?>" class="widefat">
		</p>
		<?php
	}

	/**
	 * Save product meta box data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_product_meta( $post_id ) {
		if ( ! isset( $_POST['smartrec_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smartrec_product_nonce'] ) ), 'smartrec_product_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		update_post_meta( $post_id, '_smartrec_exclude', ! empty( $_POST['smartrec_exclude'] ) ? '1' : '' );

		if ( isset( $_POST['smartrec_related_products'] ) ) {
			$related = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['smartrec_related_products'] ) ) ) ) );
			update_post_meta( $post_id, '_smartrec_related_products', $related );
		}

		if ( isset( $_POST['smartrec_complementary_products'] ) ) {
			$complementary = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['smartrec_complementary_products'] ) ) ) ) );
			update_post_meta( $post_id, '_smartrec_complementary_products', $complementary );
		}

		// Invalidate cache for this product.
		$cache = new \SmartRec\Cache\CacheManager( $this->settings );
		$cache->invalidate_product( $post_id );
	}
}
