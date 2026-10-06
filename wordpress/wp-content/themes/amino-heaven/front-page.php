<?php
/**
 * Front page.
 *
 * Reproduces the source storefront's single-page composition: hero band, trust
 * strip, featured catalog, quality band — then the shared footer.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="ah-home">
	<?php get_template_part( 'template-parts/home/hero' ); ?>
	<?php get_template_part( 'template-parts/home/trust-strip' ); ?>
	<?php get_template_part( 'template-parts/home/catalog' ); ?>
	<?php get_template_part( 'template-parts/home/quality-band' ); ?>
</div>

<?php
get_footer();
