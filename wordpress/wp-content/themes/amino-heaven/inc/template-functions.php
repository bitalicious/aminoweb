<?php
/**
 * Template helpers.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Attribute slug used for the compound strength ("10mg", "5mg/5mg", …).
 *
 * @return string
 */
function amino_heaven_dose_attribute_slug() {
	return apply_filters( 'amino_heaven_dose_attribute_slug', 'dose' );
}

/**
 * Attribute slug used for the vial pack size ("1 Vial", "2 Vials", "5 Vials").
 *
 * @return string
 */
function amino_heaven_pack_attribute_slug() {
	return apply_filters( 'amino_heaven_pack_attribute_slug', 'pack' );
}

/**
 * Additional body classes.
 *
 * @param string[] $classes Existing classes.
 * @return string[]
 */
function amino_heaven_body_classes( $classes ) {
	$classes[] = 'ah-body';

	if ( amino_heaven_is_woocommerce_context() ) {
		$classes[] = 'ah-woo';
	}

	if ( ! is_active_sidebar( 'shop-sidebar' ) ) {
		$classes[] = 'ah-no-sidebar';
	}

	if ( is_singular() ) {
		$classes[] = 'ah-singular';
	}

	return $classes;
}
add_filter( 'body_class', 'amino_heaven_body_classes' );

/**
 * Human readable price for an amount, honouring WooCommerce currency settings.
 *
 * @param float|string $amount Amount to format.
 * @return string HTML.
 */
function amino_heaven_price( $amount ) {
	if ( '' === $amount || null === $amount ) {
		return '';
	}

	if ( function_exists( 'wc_price' ) ) {
		return wc_price( (float) $amount );
	}

	return esc_html( number_format_i18n( (float) $amount, 2 ) );
}

/**
 * Strip tags from a price so it can be used inside a plain-text attribute.
 *
 * @param float|string $amount Amount to format.
 * @return string
 */
function amino_heaven_price_text( $amount ) {
	return trim( wp_strip_all_tags( amino_heaven_price( $amount ) ) );
}

/**
 * Print a formatted price.
 *
 * `wc_price()` escapes its own output, so it is echoed directly rather than
 * round-tripped through kses (which would strip the `<bdi>` element that keeps
 * currency formatting correct in RTL locales).
 *
 * @param float|string $amount Amount to format.
 */
function amino_heaven_the_price( $amount ) {
	echo amino_heaven_price( $amount ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by wc_price().
}

/**
 * Count the vials represented by a pack attribute label.
 *
 * Falls back to the pack's position in the list when the label does not start
 * with a number, so a renamed pack such as "Bulk" still resolves sensibly.
 *
 * @param string $pack_label Pack attribute value, e.g. "5 Vials".
 * @param int    $fallback  Fallback count.
 * @return int
 */
function amino_heaven_pack_count( $pack_label, $fallback = 1 ) {
	if ( preg_match( '/\d+/', (string) $pack_label, $matches ) ) {
		$count = (int) $matches[0];

		if ( $count > 0 ) {
			return $count;
		}
	}

	return max( 1, (int) $fallback );
}

/**
 * Read a non-taxonomy product attribute's raw value.
 *
 * @param WC_Product $product Product object.
 * @param string     $slug    Attribute slug.
 * @return string Raw attribute value ("10mg" or "10mg | 60mg").
 */
function amino_heaven_product_attribute_raw( $product, $slug ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	$attributes = $product->get_attributes();

	if ( isset( $attributes[ $slug ] ) && $attributes[ $slug ] instanceof WC_Product_Attribute ) {
		$options = $attributes[ $slug ]->get_options();

		return implode( ' | ', array_map( 'strval', $options ) );
	}

	// Fall back to the generic accessor (also covers taxonomy attributes).
	$value = $product->get_attribute( $slug );

	return is_string( $value ) ? $value : '';
}

/**
 * Collect the display data a catalog card needs for a product.
 *
 * The whole card is generated from the *product's own variations*, so every
 * price, badge and image on the card is authoritative WooCommerce data. The
 * only derived value is the bulk saving badge, which compares the multi-vial
 * variation with buying the same number of single vials.
 *
 * @param WC_Product $product Product object.
 * @return array<string,mixed>
 */
function amino_heaven_get_card_data( $product ) {
	$data = array(
		'product'     => $product,
		'id'          => $product ? $product->get_id() : 0,
		'name'        => $product ? $product->get_name() : '',
		'permalink'   => $product ? $product->get_permalink() : '',
		'doses'       => array(),
		'dose_labels' => array(),
		'pack_labels' => array(),
		'variations'  => array(),
		'default'     => '',
		'single_dose' => true,
		'strength'    => '',
	);

	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return $data;
	}

	$pack_slug = amino_heaven_pack_attribute_slug();
	$dose_slug = amino_heaven_dose_attribute_slug();

	$attributes = $product->get_attributes();

	// Respect the attribute ordering configured in WooCommerce.
	$pack_options = array();
	if ( isset( $attributes[ $pack_slug ] ) && $attributes[ $pack_slug ] instanceof WC_Product_Attribute ) {
		$pack_options = array_map( 'strval', $attributes[ $pack_slug ]->get_options() );
	}

	$dose_options = array();
	if ( isset( $attributes[ $dose_slug ] ) && $attributes[ $dose_slug ] instanceof WC_Product_Attribute ) {
		$dose_options = array_map( 'strval', $attributes[ $dose_slug ]->get_options() );
	}

	$strength = amino_heaven_product_attribute_raw( $product, $dose_slug );
	$data['strength']    = $strength;
	$data['single_dose'] = ( count( $dose_options ) <= 1 );

	foreach ( $product->get_children() as $variation_id ) {
		$variation = wc_get_product( $variation_id );

		if ( ! $variation instanceof WC_Product_Variation || ! $variation->exists() ) {
			continue;
		}

		$variation_attributes = $variation->get_variation_attributes();
		$pack_label           = isset( $variation_attributes[ 'attribute_' . $pack_slug ] ) ? (string) $variation_attributes[ 'attribute_' . $pack_slug ] : '';
		$dose_label           = isset( $variation_attributes[ 'attribute_' . $dose_slug ] ) ? (string) $variation_attributes[ 'attribute_' . $dose_slug ] : '';

		if ( '' === $pack_label && ! empty( $pack_options ) ) {
			$pack_label = (string) reset( $pack_options );
		}

		if ( '' === $dose_label ) {
			// Single-strength products keep the strength on the parent product.
			$dose_label = $data['single_dose'] ? $strength : (string) reset( $dose_options );
		}

		if ( '' === $dose_label ) {
			$dose_label = __( 'Standard', 'amino-heaven' );
		}

		$regular = $variation->get_regular_price();
		$sale    = $variation->get_sale_price();
		$active  = $variation->get_price();

		$image_id = $variation->get_image_id();

		if ( ! $image_id ) {
			$image_id = $product->get_image_id();
		}

		$image_url    = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';
		$image_srcset = $image_id ? wp_get_attachment_image_srcset( $image_id, 'woocommerce_thumbnail' ) : '';
		$image_sizes  = $image_id ? wp_get_attachment_image_sizes( $image_id, 'woocommerce_thumbnail' ) : '';

		$data['variations'][ $variation_id ] = array(
			'id'           => $variation_id,
			'dose'         => $dose_label,
			'pack'         => $pack_label,
			'pack_count'   => amino_heaven_pack_count( $pack_label, count( $data['variations'] ) + 1 ),
			'regular'      => $regular,
			'sale'         => $sale,
			'price'        => $active,
			'on_sale'      => ( '' !== $sale && (float) $sale < (float) $regular ),
			'image'        => $image_url ? $image_url : '',
			'image_srcset' => $image_srcset ? $image_srcset : '',
			'image_sizes'  => $image_sizes ? $image_sizes : '',
			'sku'          => $variation->get_sku(),
			'in_stock'     => $variation->is_in_stock(),
			'save'         => 0,
		);
	}

	if ( empty( $data['variations'] ) ) {
		return $data;
	}

	// Order the dose groups the way WooCommerce lists them.
	$ordered_doses = array();
	foreach ( $dose_options as $option ) {
		$ordered_doses[ $option ] = true;
	}

	foreach ( $data['variations'] as $variation ) {
		if ( ! isset( $ordered_doses[ $variation['dose'] ] ) ) {
			$ordered_doses[ $variation['dose'] ] = true;
		}
	}

	$ordered_packs = array();
	foreach ( $pack_options as $option ) {
		$ordered_packs[ $option ] = true;
	}

	foreach ( $data['variations'] as $variation ) {
		if ( ! isset( $ordered_packs[ $variation['pack'] ] ) ) {
			$ordered_packs[ $variation['pack'] ] = true;
		}
	}

	$data['dose_labels'] = array_keys( $ordered_doses );
	$data['pack_labels'] = array_keys( $ordered_packs );

	foreach ( $data['dose_labels'] as $dose_label ) {
		$data['doses'][ $dose_label ] = array(
			'label' => $dose_label,
			'packs' => array(),
		);
	}

	foreach ( $data['variations'] as $variation_id => $variation ) {
		$data['variations'][ $variation_id ]['save'] = amino_heaven_bulk_save_percent( $variation, $data['variations'] );

		if ( isset( $data['doses'][ $variation['dose'] ] ) ) {
			$data['doses'][ $variation['dose'] ]['packs'][ $variation['pack'] ] = $variation_id;
		}
	}

	// Default selection: the first pack of the first dose.
	reset( $data['doses'] );
	$first_dose = key( $data['doses'] );
	if ( is_array( $data['doses'][ $first_dose ]['packs'] ) && ! empty( $data['doses'][ $first_dose ]['packs'] ) ) {
		$data['default'] = (int) reset( $data['doses'][ $first_dose ]['packs'] );
	}

	return $data;
}

/**
 * Percentage saved by buying a multi-vial pack instead of the same number of
 * single vials for the same strength.
 *
 * This mirrors the source storefront's "Save 5%" / "Save 15%" badges, which
 * describe the *bulk tier* discount rather than the site-wide sale.
 *
 * @param array<string,mixed> $variation  Variation data.
 * @param array<int,array>    $variations All variations for the product.
 * @return int Percentage (rounded).
 */
function amino_heaven_bulk_save_percent( $variation, $variations ) {
	$count = max( 1, (int) $variation['pack_count'] );

	if ( $count < 2 ) {
		return 0;
	}

	$unit_price = null;

	foreach ( $variations as $candidate ) {
		if ( $candidate['dose'] === $variation['dose'] && 1 === (int) $candidate['pack_count'] ) {
			$unit_price = (float) $candidate['price'];
			break;
		}
	}

	if ( null === $unit_price || $unit_price <= 0 ) {
		return 0;
	}

	$comparison = $unit_price * $count;

	if ( $comparison <= 0 ) {
		return 0;
	}

	$save = ( 1 - ( (float) $variation['price'] / $comparison ) ) * 100;

	return max( 0, (int) round( $save ) );
}

/**
 * Render the stock indicator for a product.
 *
 * @param WC_Product $product Product object.
 * @param bool       $echo    Whether to print or return.
 * @return string
 */
function amino_heaven_stock_badge( $product, $echo = true ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	$in_stock = $product->is_in_stock();
	$class    = $in_stock ? 'ah-stock is-in-stock' : 'ah-stock is-out-of-stock';
	$label    = $in_stock ? __( 'In Stock', 'amino-heaven' ) : __( 'Out of Stock', 'amino-heaven' );

	$html  = '<span class="' . esc_attr( $class ) . '">';
	$html .= '<span aria-hidden="true"></span>';
	$html .= esc_html( $label );
	$html .= '</span>';

	if ( $echo ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}

	return $html;
}

/**
 * Bootstrap-styled pagination matching the design.
 *
 * @param WP_Query|null $query Optional query.
 * @param bool          $echo  Whether to print.
 * @return string
 */
function amino_heaven_pagination( $query = null, $echo = true ) {
	$html = paginate_links(
		array(
			'total'     => $query instanceof WP_Query ? max( 1, (int) $query->max_num_pages ) : 0,
			'current'   => max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ),
			'type'      => 'list',
			'prev_text' => esc_html__( 'Previous', 'amino-heaven' ),
			'next_text' => esc_html__( 'Next', 'amino-heaven' ),
		)
	);

	if ( ! $html ) {
		return '';
	}

	$html = '<nav class="ah-pagination" aria-label="' . esc_attr__( 'Pagination', 'amino-heaven' ) . '">' . $html . '</nav>';

	if ( $echo ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated by paginate_links().
	}

	return $html;
}

/**
 * Whether WordPress should show the shop sidebar on the current view.
 *
 * @return bool
 */
function amino_heaven_show_sidebar() {
	if ( ! is_active_sidebar( 'shop-sidebar' ) ) {
		return false;
	}

	return (bool) apply_filters( 'amino_heaven_show_sidebar', true );
}

/**
 * Excerpt length aligned with the card layout.
 *
 * @param int $length Default length.
 * @return int
 */
function amino_heaven_excerpt_length( $length ) {
	if ( is_admin() ) {
		return $length;
	}

	return 28;
}
add_filter( 'excerpt_length', 'amino_heaven_excerpt_length', 20 );

/**
 * Excerpt ellipsis.
 *
 * @return string
 */
function amino_heaven_excerpt_more() {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'amino_heaven_excerpt_more' );

/**
 * Retrieve the site's compliance / disclaimer paragraphs.
 *
 * Content lives here (rather than being hardcoded in the footer template) so it
 * can be filtered or, over time, moved into an option without touching markup.
 * The wording is carried over verbatim from the source storefront.
 *
 * @return string[] Paragraphs of plain text.
 */
function amino_heaven_compliance_statements() {
	$statements = array(
		__( 'All products distributed through this platform are designated strictly for in vitro research, laboratory analysis, and development purposes. Under no circumstances are these compounds intended, formulated, or approved for human consumption, veterinary administration, or therapeutic application of any kind.', 'amino-heaven' ),
		__( 'Statements on this website have not been evaluated by the U.S. Food and Drug Administration. Neither the information presented nor the materials supplied are intended to diagnose, treat, cure, mitigate, or prevent any medical condition or disease.', 'amino-heaven' ),
		__( 'Amino Heaven operates solely as an independent biochemical reagent supplier. Amino Heaven is not a compounding pharmacy or chemical compounding facility as defined under Section 503A of the Federal Food, Drug, and Cosmetic Act (FD&C Act), nor an outsourcing facility as defined under Section 503B of the FD&C Act. All inventory is cataloged and sold exclusively for controlled laboratory, analytical, and academic research use by qualified personnel.', 'amino-heaven' ),
	);

	return (array) apply_filters( 'amino_heaven_compliance_statements', $statements );
}

/**
 * Research-use reminder used next to add-to-cart controls.
 *
 * @return string
 */
function amino_heaven_research_use_notice() {
	return (string) apply_filters(
		'amino_heaven_research_use_notice',
		__( 'For laboratory research use only—not for human or animal consumption.', 'amino-heaven' )
	);
}

/**
 * Whether a page supplies its own <h1> in its content.
 *
 * The provisioned pages (Quality / COAs, About, Contact, Research Use & Terms)
 * each start with their own eyebrow + h1 so the heading order matches the source
 * storefront's section design. When they do, page.php must not print the post
 * title as well, or the page would contain two h1 elements.
 *
 * @param int|WP_Post|null $post Post to inspect. Defaults to the current post.
 * @return bool
 */
function amino_heaven_page_has_own_title( $post = null ) {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	return (bool) preg_match( '/<h1[\s>]/i', (string) $post->post_content );
}

/**
 * Heading level for catalog card titles.
 *
 * The front page already has an h1 (hero) and an h2 (section heading), so cards
 * use h3 there and h2 everywhere else. Keeps heading order valid on every view.
 *
 * @return string
 */
function amino_heaven_card_heading_level() {
	$level = is_front_page() ? 'h3' : 'h2';

	return (string) apply_filters( 'amino_heaven_card_heading_level', $level );
}

/**
 * Render a set of products through WooCommerce's loop, using the theme's card.
 *
 * @param WP_Query $query Product query.
 * @param array    $args  {
 *     Optional. Loop arguments.
 *
 *     @type int    $columns       Columns. Defaults to the shop column setting.
 *     @type string $name          Loop name, used by templates for branching.
 *     @type string $heading_level Card heading level.
 *     @type bool   $compact       Render compact cards (cross-sell rows).
 * }
 * @return bool Whether anything was rendered.
 */
function amino_heaven_product_grid( $query, $args = array() ) {
	if ( ! function_exists( 'wc_set_loop_prop' ) || ! $query instanceof WP_Query ) {
		return false;
	}

	$args = wp_parse_args(
		$args,
		array(
			'columns'       => (int) apply_filters( 'loop_shop_columns', 3 ),
			'name'          => 'amino-heaven-grid',
			'heading_level' => amino_heaven_card_heading_level(),
			'compact'       => false,
		)
	);

	if ( ! $query->have_posts() ) {
		return false;
	}

	$per_page = (int) $query->get( 'posts_per_page' );

	wc_set_loop_prop( 'columns', $args['columns'] );
	wc_set_loop_prop( 'name', $args['name'] );
	wc_set_loop_prop( 'is_paginated', false );
	wc_set_loop_prop( 'total', (int) $query->found_posts );
	wc_set_loop_prop( 'total_pages', (int) $query->max_num_pages );
	wc_set_loop_prop( 'per_page', $per_page > 0 ? $per_page : (int) $query->found_posts );
	wc_set_loop_prop( 'current_page', max( 1, (int) get_query_var( 'paged' ) ) );
	wc_set_loop_prop( 'ah_heading_level', $args['heading_level'] );
	wc_set_loop_prop( 'ah_compact', (bool) $args['compact'] );

	woocommerce_product_loop_start();

	while ( $query->have_posts() ) {
		$query->the_post();
		wc_get_template_part( 'content', 'product' );
	}

	woocommerce_product_loop_end();
	wp_reset_postdata();
	wc_reset_loop();

	return true;
}

/**
 * Currency formatting settings handed to the card script.
 *
 * Mirrors `wc_price()` so JavaScript-updated display matches the server-rendered
 * price exactly. Display only — the cart remains authoritative.
 *
 * @return array<string,mixed>
 */
function amino_heaven_currency_config() {
	if ( ! function_exists( 'wc_get_price_decimals' ) ) {
		return array();
	}

	return array(
		'symbol'      => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
		'position'    => get_option( 'woocommerce_currency_pos', 'left' ),
		'decimals'    => (int) wc_get_price_decimals(),
		'decimalSep'  => wc_get_price_decimal_separator(),
		'thousandSep' => wc_get_price_thousand_separator(),
	);
}
