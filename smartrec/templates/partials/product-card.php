<?php
/**
 * Partial: Single product card for SmartRec.
 *
 * Used by both full templates and the Load More AJAX response.
 *
 * Available variables:
 * @var WC_Product $product   WooCommerce product object.
 * @var int        $product_id Product ID.
 * @var string     $reason    Reason string (e.g. "Trending").
 * @var array      $settings  Display settings.
 *
 * @package SmartRec
 */

defined( 'ABSPATH' ) || exit;

$permalink = $product->get_permalink();
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
