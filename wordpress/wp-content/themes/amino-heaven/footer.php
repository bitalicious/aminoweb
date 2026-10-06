<?php
/**
 * Site footer.
 *
 * Reproduces the source storefront's four-column footer plus the three-paragraph
 * legal compliance statement and the legal bar. Column content is editable via
 * the Customizer, WP menus and widgets rather than hardcoded markup.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

$amino_heaven_tagline = get_theme_mod( 'amino_heaven_footer_tagline', __( 'Discover. Innovate. Elevate.', 'amino-heaven' ) );
$amino_heaven_support = get_theme_mod( 'amino_heaven_footer_support', __( 'Research support available Monday–Friday.', 'amino-heaven' ) );
$amino_heaven_email   = get_theme_mod( 'amino_heaven_contact_email', 'hello@aminoheaven.com' );
$amino_heaven_scam    = get_theme_mod( 'amino_heaven_scam_warning', __( 'Scam warning: anyone who contacts you first to solicit an order is a scammer.', 'amino-heaven' ) );
?>
	</main><!-- #ah-content -->

	<footer id="contact" class="ah-footer">
		<div class="footer-grid">
			<div class="footer-brand">
				<b><?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?></b>
				<p><?php echo esc_html( $amino_heaven_tagline ); ?></p>
				<p><?php echo esc_html( $amino_heaven_support ); ?></p>
				<?php if ( is_email( $amino_heaven_email ) ) : ?>
					<a href="<?php echo esc_url( 'mailto:' . $amino_heaven_email ); ?>"><?php echo esc_html( $amino_heaven_email ); ?></a>
				<?php endif; ?>
			</div>

			<div class="footer-column">
				<h3><?php esc_html_e( 'Quick links', 'amino-heaven' ); ?></h3>
				<?php amino_heaven_footer_nav(); ?>
			</div>

			<?php if ( get_theme_mod( 'amino_heaven_footer_newsletter_enabled', true ) ) : ?>
				<div class="footer-column footer-newsletter">
					<h3><?php esc_html_e( 'Newsletter', 'amino-heaven' ); ?></h3>
					<p><?php echo esc_html( get_theme_mod( 'amino_heaven_newsletter_intro', __( 'Restock alerts, new COAs and promo vials.', 'amino-heaven' ) ) ); ?></p>
					<?php amino_heaven_newsletter_form(); ?>
				</div>
			<?php endif; ?>

			<?php if ( is_active_sidebar( 'footer-extra' ) ) : ?>
				<div class="footer-column">
					<?php dynamic_sidebar( 'footer-extra' ); ?>
				</div>
			<?php endif; ?>

			<div class="footer-column">
				<h3><?php esc_html_e( 'Compliance', 'amino-heaven' ); ?></h3>
				<p><?php echo esc_html( amino_heaven_research_use_notice() ); ?></p>
				<?php if ( '' !== trim( (string) $amino_heaven_scam ) ) : ?>
					<p><?php echo esc_html( $amino_heaven_scam ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="compliance-statement">
			<?php foreach ( amino_heaven_compliance_statements() as $amino_heaven_statement ) : ?>
				<p><?php echo esc_html( $amino_heaven_statement ); ?></p>
			<?php endforeach; ?>
		</div>

		<div class="legal">
			<span>
				<?php
				printf(
					/* translators: %s: current year. */
					esc_html__( '© %s Amino Heaven. All rights reserved.', 'amino-heaven' ),
					esc_html( gmdate( 'Y' ) )
				);
				?>
			</span>
			<span><?php esc_html_e( 'RESEARCH USE ONLY · NOT FOR HUMAN OR ANIMAL CONSUMPTION', 'amino-heaven' ); ?></span>
			<?php if ( has_nav_menu( 'legal' ) ) : ?>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'legal',
						'depth'          => 1,
						'fallback_cb'    => false,
					)
				);
				?>
			<?php endif; ?>
		</div>
	</footer>
</div><!-- #page -->

<?php wp_footer(); ?>
</body>
</html>
