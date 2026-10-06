<?php
/**
 * Product quantity input.
 *
 * Overrides woocommerce/global/quantity-input.php to match the storefront's
 * `- [n] +` stepper. Every WooCommerce hook and attribute from the core template
 * is preserved; the two buttons are `type="button"` so they can never submit the
 * surrounding cart/product form on their own — the theme's JavaScript updates the
 * native input value (and dispatches a `change` event) instead of inventing a
 * parallel quantity mechanism.
 *
 * This template overrides WooCommerce 10.1.0.
 *
 * @package WooCommerce\Templates
 * @version 10.1.0
 *
 * @var bool   $readonly If the input should be set to readonly mode.
 * @var string $type     The input type attribute.
 */

defined( 'ABSPATH' ) || exit;

/* translators: %s: Quantity. */
$label = ! empty( $args['product_name'] ) ? sprintf( esc_html__( '%s quantity', 'woocommerce' ), wp_strip_all_tags( $args['product_name'] ) ) : esc_html__( 'Quantity', 'woocommerce' );

// Defensive defaults: keeps the override working if a future WooCommerce adds
// or renames one of these locals.
$type     = isset( $type ) && $type ? $type : 'number';
$readonly = ! empty( $readonly );
?>
<div class="quantity ah-qty" data-ah-quantity>
	<?php
	/**
	 * Hook to output something before the quantity input field.
	 *
	 * @since 7.2.0
	 */
	do_action( 'woocommerce_before_quantity_input_field' );

	if ( ! $readonly ) :
		?>
		<button
			type="button"
			class="ah-qty-button"
			data-ah-qty="decrease"
			aria-label="<?php esc_attr_e( 'Decrease quantity', 'amino-heaven' ); ?>"
		>
			<?php amino_heaven_the_icon( 'minus', array( 'size' => 14 ) ); ?>
		</button>
		<?php
	endif;
	?>
	<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_attr( $label ); ?></label>
	<input
		type="<?php echo esc_attr( $type ); ?>"
		<?php echo $readonly ? 'readonly="readonly"' : ''; ?>
		id="<?php echo esc_attr( $input_id ); ?>"
		class="<?php echo esc_attr( join( ' ', (array) $classes ) ); ?>"
		name="<?php echo esc_attr( $input_name ); ?>"
		value="<?php echo esc_attr( $input_value ); ?>"
		aria-label="<?php esc_attr_e( 'Product quantity', 'amino-heaven' ); ?>"
		<?php if ( in_array( $type, array( 'text', 'search', 'tel', 'url', 'email', 'password' ), true ) ) : ?>
			size="4"
		<?php endif; ?>
		min="<?php echo esc_attr( $min_value ); ?>"
		<?php if ( 0 < $max_value ) : ?>
			max="<?php echo esc_attr( $max_value ); ?>"
		<?php endif; ?>
		<?php if ( ! $readonly ) : ?>
			step="<?php echo esc_attr( $step ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			inputmode="<?php echo esc_attr( $inputmode ); ?>"
			autocomplete="<?php echo esc_attr( isset( $autocomplete ) ? $autocomplete : 'on' ); ?>"
		<?php endif; ?>
	/>
	<?php
	if ( ! $readonly ) :
		?>
		<button
			type="button"
			class="ah-qty-button"
			data-ah-qty="increase"
			aria-label="<?php esc_attr_e( 'Increase quantity', 'amino-heaven' ); ?>"
		>
			<?php amino_heaven_the_icon( 'plus', array( 'size' => 14 ) ); ?>
		</button>
		<?php
	endif;

	/**
	 * Hook to output something after quantity input field
	 *
	 * @since 3.6.0
	 */
	do_action( 'woocommerce_after_quantity_input_field' );
	?>
</div>
