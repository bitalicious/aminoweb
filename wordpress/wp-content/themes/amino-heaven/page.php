<?php
/**
 * Default page template.
 *
 * Also serves the WooCommerce cart, checkout, account and shortcode pages.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="content-section ah-page-section">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'ah-entry' ); ?>>
			<?php if ( ! is_front_page() && ! amino_heaven_page_has_own_title() ) : ?>
				<header class="ah-page-header">
					<h1 class="ah-page-title"><?php the_title(); ?></h1>
				</header>
			<?php endif; ?>

			<?php if ( has_post_thumbnail() && ! is_front_page() ) : ?>
				<figure class="ah-page-thumbnail">
					<?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
				</figure>
			<?php endif; ?>

			<div class="ah-entry-content entry-content">
				<?php
				the_content();

				wp_link_pages(
					array(
						'before' => '<nav class="ah-page-links"><span>' . esc_html__( 'Pages:', 'amino-heaven' ) . '</span>',
						'after'  => '</nav>',
					)
				);
				?>
			</div>
		</article>
		<?php
	endwhile;

	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
	?>
</div>

<?php
get_footer();
