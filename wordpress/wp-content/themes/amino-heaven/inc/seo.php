<?php
/**
 * Structured metadata.
 *
 * The source storefront shipped a title, meta description, Open Graph tags and
 * a canonical link. WordPress emits the title natively; this module restores the
 * rest *only when no SEO plugin is active*, so it can never duplicate tags.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a dedicated SEO plugin is handling metadata for this site.
 *
 * @return bool
 */
function amino_heaven_seo_plugin_active() {
	$detected = (
		defined( 'WPSEO_VERSION' )        // Yoast SEO.
		|| defined( 'RANK_MATH_VERSION' ) // Rank Math.
		|| defined( 'AIOSEO_VERSION' )    // All in One SEO.
		|| defined( 'SEOPRESS_VERSION' )  // SEOPress.
		|| class_exists( 'The_SEO_Framework\\Load' )
	);

	return (bool) apply_filters( 'amino_heaven_seo_plugin_active', $detected );
}

/**
 * A description for the current view.
 *
 * @return string
 */
function amino_heaven_meta_description() {
	$description = '';

	if ( is_front_page() ) {
		$description = get_bloginfo( 'description', 'display' );
	} elseif ( is_singular() ) {
		$post = get_queried_object();

		if ( $post instanceof WP_Post ) {
			$description = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( $post->post_content );
		}
	} elseif ( is_product_taxonomy() || is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			$description = $term->description;
		}
	} elseif ( is_home() ) {
		$description = get_bloginfo( 'description', 'display' );
	} elseif ( is_search() ) {
		/* translators: %s: search query. */
		$description = sprintf( __( 'Search results for “%s”.', 'amino-heaven' ), get_search_query() );
	}

	$description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $description ) ) );

	if ( mb_strlen( $description ) > 160 ) {
		$description = mb_substr( $description, 0, 157 ) . '…';
	}

	return (string) apply_filters( 'amino_heaven_meta_description', $description );
}

/**
 * The canonical URL for the current view.
 *
 * Singular canonical output is left to WordPress core; this fills the gaps for
 * the front page and archives.
 *
 * @return string
 */
function amino_heaven_canonical_url() {
	if ( is_front_page() ) {
		return home_url( '/' );
	}

	if ( is_home() && ! is_front_page() ) {
		$page_for_posts = (int) get_option( 'page_for_posts' );

		return $page_for_posts ? get_permalink( $page_for_posts ) : home_url( '/' );
	}

	if ( is_archive() || is_search() ) {
		$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$base  = get_pagenum_link( $paged, false );

		return $base;
	}

	return '';
}

/**
 * Print description, Open Graph, Twitter and canonical tags.
 */
function amino_heaven_print_seo_meta() {
	if ( amino_heaven_seo_plugin_active() || is_admin() || is_feed() ) {
		return;
	}

	$description = amino_heaven_meta_description();
	$title       = wp_get_document_title();
	$canonical   = amino_heaven_canonical_url();

	if ( '' !== $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
	}

	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name', 'display' ) ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";

	if ( '' !== $description ) {
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	}

	$og_type = is_singular( 'product' ) ? 'product' : ( is_singular() ? 'article' : 'website' );
	echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";

	echo '<meta property="og:url" content="' . esc_url( $canonical ? $canonical : home_url( add_query_arg( array() ) ) ) . '" />' . "\n";

	$image = amino_heaven_social_image();

	if ( $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
	}

	echo '<meta name="twitter:card" content="' . esc_attr( $image ? 'summary_large_image' : 'summary' ) . '" />' . "\n";

	if ( '' !== $canonical ) {
		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
	}
}
add_action( 'wp_head', 'amino_heaven_print_seo_meta', 5 );

/**
 * Best available social preview image.
 *
 * @return string
 */
function amino_heaven_social_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		$url = get_the_post_thumbnail_url( null, 'large' );

		if ( $url ) {
			return $url;
		}
	}

	$custom_logo_id = (int) get_theme_mod( 'custom_logo' );

	if ( $custom_logo_id ) {
		$url = wp_get_attachment_image_url( $custom_logo_id, 'full' );

		if ( $url ) {
			return $url;
		}
	}

	return '';
}
