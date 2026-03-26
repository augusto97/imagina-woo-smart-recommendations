<?php
/**
 * Template: List Layout for SmartRec Recommendations
 *
 * Vertical list with image on the left and product details on the right.
 *
 * Available variables:
 * @var WC_Product[] $products Array of WooCommerce product objects.
 * @var array        $reasons  Associative array of product_id => reason string.
 * @var string       $title    Widget title.
 * @var array        $settings Display settings (show_price, show_rating, show_add_to_cart,
 *                             show_reason, columns, css_class).
 *
 * @package SmartRec
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $products ) ) {
	return;
}

$css_class = ! empty( $settings['css_class'] ) ? ' ' . esc_attr( $settings['css_class'] ) : '';
?>

<div class="smartrec-widget smartrec-widget--list<?php echo $css_class; ?>">

	<?php if ( ! empty( $title ) ) : ?>
		<h2 class="smartrec-widget__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<div class="smartrec-widget__list">
		<?php foreach ( $products as $product ) :
			$product_id = $product->get_id();
			$permalink  = $product->get_permalink();
			$reason     = isset( $reasons[ $product_id ] ) ? $reasons[ $product_id ] : '';
		?>
			<div class="smartrec-widget__item" data-product-id="<?php echo esc_attr( $product_id ); ?>">

				<a href="<?php echo esc_url( $permalink ); ?>"
				   class="smartrec-widget__link"
				   data-product-id="<?php echo esc_attr( $product_id ); ?>">

					<div class="smartrec-widget__item-image">
						<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
					</div>
				</a>

				<div class="smartrec-widget__item-content">

					<?php if ( ! empty( $settings['show_reason'] ) && ! empty( $reason ) ) : ?>
						<span class="smartrec-widget__badge"><?php echo esc_html( $reason ); ?></span>
					<?php endif; ?>

					<a href="<?php echo esc_url( $permalink ); ?>"
					   class="smartrec-widget__link"
					   data-product-id="<?php echo esc_attr( $product_id ); ?>">
						<h3 class="smartrec-widget__item-title">
							<?php echo esc_html( $product->get_name() ); ?>
						</h3>
					</a>

					<?php if ( ! empty( $settings['show_rating'] ) ) : ?>
						<div class="smartrec-widget__rating">
							<?php
							$rating = $product->get_average_rating();
							if ( $rating > 0 ) {
								echo wp_kses_post( wc_get_rating_html( $rating, $product->get_rating_count() ) );
							}
							?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $settings['show_price'] ) ) : ?>
						<div class="smartrec-widget__price">
							<?php echo wp_kses_post( $product->get_price_html() ); ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $settings['show_add_to_cart'] ) ) : ?>
						<div class="smartrec-widget__actions">
							<?php
							woocommerce_template_loop_add_to_cart(
								array(
									'quantity' => 1,
									'class'    => implode(
										' ',
										array_filter(
											array(
												'smartrec-widget__add-to-cart',
												'button',
												$product->is_purchasable() && $product->is_in_stock() ? 'add_to_cart_button' : '',
												$product->supports( 'ajax_add_to_cart' ) && $product->is_purchasable() && $product->is_in_stock() ? 'ajax_add_to_cart' : '',
											)
										)
									),
								)
							);
							?>
						</div>
					<?php endif; ?>

				</div>

			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( ! empty( $settings['load_more'] ) ) : ?>
		<div class="smartrec-load-more">
			<button type="button"
					class="smartrec-load-more__btn"
					data-location="<?php echo esc_attr( $settings['location'] ?? '' ); ?>"
					data-product-id="<?php echo esc_attr( $settings['product_id'] ?? 0 ); ?>"
					data-engine="<?php echo esc_attr( $settings['engine'] ?? '' ); ?>"
					data-offset="<?php echo esc_attr( count( $products ) ); ?>"
					data-limit="<?php echo esc_attr( $settings['load_more_count'] ?? 4 ); ?>"
					data-layout="list"
					data-use-wc="0"
					data-columns="1">
				<?php echo esc_html( $settings['load_more_text'] ?? __( 'Load more', 'smartrec' ) ); ?>
			</button>
		</div>
	<?php endif; ?>

</div>
