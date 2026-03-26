<?php
/**
 * Analytics page for the admin panel.
 *
 * @package SmartRec\Admin
 */

namespace SmartRec\Admin;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class AnalyticsPage
 *
 * Renders the analytics dashboard with event statistics, indexing status, and charts.
 */
class AnalyticsPage {

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
	 * Render the analytics page.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="smartrec-analytics">
			<?php $this->render_indexing_status(); ?>
			<?php $this->render_event_counts(); ?>
			<?php $this->render_engine_data_status(); ?>
			<?php $this->render_top_clicked_products(); ?>
			<?php $this->render_daily_clicks_chart(); ?>
		</div>
		<?php
	}

	/**
	 * Render the indexing/cron status section.
	 *
	 * @return void
	 */
	private function render_indexing_status() {
		$last_build   = $this->settings->get( 'last_relationship_build', '' );
		$last_cleanup = $this->settings->get( 'last_data_cleanup', '' );

		$next_build   = wp_next_scheduled( 'smartrec_build_relationships' );
		$next_cleanup = wp_next_scheduled( 'smartrec_data_cleanup' );
		?>
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Indexing Status', 'smartrec' ); ?></h3>
			<p class="smartrec-section__desc"><?php esc_html_e( 'SmartRec re-indexes product relationships every 6 hours via WP-Cron. Each run processes up to 500 records with a 120-second timeout, resuming on next run if needed.', 'smartrec' ); ?></p>

			<table class="widefat striped">
				<tbody>
					<tr>
						<td style="width:220px;font-weight:500;"><?php esc_html_e( 'Last Relationship Build', 'smartrec' ); ?></td>
						<td>
							<?php
							if ( ! empty( $last_build ) ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_build ) ) );
								$ago = human_time_diff( strtotime( $last_build ), current_time( 'timestamp' ) );
								echo ' <span class="description">(' . esc_html( $ago ) . ' ' . esc_html__( 'ago', 'smartrec' ) . ')</span>';
							} else {
								echo '<span style="color:#b32d2e;">' . esc_html__( 'Never — go to Tools tab and click "Rebuild Relationships"', 'smartrec' ) . '</span>';
							}
							?>
						</td>
					</tr>
					<tr>
						<td style="font-weight:500;"><?php esc_html_e( 'Next Scheduled Build', 'smartrec' ); ?></td>
						<td>
							<?php
							if ( $next_build ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_build ) );
							} else {
								echo '<span style="color:#b32d2e;">' . esc_html__( 'Not scheduled — cron may not be running', 'smartrec' ) . '</span>';
							}
							?>
						</td>
					</tr>
					<tr>
						<td style="font-weight:500;"><?php esc_html_e( 'Last Data Cleanup', 'smartrec' ); ?></td>
						<td>
							<?php echo ! empty( $last_cleanup ) ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_cleanup ) ) ) : esc_html__( 'Never', 'smartrec' ); ?>
						</td>
					</tr>
					<tr>
						<td style="font-weight:500;"><?php esc_html_e( 'Batch Size / Timeout', 'smartrec' ); ?></td>
						<td>
							<?php
							echo esc_html( $this->settings->get( 'cron_batch_size', 500 ) ) . ' ' . esc_html__( 'records per run', 'smartrec' );
							echo ' / ' . esc_html( $this->settings->get( 'cron_max_runtime', 120 ) ) . 's ' . esc_html__( 'max runtime', 'smartrec' );
							?>
						</td>
					</tr>
					<tr>
						<td style="font-weight:500;"><?php esc_html_e( 'Data Retention', 'smartrec' ); ?></td>
						<td>
							<?php
							/* translators: %d: number of days */
							printf( esc_html__( '%d days', 'smartrec' ), $this->settings->get( 'data_retention_days', 90 ) );
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render engine data availability.
	 *
	 * @return void
	 */
	private function render_engine_data_status() {
		global $wpdb;

		$relationships = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}smartrec_product_relationships" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$scores        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}smartrec_product_scores" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$profiles      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}smartrec_user_profiles" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$bought = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}smartrec_product_relationships WHERE relationship_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'bought_together'
			)
		);

		$viewed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}smartrec_product_relationships WHERE relationship_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'viewed_together'
			)
		);
		?>
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Engine Data', 'smartrec' ); ?></h3>
			<p class="smartrec-section__desc"><?php esc_html_e( 'How much data each engine has available. More data = better recommendations.', 'smartrec' ); ?></p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Engine', 'smartrec' ); ?></th>
						<th><?php esc_html_e( 'Data Available', 'smartrec' ); ?></th>
						<th><?php esc_html_e( 'Status', 'smartrec' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Bought Together', 'smartrec' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $bought ) . ' ' . __( 'relationships', 'smartrec' ) ); ?></td>
						<td><?php echo $bought > 0 ? '<span class="smartrec-status smartrec-status--active">' . esc_html__( 'Active', 'smartrec' ) . '</span>' : '<span class="smartrec-status smartrec-status--inactive">' . esc_html__( 'Needs more purchase data', 'smartrec' ) . '</span>'; ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Viewed Together', 'smartrec' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $viewed ) . ' ' . __( 'relationships', 'smartrec' ) ); ?></td>
						<td><?php echo $viewed > 0 ? '<span class="smartrec-status smartrec-status--active">' . esc_html__( 'Active', 'smartrec' ) . '</span>' : '<span class="smartrec-status smartrec-status--inactive">' . esc_html__( 'Needs more browsing data', 'smartrec' ) . '</span>'; ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Trending Products', 'smartrec' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $scores ) . ' ' . __( 'scored products', 'smartrec' ) ); ?></td>
						<td><?php echo $scores > 0 ? '<span class="smartrec-status smartrec-status--active">' . esc_html__( 'Active', 'smartrec' ) . '</span>' : '<span class="smartrec-status smartrec-status--inactive">' . esc_html__( 'Run "Recount Scores" in Tools', 'smartrec' ) . '</span>'; ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Personalized Mix', 'smartrec' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $profiles ) . ' ' . __( 'user profiles', 'smartrec' ) ); ?></td>
						<td><?php echo $profiles > 0 ? '<span class="smartrec-status smartrec-status--active">' . esc_html__( 'Active', 'smartrec' ) . '</span>' : '<span class="smartrec-status smartrec-status--inactive">' . esc_html__( 'Builds automatically with traffic', 'smartrec' ) . '</span>'; ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Similar Products', 'smartrec' ); ?></td>
						<td><?php esc_html_e( 'Uses product attributes', 'smartrec' ); ?></td>
						<td><span class="smartrec-status smartrec-status--active"><?php esc_html_e( 'Always available', 'smartrec' ); ?></span></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Recently Viewed', 'smartrec' ); ?></td>
						<td><?php esc_html_e( 'Session-based', 'smartrec' ); ?></td>
						<td><span class="smartrec-status smartrec-status--active"><?php esc_html_e( 'Always available', 'smartrec' ); ?></span></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render event counts by type and time period.
	 *
	 * @return void
	 */
	private function render_event_counts() {
		global $wpdb;

		$table  = $wpdb->prefix . 'smartrec_events';
		$types  = array( 'view', 'click', 'cart_add', 'purchase' );
		$periods = array(
			'24h' => gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) ),
			'7d'  => gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
			'30d' => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
		);

		$counts = array();
		foreach ( $types as $type ) {
			foreach ( $periods as $period_key => $since ) {
				$counts[ $type ][ $period_key ] = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE event_type = %s AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$type,
						$since
					)
				);
			}
		}

		$type_labels = array(
			'view'     => __( 'Product Views', 'smartrec' ),
			'click'    => __( 'Recommendation Clicks', 'smartrec' ),
			'cart_add' => __( 'Add to Cart', 'smartrec' ),
			'purchase' => __( 'Purchases', 'smartrec' ),
		);

		$total_events = array_sum( array_column( $counts, '30d' ) );
		?>
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Tracked Events', 'smartrec' ); ?></h3>
			<?php if ( 0 === $total_events ) : ?>
				<div class="notice notice-info inline" style="margin:0;">
					<p><?php esc_html_e( 'No events tracked yet. Events will appear here once visitors browse your store with tracking enabled. Make sure "Enable Tracking" is ON in Settings > Tracking.', 'smartrec' ); ?></p>
				</div>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Event Type', 'smartrec' ); ?></th>
							<th><?php esc_html_e( 'Last 24h', 'smartrec' ); ?></th>
							<th><?php esc_html_e( 'Last 7 Days', 'smartrec' ); ?></th>
							<th><?php esc_html_e( 'Last 30 Days', 'smartrec' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $types as $type ) : ?>
							<tr>
								<td><?php echo esc_html( $type_labels[ $type ] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $counts[ $type ]['24h'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $counts[ $type ]['7d'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $counts[ $type ]['30d'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php
				// CTR calculation.
				$views_30d  = $counts['view']['30d'];
				$clicks_30d = $counts['click']['30d'];
				if ( $views_30d > 0 ) {
					$ctr = round( ( $clicks_30d / $views_30d ) * 100, 1 );
					echo '<p class="description" style="margin-top:8px;">';
					/* translators: %s: click-through rate percentage */
					printf( esc_html__( 'Recommendation CTR (30 days): %s%%', 'smartrec' ), esc_html( $ctr ) );
					echo '</p>';
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render top clicked products table.
	 *
	 * @return void
	 */
	private function render_top_clicked_products() {
		global $wpdb;

		$table = $wpdb->prefix . 'smartrec_events';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$top_products = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, COUNT(*) AS click_count
				FROM {$table}
				WHERE event_type = 'click' AND created_at >= %s
				GROUP BY product_id
				ORDER BY click_count DESC
				LIMIT 10",
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			)
		);

		if ( empty( $top_products ) ) {
			return; // Don't show section if no data.
		}
		?>
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Top Clicked Recommendations (30 Days)', 'smartrec' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'smartrec' ); ?></th>
						<th style="width:100px;"><?php esc_html_e( 'Clicks', 'smartrec' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $top_products as $row ) : ?>
						<tr>
							<td>
								<?php
								$product_title = get_the_title( $row->product_id );
								if ( $product_title ) {
									$edit_link = get_edit_post_link( $row->product_id );
									if ( $edit_link ) {
										echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $product_title ) . '</a>';
									} else {
										echo esc_html( $product_title );
									}
									echo ' <span class="description">(#' . esc_html( $row->product_id ) . ')</span>';
								} else {
									/* translators: %d: product ID */
									echo esc_html( sprintf( __( 'Product #%d (deleted)', 'smartrec' ), $row->product_id ) );
								}
								?>
							</td>
							<td><?php echo esc_html( number_format_i18n( $row->click_count ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render a simple HTML bar chart of daily clicks over the last 30 days.
	 *
	 * @return void
	 */
	private function render_daily_clicks_chart() {
		global $wpdb;

		$table = $wpdb->prefix . 'smartrec_events';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$daily_clicks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS click_date, COUNT(*) AS click_count
				FROM {$table}
				WHERE event_type = 'click' AND created_at >= %s
				GROUP BY DATE(created_at)
				ORDER BY click_date ASC",
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			)
		);

		$daily_map = array();
		if ( ! empty( $daily_clicks ) ) {
			foreach ( $daily_clicks as $row ) {
				$daily_map[ $row->click_date ] = (int) $row->click_count;
			}
		}

		$max_clicks = 0;
		$chart_data = array();
		for ( $i = 29; $i >= 0; $i-- ) {
			$date  = gmdate( 'Y-m-d', strtotime( '-' . $i . ' days' ) );
			$count = $daily_map[ $date ] ?? 0;
			$chart_data[ $date ] = $count;
			if ( $count > $max_clicks ) {
				$max_clicks = $count;
			}
		}

		if ( 0 === $max_clicks ) {
			return; // Don't show chart with no data.
		}
		?>
		<div class="smartrec-section">
			<h3 class="smartrec-section__title"><?php esc_html_e( 'Daily Recommendation Clicks', 'smartrec' ); ?></h3>
			<div class="smartrec-chart" style="display:flex;align-items:flex-end;gap:2px;height:180px;padding:10px 0;border-bottom:1px solid #dcdcde;">
				<?php foreach ( $chart_data as $date => $count ) :
					$height_pct = round( ( $count / $max_clicks ) * 100 );
					$bar_height = max( $height_pct, 2 );
					$formatted_date = wp_date( get_option( 'date_format' ), strtotime( $date ) );
					?>
					<div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;"
						 title="<?php echo esc_attr( $formatted_date . ': ' . number_format_i18n( $count ) . ' ' . __( 'clicks', 'smartrec' ) ); ?>">
						<div style="width:100%;background:var(--smartrec-accent,#7f54b3);border-radius:2px 2px 0 0;min-height:2px;height:<?php echo esc_attr( $bar_height ); ?>%;"></div>
					</div>
				<?php endforeach; ?>
			</div>
			<div style="display:flex;justify-content:space-between;font-size:11px;color:#8c8f94;margin-top:6px;">
				<span><?php echo esc_html( wp_date( 'M j', strtotime( '-29 days' ) ) ); ?></span>
				<span><?php echo esc_html( wp_date( 'M j' ) ); ?></span>
			</div>
			<p class="description" style="margin-top:8px;">
				<?php
				/* translators: %s: total click count */
				printf( esc_html__( 'Total: %s clicks', 'smartrec' ), number_format_i18n( array_sum( $chart_data ) ) );
				?>
			</p>
		</div>
		<?php
	}
}
