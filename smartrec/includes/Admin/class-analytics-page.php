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
 * Renders the analytics dashboard with event statistics and charts.
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
			<h2><?php esc_html_e( 'Analytics Dashboard', 'smartrec' ); ?></h2>

			<?php $this->render_event_counts(); ?>
			<?php $this->render_top_clicked_products(); ?>
			<?php $this->render_daily_clicks_chart(); ?>
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
			'view'     => __( 'Views', 'smartrec' ),
			'click'    => __( 'Clicks', 'smartrec' ),
			'cart_add' => __( 'Cart Adds', 'smartrec' ),
			'purchase' => __( 'Purchases', 'smartrec' ),
		);
		?>
		<h3><?php esc_html_e( 'Event Counts', 'smartrec' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Event Type', 'smartrec' ); ?></th>
					<th><?php esc_html_e( 'Last 24 Hours', 'smartrec' ); ?></th>
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
		?>
		<h3><?php esc_html_e( 'Top Clicked Products (Last 30 Days)', 'smartrec' ); ?></h3>
		<?php if ( empty( $top_products ) ) : ?>
			<p><?php esc_html_e( 'No click data available yet.', 'smartrec' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'smartrec' ); ?></th>
						<th><?php esc_html_e( 'Clicks', 'smartrec' ); ?></th>
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
		<?php endif; ?>
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

		// Build a map of date => count, filling in zero-days.
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
		?>
		<h3><?php esc_html_e( 'Daily Recommendation Clicks (Last 30 Days)', 'smartrec' ); ?></h3>
		<?php if ( 0 === $max_clicks ) : ?>
			<p><?php esc_html_e( 'No click data available yet.', 'smartrec' ); ?></p>
		<?php else : ?>
			<div class="smartrec-chart" style="display: flex; align-items: flex-end; gap: 2px; height: 200px; padding: 10px 0; border-bottom: 1px solid #ccc;">
				<?php foreach ( $chart_data as $date => $count ) :
					$height_pct = ( $max_clicks > 0 ) ? round( ( $count / $max_clicks ) * 100 ) : 0;
					$bar_height = max( $height_pct, 1 );
					$formatted_date = wp_date( get_option( 'date_format' ), strtotime( $date ) );
					?>
					<div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%;"
						 title="<?php echo esc_attr( $formatted_date . ': ' . number_format_i18n( $count ) . ' ' . __( 'clicks', 'smartrec' ) ); ?>">
						<div style="width: 100%; background-color: #7f54b3; min-height: 2px; height: <?php echo esc_attr( $bar_height ); ?>%;"></div>
					</div>
				<?php endforeach; ?>
			</div>
			<div style="display: flex; justify-content: space-between; font-size: 11px; color: #666; margin-top: 4px;">
				<span><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( '-29 days' ) ) ); ?></span>
				<span><?php echo esc_html( wp_date( get_option( 'date_format' ) ) ); ?></span>
			</div>
			<p class="description">
				<?php
				/* translators: %s: total click count */
				echo esc_html( sprintf( __( 'Total clicks in period: %s', 'smartrec' ), number_format_i18n( array_sum( $chart_data ) ) ) );
				?>
			</p>
		<?php endif; ?>
		<?php
	}
}
