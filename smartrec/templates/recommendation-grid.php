<?php
/**
 * Template: Grid Layout for SmartRec Recommendations
 *
 * Available variables:
 * @var WC_Product[] $products Array of WooCommerce product objects.
 * @var array        $reasons  Associative array of product_id => reason string.
 * @var string       $title    Widget title.
 * @var array        $settings Display settings (show_price, show_rating, show_add_to_cart,
 *                             show_reason, columns, css_class, use_wc_template).
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

<div class="smartrec-widget smartrec-widget--grid<?php echo $css_class; ?>" style="--smartrec-columns: <?php echo esc_attr( $columns ); ?>;">

	<?php if ( ! empty( $title ) ) : ?>
		<h2 class="smartrec-widget__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<?php if ( ! empty( $settings['use_wc_template'] ) ) : ?>

		<div class="woocommerce smartrec-wc-products">
			<ul class="products columns-<?php echo esc_attr( $columns ); ?>">
				<?php
				// Use WooCommerce loop columns filter so themes pick up the right value.
				$smartrec_prev_columns = 0;
				$smartrec_set_columns  = static function () use ( $columns ) {
					return $columns;
				};
				add_filter( 'loop_shop_columns', $smartrec_set_columns, 999 );

				foreach ( $products as $product ) {
					$GLOBALS['post']    = get_post( $product->get_id() );
					$GLOBALS['product'] = $product;
					setup_postdata( $GLOBALS['post'] );

					wc_get_template_part( 'content', 'product' );
				}
				wp_reset_postdata();

				remove_filter( 'loop_shop_columns', $smartrec_set_columns, 999 );
				?>
			</ul>
		</div>

	<?php else : ?>

		<div class="smartrec-widget__grid">
			<?php foreach ( $products as $product ) :
				$product_id   = $product->get_id();
				$permalink    = $product->get_permalink();
				$reason       = isset( $reasons[ $product_id ] ) ? $reasons[ $product_id ] : '';
			?>
				<div class="smartrec-widget__item" data-product-id="<?php echo esc_attr( $product_id ); ?>">

					<?php if ( ! empty( $settings['show_reason'] ) && ! empty( $reason ) ) : ?>
						<span class="smartrec-widget__badge"><?php echo esc_html( $reason ); ?></span>
					<?php endif; ?>

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
			<?php endforeach; ?>
		</div>

	<?php endif; ?>

</div>
