<?php
/**
 * Product card used inside WooCommerce product loops.
 *
 * Deliberately thin: all layout lives in template-parts/product/card.php (or
 * card-compact.php for cross-sell rows) so the homepage catalog and the shop
 * archive render identical cards from one source.
 *
 * This template overrides WooCommerce 9.4.0.
 *
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

get_template_part( 'template-parts/product/card' );
