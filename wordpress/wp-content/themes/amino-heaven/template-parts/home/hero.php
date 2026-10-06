<?php
/**
 * Front-page hero band.
 *
 * The copy is the front page's own content, so it is editable in the block
 * editor. On a fresh install `wp amino-heaven setup-content` seeds the source
 * storefront's exact wording. When the page has no content the fallback below
 * reproduces the source copy.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

$amino_heaven_front_id = (int) get_option( 'page_on_front' );
$amino_heaven_has_copy = false;

if ( $amino_heaven_front_id ) {
	$amino_heaven_front = get_post( $amino_heaven_front_id );
	$amino_heaven_has_copy = $amino_heaven_front instanceof WP_Post && '' !== trim( $amino_heaven_front->post_content );
}
?>
<section class="hero-band">
	<?php get_template_part( 'template-parts/header/helix' ); ?>

	<div class="hero-inner">
		<div class="hero-copy">
			<?php if ( $amino_heaven_has_copy ) : ?>
				<?php
				// Rendered through the block pipeline so editor formatting and
				// block styles are honoured.
				echo apply_filters( 'the_content', $amino_heaven_front->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered post content.
				?>
			<?php else : ?>
				<p class="eyebrow"><?php esc_html_e( 'Independent U.S. Lab Verification', 'amino-heaven' ); ?></p>
				<h1><?php esc_html_e( 'Research compounds you can actually verify', 'amino-heaven' ); ?></h1>
				<p><?php esc_html_e( 'Amino Heaven delivers American-synthesized, analytical-grade research peptides—each batch accompanied by an independent Certificate of Analysis from accredited U.S. laboratories. Explore. Discover. Pioneer.', 'amino-heaven' ); ?></p>
			<?php endif; ?>

			<div class="hero-actions">
				<a class="ah-button" href="#catalog">
					<?php esc_html_e( 'Browse the catalog', 'amino-heaven' ); ?>
					<?php amino_heaven_the_icon( 'chevron', array( 'size' => 16 ) ); ?>
				</a>
				<a class="ah-button is-outline" href="<?php echo esc_url( amino_heaven_resolve_default_nav_url( 'quality-coas' ) ); ?>">
					<?php esc_html_e( 'View COAs', 'amino-heaven' ); ?>
				</a>
			</div>

			<p class="ah-hero-disclaimer"><?php echo esc_html( amino_heaven_research_use_notice() ); ?></p>
		</div>

		<div class="hero-product">
			<div class="halo-ring" aria-hidden="true"></div>
			<img
				src="<?php echo esc_url( AMINO_HEAVEN_URI . 'assets/images/hero-vial.webp' ); ?>"
				alt="<?php esc_attr_e( 'Amino Heaven laboratory research vial', 'amino-heaven' ); ?>"
				width="1920"
				height="1920"
				decoding="async"
				fetchpriority="high"
			/>
			<span aria-hidden="true">
				<?php esc_html_e( 'VERIFIED', 'amino-heaven' ); ?><br />
				<?php esc_html_e( 'RESEARCH', 'amino-heaven' ); ?><br />
				<?php esc_html_e( 'MATERIAL', 'amino-heaven' ); ?>
			</span>
		</div>
	</div>
</section>
