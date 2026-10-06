<?php
/**
 * Catalog product card.
 *
 * Reproduces the source storefront's card: image, "NAME STRENGTH" title, stock
 * badge, research eyebrow, optional strength selector, struck-through list price,
 * bulk summary, COA button, 1/2/5-vial pack grid, quantity stepper and total.
 *
 * Commerce behaviour is 100% native WooCommerce:
 *
 *   - the pack grid is a radio group named `variation_id`, so the POST is
 *     understood by `WC_Form_Handler::add_to_cart_action()` with no JavaScript;
 *   - the quantity field is WooCommerce's own `quantity` request parameter;
 *   - every price shown comes from a real variation (`_regular_price`,
 *     `_sale_price`), so the cart total is always recalculated server-side.
 *
 * JavaScript only *enhances* the card (switching strengths, live totals). The
 * `is-dose-hidden` / `ah-pack-dose` classes are revealed by a `<noscript>` rule
 * so the control set is complete and correct without scripting.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}

// Cross-sell rows use the compact card.
if ( wc_get_loop_prop( 'ah_compact' ) ) {
	get_template_part( 'template-parts/product/card-compact' );

	return;
}

if ( ! $product->is_type( 'variable' ) || ! $product->is_purchasable() ) {
	get_template_part( 'template-parts/product/card-compact' );

	return;
}

$amino_heaven_card = amino_heaven_get_card_data( $product );

if ( empty( $amino_heaven_card['variations'] ) || ! $amino_heaven_card['default'] ) {
	get_template_part( 'template-parts/product/card-compact' );

	return;
}

$amino_heaven_default = $amino_heaven_card['variations'][ $amino_heaven_card['default'] ];
$amino_heaven_heading = (string) wc_get_loop_prop( 'ah_heading_level', 'h2' );
$amino_heaven_heading = in_array( $amino_heaven_heading, array( 'h2', 'h3', 'h4' ), true ) ? $amino_heaven_heading : 'h2';
$amino_heaven_uid     = 'ah-card-' . $product->get_id();
$amino_heaven_multi   = count( $amino_heaven_card['dose_labels'] ) > 1;

// The bulk line summarises the largest pack for the active strength.
$amino_heaven_bulk = $amino_heaven_default;
foreach ( $amino_heaven_card['variations'] as $amino_heaven_candidate ) {
	if ( $amino_heaven_candidate['dose'] === $amino_heaven_default['dose']
		&& (int) $amino_heaven_candidate['pack_count'] > (int) $amino_heaven_bulk['pack_count'] ) {
		$amino_heaven_bulk = $amino_heaven_candidate;
	}
}

$amino_heaven_js = array(
	'productId'  => $product->get_id(),
	'defaultId'  => (int) $amino_heaven_card['default'],
	'currency'   => amino_heaven_currency_config(),
	'variations' => array(),
);

foreach ( $amino_heaven_card['variations'] as $amino_heaven_id => $amino_heaven_variation ) {
	$amino_heaven_js['variations'][ (string) $amino_heaven_id ] = array(
		'dose'      => $amino_heaven_variation['dose'],
		'pack'      => $amino_heaven_variation['pack'],
		'packCount' => (int) $amino_heaven_variation['pack_count'],
		'price'     => (float) $amino_heaven_variation['price'],
		'regular'   => (float) $amino_heaven_variation['regular'],
		'onSale'    => (bool) $amino_heaven_variation['on_sale'],
		'save'      => (int) $amino_heaven_variation['save'],
		'image'     => (string) $amino_heaven_variation['image'],
		'srcset'    => (string) $amino_heaven_variation['image_srcset'],
		'sizes'     => (string) $amino_heaven_variation['image_sizes'],
	);
}

$amino_heaven_image_id = $product->get_image_id();
?>
<li <?php wc_product_class( 'ah-card', $product ); ?>>
	<a class="product-image ah-card-image" href="<?php echo esc_url( $amino_heaven_card['permalink'] ); ?>" tabindex="-1" aria-hidden="true">
		<?php
		if ( $amino_heaven_image_id ) {
			echo wp_get_attachment_image(
				$amino_heaven_image_id,
				'woocommerce_thumbnail',
				false,
				array(
					'class'              => 'ah-card-img',
					'data-ah-card-image' => '1',
					'loading'            => 'lazy',
					'decoding'           => 'async',
					'alt'                => '',
				)
			);
		} else {
			printf(
				'<img class="ah-card-img" data-ah-card-image="1" src="%s" alt="" loading="lazy" decoding="async" />',
				esc_url( wc_placeholder_img_src( 'woocommerce_thumbnail' ) )
			);
		}
		?>
	</a>

	<div class="product-copy ah-card-copy">
		<div class="ah-card-head">
			<<?php echo esc_attr( $amino_heaven_heading ); ?> class="ah-card-title">
				<a href="<?php echo esc_url( $amino_heaven_card['permalink'] ); ?>">
					<span data-ah-card-name><?php echo esc_html( $amino_heaven_card['name'] ); ?></span>
					<?php if ( '' !== $amino_heaven_card['strength'] ) : ?>
						<span class="ah-card-strength" data-ah-card-strength><?php echo esc_html( $amino_heaven_default['dose'] ); ?></span>
					<?php endif; ?>
				</a>
			</<?php echo esc_attr( $amino_heaven_heading ); ?>>
			<?php amino_heaven_stock_badge( $product ); ?>
		</div>

		<p class="eyebrow ah-card-eyebrow"><?php esc_html_e( 'Research compound', 'amino-heaven' ); ?></p>

		<?php if ( $amino_heaven_multi ) : ?>
			<div class="variant-selector ah-dose-selector" role="group" aria-label="<?php esc_attr_e( 'Strength', 'amino-heaven' ); ?>">
				<?php foreach ( $amino_heaven_card['dose_labels'] as $amino_heaven_dose ) : ?>
					<button
						type="button"
						class="ah-button is-outline<?php echo $amino_heaven_dose === $amino_heaven_default['dose'] ? ' active' : ''; ?>"
						data-ah-dose-option="<?php echo esc_attr( $amino_heaven_dose ); ?>"
						aria-pressed="<?php echo $amino_heaven_dose === $amino_heaven_default['dose'] ? 'true' : 'false'; ?>"
					><?php echo esc_html( $amino_heaven_dose ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<p class="price-line ah-price-line">
			<del data-ah-regular><?php amino_heaven_the_price( $amino_heaven_default['regular'] ); ?></del>
			<strong data-ah-price><?php amino_heaven_the_price( $amino_heaven_default['price'] ); ?></strong>
			<span>/ <?php esc_html_e( 'vial', 'amino-heaven' ); ?></span>
		</p>

		<p class="bulk-price ah-bulk-price">
			<span data-ah-bulk-label>
				<?php
				printf(
					/* translators: %d: number of vials. */
					esc_html__( '%d vials = ', 'amino-heaven' ),
					(int) $amino_heaven_bulk['pack_count']
				);
				?>
			</span>
			<del data-ah-bulk-regular><?php amino_heaven_the_price( $amino_heaven_bulk['regular'] ); ?></del>
			<strong data-ah-bulk-price><?php amino_heaven_the_price( $amino_heaven_bulk['price'] ); ?></strong>
		</p>

		<?php amino_heaven_coa_button( $product, array( 'class' => 'is-full' ) ); ?>

		<form
			class="ah-card-form cart"
			method="post"
			action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $amino_heaven_card['permalink'] ) ); ?>"
			data-ah-card-form
		>
			<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( (string) $product->get_id() ); ?>" />

			<fieldset class="pack-grid ah-pack-grid">
				<legend class="screen-reader-text"><?php esc_html_e( 'Pack size', 'amino-heaven' ); ?></legend>
				<?php
				foreach ( $amino_heaven_card['dose_labels'] as $amino_heaven_dose ) :
					foreach ( $amino_heaven_card['pack_labels'] as $amino_heaven_pack ) :

						$amino_heaven_variation_id = isset( $amino_heaven_card['doses'][ $amino_heaven_dose ]['packs'][ $amino_heaven_pack ] )
							? (int) $amino_heaven_card['doses'][ $amino_heaven_dose ]['packs'][ $amino_heaven_pack ]
							: 0;

						if ( ! $amino_heaven_variation_id ) {
							continue;
						}

						$amino_heaven_variation = $amino_heaven_card['variations'][ $amino_heaven_variation_id ];
						$amino_heaven_is_active = ( $amino_heaven_variation_id === (int) $amino_heaven_card['default'] );
						$amino_heaven_hide      = ( $amino_heaven_variation['dose'] !== $amino_heaven_default['dose'] );
						?>
						<label class="ah-pack-option<?php echo $amino_heaven_hide ? ' is-dose-hidden' : ''; ?>">
							<input
								type="radio"
								name="variation_id"
								value="<?php echo esc_attr( (string) $amino_heaven_variation_id ); ?>"
								data-ah-variation-radio
								data-ah-dose="<?php echo esc_attr( $amino_heaven_variation['dose'] ); ?>"
								data-ah-pack="<?php echo esc_attr( $amino_heaven_variation['pack'] ); ?>"
								<?php checked( $amino_heaven_is_active ); ?>
							/>
							<b>
								<span class="ah-pack-dose"><?php echo esc_html( $amino_heaven_variation['dose'] ); ?> &middot; </span>
								<?php echo esc_html( $amino_heaven_variation['pack'] ); ?>
							</b>
							<span class="ah-pack-price">
								<?php if ( $amino_heaven_variation['on_sale'] ) : ?>
									<del><?php amino_heaven_the_price( $amino_heaven_variation['regular'] ); ?></del>
								<?php endif; ?>
								<?php amino_heaven_the_price( $amino_heaven_variation['price'] ); ?>
							</span>
							<?php if ( $amino_heaven_variation['save'] > 0 ) : ?>
								<em>
									<?php
									printf(
										/* translators: %d: discount percentage. */
										esc_html__( 'Save %d%%', 'amino-heaven' ),
										(int) $amino_heaven_variation['save']
									);
									?>
								</em>
							<?php endif; ?>
						</label>
						<?php
					endforeach;
				endforeach;
				?>
			</fieldset>

			<div class="ah-card-buy">
				<div class="qty ah-qty">
					<button type="button" data-ah-qty="decrease" aria-label="<?php esc_attr_e( 'Decrease quantity', 'amino-heaven' ); ?>">
						<?php amino_heaven_the_icon( 'minus', array( 'size' => 14 ) ); ?>
					</button>
					<label class="screen-reader-text" for="<?php echo esc_attr( $amino_heaven_uid ); ?>-qty">
						<?php
						printf(
							/* translators: %s: product name. */
							esc_html__( 'Quantity of %s', 'amino-heaven' ),
							esc_html( $amino_heaven_card['name'] )
						);
						?>
					</label>
					<input
						type="number"
						id="<?php echo esc_attr( $amino_heaven_uid ); ?>-qty"
						class="ah-qty-input"
						name="quantity"
						value="1"
						min="1"
						step="1"
						inputmode="numeric"
						data-ah-qty-input
					/>
					<button type="button" data-ah-qty="increase" aria-label="<?php esc_attr_e( 'Increase quantity', 'amino-heaven' ); ?>">
						<?php amino_heaven_the_icon( 'plus', array( 'size' => 14 ) ); ?>
					</button>
				</div>

				<button type="submit" class="ah-button ah-add-button" data-ah-add>
					<?php amino_heaven_the_icon( 'bag', array( 'size' => 15 ) ); ?>
					<span data-ah-add-label>
						<?php esc_html_e( 'Add', 'amino-heaven' ); ?>
						<span class="ah-add-dose"><?php echo esc_html( $amino_heaven_default['dose'] ); ?></span>
					</span>
				</button>
			</div>

			<p class="ah-total">
				<?php esc_html_e( 'Total:', 'amino-heaven' ); ?>
				<span data-ah-total-value><?php amino_heaven_the_price( $amino_heaven_default['price'] ); ?></span>
			</p>

			<script type="application/json" class="ah-card-data">
				<?php echo wp_json_encode( $amino_heaven_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>
			</script>
		</form>
	</div>
</li>
