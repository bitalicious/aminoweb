<?php
/**
 * Store configuration verification.
 *
 * Asserts the WordPress/WooCommerce configuration the project specifies: region,
 * currency, locale, timezone, taxes, both offline gateways, the native flat-rate
 * shipping zone, store pages, permalinks, menus and visibility.
 *
 * Run with:
 *
 *     scripts/test.sh store
 *
 * Exit code is non-zero when any assertion fails.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wc_get_page_id' ) ) {
	WP_CLI::error( 'WooCommerce is not active.' );
}

$GLOBALS['ah_store_checks']   = 0;
$GLOBALS['ah_store_failures'] = array();

/**
 * Record an assertion result.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 */
function ah_store_assert( $condition, $message ) {
	++$GLOBALS['ah_store_checks'];

	if ( ! $condition ) {
		$GLOBALS['ah_store_failures'][] = $message;
	}
}

/**
 * Assert an option equals a value.
 *
 * @param string $option   Option name.
 * @param mixed  $expected Expected value.
 */
function ah_store_option_is( $option, $expected ) {
	$actual = get_option( $option );
	ah_store_assert(
		$actual === $expected,
		sprintf( 'option %s is %s, expected %s', $option, wp_json_encode( $actual ), wp_json_encode( $expected ) )
	);
}

/* -------------------------------------------------------------------------
 * Region, currency, locale
 * ---------------------------------------------------------------------- */
ah_store_option_is( 'woocommerce_currency', 'USD' );
ah_store_option_is( 'woocommerce_currency_pos', 'left' );
ah_store_option_is( 'woocommerce_price_num_decimals', '2' );
ah_store_option_is( 'woocommerce_price_decimal_sep', '.' );
ah_store_option_is( 'woocommerce_price_thousand_sep', ',' );
ah_store_option_is( 'woocommerce_default_country', 'US' );
ah_store_option_is( 'woocommerce_allowed_countries', 'specific' );
ah_store_option_is( 'woocommerce_specific_allowed_countries', array( 'US' ) );
ah_store_option_is( 'woocommerce_ship_to_countries', 'specific' );
ah_store_option_is( 'woocommerce_specific_ship_to_countries', array( 'US' ) );
ah_store_option_is( 'timezone_string', 'America/Los_Angeles' );

$locale = get_option( 'WPLANG' );
ah_store_assert( '' === $locale || 'en_US' === $locale, sprintf( 'site locale is %s, expected en_US', $locale ) );

/* -------------------------------------------------------------------------
 * Taxes: calculation off, no rates invented
 * ---------------------------------------------------------------------- */
ah_store_option_is( 'woocommerce_calc_taxes', 'no' );
ah_store_option_is( 'woocommerce_prices_include_tax', 'no' );

$tax_rate_count = 0;

if ( class_exists( 'WC_Tax' ) ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- verification only.
	$tax_rate_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates" );
}

ah_store_assert( 0 === $tax_rate_count, sprintf( 'expected no tax rates, found %d', $tax_rate_count ) );

/* -------------------------------------------------------------------------
 * Payment gateways: BACS + COD, nothing else
 * ---------------------------------------------------------------------- */
$available = array_keys( WC()->payment_gateways()->payment_gateways() );
$enabled   = array();

foreach ( WC()->payment_gateways()->payment_gateways() as $gateway_id => $gateway ) {
	if ( 'yes' === $gateway->enabled ) {
		$enabled[] = $gateway_id;
	}
}

sort( $enabled );

ah_store_assert( in_array( 'bacs', $enabled, true ), sprintf( 'bank transfer (bacs) is not enabled; enabled: [%s]', implode( ', ', $enabled ) ) );
ah_store_assert( in_array( 'cod', $enabled, true ), sprintf( 'cash on delivery (cod) is not enabled; enabled: [%s]', implode( ', ', $enabled ) ) );

$online_gateways = array_intersect( $enabled, array( 'stripe', 'paypal', 'ppcp-gateway', 'square_credit_card', 'braintree', 'authorize_net' ) );
ah_store_assert( empty( $online_gateways ), sprintf( 'unexpected online gateway(s) enabled: [%s]', implode( ', ', $online_gateways ) ) );

$bacs_accounts = get_option( 'woocommerce_bacs_accounts', array() );
ah_store_assert( is_array( $bacs_accounts ) && ! empty( $bacs_accounts ), 'bank transfer has no account details configured' );

/* -------------------------------------------------------------------------
 * Shipping: a United States flat rate zone
 * ---------------------------------------------------------------------- */
$zones           = class_exists( 'WC_Shipping_Zones' ) ? WC_Shipping_Zones::get_zones() : array();
$us_zone         = null;
$flat_rate_found = false;
$flat_rate_cost  = null;

foreach ( $zones as $zone ) {
	if ( 'United States' === $zone['zone_name'] ) {
		$us_zone = $zone;

		foreach ( $zone['shipping_methods'] as $method ) {
			if ( 'flat_rate' === $method->id ) {
				$flat_rate_found = true;
				$flat_rate_cost  = $method->cost;
			}
		}
	}
}

ah_store_assert( null !== $us_zone, 'no "United States" shipping zone found' );
ah_store_assert( $flat_rate_found, 'no native flat-rate shipping method in the United States zone' );
ah_store_assert( null !== $flat_rate_cost && is_numeric( $flat_rate_cost ), 'flat rate has no numeric cost' );

/* -------------------------------------------------------------------------
 * Store pages
 * ---------------------------------------------------------------------- */
$expected_pages = array(
	'shop'      => 'woocommerce_shop_page_id',
	'cart'      => 'woocommerce_cart_page_id',
	'checkout'  => 'woocommerce_checkout_page_id',
	'myaccount' => 'woocommerce_myaccount_page_id',
);

$shortcodes = array(
	'cart'      => 'woocommerce_cart',
	'checkout'  => 'woocommerce_checkout',
	'myaccount' => 'woocommerce_my_account',
);

foreach ( $expected_pages as $key => $option ) {
	$page_id = (int) get_option( $option );
	$page    = $page_id > 0 ? get_post( $page_id ) : null;

	ah_store_assert( $page instanceof WP_Post && 'publish' === $page->post_status, sprintf( 'WooCommerce %s page is missing or unpublished', $key ) );

	if ( $page instanceof WP_Post && isset( $shortcodes[ $key ] ) ) {
		ah_store_assert(
			has_shortcode( $page->post_content, $shortcodes[ $key ] ),
			sprintf( '%s page does not use the classic [%s] shortcode', $key, $shortcodes[ $key ] )
		);
	}
}

$terms_id = (int) get_option( 'woocommerce_terms_page_id' );
ah_store_assert( $terms_id > 0 && get_post( $terms_id ) instanceof WP_Post, 'no terms and conditions page is assigned to checkout' );

$track = get_page_by_path( 'track-order' );
ah_store_assert( $track instanceof WP_Post, 'the Track Order page is missing' );

if ( $track instanceof WP_Post ) {
	ah_store_assert( has_shortcode( $track->post_content, 'woocommerce_order_tracking' ), 'the Track Order page does not use [woocommerce_order_tracking]' );
}

/* -------------------------------------------------------------------------
 * Front page, permalinks, menus, visibility
 * ---------------------------------------------------------------------- */
ah_store_option_is( 'show_on_front', 'page' );
ah_store_assert( (int) get_option( 'page_on_front' ) > 0, 'no static front page is assigned' );
ah_store_assert( false !== strpos( (string) get_option( 'permalink_structure' ), '%postname%' ), 'permalinks are not set to /%postname%/' );
ah_store_option_is( 'woocommerce_coming_soon', 'no' );
ah_store_option_is( 'woocommerce_enable_guest_checkout', 'yes' );
ah_store_option_is( 'woocommerce_enable_reviews', 'no' );

$locations = get_theme_mod( 'nav_menu_locations', array() );
foreach ( array( 'primary', 'footer', 'legal' ) as $location ) {
	ah_store_assert( ! empty( $locations[ $location ] ), sprintf( 'no menu assigned to the %s location', $location ) );
}

$primary_items = ! empty( $locations['primary'] ) ? wp_get_nav_menu_items( (int) $locations['primary'] ) : array();
$primary_titles = wp_list_pluck( is_array( $primary_items ) ? $primary_items : array(), 'title' );
ah_store_assert(
	array( 'Shop', 'Quality / COAs', 'About', 'Contact' ) === array_values( $primary_titles ),
	sprintf( 'primary menu is [%s], expected [Shop, Quality / COAs, About, Contact]', implode( ', ', $primary_titles ) )
);

/* -------------------------------------------------------------------------
 * Theme
 * ---------------------------------------------------------------------- */
ah_store_assert( 'amino-heaven' === get_stylesheet(), sprintf( 'active theme is %s, expected amino-heaven', get_stylesheet() ) );
ah_store_assert( 'amino-heaven' === get_template(), 'the active theme is a child theme; amino-heaven must be the base theme' );
ah_store_assert( (int) get_option( 'site_icon' ) > 0, 'no site icon is configured' );

/* -------------------------------------------------------------------------
 * Orders (HPOS or legacy)
 * ---------------------------------------------------------------------- */
$order_ids = wc_get_orders(
	array(
		'limit'  => 100,
		'return' => 'ids',
		'status' => array_keys( wc_get_order_statuses() ),
	)
);
WP_CLI::log( sprintf( '  orders present    : %d', count( $order_ids ) ) );
WP_CLI::log( sprintf( '  HPOS enabled      : %s', get_option( 'woocommerce_custom_orders_table_enabled' ) ? 'yes' : 'no' ) );
WP_CLI::log( sprintf( '  flat rate cost    : %s', (string) $flat_rate_cost ) );

/* -------------------------------------------------------------------------
 * Report
 * ---------------------------------------------------------------------- */
WP_CLI::log( '' );
WP_CLI::log( 'Store verification' );
WP_CLI::log( sprintf( '  assertions        : %d', $GLOBALS['ah_store_checks'] ) );
WP_CLI::log( sprintf( '  failures          : %d', count( $GLOBALS['ah_store_failures'] ) ) );

if ( $GLOBALS['ah_store_failures'] ) {
	foreach ( $GLOBALS['ah_store_failures'] as $failure ) {
		WP_CLI::log( '  ✗ ' . $failure );
	}

	WP_CLI::error( sprintf( 'Store verification FAILED with %d problem(s).', count( $GLOBALS['ah_store_failures'] ) ), false );
	exit( 1 );
}

WP_CLI::success( sprintf( 'Store verification passed (%d assertions).', $GLOBALS['ah_store_checks'] ) );
