<?php
/**
 * WooCommerce integration.
 *
 * Design rule for this file: WooCommerce stays the source of truth for
 * products, variations, pricing, stock, cart, checkout and orders. The theme
 * only changes *presentation* (wrappers, headings, the catalog card, quantity
 * controls) and adds the storefront's COA + research-use messaging. No pricing
 * is calculated in the theme, and no WooCommerce core file is modified.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

/* -------------------------------------------------------------------------
 * Loop configuration
 * ---------------------------------------------------------------------- */

/**
 * Products per row, matching the source catalog grid (3 across on desktop).
 *
 * @return int
 */
function amino_heaven_loop_columns() {
	return (int) apply_filters( 'amino_heaven_loop_columns', 3 );
}
add_filter( 'loop_shop_columns', 'amino_heaven_loop_columns', 20 );

/**
 * Products per page on the shop archive.
 *
 * @return int
 */
function amino_heaven_products_per_page() {
	return (int) apply_filters( 'amino_heaven_products_per_page', 12 );
}
add_filter( 'loop_shop_per_page', 'amino_heaven_products_per_page', 20 );

/* -------------------------------------------------------------------------
 * Layout: wrappers and section headings
 * ---------------------------------------------------------------------- */

remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

if ( ! function_exists( 'amino_heaven_wc_wrapper_start' ) ) {
	/**
	 * Open the themed content wrapper for WooCommerce templates.
	 */
	function amino_heaven_wc_wrapper_start() {
		$classes = is_product() ? 'content-section ah-single-section' : 'content-section ah-catalog-section';

		printf( '<div class="%s">', esc_attr( $classes ) );

		if ( ! is_product() ) {
			amino_heaven_wc_section_heading();
		}
	}
}
add_action( 'woocommerce_before_main_content', 'amino_heaven_wc_wrapper_start', 10 );

if ( ! function_exists( 'amino_heaven_wc_wrapper_end' ) ) {
	/**
	 * Close the themed content wrapper.
	 */
	function amino_heaven_wc_wrapper_end() {
		echo '</div>';
	}
}
add_action( 'woocommerce_after_main_content', 'amino_heaven_wc_wrapper_end', 10 );

if ( ! function_exists( 'amino_heaven_wc_section_heading' ) ) {
	/**
	 * Print the catalog heading block used by the shop, category and search
	 * archives. WooCommerce's own page title is suppressed so the theme can own
	 * the heading markup (and keep exactly one h1 per page).
	 */
	function amino_heaven_wc_section_heading() {
		if ( is_product_taxonomy() ) {
			$term = get_queried_object();
			$eyebrow = __( 'Curated compounds', 'amino-heaven' );
			$title   = $term instanceof WP_Term ? $term->name : __( 'Shop', 'amino-heaven' );
			$intro   = $term instanceof WP_Term ? $term->description : '';
		} elseif ( is_search() ) {
			/* translators: %s: search query. */
			$eyebrow = __( 'Search results', 'amino-heaven' );
			$title   = sprintf( __( 'Results for “%s”', 'amino-heaven' ), get_search_query() );
			$intro   = '';
		} else {
			$eyebrow = __( 'Curated compounds', 'amino-heaven' );
			$title   = __( 'Featured compounds', 'amino-heaven' );
			$intro   = __( 'Best-moving vials available this week.', 'amino-heaven' );
		}

		$intro = trim( wp_strip_all_tags( (string) $intro ) );
		?>
		<header class="section-heading ah-section-heading">
			<div>
				<p class="eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php if ( '' !== $intro ) : ?>
					<p><?php echo esc_html( $intro ); ?></p>
				<?php endif; ?>
			</div>
		</header>
		<?php
	}
}

// The theme supplies its own heading markup.
add_filter( 'woocommerce_show_page_title', '__return_false' );

if ( ! function_exists( 'amino_heaven_wc_shop_sidebar' ) ) {
	/**
	 * Render the optional shop sidebar above the product grid.
	 */
	function amino_heaven_wc_shop_sidebar() {
		if ( ! is_active_sidebar( 'shop-sidebar' ) ) {
			return;
		}
		?>
		<aside class="ah-shop-sidebar" aria-label="<?php esc_attr_e( 'Shop filters', 'amino-heaven' ); ?>">
			<?php dynamic_sidebar( 'shop-sidebar' ); ?>
		</aside>
		<?php
	}
}
add_action( 'woocommerce_before_shop_loop', 'amino_heaven_wc_shop_sidebar', 4 );

if ( ! function_exists( 'amino_heaven_wc_toolbar_open' ) ) {
	/**
	 * Wrap WooCommerce's result count and ordering dropdown in a toolbar row.
	 *
	 * WooCommerce prints them as loose siblings; the design needs them on one
	 * line, so the theme supplies the container between the notices (10) and the
	 * result count (20).
	 */
	function amino_heaven_wc_toolbar_open() {
		echo '<div class="ah-shop-toolbar">';
	}
}
add_action( 'woocommerce_before_shop_loop', 'amino_heaven_wc_toolbar_open', 15 );

if ( ! function_exists( 'amino_heaven_wc_toolbar_close' ) ) {
	/**
	 * Close the shop toolbar row.
	 */
	function amino_heaven_wc_toolbar_close() {
		echo '</div>';
	}
}
add_action( 'woocommerce_before_shop_loop', 'amino_heaven_wc_toolbar_close', 35 );

/* -------------------------------------------------------------------------
 * Catalog card
 * ---------------------------------------------------------------------- */

/**
 * The source storefront never displayed a "Sale!" flash — the discount is
 * communicated by the struck-through list price. Drop the loop flash to match.
 */
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );

/**
 * Wrap the loop thumbnail in the themed image frame and add the product's
 * strength to the title, matching the source's "NAME STRENGTH" card titles.
 */
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );

/**
 * Catalog card strength suffix.
 *
 * @param WC_Product $product Product object.
 * @return string e.g. "10mg" or the first available strength.
 */
function amino_heaven_card_strength( $product ) {
	$strength = amino_heaven_product_attribute_raw( $product, amino_heaven_dose_attribute_slug() );

	if ( '' === $strength ) {
		return '';
	}

	// Multi-strength products store "10mg | 60mg"; the card shows the first.
	$parts = array_map( 'trim', explode( '|', $strength ) );

	return (string) reset( $parts );
}

/* -------------------------------------------------------------------------
 * Single product
 * ---------------------------------------------------------------------- */

/**
 * Research-use reminder directly beneath the add-to-cart form.
 */
function amino_heaven_single_product_research_notice() {
	echo '<p class="ah-research-notice">';
	amino_heaven_the_icon( 'shield', array( 'size' => 15 ) );
	echo '<span>' . esc_html( amino_heaven_research_use_notice() ) . '</span>';
	echo '</p>';
}
add_action( 'woocommerce_after_add_to_cart_form', 'amino_heaven_single_product_research_notice', 20 );

/**
 * COA button inside the single product summary.
 */
function amino_heaven_single_product_coa() {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$coa = amino_heaven_get_coa( $product );
	?>
	<div class="ah-single-coa">
		<?php amino_heaven_coa_button( $product ); ?>
		<?php if ( '' !== $coa['lot'] ) : ?>
			<span class="ah-lot">
				<?php esc_html_e( 'Lot', 'amino-heaven' ); ?> <b><?php echo esc_html( $coa['lot'] ); ?></b>
			</span>
		<?php endif; ?>
		<?php if ( ! $coa['is_specific'] ) : ?>
			<span class="ah-coa-note"><?php esc_html_e( 'Batch COA pending publication', 'amino-heaven' ); ?></span>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'woocommerce_single_product_summary', 'amino_heaven_single_product_coa', 26 );

/**
 * Remove the upsell/related rating markup noise from the single product page,
 * keeping the standard "Additional information" tab styling to the theme.
 */
add_filter( 'woocommerce_product_description_heading', 'amino_heaven_product_tab_heading' );
add_filter( 'woocommerce_product_additional_information_heading', 'amino_heaven_product_tab_heading' );

if ( ! function_exists( 'amino_heaven_product_tab_heading' ) ) {
	/**
	 * Title used by the product data tabs.
	 *
	 * @param string $heading Default heading.
	 * @return string
	 */
	function amino_heaven_product_tab_heading( $heading ) {
		return $heading;
	}
}

/**
 * Related products: keep WooCommerce's native related/upsell logic but render a
 * compact card (the full catalog card is far too heavy for a cross-sell row).
 *
 * @param array $args Related product query args.
 * @return array
 */
function amino_heaven_related_products_args( $args ) {
	$args['posts_per_page'] = 3;
	$args['columns']        = 3;

	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'amino_heaven_related_products_args', 20 );

/* -------------------------------------------------------------------------
 * Notices outside WooCommerce templates
 * ---------------------------------------------------------------------- */

/**
 * Print WooCommerce notices on pages WooCommerce does not own (the front page
 * hosts a real add-to-cart catalog card, so its notices must be visible).
 */
function amino_heaven_print_notices_outside_woocommerce() {
	if ( ! function_exists( 'wc_print_notices' ) ) {
		return;
	}

	if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
		return;
	}

	if ( function_exists( 'woocommerce_output_all_notices' ) ) {
		woocommerce_output_all_notices();
	} else {
		wc_print_notices();
	}
}
add_action( 'amino_heaven_after_header', 'amino_heaven_print_notices_outside_woocommerce', 10 );

/* -------------------------------------------------------------------------
 * Header cart
 * ---------------------------------------------------------------------- */

/**
 * Render the header shopping-bag link with a live item count.
 *
 * Uses WooCommerce's own cart API, so the count is always the server's count.
 */
function amino_heaven_header_cart_link() {
	if ( ! function_exists( 'wc_get_cart_url' ) ) {
		return;
	}

	$count = ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
	?>
	<a class="ah-cart-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
		<?php amino_heaven_the_icon( 'bag', array( 'size' => 19 ) ); ?>
		<span class="screen-reader-text"><?php esc_html_e( 'Shopping bag', 'amino-heaven' ); ?></span>
		<?php // The data attribute carries the count so CSS can hide an empty badge. ?>
		<span class="ah-cart-count" data-ah-cart-count="<?php echo esc_attr( (string) $count ); ?>" aria-hidden="true"><?php echo esc_html( (string) $count ); ?></span>
	</a>
	<?php
}

/**
 * Keep the header count correct after AJAX add-to-cart by using WooCommerce's
 * native cart fragments instead of a bespoke AJAX endpoint.
 *
 * @param array $fragments Cart fragments.
 * @return array
 */
function amino_heaven_cart_count_fragment( $fragments ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $fragments;
	}

	$count = (int) WC()->cart->get_cart_contents_count();

	$fragments['span[data-ah-cart-count]'] = sprintf(
		'<span class="ah-cart-count" data-ah-cart-count="%1$s" aria-hidden="true">%1$s</span>',
		esc_attr( (string) $count )
	);

	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'amino_heaven_cart_count_fragment' );

/* -------------------------------------------------------------------------
 * Breadcrumbs
 * ---------------------------------------------------------------------- */

/**
 * Breadcrumb defaults matching the theme's separators and wrapper.
 *
 * @param array $args Defaults.
 * @return array
 */
function amino_heaven_breadcrumb_defaults( $args ) {
	$args['delimiter']   = '<span class="ah-crumb-sep" aria-hidden="true">/</span>';
	$args['wrap_before'] = '<nav class="woocommerce-breadcrumb ah-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'amino-heaven' ) . '">';
	$args['wrap_after']  = '</nav>';

	return $args;
}
add_filter( 'woocommerce_breadcrumb_defaults', 'amino_heaven_breadcrumb_defaults' );

/* -------------------------------------------------------------------------
 * Checkout: research-use acknowledgement
 * ---------------------------------------------------------------------- */

/**
 * Surface the research-use restriction on the checkout page and rely on
 * WooCommerce's native "Terms and conditions" checkbox for the binding
 * acknowledgement (configured by `wp amino-heaven setup-store`).
 */
function amino_heaven_checkout_research_notice() {
	if ( ! is_checkout() ) {
		return;
	}

	echo '<div class="ah-checkout-notice" role="note">';
	amino_heaven_the_icon( 'info', array( 'size' => 18 ) );
	echo '<div>';
	echo '<strong>' . esc_html__( 'Research use only', 'amino-heaven' ) . '</strong>';
	echo '<p>' . esc_html__( 'Orders are accepted exclusively from qualified personnel for in vitro research, laboratory analysis and development purposes. Products are not for human or animal consumption, and are not for therapeutic or veterinary use.', 'amino-heaven' ) . '</p>';
	echo '</div>';
	echo '</div>';
}
add_action( 'woocommerce_before_checkout_form', 'amino_heaven_checkout_research_notice', 5 );

/**
 * Add a research-use reminder to order confirmation emails and the thank-you
 * page so the restriction travels with the order.
 *
 * @param WC_Order $order Order object.
 */
function amino_heaven_thankyou_research_notice( $order ) {
	echo '<p class="ah-research-notice">' . esc_html( amino_heaven_research_use_notice() ) . '</p>';
}
add_action( 'woocommerce_thankyou', 'amino_heaven_thankyou_research_notice', 5 );

/* -------------------------------------------------------------------------
 * Cart / checkout presentation helpers
 * ---------------------------------------------------------------------- */

/**
 * Ensure WooCommerce's cart fragments script is available so the header cart
 * count stays accurate after AJAX additions.
 */
function amino_heaven_cart_fragments_support() {
	add_theme_support( 'wc-cart-fragments' );
}
add_action( 'after_setup_theme', 'amino_heaven_cart_fragments_support', 20 );

/**
 * Keep the "Continue shopping" button on the cart page pointing at the shop.
 *
 * @param string $url Default URL.
 * @return string
 */
function amino_heaven_continue_shopping_url( $url ) {
	if ( function_exists( 'wc_get_page_id' ) ) {
		$shop_id = wc_get_page_id( 'shop' );

		if ( $shop_id > 0 ) {
			return get_permalink( $shop_id );
		}
	}

	return $url;
}
add_filter( 'woocommerce_continue_shopping_redirect', 'amino_heaven_continue_shopping_url' );

/**
 * Show the research-use note in the order totals area of the cart.
 */
function amino_heaven_cart_research_note() {
	echo '<tr class="ah-cart-research"><td colspan="2">';
	echo esc_html( amino_heaven_research_use_notice() );
	echo '</td></tr>';
}
add_action( 'woocommerce_cart_totals_after_order_total', 'amino_heaven_cart_research_note' );
