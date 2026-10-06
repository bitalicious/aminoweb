<?php
/**
 * Catalog verification test.
 *
 * Asserts that the WooCommerce catalog matches the source storefront definition
 * in `inc/data/catalog.json`: product type, categories, featured flags, imagery,
 * SKUs, and every variation's regular/sale/active price.
 *
 * Run with:
 *
 *     scripts/wp.sh eval-file /scripts/../tests/wp/verify-catalog.php
 *
 * or, from the repository root:
 *
 *     scripts/test.sh
 *
 * Exit code is non-zero when any assertion fails, so it is usable in CI.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
	WP_CLI::error( 'WooCommerce is not active.' );
}

$catalog_file = AMINO_HEAVEN_DIR . 'inc/data/catalog.json';

if ( ! is_readable( $catalog_file ) ) {
	WP_CLI::error( sprintf( 'Catalog file not found: %s', $catalog_file ) );
}

$catalog = json_decode( (string) file_get_contents( $catalog_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.

// `wp eval-file` includes this file at method scope, so the counters live in
// $GLOBALS explicitly: a plain `global` inside the helper would not see locals.
$GLOBALS['ah_test_checks']   = 0;
$GLOBALS['ah_test_failures'] = array();

/**
 * Record an assertion result.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 */
function ah_assert( $condition, $message ) {
	++$GLOBALS['ah_test_checks'];

	if ( ! $condition ) {
		$GLOBALS['ah_test_failures'][] = $message;
	}
}

/**
 * Array comparison of price strings.
 *
 * @param float|string $actual   Actual value.
 * @param float|string $expected Expected value.
 * @return bool
 */
function ah_price_equals( $actual, $expected ) {
	return abs( (float) $actual - (float) $expected ) < 0.0001;
}

$factor = (float) $catalog['pricePolicy']['listPriceFactor'];
$tiers  = $catalog['pricePolicy']['tiers'];

// Source semantics: Math.round( x * 100 ) / 100.
$compute = static function ( $unit_price, $count, $tier_factor ) {
	return round( $unit_price * $count * $tier_factor * 100 ) / 100;
};

$product_count    = 0;
$variation_count  = 0;
$expected_images  = array();

foreach ( $catalog['products'] as $definition ) {
	$sku = (string) $definition['sku'];

	$product_id = wc_get_product_id_by_sku( $sku );
	ah_assert( (bool) $product_id, sprintf( '%s: product with SKU %s not found', $definition['name'], $sku ) );

	if ( ! $product_id ) {
		continue;
	}

	++$product_count;

	$product = wc_get_product( $product_id );

	ah_assert( $product instanceof WC_Product_Variable, sprintf( '%s: expected a variable product, got %s', $sku, $product->get_type() ) );
	ah_assert( 'publish' === $product->get_status(), sprintf( '%s: expected published status', $sku ) );
	ah_assert( $product->is_featured() === ! empty( $definition['featured'] ), sprintf( '%s: featured flag mismatch', $sku ) );
	ah_assert( (bool) $product->get_image_id(), sprintf( '%s: no featured image', $sku ) );
	ah_assert( '' !== trim( (string) $product->get_description() ), sprintf( '%s: empty description', $sku ) );
	ah_assert( '' !== trim( (string) $product->get_short_description() ), sprintf( '%s: empty short description', $sku ) );

	// Category assignment.
	$category_slugs = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) );
	ah_assert(
		in_array( (string) $definition['category'], $category_slugs, true ),
		sprintf( '%s: expected category "%s", found [%s]', $sku, $definition['category'], implode( ', ', $category_slugs ) )
	);

	// Attributes.
	$attributes   = $product->get_attributes();
	$dose_slug    = (string) $catalog['attributes']['dose']['slug'];
	$pack_slug    = (string) $catalog['attributes']['pack']['slug'];
	$dose_labels  = array_map( static function ( $dose ) { return (string) $dose['dose']; }, $definition['doses'] );
	$pack_labels  = array_map( static function ( $tier ) { return (string) $tier['pack']; }, $tiers );

	ah_assert( isset( $attributes[ $dose_slug ] ), sprintf( '%s: missing "%s" attribute', $sku, $dose_slug ) );
	ah_assert( isset( $attributes[ $pack_slug ] ), sprintf( '%s: missing "%s" attribute', $sku, $pack_slug ) );

	if ( isset( $attributes[ $dose_slug ] ) ) {
		$saved_doses = array_map( 'strval', $attributes[ $dose_slug ]->get_options() );
		ah_assert( $saved_doses === $dose_labels, sprintf( '%s: dose options mismatch (expected [%s], got [%s])', $sku, implode( ', ', $dose_labels ), implode( ', ', $saved_doses ) ) );
		ah_assert(
			$attributes[ $dose_slug ]->get_variation() === ( count( $dose_labels ) > 1 ),
			sprintf( '%s: dose attribute should%s be used for variations', $sku, count( $dose_labels ) > 1 ? '' : ' not' )
		);
	}

	if ( isset( $attributes[ $pack_slug ] ) ) {
		$saved_packs = array_map( 'strval', $attributes[ $pack_slug ]->get_options() );
		ah_assert( $saved_packs === $pack_labels, sprintf( '%s: pack options mismatch (expected [%s], got [%s])', $sku, implode( ', ', $pack_labels ), implode( ', ', $saved_packs ) ) );
		ah_assert( $attributes[ $pack_slug ]->get_variation(), sprintf( '%s: pack attribute must be used for variations', $sku ) );
	}

	// Variations.
	$children = $product->get_children();

	ah_assert(
		count( $children ) === count( $definition['doses'] ) * count( $tiers ),
		sprintf( '%s: expected %d variations, found %d', $sku, count( $definition['doses'] ) * count( $tiers ), count( $children ) )
	);

	foreach ( $definition['doses'] as $dose ) {
		$dose_label = (string) $dose['dose'];
		$unit_price = (float) $dose['unitPrice'];

		foreach ( $tiers as $tier ) {
			$pack_label = (string) $tier['pack'];
			$count      = (int) $tier['count'];

			$expected_sale    = $compute( $unit_price, $count, (float) $tier['factor'] );
			$expected_regular = $compute( $expected_sale, 1, $factor );

			$match = null;

			foreach ( $children as $child_id ) {
				$variation = wc_get_product( $child_id );

				if ( ! $variation instanceof WC_Product_Variation ) {
					continue;
				}

				$variation_attributes = $variation->get_variation_attributes();
				$variation_dose       = isset( $variation_attributes[ 'attribute_' . $dose_slug ] ) ? (string) $variation_attributes[ 'attribute_' . $dose_slug ] : $dose_label;
				$variation_pack       = isset( $variation_attributes[ 'attribute_' . $pack_slug ] ) ? (string) $variation_attributes[ 'attribute_' . $pack_slug ] : '';

				if ( sanitize_title( $variation_dose ) === sanitize_title( $dose_label )
					&& sanitize_title( $variation_pack ) === sanitize_title( $pack_label ) ) {
					$match = $variation;
					break;
				}
			}

			++$variation_count;

			if ( ! $match ) {
				ah_assert( false, sprintf( '%s: no variation for %s / %s', $sku, $dose_label, $pack_label ) );
				continue;
			}

			$label = sprintf( '%s %s %s', $sku, $dose_label, $pack_label );

			ah_assert( ah_price_equals( $match->get_sale_price(), $expected_sale ), sprintf( '%s: sale price %s, expected %s', $label, $match->get_sale_price(), $expected_sale ) );
			ah_assert( ah_price_equals( $match->get_regular_price(), $expected_regular ), sprintf( '%s: regular price %s, expected %s', $label, $match->get_regular_price(), $expected_regular ) );
			ah_assert( ah_price_equals( $match->get_price(), $expected_sale ), sprintf( '%s: active price %s, expected %s', $label, $match->get_price(), $expected_sale ) );
			ah_assert( $match->is_in_stock(), sprintf( '%s: expected in stock', $label ) );
			ah_assert( '' !== (string) $match->get_sku(), sprintf( '%s: missing variation SKU', $label ) );
			ah_assert( (bool) $match->get_image_id(), sprintf( '%s: missing variation image', $label ) );
		}
	}

	foreach ( $definition['doses'] as $dose ) {
		$expected_images[] = (string) $dose['image'];
	}
}

// --- Media library -----------------------------------------------------------
$unique_images = array_unique( $expected_images );
$missing_media = array();

foreach ( $unique_images as $filename ) {
	$found = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => '_amino_heaven_source_image',
					'value' => $filename,
				),
			),
		)
	);

	if ( ! $found->posts ) {
		$missing_media[] = $filename;
	}

	ah_assert( (bool) $found->posts, sprintf( 'media: no attachment imported for %s', $filename ) );
}

// --- Categories --------------------------------------------------------------
foreach ( $catalog['categories'] as $category ) {
	$term = get_term_by( 'slug', (string) $category['slug'], 'product_cat' );

	ah_assert( $term instanceof WP_Term, sprintf( 'category "%s" not found', $category['slug'] ) );

	if ( $term instanceof WP_Term && '' !== (string) $category['parent'] ) {
		$parent = get_term_by( 'slug', (string) $category['parent'], 'product_cat' );
		ah_assert( $parent instanceof WP_Term, sprintf( 'parent category "%s" not found', $category['parent'] ) );

		if ( $parent instanceof WP_Term ) {
			ah_assert( (int) $term->parent === (int) $parent->term_id, sprintf( 'category "%s" has the wrong parent', $category['slug'] ) );
		}
	}
}

// --- Report ------------------------------------------------------------------
WP_CLI::log( '' );
WP_CLI::log( 'Catalog verification' );
WP_CLI::log( sprintf( '  products checked  : %d', $product_count ) );
WP_CLI::log( sprintf( '  variations checked: %d', $variation_count ) );
WP_CLI::log( sprintf( '  images expected   : %d (missing: %d)', count( $unique_images ), count( $missing_media ) ) );
WP_CLI::log( sprintf( '  assertions        : %d', $GLOBALS['ah_test_checks'] ) );
WP_CLI::log( sprintf( '  failures          : %d', count( $GLOBALS['ah_test_failures'] ) ) );

if ( $GLOBALS['ah_test_failures'] ) {
	foreach ( array_slice( $GLOBALS['ah_test_failures'], 0, 40 ) as $failure ) {
		WP_CLI::log( '  ✗ ' . $failure );
	}

	if ( count( $GLOBALS['ah_test_failures'] ) > 40 ) {
		WP_CLI::log( sprintf( '  … and %d more', count( $GLOBALS['ah_test_failures'] ) - 40 ) );
	}

	WP_CLI::error( sprintf( 'Catalog verification FAILED with %d problem(s).', count( $GLOBALS['ah_test_failures'] ) ), false );
	exit( 1 );
}

WP_CLI::success( sprintf( 'Catalog verification passed (%d assertions).', $GLOBALS['ah_test_checks'] ) );
