<?php
/**
 * Customizer options.
 *
 * Deliberately small: the footer, promo ribbon and compliance copy that the
 * source storefront hardcoded into React are exposed here (and/or through WP
 * menus and widgets) rather than through a bespoke admin framework.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register theme options.
 *
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 */
function amino_heaven_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'amino_heaven_storefront',
		array(
			'title'       => __( 'Amino Heaven storefront', 'amino-heaven' ),
			'description' => __( 'Promotional ribbon, entry gate, footer content and COA settings.', 'amino-heaven' ),
			'priority'    => 25,
		)
	);

	$text_fields = array(
		'amino_heaven_sale_ticker_text' => array(
			'label'   => __( 'Promotional ribbon text', 'amino-heaven' ),
			'default' => __( 'Fall Sale 30% Off Sitewide - No Code Needed', 'amino-heaven' ),
		),
		'amino_heaven_footer_tagline'   => array(
			'label'   => __( 'Footer tagline', 'amino-heaven' ),
			'default' => __( 'Discover. Innovate. Elevate.', 'amino-heaven' ),
		),
		'amino_heaven_footer_support'   => array(
			'label'   => __( 'Footer support line', 'amino-heaven' ),
			'default' => __( 'Research support available Monday–Friday.', 'amino-heaven' ),
		),
		'amino_heaven_contact_email'    => array(
			'label'   => __( 'Contact email address', 'amino-heaven' ),
			'default' => 'hello@aminoheaven.com',
		),
		'amino_heaven_scam_warning'     => array(
			'label'   => __( 'Scam warning', 'amino-heaven' ),
			'default' => __( 'Scam warning: anyone who contacts you first to solicit an order is a scammer.', 'amino-heaven' ),
		),
		'amino_heaven_newsletter_intro' => array(
			'label'   => __( 'Newsletter blurb', 'amino-heaven' ),
			'default' => __( 'Restock alerts, new COAs and promo vials.', 'amino-heaven' ),
		),
	);

	foreach ( $text_fields as $id => $field ) {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $field['default'],
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			$id,
			array(
				'label'   => $field['label'],
				'section' => 'amino_heaven_storefront',
				'type'    => 'text',
			)
		);
	}

	$wp_customize->add_setting(
		'amino_heaven_homepage_products',
		array(
			'default'           => 24,
			'sanitize_callback' => 'absint',
		)
	);
	$wp_customize->add_control(
		'amino_heaven_homepage_products',
		array(
			'label'       => __( 'Homepage catalog size', 'amino-heaven' ),
			'description' => __( 'Maximum number of compounds on the front page. Featured products are shown when any are marked featured.', 'amino-heaven' ),
			'section'     => 'amino_heaven_storefront',
			'type'        => 'number',
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 100,
				'step' => 1,
			),
		)
	);

	$wp_customize->add_setting(
		'amino_heaven_coa_fallback_url',
		array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		)
	);
	$wp_customize->add_control(
		'amino_heaven_coa_fallback_url',
		array(
			'label'       => __( 'COA hub URL', 'amino-heaven' ),
			'description' => __( 'Where "View COA" points for products without a batch-specific certificate. Defaults to the Quality &amp; COAs page.', 'amino-heaven' ),
			'section'     => 'amino_heaven_storefront',
			'type'        => 'url',
		)
	);

	$wp_customize->add_setting(
		'amino_heaven_newsletter_endpoint',
		array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		)
	);
	$wp_customize->add_control(
		'amino_heaven_newsletter_endpoint',
		array(
			'label'       => __( 'Newsletter endpoint', 'amino-heaven' ),
			'description' => __( 'Optional server-side URL that newsletter sign-ups are forwarded to. Leave empty to email the sign-up to the store address instead.', 'amino-heaven' ),
			'section'     => 'amino_heaven_storefront',
			'type'        => 'url',
		)
	);

	$wp_customize->add_setting(
		'amino_heaven_age_gate_enabled',
		array(
			'default'           => true,
			'sanitize_callback' => 'amino_heaven_sanitize_checkbox',
		)
	);
	$wp_customize->add_control(
		'amino_heaven_age_gate_enabled',
		array(
			'label'       => __( 'Show 21+ / research-use entry gate', 'amino-heaven' ),
			'description' => __( 'The gate is never shown to logged-in administrators or recognised crawlers.', 'amino-heaven' ),
			'section'     => 'amino_heaven_storefront',
			'type'        => 'checkbox',
		)
	);

	$wp_customize->add_setting(
		'amino_heaven_footer_newsletter_enabled',
		array(
			'default'           => true,
			'sanitize_callback' => 'amino_heaven_sanitize_checkbox',
		)
	);
	$wp_customize->add_control(
		'amino_heaven_footer_newsletter_enabled',
		array(
			'label'   => __( 'Show the footer newsletter form', 'amino-heaven' ),
			'section' => 'amino_heaven_storefront',
			'type'    => 'checkbox',
		)
	);

	// Live preview for the ribbon.
	$wp_customize->get_setting( 'amino_heaven_sale_ticker_text' )->transport = 'postMessage';
	$wp_customize->selective_refresh->add_partial(
		'amino_heaven_sale_ticker_text',
		array(
			'selector'        => '.sale-ticker div',
			'render_callback' => function () {
				return esc_html( get_theme_mod( 'amino_heaven_sale_ticker_text', __( 'Fall Sale 30% Off Sitewide - No Code Needed', 'amino-heaven' ) ) );
			},
		)
	);
}
add_action( 'customize_register', 'amino_heaven_customize_register' );

/**
 * Sanitize a checkbox value.
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function amino_heaven_sanitize_checkbox( $value ) {
	return (bool) $value;
}

/**
 * Customizer preview script for postMessage settings.
 */
function amino_heaven_customize_preview_js() {
	wp_enqueue_script(
		'amino-heaven-customizer',
		AMINO_HEAVEN_URI . 'assets/js/customizer.js',
		array( 'customize-preview' ),
		amino_heaven_asset_version( 'assets/js/customizer.js' ),
		true
	);
}
add_action( 'customize_preview_init', 'amino_heaven_customize_preview_js' );

/**
 * Convenience accessor with a sane fallback.
 *
 * @param string $key     Theme mod key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function amino_heaven_mod( $key, $default = '' ) {
	return get_theme_mod( $key, $default );
}
