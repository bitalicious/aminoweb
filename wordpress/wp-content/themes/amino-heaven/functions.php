<?php
/**
 * Amino Heaven theme bootstrap.
 *
 * Loads the theme's modules. Each module owns one concern so the theme stays
 * navigable:
 *
 *  - setup                 theme supports, menus, widget areas, content width
 *  - enqueue               front-end and editor assets
 *  - template-functions    small helpers used by templates
 *  - template-hooks        header/footer/content hook wiring
 *  - customizer            store-facing theme options
 *  - compliance            age gate + research-use notices
 *  - coa                   Certificate of Analysis product meta
 *  - woocommerce           WooCommerce integration
 *  - cli                   WP-CLI provisioning commands (migration tooling)
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

define( 'AMINO_HEAVEN_VERSION', '1.0.0' );
define( 'AMINO_HEAVEN_DIR', trailingslashit( get_template_directory() ) );
define( 'AMINO_HEAVEN_URI', trailingslashit( get_template_directory_uri() ) );

/**
 * Load a theme module from inc/.
 *
 * @param string $module Module file name without extension.
 */
function amino_heaven_require_module( $module ) {
	$path = AMINO_HEAVEN_DIR . 'inc/' . $module . '.php';

	if ( ! is_readable( $path ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html( sprintf( 'Missing theme module: inc/%s.php', $module ) ),
			esc_html( AMINO_HEAVEN_VERSION )
		);

		return;
	}

	require_once $path;
}

$amino_heaven_modules = array(
	'setup',
	'icons',
	'template-functions',
	'template-hooks',
	'enqueue',
	'seo',
	'customizer',
	'compliance',
	'coa',
	'newsletter',
	'woocommerce',
);

foreach ( $amino_heaven_modules as $amino_heaven_module ) {
	amino_heaven_require_module( $amino_heaven_module );
}
unset( $amino_heaven_modules, $amino_heaven_module );

// Provisioning/import tooling is only needed under WP-CLI.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	amino_heaven_require_module( 'class-amino-heaven-product-importer' );
	amino_heaven_require_module( 'class-amino-heaven-store-setup' );
	amino_heaven_require_module( 'class-amino-heaven-content-setup' );
	amino_heaven_require_module( 'cli' );
}
