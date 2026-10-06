<?php
/**
 * Store provisioning: WooCommerce settings, payments, shipping, pages.
 *
 * Everything here uses WooCommerce/WooCommerce-core option APIs and the
 * `WC_Shipping_Zones` API — no custom shipping calculator, no bespoke checkout,
 * no direct schema changes. The command is idempotent: running it twice leaves
 * the store in the same state.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Configures the WooCommerce store for the Amino Heaven deployment.
 */
class Amino_Heaven_Store_Setup {

	/**
	 * Default flat-rate cost used when SHIPPING_FLAT_RATE_COST is not provided.
	 *
	 * This is a development placeholder, not a business decision: the source
	 * storefront never published a shipping price. Override it in `.env` (or in
	 * WooCommerce > Settings > Shipping) before taking orders.
	 */
	const PLACEHOLDER_FLAT_RATE = '9.95';

	/**
	 * Applied changes, for reporting.
	 *
	 * @var string[]
	 */
	private $notes = array();

	/**
	 * Run every configuration step.
	 *
	 * @return string[] Change notes.
	 */
	public function run() {
		$this->configure_general();
		$this->configure_payments();
		$this->configure_shipping();
		$this->configure_pages();
		$this->configure_checkout_behaviour();

		return $this->notes;
	}

	/**
	 * Print the change notes.
	 *
	 * @return string[]
	 */
	public function get_notes() {
		return $this->notes;
	}

	/**
	 * Record a change.
	 *
	 * @param string $note Note.
	 */
	private function note( $note ) {
		$this->notes[] = $note;
	}

	/**
	 * Update an option only when it differs, and record the change.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Value.
	 * @param string $label Human-readable label.
	 * @return bool Whether a write happened.
	 */
	private function set_option( $key, $value, $label ) {
		if ( get_option( $key ) === $value ) {
			return false;
		}

		update_option( $key, $value );
		$this->note( sprintf( '%s → %s', $label, is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * General
	 * ------------------------------------------------------------------ */

	/**
	 * Region, currency and tax settings.
	 */
	private function configure_general() {
		// US store, US currency, US-only selling and shipping.
		$this->set_option( 'woocommerce_default_country', 'US', 'Store country' );
		$this->set_option( 'woocommerce_currency', 'USD', 'Currency' );
		$this->set_option( 'woocommerce_currency_pos', 'left', 'Currency position' );
		$this->set_option( 'woocommerce_price_num_decimals', '2', 'Price decimals' );
		$this->set_option( 'woocommerce_price_decimal_sep', '.', 'Decimal separator' );
		$this->set_option( 'woocommerce_price_thousand_sep', ',', 'Thousand separator' );

		$this->set_option( 'woocommerce_allowed_countries', 'specific', 'Selling locations mode' );
		$this->set_option( 'woocommerce_specific_allowed_countries', array( 'US' ), 'Selling locations' );
		$this->set_option( 'woocommerce_ship_to_countries', 'specific', 'Shipping locations mode' );
		$this->set_option( 'woocommerce_specific_ship_to_countries', array( 'US' ), 'Shipping locations' );

		// No tax rates exist in the source project, so tax calculation stays off
		// rather than inventing rates.
		$this->set_option( 'woocommerce_calc_taxes', 'no', 'Tax calculation' );
		$this->set_option( 'woocommerce_prices_include_tax', 'no', 'Prices entered with tax' );

		// US customary units.
		$this->set_option( 'woocommerce_weight_unit', 'lbs', 'Weight unit' );
		$this->set_option( 'woocommerce_dimension_unit', 'in', 'Dimension unit' );

		// Inventory management on, but the seeded products are not stock-managed
		// (the source storefront always shows "In Stock").
		$this->set_option( 'woocommerce_manage_stock', 'yes', 'Stock management' );

		// The storefront has no customer reviews.
		$this->set_option( 'woocommerce_enable_reviews', 'no', 'Product reviews' );
		$this->set_option( 'woocommerce_enable_review_rating', 'no', 'Product ratings' );

		// Make sure the storefront is publicly visible: WooCommerce 9.1+ defaults
		// fresh installs to "Coming soon" mode.
		$this->set_option( 'woocommerce_coming_soon', 'no', 'Store visibility' );
		$this->set_option( 'woocommerce_task_list_hidden', 'yes', 'Setup task list' );

		// Store address placeholders. WooCommerce requires an address for invoices
		// and tax; the source project does not publish one, so this is a clearly
		// marked placeholder that the administrator must replace.
		$this->set_option( 'woocommerce_store_address', 'PLACEHOLDER — set your store address', 'Store address' );
		$this->set_option( 'woocommerce_store_city', 'PLACEHOLDER', 'Store city' );
		$this->set_option( 'woocommerce_store_postcode', '00000', 'Store postcode' );

		$store_email = getenv( 'STORE_EMAIL' );

		if ( is_string( $store_email ) && is_email( $store_email ) ) {
			$this->set_option( 'woocommerce_store_email', $store_email, 'Store email' );
			$this->set_option( 'admin_email', $store_email, 'Administration email' );
		}

		$this->set_option( 'timezone_string', 'America/Los_Angeles', 'Timezone' );
		$this->set_option( 'blogdescription', 'Analytical-grade research compounds verified by independent U.S. laboratories', 'Tagline' );
	}

	/* ---------------------------------------------------------------------
	 * Payments
	 * ------------------------------------------------------------------ */

	/**
	 * Enable the two native offline gateways: BACS and Cash on delivery.
	 */
	private function configure_payments() {
		$research_note = __( 'Orders are accepted exclusively from qualified personnel for in vitro research, laboratory analysis and development purposes. Products are not for human or animal consumption.', 'amino-heaven' );

		// --- Direct bank transfer (BACS) -----------------------------------
		$bacs = get_option( 'woocommerce_bacs_settings', array() );
		$bacs = is_array( $bacs ) ? $bacs : array();

		$bacs['enabled']      = 'yes';
		$bacs['title']        = __( 'Direct bank transfer', 'amino-heaven' );
		$bacs['description']  = __( 'Pay by bank transfer. Use your order number as the payment reference. Your order is released once the funds have cleared.', 'amino-heaven' );
		$bacs['instructions'] = __( 'Thank you for your order. Please transfer the order total using the bank details below, quoting your order number as the reference. Orders are released for dispatch once the transfer has cleared.', 'amino-heaven' )
			. "\n\n" . $research_note;

		update_option( 'woocommerce_bacs_settings', $bacs );
		$this->note( 'Bank transfer (BACS) enabled' );

		// Account details are stored in their own option as a list of accounts.
		// The source project publishes no bank details, so these are explicitly
		// marked placeholders. Replace them in WooCommerce > Settings > Payments.
		$accounts = get_option( 'woocommerce_bacs_accounts', array() );

		if ( empty( $accounts ) || ! is_array( $accounts ) ) {
			update_option(
				'woocommerce_bacs_accounts',
				array(
					array(
						'account_name'   => getenv( 'BANK_ACCOUNT_NAME' ) ?: 'PLACEHOLDER — set the account name',
						'account_number' => getenv( 'BANK_ACCOUNT_NUMBER' ) ?: 'PLACEHOLDER-0000000000',
						'bank_name'      => getenv( 'BANK_NAME' ) ?: 'PLACEHOLDER — set the bank name',
						'sort_code'      => getenv( 'BANK_SORT_CODE' ) ?: 'PLACEHOLDER',
						'iban'           => getenv( 'BANK_IBAN' ) ?: 'PLACEHOLDER',
						'bic'            => getenv( 'BANK_BIC' ) ?: 'PLACEHOLDER',
					),
				)
			);
			$this->note( 'Bank transfer account details set to PLACEHOLDERS (replace before taking payments)' );
		}

		// --- Cash on delivery (COD) ---------------------------------------
		$cod = get_option( 'woocommerce_cod_settings', array() );
		$cod = is_array( $cod ) ? $cod : array();

		$cod['enabled']      = 'yes';
		$cod['title']        = __( 'Cash on delivery', 'amino-heaven' );
		$cod['description']  = __( 'Pay in cash when your order is delivered.', 'amino-heaven' ) . ' ' . $research_note;
		$cod['instructions'] = __( 'Your order will be paid for in cash on delivery.', 'amino-heaven' );

		update_option( 'woocommerce_cod_settings', $cod );
		$this->note( 'Cash on delivery enabled' );

		// No online gateways are configured: the project explicitly does not
		// require Stripe, PayPal or wallet payments.
		foreach ( array( 'woocommerce_stripe_settings', 'woocommerce_paypal_settings' ) as $gateway_option ) {
			$settings = get_option( $gateway_option, array() );

			if ( is_array( $settings ) && isset( $settings['enabled'] ) && 'yes' === $settings['enabled'] ) {
				$settings['enabled'] = 'no';
				update_option( $gateway_option, $settings );
				$this->note( sprintf( 'Disabled non-required gateway %s', $gateway_option ) );
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Shipping
	 * ------------------------------------------------------------------ */

	/**
	 * Create a United States shipping zone with a single flat-rate method.
	 */
	private function configure_shipping() {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return;
		}

		$zone_name = __( 'United States', 'amino-heaven' );
		$zone      = null;

		foreach ( WC_Shipping_Zones::get_zones() as $existing ) {
			if ( isset( $existing['zone_name'] ) && $existing['zone_name'] === $zone_name ) {
				$zone = WC_Shipping_Zones::get_zone( (int) $existing['zone_id'] );
				break;
			}
		}

		if ( ! $zone instanceof WC_Shipping_Zone ) {
			$zone = new WC_Shipping_Zone();
			$zone->set_zone_name( $zone_name );
			$zone->add_location( 'US', 'country' );
			$this->note( sprintf( 'Created shipping zone "%s" for US addresses', $zone_name ) );
		}

		$zone->save();
		$zone_id = $zone->get_id();

		// Converge on exactly one flat-rate method. `get_shipping_methods()`
		// returns WC_Shipping_Method objects keyed by instance id, whose method
		// type is `->id` (not `->method_id`).
		$flat_rate_instance = 0;

		foreach ( $zone->get_shipping_methods() as $method ) {
			$method_type = isset( $method->id ) ? (string) $method->id : '';
			$instance_id = isset( $method->instance_id ) ? (int) $method->instance_id : 0;

			if ( $instance_id <= 0 ) {
				continue;
			}

			if ( 'flat_rate' === $method_type && 0 === $flat_rate_instance ) {
				$flat_rate_instance = $instance_id;
				continue;
			}

			// Either a different method type or a duplicate flat rate.
			$zone->delete_shipping_method( $instance_id );
			delete_option( 'woocommerce_flat_rate_' . $instance_id . '_settings' );
			$this->note( sprintf( 'Removed shipping method "%s" (instance %d)', $method_type, $instance_id ) );
		}

		if ( ! $flat_rate_instance ) {
			$flat_rate_instance = (int) $zone->add_shipping_method( 'flat_rate' );
			$this->note( 'Added a Flat rate shipping method' );
		}

		$cost = getenv( 'SHIPPING_FLAT_RATE_COST' );

		if ( ! is_string( $cost ) || '' === trim( $cost ) ) {
			$cost = self::PLACEHOLDER_FLAT_RATE;
			$this->note( sprintf( 'NOTE: flat rate cost is the development placeholder %s — replace it before launch', $cost ) );
		}

		update_option(
			'woocommerce_flat_rate_' . $flat_rate_instance . '_settings',
			array(
				'title'      => __( 'Flat rate shipping', 'amino-heaven' ),
				'tax_status' => 'taxable',
				'cost'       => wc_format_decimal( $cost ),
			)
		);
		$this->note( sprintf( 'Flat rate cost set to %s (zone %d, instance %d)', wc_format_decimal( $cost ), $zone_id, $flat_rate_instance ) );
	}

	/* ---------------------------------------------------------------------
	 * Pages
	 * ------------------------------------------------------------------ */

	/**
	 * Ensure the WooCommerce pages exist and are assigned.
	 */
	private function configure_pages() {
		// WooCommerce creates these on activation; verify rather than assume.
		$expected = array(
			'shop'      => 'shop',
			'cart'      => 'cart',
			'checkout'  => 'checkout',
			'myaccount' => 'my-account',
		);

		foreach ( $expected as $page_key => $slug ) {
			$page_id = (int) wc_get_page_id( $page_key );

			if ( $page_id > 0 && get_post( $page_id ) instanceof WP_Post ) {
				continue;
			}

			$existing = get_page_by_path( $slug );

			if ( $existing instanceof WP_Post ) {
				update_option( 'woocommerce_' . $page_key . '_page_id', $existing->ID );
				$this->note( sprintf( 'Linked existing page "%s" to WooCommerce %s', $slug, $page_key ) );
			}
		}

		// Terms and conditions page: the research-use agreement. WooCommerce
		// renders a required acknowledgement checkbox at checkout when this is set.
		$terms = get_page_by_path( 'research-use-terms' );

		if ( $terms instanceof WP_Post ) {
			$this->set_option( 'woocommerce_terms_page_id', (string) $terms->ID, 'Terms and conditions page' );
		}

		$this->use_classic_commerce_pages();
	}

	/**
	 * Serve the cart, checkout and account pages with WooCommerce's classic
	 * server-rendered shortcodes instead of its client-rendered blocks.
	 *
	 * Both are native WooCommerce; this project uses the classic templates because
	 * they are the ones the theme's stylesheet and hooks are written against, they
	 * render on the server (so the purchasing flow works without JavaScript), and
	 * they keep the store's critical path testable and cache-friendly.
	 *
	 * Only WooCommerce's own untouched block markup is converted, and only once:
	 * if the equivalent shortcode is already present the page is left alone, so an
	 * administrator's later edits are never overwritten. Opt out entirely with:
	 *
	 *     add_filter( 'amino_heaven_use_classic_commerce_pages', '__return_false' );
	 */
	private function use_classic_commerce_pages() {
		if ( ! apply_filters( 'amino_heaven_use_classic_commerce_pages', true ) ) {
			return;
		}

		$shortcodes = array(
			'cart'      => 'woocommerce_cart',
			'checkout'  => 'woocommerce_checkout',
			'myaccount' => 'woocommerce_my_account',
		);

		foreach ( $shortcodes as $page_key => $shortcode ) {
			$page_id = (int) wc_get_page_id( $page_key );
			$page    = $page_id > 0 ? get_post( $page_id ) : null;

			if ( ! $page instanceof WP_Post ) {
				continue;
			}

			if ( has_shortcode( $page->post_content, $shortcode ) ) {
				continue;
			}

			// Leave anything that is not WooCommerce's own block page untouched.
			if ( false === strpos( $page->post_content, 'wp:woocommerce/' ) ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'           => $page_id,
					'post_content' => '[' . $shortcode . ']',
				)
			);

			$this->note( sprintf( 'Switched the %s page to the classic [%s] shortcode', $page_key, $shortcode ) );
		}
	}

	/**
	 * Checkout behaviour that matches the source storefront's UX.
	 */
	private function configure_checkout_behaviour() {
		$this->set_option( 'woocommerce_enable_guest_checkout', 'yes', 'Guest checkout' );
		$this->set_option( 'woocommerce_enable_checkout_login_reminder', 'yes', 'Checkout login reminder' );
		$this->set_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes', 'Sign-up from checkout' );
		$this->set_option( 'woocommerce_cart_redirect_after_add', 'no', 'Redirect to cart after add' );
		$this->set_option( 'woocommerce_enable_ajax_add_to_cart', 'yes', 'AJAX add to cart' );
		$this->set_option( 'woocommerce_enable_coupons', 'yes', 'Coupons' );
		$this->set_option( 'woocommerce_calc_discounts_sequentially', 'no', 'Sequential discounts' );
	}
}
