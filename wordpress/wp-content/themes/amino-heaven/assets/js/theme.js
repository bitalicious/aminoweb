/**
 * Amino Heaven — front-end behaviour.
 *
 * Plain ES5-compatible vanilla JavaScript, no build step, no framework. The
 * source storefront was a React SPA; only the behaviour that WordPress actually
 * needs was ported:
 *
 *   1. mobile navigation toggle
 *   2. the 21+ / research-use entry gate (session scoped, focus trapped)
 *   3. catalog card interaction (strength switching, live totals)
 *   4. quantity steppers around WooCommerce's native inputs
 *
 * Nothing here is authoritative for pricing: the cart and order totals are
 * always recalculated by WooCommerce on the server. The card script only mirrors
 * the server-rendered variation prices while the shopper makes a selection.
 */
( function () {
	'use strict';

	var config = window.aminoHeaven || {};
	var i18n = config.i18n || {};
	var AGE_KEY = 'amino-heaven-age-verified';

	/* ---------------------------------------------------------------------
	 * Small helpers
	 * ------------------------------------------------------------------ */
	function qs( selector, context ) {
		return ( context || document ).querySelector( selector );
	}

	function qsa( selector, context ) {
		return Array.prototype.slice.call( ( context || document ).querySelectorAll( selector ) );
	}

	function on( node, type, handler ) {
		if ( node ) {
			node.addEventListener( type, handler );
		}
	}

	function isVisible( node ) {
		return !! ( node && ( node.offsetWidth || node.offsetHeight || node.getClientRects().length ) );
	}

	function safeStorageGet( store, key ) {
		try {
			return window[ store ].getItem( key );
		} catch ( e ) {
			return null;
		}
	}

	function safeStorageSet( store, key, value ) {
		try {
			window[ store ].setItem( key, value );
		} catch ( e ) {
			// Storage disabled (private mode, cookie policy) — the cookie carries
			// the same state server-side, so this is not fatal.
		}
	}

	/**
	 * Build a price formatter that mirrors WooCommerce's wc_price().
	 *
	 * @param {Object} currency Currency settings from the server.
	 * @return {Function} Formatter taking a number and returning a string.
	 */
	function createPriceFormatter( currency ) {
		if ( ! currency || ! currency.symbol ) {
			return function ( amount ) {
				return String( amount );
			};
		}

		var decimals = typeof currency.decimals === 'number' ? currency.decimals : 2;
		var decimalSep = currency.decimalSep || '.';
		var thousandSep = currency.thousandSep || ',';

		return function ( amount ) {
			var value = Number( amount );

			if ( isNaN( value ) ) {
				return '';
			}

			var parts = value.toFixed( decimals ).split( '.' );
			var whole = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, thousandSep );
			var number = parts[ 1 ] ? whole + decimalSep + parts[ 1 ] : whole;

			switch ( currency.position ) {
				case 'right':
					return number + currency.symbol;
				case 'left_space':
					return currency.symbol + '\u00a0' + number;
				case 'right_space':
					return number + '\u00a0' + currency.symbol;
				case 'left':
				default:
					return currency.symbol + number;
			}
		};
	}

	/* ---------------------------------------------------------------------
	 * 1. Mobile navigation
	 * ------------------------------------------------------------------ */
	function initMobileMenu() {
		var toggle = qs( '[data-ah-menu-toggle]' );
		var nav = qs( '[data-ah-mobile-nav]' );

		if ( ! toggle || ! nav ) {
			return;
		}

		var openIcon = qs( '[data-ah-menu-icon="open"]', toggle );
		var closeIcon = qs( '[data-ah-menu-icon="close"]', toggle );

		function setOpen( open ) {
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

			if ( open ) {
				nav.removeAttribute( 'hidden' );
			} else {
				nav.setAttribute( 'hidden', 'hidden' );
			}

			if ( openIcon ) {
				openIcon.hidden = open;
			}
			if ( closeIcon ) {
				closeIcon.hidden = ! open;
			}
		}

		setOpen( false );

		on( toggle, 'click', function () {
			setOpen( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
		} );

		// Close on Escape and when focus leaves the panel.
		on( document, 'keydown', function ( event ) {
			if ( event.key === 'Escape' && toggle.getAttribute( 'aria-expanded' ) === 'true' ) {
				setOpen( false );
				toggle.focus();
			}
		} );

		// Navigation inside the panel closes it.
		qsa( 'a', nav ).forEach( function ( link ) {
			on( link, 'click', function () {
				setOpen( false );
			} );
		} );

		// Reset to the desktop layout when the viewport grows.
		window.addEventListener( 'resize', function () {
			if ( window.innerWidth >= 768 ) {
				setOpen( false );
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * 2. Entry gate (21+ / research use)
	 * ------------------------------------------------------------------ */
	function initAgeGate() {
		var gate = qs( '[data-ah-age-gate]' );

		if ( ! gate ) {
			return;
		}

		function dismiss() {
			gate.setAttribute( 'hidden', 'hidden' );
			document.documentElement.classList.add( 'ah-gate-passed' );
			document.body.classList.remove( 'ah-gate-open' );
		}

		// Already accepted earlier in this browser session.
		if ( safeStorageGet( 'sessionStorage', AGE_KEY ) === 'true' ) {
			dismiss();
			return;
		}

		var dialog = qs( '[data-ah-age-dialog]', gate );
		var confirmAge = qs( '[data-ah-confirm-age]', gate );
		var confirmResearch = qs( '[data-ah-confirm-research]', gate );
		var enterButton = qs( '[data-ah-age-enter]', gate );
		var exitButton = qs( '[data-ah-age-exit]', gate );
		var status = qs( '[data-ah-age-status]', gate );
		var previouslyFocused = document.activeElement;

		document.body.classList.add( 'ah-gate-open' );

		if ( dialog ) {
			dialog.focus();
		}

		function isConfirmed() {
			return !! ( confirmAge && confirmAge.checked && confirmResearch && confirmResearch.checked );
		}

		function syncButton() {
			var confirmed = isConfirmed();

			if ( enterButton ) {
				enterButton.disabled = ! confirmed;
				enterButton.setAttribute( 'aria-disabled', confirmed ? 'false' : 'true' );
			}

			return confirmed;
		}

		function setStatus( message ) {
			if ( status ) {
				status.textContent = message || '';
			}
		}

		function accept() {
			if ( ! syncButton() ) {
				setStatus( i18n.ageGateDenied || 'You must confirm both statements to continue.' );
				return;
			}

			safeStorageSet( 'sessionStorage', AGE_KEY, 'true' );

			// Session cookie (no max-age) so PHP can skip rendering the gate on
			// subsequent page views without a flash of the overlay.
			var cookie = 'ah_age_verified=1; path=' + ( config.cookiePath || '/' ) + '; SameSite=Lax';

			if ( window.location.protocol === 'https:' ) {
				cookie += '; Secure';
			}

			document.cookie = cookie;

			setStatus( '' );
			dismiss();

			if ( previouslyFocused && typeof previouslyFocused.focus === 'function' ) {
				try {
					previouslyFocused.focus();
				} catch ( e ) {
					// Element may no longer be focusable; nothing to do.
				}
			}
		}

		syncButton();

		on( confirmAge, 'change', syncButton );
		on( confirmResearch, 'change', syncButton );
		on( enterButton, 'click', accept );

		on( exitButton, 'click', function () {
			window.location.assign( 'about:blank' );
		} );

		// Keep keyboard focus inside the dialog (it is a blocking modal).
		on( gate, 'keydown', function ( event ) {
			if ( event.key !== 'Tab' ) {
				return;
			}

			var focusable = qsa(
				'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
				dialog || gate
			).filter( function ( node ) {
				return isVisible( node ) && node.getAttribute( 'aria-hidden' ) !== 'true';
			} );

			if ( ! focusable.length ) {
				return;
			}

			var first = focusable[ 0 ];
			var last = focusable[ focusable.length - 1 ];

			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * 3. Quantity steppers (catalog cards and WooCommerce inputs)
	 * ------------------------------------------------------------------ */
	function initQuantitySteppers() {
		qsa( '[data-ah-quantity], .ah-qty' ).forEach( function ( wrapper ) {
			var input = qs( 'input.qty, input[data-ah-qty-input]', wrapper );

			if ( ! input || input.readOnly || input.disabled ) {
				return;
			}

			qsa( '[data-ah-qty]', wrapper ).forEach( function ( button ) {
				on( button, 'click', function () {
					var direction = button.getAttribute( 'data-ah-qty' );
					var step = parseFloat( input.getAttribute( 'step' ) );
					var min = parseFloat( input.getAttribute( 'min' ) );
					var max = parseFloat( input.getAttribute( 'max' ) );

					if ( isNaN( step ) || step <= 0 ) {
						step = 1;
					}

					var value = parseFloat( input.value );

					if ( isNaN( value ) ) {
						value = isNaN( min ) ? 1 : min;
					}

					value += direction === 'increase' ? step : -step;

					if ( ! isNaN( min ) && value < min ) {
						value = min;
					}

					if ( ! isNaN( max ) && max > 0 && value > max ) {
						value = max;
					}

					input.value = value;

					// Let any listener (WooCommerce's own cart script, or the card
					// renderer below) react to the change.
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				} );
			} );
		} );
	}

	/* ---------------------------------------------------------------------
	 * 4. Catalog cards
	 * ------------------------------------------------------------------ */
	function initCatalogCards() {
		qsa( '[data-ah-card-form]' ).forEach( function ( form ) {
			var dataNode = qs( '.ah-card-data', form );
			var card = form.closest ? form.closest( 'li' ) : form.parentNode;

			if ( ! dataNode || ! card ) {
				return;
			}

			var data;

			try {
				data = JSON.parse( dataNode.textContent );
			} catch ( e ) {
				return;
			}

			if ( ! data || ! data.variations ) {
				return;
			}

			var format = createPriceFormatter( data.currency );
			var radios = qsa( '[data-ah-variation-radio]', form );
			// The strength selector sits above the form (it is a sibling of it),
			// so it must be queried from the card, not from the form.
			var doseButtons = qsa( '[data-ah-dose-option]', card );
			var priceEl = qs( '[data-ah-price]', card );
			var regularEl = qs( '[data-ah-regular]', card );
			var bulkLabelEl = qs( '[data-ah-bulk-label]', card );
			var bulkRegularEl = qs( '[data-ah-bulk-regular]', card );
			var bulkPriceEl = qs( '[data-ah-bulk-price]', card );
			var totalEl = qs( '[data-ah-total-value]', card );
			var imageEl = qs( '[data-ah-card-image]', card );
			var strengthEl = qs( '[data-ah-card-strength]', card );
			var addDoseEl = qs( '.ah-add-dose', card );
			var qtyInput = qs( '[data-ah-qty-input]', card );
			var addButton = qs( '[data-ah-add]', form );

			if ( ! radios.length ) {
				return;
			}

			function checkedRadio() {
				return form.querySelector( '[data-ah-variation-radio]:checked' );
			}

			function variationOf( radio ) {
				return radio ? data.variations[ radio.value ] : null;
			}

			/**
			 * Largest pack available for a given strength (drives the bulk line).
			 */
			function bulkFor( dose ) {
				var best = null;

				Object.keys( data.variations ).forEach( function ( id ) {
					var variation = data.variations[ id ];

					if ( variation.dose !== dose ) {
						return;
					}

					if ( ! best || variation.packCount > best.packCount ) {
						best = variation;
					}
				} );

				return best;
			}

			function updateTotal() {
				var variation = variationOf( checkedRadio() );

				if ( ! variation || ! totalEl ) {
					return;
				}

				var quantity = qtyInput ? parseInt( qtyInput.value, 10 ) : 1;

				if ( isNaN( quantity ) || quantity < 1 ) {
					quantity = 1;
				}

				totalEl.textContent = format( variation.price * quantity );
			}

			function render() {
				var radio = checkedRadio();
				var variation = variationOf( radio );

				if ( ! variation ) {
					return;
				}

				if ( priceEl ) {
					priceEl.textContent = format( variation.price );
				}

				if ( regularEl ) {
					regularEl.textContent = format( variation.regular );
					regularEl.style.display = variation.onSale ? '' : 'none';
				}

				var bulk = bulkFor( variation.dose );

				if ( bulk ) {
					if ( bulkLabelEl ) {
						bulkLabelEl.textContent = bulk.packCount + ' ';
						bulkLabelEl.textContent += ( bulk.packCount === 1 ? 'vial' : 'vials' ) + ' = ';
					}
					if ( bulkRegularEl ) {
						bulkRegularEl.textContent = format( bulk.regular );
					}
					if ( bulkPriceEl ) {
						bulkPriceEl.textContent = format( bulk.price );
					}
				}

				if ( imageEl && variation.image && imageEl.getAttribute( 'src' ) !== variation.image ) {
					imageEl.setAttribute( 'src', variation.image );

					if ( variation.srcset ) {
						imageEl.setAttribute( 'srcset', variation.srcset );
					} else {
						imageEl.removeAttribute( 'srcset' );
					}

					if ( variation.sizes ) {
						imageEl.setAttribute( 'sizes', variation.sizes );
					} else {
						imageEl.removeAttribute( 'sizes' );
					}
				}

				qsa( '.ah-pack-option', form ).forEach( function ( label ) {
					var input = qs( '[data-ah-variation-radio]', label );
					label.classList.toggle( 'is-active', !! ( input && input.checked ) );
				} );

				if ( strengthEl ) {
					strengthEl.textContent = variation.dose;
				}

				if ( addDoseEl ) {
					addDoseEl.textContent = variation.dose;
				}

				updateTotal();
			}

			function selectDose( dose ) {
				radios.forEach( function ( radio ) {
					var label = radio.closest( '.ah-pack-option' );
					var hidden = radio.getAttribute( 'data-ah-dose' ) !== dose;

					if ( label ) {
						label.classList.toggle( 'is-dose-hidden', hidden );
					}
				} );

				doseButtons.forEach( function ( button ) {
					var active = button.getAttribute( 'data-ah-dose-option' ) === dose;
					button.classList.toggle( 'active', active );
					button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				} );

				var current = checkedRadio();

				if ( ! current || current.getAttribute( 'data-ah-dose' ) !== dose ) {
					var first = radios.filter( function ( radio ) {
						return radio.getAttribute( 'data-ah-dose' ) === dose;
					} )[ 0 ];

					if ( first ) {
						first.checked = true;
					}
				}

				render();
			}

			radios.forEach( function ( radio ) {
				on( radio, 'change', render );
			} );

			doseButtons.forEach( function ( button ) {
				on( button, 'click', function () {
					selectDose( button.getAttribute( 'data-ah-dose-option' ) );
				} );
			} );

			on( qtyInput, 'change', updateTotal );
			on( qtyInput, 'input', updateTotal );

			// Prevent a double submission while WooCommerce navigates away.
			on( form, 'submit', function () {
				if ( addButton ) {
					addButton.disabled = true;
					addButton.setAttribute( 'aria-busy', 'true' );
				}
			} );

			// Initial state: mirror what the server already rendered.
			render();
		} );
	}

	/* ---------------------------------------------------------------------
	 * 5. Anchor scrolling with a sticky header
	 * ------------------------------------------------------------------ */
	function initAnchorOffset() {
		function offsetFor( id ) {
			var target = document.getElementById( id.replace( '#', '' ) );

			if ( ! target ) {
				return;
			}

			var header = qs( '.site-header' );
			var offset = header ? header.getBoundingClientRect().height + 12 : 90;

			target.style.scrollMarginTop = offset + 'px';
		}

		qsa( 'a[href^="#"]' ).forEach( function ( link ) {
			var href = link.getAttribute( 'href' );

			if ( href && href.length > 1 ) {
				offsetFor( href );
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------------ */
	function boot() {
		initAgeGate();
		initMobileMenu();
		initQuantitySteppers();
		initCatalogCards();
		initAnchorOffset();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
