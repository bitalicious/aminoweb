<?php
/**
 * Trust strip.
 *
 * Three claim tiles under the hero. Titles and copy are Customizer options so
 * store staff can retire or reword a claim (as the source roadmap did when the
 * "US Warehouse" item was removed) without editing PHP.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

$amino_heaven_items = array(
	array(
		'icon'  => 'flask',
		'title' => get_theme_mod( 'amino_heaven_trust_1_title', __( '≥99% Purity', 'amino-heaven' ) ),
		'text'  => get_theme_mod( 'amino_heaven_trust_1_text', __( 'HPLC & MS verified per batch', 'amino-heaven' ) ),
	),
	array(
		'icon'  => 'shield',
		'title' => get_theme_mod( 'amino_heaven_trust_2_title', __( 'Third-Party COAs', 'amino-heaven' ) ),
		'text'  => get_theme_mod( 'amino_heaven_trust_2_text', __( 'Independent lab reports published', 'amino-heaven' ) ),
	),
	array(
		'icon'  => 'truck',
		'title' => get_theme_mod( 'amino_heaven_trust_3_title', __( 'Ships same day if ordered before 1 PM PST', 'amino-heaven' ) ),
		'text'  => get_theme_mod( 'amino_heaven_trust_3_text', __( 'Prompt order processing', 'amino-heaven' ) ),
	),
);

$amino_heaven_items = array_filter(
	$amino_heaven_items,
	static function ( $item ) {
		return '' !== trim( (string) $item['title'] );
	}
);

/**
 * Filter the trust strip items.
 *
 * @param array $amino_heaven_items Trust items.
 */
$amino_heaven_items = apply_filters( 'amino_heaven_trust_items', $amino_heaven_items );

if ( empty( $amino_heaven_items ) ) {
	return;
}
?>
<section class="trust-strip" aria-label="<?php esc_attr_e( 'Why researchers buy from Amino Heaven', 'amino-heaven' ); ?>">
	<?php foreach ( $amino_heaven_items as $amino_heaven_item ) : ?>
		<div>
			<?php amino_heaven_the_icon( $amino_heaven_item['icon'], array( 'size' => 23 ) ); ?>
			<span>
				<b><?php echo esc_html( $amino_heaven_item['title'] ); ?></b>
				<?php if ( '' !== trim( (string) $amino_heaven_item['text'] ) ) : ?>
					<small><?php echo esc_html( $amino_heaven_item['text'] ); ?></small>
				<?php endif; ?>
			</span>
		</div>
	<?php endforeach; ?>
</section>
