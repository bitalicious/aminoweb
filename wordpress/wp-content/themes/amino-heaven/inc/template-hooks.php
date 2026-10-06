<?php
/**
 * Template hooks and small markup affordances shared by every template.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Skip link, printed before the header so keyboard users can bypass navigation.
 */
function amino_heaven_skip_link() {
	?>
	<a class="ah-skip-link screen-reader-text" href="#ah-content">
		<?php esc_html_e( 'Skip to content', 'amino-heaven' ); ?>
	</a>
	<?php
}
add_action( 'wp_body_open', 'amino_heaven_skip_link', 1 );

/**
 * Fire the after-header hook that carries WooCommerce notices (and anything a
 * child theme wants to inject below the header).
 */
function amino_heaven_after_header_hook() {
	do_action( 'amino_heaven_after_header' );
}

/**
 * Add design classes to navigation menus without hardcoding markup.
 *
 * @param array $args wp_nav_menu() arguments.
 * @return array
 */
function amino_heaven_nav_menu_args( $args ) {
	if ( 'primary' === $args['theme_location'] ) {
		$args['container'] = false;
		$args['menu_class'] = 'ah-menu';
	}

	if ( 'footer' === $args['theme_location'] ) {
		$args['container'] = false;
		$args['menu_class'] = 'ah-footer-menu';
		$args['depth']      = 1;
	}

	if ( 'legal' === $args['theme_location'] ) {
		$args['container'] = false;
		$args['menu_class'] = 'ah-legal-menu';
		$args['depth']      = 1;
	}

	return $args;
}
add_filter( 'wp_nav_menu_args', 'amino_heaven_nav_menu_args' );

/**
 * The fallback navigation used before an administrator assigns a menu.
 *
 * Mirrors the source storefront's navigation so a fresh install looks right.
 *
 * @return array<int,array{label:string,path:string}> Label/path pairs.
 */
function amino_heaven_default_nav_items() {
	$items = array(
		array(
			'label' => __( 'Shop', 'amino-heaven' ),
			'path'  => 'shop',
		),
		array(
			'label' => __( 'Quality / COAs', 'amino-heaven' ),
			'path'  => 'quality-coas',
		),
		array(
			'label' => __( 'About', 'amino-heaven' ),
			'path'  => 'about',
		),
		array(
			'label' => __( 'Contact', 'amino-heaven' ),
			'path'  => 'contact',
		),
	);

	return (array) apply_filters( 'amino_heaven_default_nav_items', $items );
}

/**
 * Resolve a default nav item to a real URL, falling back to the home page.
 *
 * @param string $path Page slug or a WooCommerce page identifier.
 * @return string
 */
function amino_heaven_resolve_default_nav_url( $path ) {
	if ( 'shop' === $path && function_exists( 'wc_get_page_id' ) ) {
		$shop_id = wc_get_page_id( 'shop' );

		if ( $shop_id > 0 ) {
			return get_permalink( $shop_id );
		}
	}

	$page = get_page_by_path( $path );

	if ( $page instanceof WP_Post ) {
		return get_permalink( $page );
	}

	return home_url( '/' );
}

/**
 * Render the primary navigation, using a real WP menu when one is assigned.
 */
function amino_heaven_primary_nav() {
	if ( has_nav_menu( 'primary' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'depth'          => 2,
				'fallback_cb'    => false,
			)
		);

		return;
	}

	echo '<ul class="ah-menu">';
	foreach ( amino_heaven_default_nav_items() as $item ) {
		printf(
			'<li><a href="%1$s">%2$s</a></li>',
			esc_url( amino_heaven_resolve_default_nav_url( $item['path'] ) ),
			esc_html( $item['label'] )
		);
	}
	echo '</ul>';
}

/**
 * Render the footer "quick links" navigation.
 */
function amino_heaven_footer_nav() {
	if ( has_nav_menu( 'footer' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'footer',
				'depth'          => 1,
				'fallback_cb'    => false,
			)
		);

		return;
	}

	$items = amino_heaven_default_nav_items();

	// The source footer exposed a "Track Order" entry that the header did not.
	$items[] = array(
		'label' => __( 'Track Order', 'amino-heaven' ),
		'path'  => 'track-order',
	);

	$items[] = array(
		'label' => __( 'About Us', 'amino-heaven' ),
		'path'  => 'about',
	);

	echo '<ul class="ah-footer-menu">';
	foreach ( $items as $item ) {
		printf(
			'<li><a href="%1$s">%2$s</a></li>',
			esc_url( amino_heaven_resolve_default_nav_url( $item['path'] ) ),
			esc_html( $item['label'] )
		);
	}
	echo '</ul>';
}

/**
 * Add a helpful hint to the admin bar linking back to the storefront.
 *
 * @param WP_Admin_Bar $bar Admin bar instance.
 */
function amino_heaven_admin_bar_link( $bar ) {
	if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'wc_get_page_id' ) ) {
		return;
	}

	$bar->add_node(
		array(
			'id'    => 'amino-heaven-shop',
			'title' => __( 'Amino Heaven shop', 'amino-heaven' ),
			'href'  => wc_get_page_permalink( 'shop' ),
			'meta'  => array( 'title' => __( 'Open the storefront shop page', 'amino-heaven' ) ),
		)
	);
}
add_action( 'admin_bar_menu', 'amino_heaven_admin_bar_link', 80 );
