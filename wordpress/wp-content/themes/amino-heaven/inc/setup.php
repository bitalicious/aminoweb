<?php
/**
 * Theme setup: supports, menus, widget areas.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register theme supports and navigation locations.
 */
function amino_heaven_setup() {
	load_theme_textdomain( 'amino-heaven', AMINO_HEAVEN_DIR . 'languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );

	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);

	add_theme_support(
		'custom-logo',
		array(
			'height'               => 108,
			'width'                => 108,
			'flex-height'          => true,
			'flex-width'           => true,
			'unlink-homepage-logo' => false,
		)
	);

	// WooCommerce: the theme supplies its own gallery/lightbox handling.
	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 600,
			'single_image_width'    => 1000,
			'product_grid'          => array(
				'default_rows'    => 4,
				'min_rows'        => 1,
				'default_columns' => 3,
				'min_columns'     => 1,
				'max_columns'     => 4,
			),
		)
	);
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'amino-heaven' ),
			'footer'  => __( 'Footer quick links', 'amino-heaven' ),
			'legal'   => __( 'Footer legal links', 'amino-heaven' ),
		)
	);

	// Content width used by oEmbeds and wide blocks.
	$GLOBALS['content_width'] = apply_filters( 'amino_heaven_content_width', 1240 );
}
add_action( 'after_setup_theme', 'amino_heaven_setup' );

/**
 * Register widget areas.
 *
 * The design only ever needs a shop sidebar and a footer column area, so the
 * theme deliberately registers just those two rather than a widget area per
 * template.
 */
function amino_heaven_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Shop sidebar', 'amino-heaven' ),
			'id'            => 'shop-sidebar',
			'description'   => __( 'Shown beside the shop and product archives when populated.', 'amino-heaven' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);

	register_sidebar(
		array(
			'name'          => __( 'Footer extra', 'amino-heaven' ),
			'id'            => 'footer-extra',
			'description'   => __( 'Optional extra footer column, rendered after the newsletter column.', 'amino-heaven' ),
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'amino_heaven_widgets_init' );

/**
 * Set the content width in pixels, based on the theme's design.
 *
 * @global int $content_width
 */
function amino_heaven_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'amino_heaven_content_width', 1240 );
}
add_action( 'after_setup_theme', 'amino_heaven_content_width', 0 );

/**
 * Products are research materials, not consumer goods: the source storefront
 * has no customer reviews, so keep them off on fresh installs while leaving
 * the WooCommerce setting as the single source of truth.
 */
function amino_heaven_disable_product_reviews_by_default() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Only apply the opinionated default once, so an administrator who turns
	// reviews back on is not fought by the theme on every request.
	if ( get_option( 'amino_heaven_reviews_default_applied' ) ) {
		return;
	}

	update_option( 'woocommerce_enable_reviews', 'no' );
	update_option( 'woocommerce_enable_review_rating', 'no' );
	update_option( 'amino_heaven_reviews_default_applied', AMINO_HEAVEN_VERSION );
}
add_action( 'after_switch_theme', 'amino_heaven_disable_product_reviews_by_default' );

/**
 * Keep the base font stacks in one place so the Customizer can extend them
 * without duplicating the design tokens.
 *
 * @return array<string,string>
 */
function amino_heaven_font_stacks() {
	return array(
		'sans'    => '"Manrope", ui-sans-serif, system-ui, sans-serif',
		'display' => '"Barlow Condensed", ui-sans-serif, system-ui, sans-serif',
	);
}
