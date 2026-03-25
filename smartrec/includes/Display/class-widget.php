<?php
/**
 * WordPress Widget for recommendations.
 *
 * @package SmartRec\Display
 */

namespace SmartRec\Display;

use SmartRec\Core\Settings;
use SmartRec\Core\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Widget
 *
 * WordPress widget that displays product recommendations.
 */
class Widget extends \WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'smartrec_widget',
			__( 'SmartRec Recommendations', 'smartrec' ),
			array(
				'description' => __( 'Display intelligent product recommendations.', 'smartrec' ),
				'classname'   => 'smartrec-widget-container',
			)
		);
	}

	/**
	 * Front-end display.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$plugin = Plugin::get_instance();
		$manager  = $plugin->get_recommendation_manager();
		$settings = $plugin->get_settings();
		$renderer = new Renderer( $manager, $settings );

		$product_id = 0;
		if ( is_product() ) {
			global $product;
			if ( $product instanceof \WC_Product ) {
				$product_id = $product->get_id();
			}
		}

		$render_args = array(
			'engine'         => $instance['engine'] ?? 'personalized_mix',
			'limit'          => (int) ( $instance['limit'] ?? 4 ),
			'layout'         => $instance['layout'] ?? 'grid',
			'title'          => $instance['title'] ?? __( 'Recommended', 'smartrec' ),
			'columns'        => (int) ( $instance['columns'] ?? 2 ),
			'show_price'     => ! empty( $instance['show_price'] ),
			'show_rating'    => ! empty( $instance['show_rating'] ),
			'show_add_to_cart' => ! empty( $instance['show_add_to_cart'] ),
			'show_reason'    => ! empty( $instance['show_reason'] ),
		);

		$html = $renderer->render( 'custom_widget', $product_id, $render_args );

		if ( empty( $html ) ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Back-end form.
	 *
	 * @param array $instance Widget instance.
	 * @return void
	 */
	public function form( $instance ) {
		$title          = $instance['title'] ?? __( 'Recommended', 'smartrec' );
		$engine         = $instance['engine'] ?? 'personalized_mix';
		$limit          = $instance['limit'] ?? 4;
		$layout         = $instance['layout'] ?? 'grid';
		$columns        = $instance['columns'] ?? 2;
		$show_price     = isset( $instance['show_price'] ) ? $instance['show_price'] : true;
		$show_rating    = isset( $instance['show_rating'] ) ? $instance['show_rating'] : true;
		$show_add_to_cart = isset( $instance['show_add_to_cart'] ) ? $instance['show_add_to_cart'] : true;
		$show_reason    = isset( $instance['show_reason'] ) ? $instance['show_reason'] : true;

		$engines = array(
			'personalized_mix' => __( 'Personalized Mix', 'smartrec' ),
			'similar_products' => __( 'Similar Products', 'smartrec' ),
			'bought_together'  => __( 'Bought Together', 'smartrec' ),
			'viewed_together'  => __( 'Viewed Together', 'smartrec' ),
			'recently_viewed'  => __( 'Recently Viewed', 'smartrec' ),
			'trending'         => __( 'Trending', 'smartrec' ),
			'complementary'    => __( 'Complementary', 'smartrec' ),
		);

		$layouts = array(
			'grid'    => __( 'Grid', 'smartrec' ),
			'slider'  => __( 'Slider', 'smartrec' ),
			'list'    => __( 'List', 'smartrec' ),
			'minimal' => __( 'Minimal', 'smartrec' ),
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'smartrec' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'engine' ) ); ?>"><?php esc_html_e( 'Engine:', 'smartrec' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'engine' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'engine' ) ); ?>">
				<?php foreach ( $engines as $id => $name ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $engine, $id ); ?>><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'Number of products:', 'smartrec' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="number" min="1" max="20" value="<?php echo esc_attr( $limit ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'layout' ) ); ?>"><?php esc_html_e( 'Layout:', 'smartrec' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'layout' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'layout' ) ); ?>">
				<?php foreach ( $layouts as $id => $name ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $layout, $id ); ?>><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'columns' ) ); ?>"><?php esc_html_e( 'Columns:', 'smartrec' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'columns' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'columns' ) ); ?>" type="number" min="1" max="6" value="<?php echo esc_attr( $columns ); ?>">
		</p>
		<p>
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_price' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_price' ) ); ?>" <?php checked( $show_price ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_price' ) ); ?>"><?php esc_html_e( 'Show price', 'smartrec' ); ?></label>
		</p>
		<p>
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_rating' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_rating' ) ); ?>" <?php checked( $show_rating ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_rating' ) ); ?>"><?php esc_html_e( 'Show rating', 'smartrec' ); ?></label>
		</p>
		<p>
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_add_to_cart' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_add_to_cart' ) ); ?>" <?php checked( $show_add_to_cart ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_add_to_cart' ) ); ?>"><?php esc_html_e( 'Show Add to Cart', 'smartrec' ); ?></label>
		</p>
		<p>
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_reason' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_reason' ) ); ?>" <?php checked( $show_reason ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_reason' ) ); ?>"><?php esc_html_e( 'Show reason badge', 'smartrec' ); ?></label>
		</p>
		<?php
	}

	/**
	 * Update widget settings.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title'          => sanitize_text_field( $new_instance['title'] ?? '' ),
			'engine'         => sanitize_text_field( $new_instance['engine'] ?? 'personalized_mix' ),
			'limit'          => min( 20, max( 1, (int) ( $new_instance['limit'] ?? 4 ) ) ),
			'layout'         => sanitize_text_field( $new_instance['layout'] ?? 'grid' ),
			'columns'        => min( 6, max( 1, (int) ( $new_instance['columns'] ?? 2 ) ) ),
			'show_price'     => ! empty( $new_instance['show_price'] ),
			'show_rating'    => ! empty( $new_instance['show_rating'] ),
			'show_add_to_cart' => ! empty( $new_instance['show_add_to_cart'] ),
			'show_reason'    => ! empty( $new_instance['show_reason'] ),
		);
	}
}
