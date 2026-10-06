<?php
/**
 * Search results template.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="content-section ah-search-section">
	<header class="ah-section-heading">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Search', 'amino-heaven' ); ?></p>
			<h1>
				<?php
				printf(
					/* translators: %s: search query. */
					esc_html__( 'Results for “%s”', 'amino-heaven' ),
					esc_html( get_search_query() )
				);
				?>
			</h1>
		</div>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="ah-post-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'ah-post-card' ); ?>>
					<div class="ah-post-card-copy">
						<p class="eyebrow"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? get_post_type() ); ?></p>
						<h2 class="ah-post-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24, '…' ) ); ?></p>
					</div>
				</article>
			<?php endwhile; ?>
		</div>

		<?php amino_heaven_pagination( $GLOBALS['wp_query'] ); ?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/content/content-none' ); ?>
	<?php endif; ?>
</div>

<?php
get_footer();
