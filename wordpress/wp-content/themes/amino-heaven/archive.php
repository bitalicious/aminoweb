<?php
/**
 * Archive template (categories, tags, dates, authors).
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

get_header();

$amino_heaven_description = get_the_archive_description();
?>

<div class="content-section ah-archive-section">
	<header class="ah-section-heading">
		<div>
			<p class="eyebrow"><?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?></p>
			<h1><?php the_archive_title(); ?></h1>
			<?php if ( '' !== trim( wp_strip_all_tags( $amino_heaven_description ) ) ) : ?>
				<div class="ah-archive-description"><?php echo wp_kses_post( $amino_heaven_description ); ?></div>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="ah-post-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'ah-post-card' ); ?>>
					<?php if ( has_post_thumbnail() ) : ?>
						<a class="ah-post-card-image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
							<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?>
						</a>
					<?php endif; ?>
					<div class="ah-post-card-copy">
						<p class="eyebrow"><?php echo esc_html( get_the_date() ); ?></p>
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
