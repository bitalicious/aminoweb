/**
 * Customizer live preview for Amino Heaven theme options.
 */
( function ( $ ) {
	'use strict';

	wp.customize( 'amino_heaven_sale_ticker_text', function ( value ) {
		value.bind( function ( to ) {
			$( '.sale-ticker div' ).text( to );
		} );
	} );

	wp.customize( 'amino_heaven_footer_tagline', function ( value ) {
		value.bind( function ( to ) {
			$( '.footer-brand p' ).first().text( to );
		} );
	} );

	wp.customize( 'amino_heaven_contact_email', function ( value ) {
		value.bind( function ( to ) {
			$( '.footer-brand a[href^="mailto:"]' ).attr( 'href', 'mailto:' + to ).text( to );
		} );
	} );
} )( jQuery );
