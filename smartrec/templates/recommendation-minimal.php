<?php
/**
 * Template: Minimal Layout for SmartRec Recommendations
 *
 * Compact cards showing only product image and title. No buttons or extra details.
 *
 * Available variables:
 * @var WC_Product[] $products Array of WooCommerce product objects.
 * @var array        $reasons  Associative array of product_id => reason string.
 * @var string       $title    Widget title.
 * @var array        $settings Display settings (columns, css_class).
 *
 * @package SmartRec
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $products ) ) {
	return;
}

$columns   = ! empty( $settings['columns'] ) ? absint( $settings['columns'] ) : 4;
$css_class = ! empty( $settings['css_class'] ) ? ' ' . esc_attr( $settings['css_class'] ) : '';
?>

<div class="smartrec-widget smartrec-widget--minimal<?php echo $css_class; ?>" style="--smartrec-columns: <?php echo esc_attr( $columns ); ?>;">

	<?php if ( ! empty( $title ) ) : ?>
		<h2 class="smartrec-widget__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<div class="smartrec-widget__minimal">
		<?php foreach ( $products as $product ) :
			$product_id = $product->get_id();
			$permalink  = $product->get_permalink();
		?>
			<div class="smartrec-widget__item" data-product-id="<?php echo esc_attr( $product_id ); ?>">

				<a href="<?php echo esc_url( $permalink ); ?>"
				   class="smartrec-widget__link"
				   data-product-id="<?php echo esc_attr( $product_id ); ?>">

					<div class="smartrec-widget__image">
						<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
					</div>

					<h3 class="smartrec-widget__item-title">
						<?php echo esc_html( $product->get_name() ); ?>
					</h3>
				</a>

			</div>
		<?php endforeach; ?>
	</div>

</div>
