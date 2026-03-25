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
		if ( 'woocommerce_page_smartrec' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'smartrec-admin',
			SMARTREC_PLUGIN_URL . 'assets/css/smartrec-admin.css',
			array(),
			SMARTREC_VERSION
		);

		wp_enqueue_script(
			'smartrec-admin',
			SMARTREC_PLUGIN_URL . 'assets/js/smartrec-admin.js',
			array( 'jquery' ),
			SMARTREC_VERSION,
			true
		);

		wp_localize_script(
			'smartrec-admin',
			'smartrecAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'smartrec_admin' ),
				'i18n'    => array(
					'confirmClearCache' => __( 'Are you sure you want to clear all caches?', 'smartrec' ),
					'confirmRebuild'    => __( 'This will rebuild all product relationships. Continue?', 'smartrec' ),
					'saved'             => __( 'Settings saved.', 'smartrec' ),
				),
			)
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
			'dashboard' => __( 'Dashboard', 'smartrec' ),
			'settings'  => __( 'Settings', 'smartrec' ),
			'rules'     => __( 'Complementary Rules', 'smartrec' ),
			'analytics' => __( 'Analytics', 'smartrec' ),
			'tools'     => __( 'Tools', 'smartrec' ),
		);
		?>
		<div class="wrap smartrec-admin">
			<h1><?php esc_html_e( 'SmartRec — Intelligent Product Recommendations', 'smartrec' ); ?></h1>

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
		?>
		<div class="smartrec-rules">
			<h2><?php esc_html_e( 'Complementary Category Rules', 'smartrec' ); ?></h2>
			<p><?php esc_html_e( 'Define which product categories complement each other for cross-sell recommendations.', 'smartrec' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'smartrec_settings', 'smartrec_nonce' ); ?>
				<input type="hidden" name="smartrec_save_settings" value="rules">

				<div id="smartrec-rules-container">
					<?php if ( ! empty( $rules ) ) : ?>
						<?php foreach ( $rules as $index => $rule ) : ?>
							<div class="smartrec-rule-row">
								<select name="smartrec_rules[<?php echo esc_attr( $index ); ?>][source_category]">
									<?php foreach ( $categories as $cat ) : ?>
										<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $rule['source_category'] ?? '', $cat->term_id ); ?>>
											<?php echo esc_html( $cat->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<span>&rarr;</span>
								<select name="smartrec_rules[<?php echo esc_attr( $index ); ?>][complementary_categories][]" multiple>
									<?php foreach ( $categories as $cat ) : ?>
										<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo in_array( $cat->term_id, $rule['complementary_categories'] ?? array() ) ? 'selected' : ''; ?>>
											<?php echo esc_html( $cat->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<input type="number" name="smartrec_rules[<?php echo esc_attr( $index ); ?>][weight]" value="<?php echo esc_attr( $rule['weight'] ?? 0.5 ); ?>" min="0.1" max="1.0" step="0.1">
								<button type="button" class="button smartrec-remove-rule">&times;</button>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<p>
					<button type="button" class="button" id="smartrec-add-rule"><?php esc_html_e( 'Add Rule', 'smartrec' ); ?></button>
				</p>

				<?php submit_button( __( 'Save Rules', 'smartrec' ) ); ?>
			</form>
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

		// General settings.
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
			'location_category_page'        => 'bool',
			'location_empty_cart'           => 'bool',
			'location_thank_you_page'       => 'bool',
			'location_checkout_page'        => 'bool',
			'location_my_account'           => 'bool',
		);

		foreach ( $settings_map as $key => $type ) {
			if ( 'bool' === $type ) {
				$this->settings->set( $key, ! empty( $_POST[ 'smartrec_' . $key ] ) );
			} elseif ( 'int' === $type ) {
				$this->settings->set( $key, absint( $_POST[ 'smartrec_' . $key ] ?? 0 ) );
			} else {
				$this->settings->set( $key, sanitize_text_field( wp_unslash( $_POST[ 'smartrec_' . $key ] ?? '' ) ) );
			}
		}

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
