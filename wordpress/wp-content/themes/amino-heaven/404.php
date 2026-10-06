<?php
/**
 * 404 template.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

get_header();

$amino_heaven_shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>

<div class="content-section ah-404-section">
	<div class="ah-404">
		<p class="eyebrow"><?php esc_html_e( 'Error 404', 'amino-heaven' ); ?></p>
		<h1><?php esc_html_e( 'Page not found', 'amino-heaven' ); ?></h1>
		<p><?php esc_html_e( 'The page you are looking for does not exist or has been moved.', 'amino-heaven' ); ?></p>

		<div class="ah-404-actions">
			<a class="ah-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go home', 'amino-heaven' ); ?></a>
			<a class="ah-button is-outline" href="<?php echo esc_url( $amino_heaven_shop_url ); ?>"><?php esc_html_e( 'Browse the catalog', 'amino-heaven' ); ?></a>
		</div>

		<div class="ah-404-search">
			<h2><?php esc_html_e( 'Search the site', 'amino-heaven' ); ?></h2>
			<?php get_search_form(); ?>
		</div>
	</div>
</div>

<?php
get_footer();
