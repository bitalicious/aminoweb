<?php
/**
 * Certificate of Analysis (COA) product metadata.
 *
 * The source storefront rendered a "View COA" button on every catalog card, but
 * the button was inert and no COA data existed. Rather than dropping the feature
 * or faking documents, the COA is modelled as editable WooCommerce product meta:
 *
 *   _amino_heaven_coa_url    Public URL of the batch COA (PDF or page).
 *   _amino_heaven_coa_label  Optional button label override.
 *   _amino_heaven_lot_number Optional batch/lot reference.
 *
 * Both fields are edited from a dedicated "Certificate of Analysis" tab in the
 * WooCommerce product data panel, so an administrator can publish a COA without
 * a developer. When a product has no COA yet the theme links to the site's COA
 * hub instead of rendering a dead control.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

const AMINO_HEAVEN_COA_URL_META   = '_amino_heaven_coa_url';
const AMINO_HEAVEN_COA_LABEL_META = '_amino_heaven_coa_label';
const AMINO_HEAVEN_LOT_META       = '_amino_heaven_lot_number';

/**
 * Register the COA meta so it is sanitised, REST visible and revision aware.
 */
function amino_heaven_register_product_meta() {
	$meta = array(
		AMINO_HEAVEN_COA_URL_META   => array(
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'auth_callback'     => function () {
				return current_user_can( 'edit_products' );
			},
		),
		AMINO_HEAVEN_COA_LABEL_META => array(
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function () {
				return current_user_can( 'edit_products' );
			},
		),
		AMINO_HEAVEN_LOT_META       => array(
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function () {
				return current_user_can( 'edit_products' );
			},
		),
	);

	foreach ( $meta as $key => $args ) {
		register_post_meta(
			'product',
			$key,
			array_merge(
				$args,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => $args['sanitize_callback'],
				)
			)
		);
	}
}
add_action( 'init', 'amino_heaven_register_product_meta' );

/**
 * Add a Certificate of Analysis tab to the WooCommerce product data panel.
 *
 * @param array<string,array> $tabs Existing tabs.
 * @return array<string,array>
 */
function amino_heaven_product_data_tab( $tabs ) {
	$tabs['amino_heaven_coa'] = array(
		'label'    => __( 'Certificate of Analysis', 'amino-heaven' ),
		'target'   => 'amino_heaven_coa_product_data',
		'class'    => array( 'show_if_simple', 'show_if_variable', 'show_if_external', 'show_if_grouped' ),
		'priority' => 65,
	);

	return $tabs;
}
add_filter( 'woocommerce_product_data_tabs', 'amino_heaven_product_data_tab' );

/**
 * Render the COA panel.
 */
function amino_heaven_product_data_panel() {
	global $post;

	$product_id = $post instanceof WP_Post ? $post->ID : 0;

	echo '<div id="amino_heaven_coa_product_data" class="panel woocommerce_options_panel hidden">';

	echo '<div class="options_group">';

	woocommerce_wp_text_input(
		array(
			'id'          => AMINO_HEAVEN_COA_URL_META,
			'value'       => (string) get_post_meta( $product_id, AMINO_HEAVEN_COA_URL_META, true ),
			'label'       => __( 'COA URL', 'amino-heaven' ),
			'description' => __( 'Link to the batch Certificate of Analysis (PDF or page). Leave empty to link customers to the site COA hub instead.', 'amino-heaven' ),
			'desc_tip'    => true,
			'type'        => 'text',
			'placeholder' => 'https://example.com/coa/batch-123.pdf',
		)
	);

	woocommerce_wp_text_input(
		array(
			'id'          => AMINO_HEAVEN_COA_LABEL_META,
			'value'       => (string) get_post_meta( $product_id, AMINO_HEAVEN_COA_LABEL_META, true ),
			'label'       => __( 'COA button label', 'amino-heaven' ),
			'description' => __( 'Optional. Defaults to "View COA".', 'amino-heaven' ),
			'desc_tip'    => true,
			'type'        => 'text',
		)
	);

	woocommerce_wp_text_input(
		array(
			'id'          => AMINO_HEAVEN_LOT_META,
			'value'       => (string) get_post_meta( $product_id, AMINO_HEAVEN_LOT_META, true ),
			'label'       => __( 'Lot / batch number', 'amino-heaven' ),
			'description' => __( 'Optional. Shown on the product page next to the COA.', 'amino-heaven' ),
			'desc_tip'    => true,
			'type'        => 'text',
		)
	);

	echo '</div>';
	echo '</div>';
}
add_action( 'woocommerce_product_data_panels', 'amino_heaven_product_data_panel' );

/**
 * Persist the COA panel fields.
 *
 * @param int $product_id Product being saved.
 */
function amino_heaven_save_product_data( $product_id ) {
	$product_id = absint( $product_id );

	if ( ! $product_id || ! current_user_can( 'edit_product', $product_id ) ) {
		return;
	}

	// WooCommerce verifies its own nonce before this action fires.
	$fields = array(
		AMINO_HEAVEN_COA_URL_META   => 'esc_url_raw',
		AMINO_HEAVEN_COA_LABEL_META => 'sanitize_text_field',
		AMINO_HEAVEN_LOT_META       => 'sanitize_text_field',
	);

	foreach ( $fields as $key => $sanitizer ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce nonce already verified.
			continue;
		}

		$raw   = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised on the next line.
		$value = call_user_func( $sanitizer, $raw );

		if ( '' === $value ) {
			delete_post_meta( $product_id, $key );
		} else {
			update_post_meta( $product_id, $key, $value );
		}
	}
}
add_action( 'woocommerce_process_product_meta', 'amino_heaven_save_product_data' );

/**
 * The URL customers should reach when a product has no batch-specific COA yet.
 *
 * Defaults to the Quality / COAs page created by the theme's provisioning
 * command, and can be overridden in the Customizer.
 *
 * @return string
 */
function amino_heaven_coa_fallback_url() {
	$url = get_theme_mod( 'amino_heaven_coa_fallback_url', '' );

	if ( $url ) {
		return $url;
	}

	$page = get_page_by_path( 'quality-coas' );

	if ( $page instanceof WP_Post ) {
		return get_permalink( $page );
	}

	return home_url( '/' );
}

/**
 * Get the COA link details for a product.
 *
 * @param WC_Product|int $product Product or product ID.
 * @return array{url:string,label:string,is_specific:bool,lot:string}
 */
function amino_heaven_get_coa( $product ) {
	if ( ! $product instanceof WC_Product ) {
		$product = wc_get_product( $product );
	}

	$empty = array(
		'url'         => '',
		'label'       => '',
		'is_specific' => false,
		'lot'         => '',
	);

	if ( ! $product instanceof WC_Product ) {
		return $empty;
	}

	$product_id = $product->get_id();
	$url        = (string) get_post_meta( $product_id, AMINO_HEAVEN_COA_URL_META, true );
	$label      = (string) get_post_meta( $product_id, AMINO_HEAVEN_COA_LABEL_META, true );
	$lot        = (string) get_post_meta( $product_id, AMINO_HEAVEN_LOT_META, true );

	$is_specific = ( '' !== $url );

	if ( ! $is_specific ) {
		$url = amino_heaven_coa_fallback_url();
	}

	if ( '' === $label ) {
		$label = __( 'View COA', 'amino-heaven' );
	}

	return array(
		'url'         => $url,
		'label'       => $label,
		'is_specific' => $is_specific,
		'lot'         => $lot,
	);
}

/**
 * Render the COA button for a product.
 *
 * @param WC_Product|int $product Product or product ID.
 * @param array          $args    {
 *     Optional. Rendering arguments.
 *
 *     @type string $class Extra CSS classes.
 *     @type bool   $echo  Whether to print. Default true.
 * }
 * @return string
 */
function amino_heaven_coa_button( $product, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'class' => '',
			'echo'  => true,
		)
	);

	$coa = amino_heaven_get_coa( $product );

	if ( '' === $coa['url'] ) {
		return '';
	}

	// The source storefront rendered this as a filled primary button, so the
	// visual treatment is kept. When no batch certificate exists yet the button
	// carries `is-pending` (a styling hook for child themes) and an accessible
	// description explaining that it leads to the Quality / COAs page instead.
	$classes = 'ah-button ah-coa-button';

	if ( ! $coa['is_specific'] ) {
		$classes .= ' is-pending';
	}

	if ( $args['class'] ) {
		$classes .= ' ' . $args['class'];
	}

	$description = $coa['is_specific']
		? __( 'Opens the Certificate of Analysis for this batch in a new tab.', 'amino-heaven' )
		: __( 'No batch-specific Certificate of Analysis has been published for this lot yet. Opens the Quality and Certificates of Analysis page.', 'amino-heaven' );

	$html  = '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $coa['url'] ) . '"';
	$html .= ' aria-describedby="ah-coa-note-' . esc_attr( (string) ( $product instanceof WC_Product ? $product->get_id() : (int) $product ) ) . '"';

	if ( $coa['is_specific'] ) {
		$html .= ' target="_blank" rel="noopener noreferrer"';
	}

	$html .= '>';
	$html .= amino_heaven_get_icon( 'file', array( 'size' => 15 ) );
	$html .= '<span>' . esc_html( $coa['label'] ) . '</span>';
	$html .= '</a>';

	$html .= '<span class="screen-reader-text" id="ah-coa-note-' . esc_attr( (string) ( $product instanceof WC_Product ? $product->get_id() : (int) $product ) ) . '">';
	$html .= esc_html( $description );
	$html .= '</span>';

	if ( $args['echo'] ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}

	return $html;
}

/**
 * Show the lot number and COA link in the product page meta list.
 */
function amino_heaven_product_meta_coa() {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$coa = amino_heaven_get_coa( $product );

	if ( '' === $coa['lot'] && ! $coa['is_specific'] ) {
		return;
	}

	echo '<div class="ah-product-coa">';

	if ( '' !== $coa['lot'] ) {
		echo '<span class="ah-lot">';
		echo esc_html__( 'Lot', 'amino-heaven' ) . ' ';
		echo '<b>' . esc_html( $coa['lot'] ) . '</b>';
		echo '</span>';
	}

	if ( $coa['is_specific'] ) {
		amino_heaven_coa_button(
			$product,
			array(
				'class' => 'is-small',
				'echo'  => true,
			)
		);
	}

	echo '</div>';
}
add_action( 'woocommerce_product_meta_start', 'amino_heaven_product_meta_coa' );
