<?php
/**
 * Site header.
 *
 * Mirrors the source storefront: promotional ribbon, sticky header with brand,
 * desktop navigation, cart + mobile toggle, and an absolutely positioned mobile
 * navigation panel.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="profile" href="https://gmpg.org/xfn/11" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="ah-page">

	<?php
	$amino_heaven_ticker = get_theme_mod(
		'amino_heaven_sale_ticker_text',
		__( 'Fall Sale 30% Off Sitewide - No Code Needed', 'amino-heaven' )
	);
	?>
	<?php if ( '' !== trim( (string) $amino_heaven_ticker ) ) : ?>
		<div class="sale-ticker">
			<div><?php echo esc_html( $amino_heaven_ticker ); ?></div>
		</div>
	<?php endif; ?>

	<header class="site-header">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			$amino_heaven_logo_id = (int) get_theme_mod( 'custom_logo' );

			if ( $amino_heaven_logo_id ) {
				echo wp_get_attachment_image(
					$amino_heaven_logo_id,
					'thumbnail',
					false,
					array(
						'class' => 'ah-brand-logo',
						'alt'   => '',
					)
				);
			} else {
				printf(
					'<img class="ah-brand-logo" src="%1$s" alt="" width="54" height="54" decoding="async" />',
					esc_url( AMINO_HEAVEN_URI . 'assets/images/amino-heaven-logo.jpeg' )
				);
			}
			?>
			<span>
				<b><?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?></b>
				<small><?php esc_html_e( 'Research use only', 'amino-heaven' ); ?></small>
			</span>
		</a>

		<nav class="desktop-nav" aria-label="<?php esc_attr_e( 'Primary', 'amino-heaven' ); ?>">
			<?php amino_heaven_primary_nav(); ?>
		</nav>

		<div class="ah-header-actions">
			<?php amino_heaven_header_cart_link(); ?>
			<button
				type="button"
				class="ah-menu-toggle"
				aria-controls="ah-mobile-nav"
				aria-expanded="false"
				data-ah-menu-toggle
			>
				<span class="ah-menu-toggle-icon" data-ah-menu-icon="open"><?php amino_heaven_the_icon( 'menu', array( 'size' => 22 ) ); ?></span>
				<span class="ah-menu-toggle-icon" data-ah-menu-icon="close" hidden><?php amino_heaven_the_icon( 'close', array( 'size' => 22 ) ); ?></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Toggle menu', 'amino-heaven' ); ?></span>
			</button>
		</div>

		<nav id="ah-mobile-nav" class="mobile-nav" aria-label="<?php esc_attr_e( 'Mobile', 'amino-heaven' ); ?>" hidden data-ah-mobile-nav>
			<?php amino_heaven_primary_nav(); ?>
		</nav>

		<noscript>
			<style>
				/* Navigation: show the (otherwise JS-toggled) mobile menu inline. */
				.mobile-nav[hidden]{display:grid !important;position:static !important;grid-column:1/-1;min-width:0;margin-top:8px;border:0;box-shadow:none;padding:0}
				.ah-menu-toggle{display:none !important}
				.age-gate .ah-gate-js{display:none !important}
				/* Product cards: reveal every strength/pack combination and hide
				   the controls whose values only JavaScript can keep in sync. */
				.ah-dose-selector{display:none !important}
				.ah-pack-option.is-dose-hidden{display:grid !important}
				.ah-pack-dose{display:inline !important}
				.ah-add-dose{display:none !important}
				.ah-total{display:none !important}
			</style>
		</noscript>
	</header>

	<?php amino_heaven_after_header_hook(); ?>

	<main id="ah-content" class="ah-main">
