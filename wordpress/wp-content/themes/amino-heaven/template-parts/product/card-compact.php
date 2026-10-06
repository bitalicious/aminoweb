<?php
/**
 * Compact product card.
 *
 * Used for related products, upsells and cross-sells, where the full catalog
 * card (pack grid, quantity stepper, COA button) would overwhelm the row.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}

$amino_heaven_strength = amino_heaven_card_strength( $product );
$amino_heaven_heading  = (string) wc_get_loop_prop( 'ah_heading_level', 'h3' );
$amino_heaven_heading  = in_array( $amino_heaven_heading, array( 'h2', 'h3', 'h4' ), true ) ? $amino_heaven_heading : 'h3';
?>
<li <?php wc_product_class( 'ah-card ah-card--compact', $product ); ?>>
	<a class="product-image ah-card-image" href="<?php echo esc_url( $product->get_permalink() ); ?>" tabindex="-1" aria-hidden="true">
		<?php
		$amino_heaven_image_id = $product->get_image_id();

		if ( $amino_heaven_image_id ) {
			echo wp_get_attachment_image(
				$amino_heaven_image_id,
				'woocommerce_thumbnail',
				false,
				array(
					'class'    => 'ah-card-img',
					'loading'  => 'lazy',
					'decoding' => 'async',
					'alt'      => '',
				)
			);
		} else {
			printf(
				'<img class="ah-card-img" src="%s" alt="" loading="lazy" decoding="async" />',
				esc_url( wc_placeholder_img_src( 'woocommerce_thumbnail' ) )
			);
		}
		?>
	</a>

	<div class="product-copy ah-card-copy">
		<div class="ah-card-head">
			<<?php echo esc_attr( $amino_heaven_heading ); ?> class="ah-card-title">
				<a href="<?php echo esc_url( $product->get_permalink() ); ?>">
					<?php echo esc_html( $product->get_name() ); ?>
					<?php if ( '' !== $amino_heaven_strength ) : ?>
						<span class="ah-card-strength"><?php echo esc_html( $amino_heaven_strength ); ?></span>
					<?php endif; ?>
				</a>
			</<?php echo esc_attr( $amino_heaven_heading ); ?>>
			<?php amino_heaven_stock_badge( $product ); ?>
		</div>

		<p class="price-line ah-price-line"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>

		<a class="ah-button is-outline is-small is-full" href="<?php echo esc_url( $product->get_permalink() ); ?>">
			<?php echo esc_html( $product->add_to_cart_text() ); ?>
		</a>
	</div>
</li>
