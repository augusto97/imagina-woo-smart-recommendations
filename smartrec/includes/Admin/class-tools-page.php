<?php
/**
 * Tools page for the admin panel.
 *
 * @package SmartRec\Admin
 */

namespace SmartRec\Admin;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class ToolsPage
 *
 * Renders the tools page with maintenance actions and system information.
 */
class ToolsPage {

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
	 * Render the tools page.
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="smartrec-tools">
			<?php $this->render_actions(); ?>
			<?php $this->render_system_info(); ?>
		</div>
		<?php
	}

	/**
	 * Render the maintenance action buttons.
	 *
	 * @return void
	 */
	private function render_actions() {
		?>
		<h2><?php esc_html_e( 'Maintenance Tools', 'smartrec' ); ?></h2>

		<div class="smartrec-tools__actions" style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px;">
			<div class="smartrec-card" style="flex: 1; min-width: 250px; padding: 15px; border: 1px solid #ccd0d4; background: #fff;">
				<h3><?php esc_html_e( 'Rebuild Relationships', 'smartrec' ); ?></h3>
				<p><?php esc_html_e( 'Recalculate all product relationships (bought together, viewed together, similar) from tracking data.', 'smartrec' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'smartrec_action', 'smartrec_action_nonce' ); ?>
					<input type="hidden" name="smartrec_action" value="rebuild_relationships">
					<?php submit_button( __( 'Rebuild Relationships', 'smartrec' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<div class="smartrec-card" style="flex: 1; min-width: 250px; padding: 15px; border: 1px solid #ccd0d4; background: #fff;">
				<h3><?php esc_html_e( 'Clear Cache', 'smartrec' ); ?></h3>
				<p><?php esc_html_e( 'Remove all cached recommendation results. New recommendations will be computed on next page load.', 'smartrec' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'smartrec_action', 'smartrec_action_nonce' ); ?>
					<input type="hidden" name="smartrec_action" value="clear_cache">
					<?php submit_button( __( 'Clear Cache', 'smartrec' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<div class="smartrec-card" style="flex: 1; min-width: 250px; padding: 15px; border: 1px solid #ccd0d4; background: #fff; border-left: 3px solid #7f54b3;">
				<h3><?php esc_html_e( 'Import Order History', 'smartrec' ); ?></h3>
				<p><?php esc_html_e( 'Import existing WooCommerce orders as purchase events. This builds co-purchase relationships from your store\'s sales history. Run this once after installing the plugin.', 'smartrec' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'smartrec_action', 'smartrec_action_nonce' ); ?>
					<input type="hidden" name="smartrec_action" value="import_orders">
					<?php submit_button( __( 'Import Orders', 'smartrec' ), 'primary', 'submit', false ); ?>
				</form>
			</div>

			<div class="smartrec-card" style="flex: 1; min-width: 250px; padding: 15px; border: 1px solid #ccd0d4; background: #fff;">
				<h3><?php esc_html_e( 'Recount Scores', 'smartrec' ); ?></h3>
				<p><?php esc_html_e( 'Recalculate all product popularity and trending scores from tracking data.', 'smartrec' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'smartrec_action', 'smartrec_action_nonce' ); ?>
					<input type="hidden" name="smartrec_action" value="recount_scores">
					<?php submit_button( __( 'Recount Scores', 'smartrec' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the system information table.
	 *
	 * @return void
	 */
	private function render_system_info() {
		global $wpdb;

		$table_sizes = $this->get_table_sizes();
		$wc_version  = defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Not installed', 'smartrec' );
		?>
		<h2><?php esc_html_e( 'System Information', 'smartrec' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'PHP Version', 'smartrec' ); ?></strong></td>
					<td><?php echo esc_html( phpversion() ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WordPress Version', 'smartrec' ); ?></strong></td>
					<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'WooCommerce Version', 'smartrec' ); ?></strong></td>
					<td><?php echo esc_html( $wc_version ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Memory Limit', 'smartrec' ); ?></strong></td>
					<td><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'PHP Max Execution Time', 'smartrec' ); ?></strong></td>
					<td>
						<?php
						/* translators: %d: number of seconds */
						echo esc_html( sprintf( __( '%d seconds', 'smartrec' ), (int) ini_get( 'max_execution_time' ) ) );
						?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Database', 'smartrec' ); ?></strong></td>
					<td><?php echo esc_html( $wpdb->db_version() ); ?></td>
				</tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Database Table Sizes', 'smartrec' ); ?></h3>
		<?php if ( empty( $table_sizes ) ) : ?>
			<p><?php esc_html_e( 'No SmartRec tables found. Please deactivate and reactivate the plugin.', 'smartrec' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Table', 'smartrec' ); ?></th>
						<th><?php esc_html_e( 'Rows', 'smartrec' ); ?></th>
						<th><?php esc_html_e( 'Size', 'smartrec' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $table_sizes as $table_info ) : ?>
						<tr>
							<td><code><?php echo esc_html( $table_info['name'] ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( $table_info['rows'] ) ); ?></td>
							<td><?php echo esc_html( $table_info['size'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Get SmartRec database table sizes.
	 *
	 * @return array Array of table info with name, rows, and size.
	 */
	private function get_table_sizes() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'smartrec_events',
			$wpdb->prefix . 'smartrec_product_relationships',
			$wpdb->prefix . 'smartrec_product_scores',
			$wpdb->prefix . 'smartrec_user_profiles',
		);

		$result = array();
		foreach ( $tables as $table_name ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT TABLE_ROWS, DATA_LENGTH + INDEX_LENGTH AS total_bytes
					FROM information_schema.TABLES
					WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
					DB_NAME,
					$table_name
				)
			);

			if ( $row ) {
				$result[] = array(
					'name' => $table_name,
					'rows' => (int) $row->TABLE_ROWS,
					'size' => size_format( (int) $row->total_bytes, 2 ),
				);
			}
		}

		return $result;
	}
}
