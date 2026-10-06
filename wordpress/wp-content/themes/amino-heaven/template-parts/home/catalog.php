<?php
/**
 * Front-page catalog section.
 *
 * Renders native WooCommerce products through the shared product card, so the
 * homepage catalog and the shop archive are always the same catalog.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

$amino_heaven_limit = (int) get_theme_mod( 'amino_heaven_homepage_products', 24 );
$amino_heaven_limit = $amino_heaven_limit > 0 ? $amino_heaven_limit : 24;

$amino_heaven_args = array(
	'post_type'           => 'product',
	'post_status'         => 'publish',
	'posts_per_page'      => $amino_heaven_limit,
	'ignore_sticky_posts' => true,
	'orderby'             => array(
		'menu_order' => 'ASC',
		'title'      => 'ASC',
	),
	'tax_query'           => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- required by WooCommerce visibility rules.
	'meta_query'          => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- required by WooCommerce visibility rules.
);

$amino_heaven_featured = wc_get_featured_product_ids();

if ( ! empty( $amino_heaven_featured ) ) {
	// Restrict to featured products but keep the storefront catalog order.
	// `orderby => post__in` would instead follow wc_get_featured_product_ids()'s
	// arbitrary ID order, which does not match the source storefront's catalog.
	$amino_heaven_args['post__in'] = array_map( 'absint', $amino_heaven_featured );
}

// Respect WooCommerce's catalog visibility settings (out-of-stock hiding, etc.).
$amino_heaven_args = array_merge( $amino_heaven_args, WC()->query->get_tax_query() );
$amino_heaven_args['meta_query'] = WC()->query->get_meta_query();

$amino_heaven_catalog = new WP_Query( $amino_heaven_args );

if ( ! $amino_heaven_catalog->have_posts() ) {
	wp_reset_postdata();
	return;
}

$amino_heaven_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>
<section id="catalog" class="content-section ah-home-catalog">
	<div class="section-heading">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Curated compounds', 'amino-heaven' ); ?></p>
			<h2><?php esc_html_e( 'Featured compounds', 'amino-heaven' ); ?></h2>
			<p><?php esc_html_e( 'Best-moving vials available this week.', 'amino-heaven' ); ?></p>
		</div>
		<a href="<?php echo esc_url( $amino_heaven_shop_url ); ?>">
			<?php esc_html_e( 'View all', 'amino-heaven' ); ?>
			<?php amino_heaven_the_icon( 'chevron', array( 'size' => 16 ) ); ?>
		</a>
	</div>

	<?php
	amino_heaven_product_grid(
		$amino_heaven_catalog,
		array(
			'name'          => 'home-featured',
			'heading_level' => 'h3',
		)
	);
	?>
</section>
