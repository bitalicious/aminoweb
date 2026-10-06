<?php
/**
 * Asset loading.
 *
 * Everything is versioned and cache-busted against the theme version + file
 * mtime so a rebuilt stylesheet is picked up immediately during development.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build a cache-busting version string for a theme asset.
 *
 * @param string $relative_path Path relative to the theme root.
 * @return string
 */
function amino_heaven_asset_version( $relative_path ) {
	$file = AMINO_HEAVEN_DIR . ltrim( $relative_path, '/' );

	if ( is_readable( $file ) ) {
		return AMINO_HEAVEN_VERSION . '.' . filemtime( $file );
	}

	return AMINO_HEAVEN_VERSION;
}

/**
 * Front-end styles and scripts.
 */
function amino_heaven_enqueue_assets() {
	// The main design system. Loaded last in the dependency chain so it can
	// override WooCommerce's own stylesheet without !important.
	wp_enqueue_style(
		'amino-heaven',
		AMINO_HEAVEN_URI . 'assets/css/theme.css',
		array(),
		amino_heaven_asset_version( 'assets/css/theme.css' )
	);

	// WooCommerce-specific presentation. Kept in its own file so it is only
	// requested where WooCommerce is actually rendering something.
	if ( amino_heaven_is_woocommerce_context() ) {
		wp_enqueue_style(
			'amino-heaven-woocommerce',
			AMINO_HEAVEN_URI . 'assets/css/woocommerce.css',
			array( 'amino-heaven' ),
			amino_heaven_asset_version( 'assets/css/woocommerce.css' )
		);
	}

	wp_enqueue_script(
		'amino-heaven',
		AMINO_HEAVEN_URI . 'assets/js/theme.js',
		array(),
		amino_heaven_asset_version( 'assets/js/theme.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	wp_localize_script(
		'amino-heaven',
		'aminoHeaven',
		array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'cartUrl'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'checkoutUrl' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
			'cookiePath'  => ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/',
			'ageCookie'   => AMINO_HEAVEN_AGE_COOKIE,
			'isWoo'       => amino_heaven_is_woocommerce_context(),
			'i18n'        => array(
				'addedToCart'   => __( 'Added to cart', 'amino-heaven' ),
				'addingToCart'  => __( 'Adding…', 'amino-heaven' ),
				'addToCart'     => __( 'Add', 'amino-heaven' ),
				'decrease'      => __( 'Decrease quantity', 'amino-heaven' ),
				'increase'      => __( 'Increase quantity', 'amino-heaven' ),
				'ageGateDenied' => __( 'You must confirm both statements to continue.', 'amino-heaven' ),
			),
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'amino_heaven_enqueue_assets' );

/**
 * Whether the current request is rendering WooCommerce output.
 *
 * Used to keep WooCommerce's presentation layer off pages that do not need it.
 *
 * @return bool
 */
function amino_heaven_is_woocommerce_context() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return false;
	}

	if ( is_singular( 'product' ) || is_post_type_archive( 'product' ) || is_tax( get_object_taxonomies( 'product' ) ) ) {
		return true;
	}

	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
		return true;
	}

	// The front page renders the featured catalog, so it needs the store
	// presentation layer. One-off pages do not.
	if ( is_front_page() ) {
		return true;
	}

	// Any singular post/page/CPT that embeds WooCommerce output (shortcode or
	// block) needs it too. Detected cheaply rather than by loading WC classes.
	if ( is_singular() ) {
		$post = get_post();

		if ( $post instanceof WP_Post ) {
			$has_shortcode = function_exists( 'has_shortcode' ) && has_shortcode( $post->post_content, 'woocommerce_order_tracking' );

			if ( $has_shortcode || false !== strpos( $post->post_content, 'woocommerce' ) || false !== strpos( $post->post_content, 'wc-' ) ) {
				return true;
			}
		}
	}

	return (bool) apply_filters( 'amino_heaven_is_woocommerce_context', false );
}

/**
 * Editor styles so block content previews match the front end.
 */
function amino_heaven_enqueue_editor_assets() {
	wp_enqueue_style(
		'amino-heaven-editor',
		AMINO_HEAVEN_URI . 'assets/css/editor.css',
		array(),
		amino_heaven_asset_version( 'assets/css/editor.css' )
	);
}
add_action( 'enqueue_block_editor_assets', 'amino_heaven_enqueue_editor_assets' );

/**
 * Age-gate styling must be present before first paint, otherwise a verified
 * visitor briefly sees the overlay while the main stylesheet downloads.
 */
function amino_heaven_print_age_gate_critical_css() {
	if ( ! amino_heaven_age_gate_should_render() ) {
		return;
	}
	?>
	<style id="amino-heaven-age-gate-critical">
		html.ah-gate-passed #ah-age-gate{display:none !important}
		#ah-age-gate{position:fixed;inset:0;z-index:100000;display:grid;place-items:center;overflow-y:auto;padding:20px;background:rgba(9,26,36,.78);backdrop-filter:blur(12px) saturate(.7)}
		#ah-age-gate[hidden]{display:none !important}
		body.ah-gate-open{overflow:hidden}
	</style>
	<script id="amino-heaven-age-gate-boot">
		( function () {
			try {
				var key = 'amino-heaven-age-verified';
				if ( window.sessionStorage.getItem( key ) === 'true' || document.cookie.indexOf( 'ah_age_verified=1' ) !== -1 ) {
					document.documentElement.classList.add( 'ah-gate-passed' );
				}
			} catch ( e ) {}
		} )();
	</script>
	<?php
}
add_action( 'wp_head', 'amino_heaven_print_age_gate_critical_css', 1 );
