<?php
/**
 * WooCommerce catch-all template.
 *
 * WooCommerce routes every shop view (product archives, product taxonomies,
 * single products and the shop page) through this template when it exists in the
 * theme root. It keeps WooCommerce's documented hook contract intact:
 *
 *   woocommerce_before_main_content  → theme wrapper opens (see inc/woocommerce.php)
 *   woocommerce_content()            → the loop or the single-product summary
 *   woocommerce_after_main_content   → theme wrapper closes
 *
 * The cart, checkout and account pages are ordinary WordPress pages containing
 * WooCommerce blocks/shortcodes, so they render through page.php instead.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

get_header();

/**
 * Fires before the main WooCommerce content. The theme uses this to open its
 * content wrapper and print the catalog heading.
 */
do_action( 'woocommerce_before_main_content' );

woocommerce_content();

/**
 * Fires after the main WooCommerce content.
 */
do_action( 'woocommerce_after_main_content' );

/**
 * Fires after the main content, inside the wrapper. Nothing is hooked by
 * default: the design has no shop sidebar column.
 */
do_action( 'woocommerce_sidebar' );

get_footer();
