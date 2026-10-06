<?php
/**
 * Single post template.
 *
 * Products are rendered by WooCommerce (see woocommerce.php); this handles
 * editorial posts.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="content-section ah-single-post-section">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'ah-entry' ); ?>>
			<header class="ah-page-header">
				<p class="eyebrow"><?php echo esc_html( get_the_date() ); ?></p>
				<h1 class="ah-page-title"><?php the_title(); ?></h1>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="ah-page-thumbnail"><?php the_post_thumbnail( 'large' ); ?></figure>
			<?php endif; ?>

			<div class="ah-entry-content entry-content">
				<?php the_content(); ?>
			</div>

			<footer class="ah-entry-footer">
				<?php the_tags( '<ul class="ah-tags"><li>', '</li><li>', '</li></ul>' ); ?>
			</footer>
		</article>

		<?php
		the_post_navigation(
			array(
				'prev_text' => esc_html__( 'Previous', 'amino-heaven' ),
				'next_text' => esc_html__( 'Next', 'amino-heaven' ),
			)
		);

		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
	endwhile;
	?>
</div>

<?php
get_footer();
