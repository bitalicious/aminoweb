<?php
/**
 * Animated DNA / protein helix backdrop.
 *
 * Ported from the source storefront's `<HelixBackdrop />`. Purely decorative:
 * the SVG is hidden from assistive technology and its animation is disabled by
 * the `prefers-reduced-motion` rule in the stylesheet.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="helix-field" aria-hidden="true">
	<svg viewBox="0 0 1200 620" preserveAspectRatio="xMidYMid slice" focusable="false">
		<g class="helix-track">
			<path d="M-60 520C160 520 160 80 380 80S600 520 820 520s220-440 440-440" />
			<path d="M-60 80c220 0 220 440 440 440S600 80 820 80s220 440 440 440" />
			<?php for ( $i = 0; $i < 15; $i++ ) : ?>
				<?php
				$x1 = ( $i * 92 ) - 60;
				$x2 = ( $i * 92 ) + 60;
				$y1 = 90 + ( ( $i % 4 ) * 95 );
				$y2 = 530 - ( ( $i % 4 ) * 95 );
				?>
				<line x1="<?php echo esc_attr( (string) $x1 ); ?>" y1="<?php echo esc_attr( (string) $y1 ); ?>" x2="<?php echo esc_attr( (string) $x2 ); ?>" y2="<?php echo esc_attr( (string) $y2 ); ?>" />
			<?php endfor; ?>
		</g>
		<g class="molecule-nodes">
			<?php
			$amino_heaven_nodes = array(
				array( 105, 145 ),
				array( 235, 470 ),
				array( 390, 120 ),
				array( 535, 480 ),
				array( 690, 155 ),
				array( 845, 450 ),
				array( 1005, 120 ),
				array( 1120, 405 ),
			);

			foreach ( $amino_heaven_nodes as $amino_heaven_node ) :
				?>
				<circle cx="<?php echo esc_attr( (string) $amino_heaven_node[0] ); ?>" cy="<?php echo esc_attr( (string) $amino_heaven_node[1] ); ?>" r="8" />
			<?php endforeach; ?>
		</g>
	</svg>
</div>
