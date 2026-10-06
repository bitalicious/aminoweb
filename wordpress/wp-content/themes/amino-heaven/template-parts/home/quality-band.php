<?php
/**
 * Quality band.
 *
 * The source storefront's single "Batch-level testing" panel. Additional panels
 * can be appended with the `amino_heaven_quality_points` filter, and the heading
 * is Customizer driven.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

$amino_heaven_points = array(
	array(
		'number' => '01',
		'title'  => __( 'Batch-level testing', 'amino-heaven' ),
		'text'   => __( 'Every lot is analysed by an independent laboratory for identity, purity and endotoxin levels before release.', 'amino-heaven' ),
	),
);

/**
 * Filter the quality band panels.
 *
 * @param array $amino_heaven_points Panels.
 */
$amino_heaven_points = apply_filters( 'amino_heaven_quality_points', $amino_heaven_points );

$amino_heaven_quality_url = amino_heaven_resolve_default_nav_url( 'quality-coas' );
?>
<section id="quality" class="quality-band">
	<?php get_template_part( 'template-parts/header/helix' ); ?>

	<div class="quality-inner">
		<div class="quality-intro">
			<p class="eyebrow"><?php echo esc_html( get_theme_mod( 'amino_heaven_quality_eyebrow', __( 'Documented at every step', 'amino-heaven' ) ) ); ?></p>
			<h2><?php echo esc_html( get_theme_mod( 'amino_heaven_quality_heading', __( 'Quality you can verify', 'amino-heaven' ) ) ); ?></h2>

			<p class="quality-link">
				<a class="ah-button is-on-dark" href="<?php echo esc_url( $amino_heaven_quality_url ); ?>">
					<?php esc_html_e( 'View certificates of analysis', 'amino-heaven' ); ?>
					<?php amino_heaven_the_icon( 'chevron', array( 'size' => 16 ) ); ?>
				</a>
			</p>
		</div>

		<div class="quality-points">
			<?php foreach ( $amino_heaven_points as $amino_heaven_index => $amino_heaven_point ) : ?>
				<article>
					<span><?php echo esc_html( $amino_heaven_point['number'] ? $amino_heaven_point['number'] : sprintf( '%02d', $amino_heaven_index + 1 ) ); ?></span>
					<h3><?php echo esc_html( $amino_heaven_point['title'] ); ?></h3>
					<p><?php echo esc_html( $amino_heaven_point['text'] ); ?></p>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
