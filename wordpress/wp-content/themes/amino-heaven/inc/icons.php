<?php
/**
 * Inline SVG icon set.
 *
 * The source storefront used `lucide-react`. These are the same icon shapes
 * (lucide, ISC licensed) rendered as inline SVG so the theme ships no icon font
 * and no JavaScript icon runtime. Icons are decorative by default; pass a
 * label to expose them to assistive technology.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * The available icon paths.
 *
 * @return array<string,string> Map of icon name to inner SVG markup.
 */
function amino_heaven_icons() {
	$icons = array(
		'flask'       => '<path d="M10 2v7.31"/><path d="M14 9.3V1.99"/><path d="M8.5 2h7"/><path d="M14 9.3a6.5 6.5 0 1 1-4 0"/><path d="M5.52 16h12.96"/>',
		'shield'      => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
		'truck'       => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
		'bag'         => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
		'file'        => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
		'chevron'     => '<path d="m9 18 6-6-6-6"/>',
		'menu'        => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
		'close'       => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		'minus'       => '<path d="M5 12h14"/>',
		'plus'        => '<path d="M5 12h14"/><path d="M12 5v14"/>',
		'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
		'search'      => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'package'     => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
		'user'        => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		'mail'        => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
		'check'       => '<path d="M20 6 9 17l-5-5"/>',
		'info'        => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
		'alert'       => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
	);

	/**
	 * Filter the icon set so a child theme or plugin can extend it.
	 *
	 * @param array<string,string> $icons Icon name to inner SVG markup.
	 */
	return (array) apply_filters( 'amino_heaven_icons', $icons );
}

/**
 * Return an inline SVG icon.
 *
 * @param string $name       Icon name.
 * @param array  $args       {
 *     Optional. Rendering arguments.
 *
 *     @type int    $size  Pixel size. Default 20.
 *     @type string $class CSS class. Default 'ah-icon'.
 *     @type string $label Accessible label. When empty the icon is hidden from
 *                         assistive technology.
 * }
 * @return string SVG markup, or an empty string when the icon is unknown.
 */
function amino_heaven_get_icon( $name, $args = array() ) {
	$icons = amino_heaven_icons();

	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}

	$args = wp_parse_args(
		$args,
		array(
			'size'  => 20,
			'class' => 'ah-icon',
			'label' => '',
		)
	);

	$size = (int) $args['size'];

	$svg  = '<svg class="' . esc_attr( $args['class'] ) . '" width="' . esc_attr( $size ) . '" height="' . esc_attr( $size ) . '"';
	$svg .= ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"';
	$svg .= ' stroke-linecap="round" stroke-linejoin="round"';

	if ( '' === $args['label'] ) {
		$svg .= ' aria-hidden="true" focusable="false"';
	} else {
		$svg .= ' role="img" aria-label="' . esc_attr( $args['label'] ) . '"';
	}

	$svg .= '>' . $icons[ $name ] . '</svg>';

	return $svg;
}

/**
 * Print an inline SVG icon.
 *
 * @param string $name Icon name.
 * @param array  $args Rendering arguments. See amino_heaven_get_icon().
 */
function amino_heaven_the_icon( $name, $args = array() ) {
	echo amino_heaven_get_icon( $name, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG markup.
}
