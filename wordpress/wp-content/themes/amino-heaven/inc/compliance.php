<?php
/**
 * Entry gate: 21+ and research-use acknowledgement.
 *
 * Behaviour carried over from the source storefront:
 *   - a blocking dialog is shown before the storefront can be browsed;
 *   - *two* confirmations are required (21+ and research-use-only);
 *   - acceptance lasts for the current browser session only, so a new session
 *     is gated again.
 *
 * Implementation notes:
 *   - Acceptance is written to `sessionStorage` (client) *and* to a session
 *     cookie (server). The cookie lets PHP decide whether to render the dialog
 *     at all, which removes the flash of a modal for returning visitors and
 *     makes the gate effective across every template.
 *   - JavaScript-disabled visitors get a real, no-JS form inside a <noscript>
 *     block that posts back with a nonce, so the gate degrades gracefully
 *     instead of trapping the visitor behind an unclickable overlay.
 *   - Administrators, WP-CLI, REST/AJAX/cron/feed requests and recognised
 *     crawlers are never gated.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

const AMINO_HEAVEN_AGE_COOKIE = 'ah_age_verified';
const AMINO_HEAVEN_AGE_ACTION = 'amino_heaven_age_gate';

/**
 * Whether an administrator has switched the entry gate off.
 *
 * @return bool
 */
function amino_heaven_age_gate_enabled() {
	return (bool) get_theme_mod( 'amino_heaven_age_gate_enabled', true );
}

/**
 * Whether the current request has already accepted the gate.
 *
 * @return bool
 */
function amino_heaven_is_age_verified() {
	if ( isset( $_COOKIE[ AMINO_HEAVEN_AGE_COOKIE ] ) && '1' === sanitize_text_field( wp_unslash( $_COOKIE[ AMINO_HEAVEN_AGE_COOKIE ] ) ) ) {
		return true;
	}

	return false;
}

/**
 * Detect common crawlers so the entry gate does not become an interstitial in
 * search results. The storefront content is part of the document either way,
 * but serving the dialog to crawlers is both pointless and harmful to SEO.
 *
 * @return bool
 */
function amino_heaven_is_crawler() {
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
		? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
		: '';

	if ( '' === $user_agent ) {
		return true;
	}

	$patterns = array(
		'bot',
		'crawl',
		'spider',
		'slurp',
		'facebookexternalhit',
		'embedly',
		'quora link preview',
		'outbrain',
		'pinterest',
		'vkShare',
		'W3C_Validator',
		'Validator.nu',
		'WhatsApp',
		'Google-InspectionTool',
		'PTST',
	);

	$is_crawler = false;
	foreach ( $patterns as $pattern ) {
		if ( false !== stripos( $user_agent, $pattern ) ) {
			$is_crawler = true;
			break;
		}
	}

	return (bool) apply_filters( 'amino_heaven_is_crawler', $is_crawler, $user_agent );
}

/**
 * Decide whether the entry gate markup should be printed for this request.
 *
 * @return bool
 */
function amino_heaven_age_gate_should_render() {
	if ( ! amino_heaven_age_gate_enabled() ) {
		return false;
	}

	if ( amino_heaven_is_age_verified() ) {
		return false;
	}

	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return false;
	}

	if ( is_feed() || is_robots() || is_trackback() ) {
		return false;
	}

	// Never obstruct store staff while they work.
	if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		return false;
	}

	if ( amino_heaven_is_crawler() ) {
		return false;
	}

	return (bool) apply_filters( 'amino_heaven_age_gate_should_render', true );
}

/**
 * Handle the no-JavaScript confirmation POST.
 *
 * Runs on `template_redirect` so the cookie can be set before any output.
 */
function amino_heaven_handle_age_gate_submission() {
	if ( ! isset( $_POST['ah_age_gate_submit'] ) ) {
		return;
	}

	if ( ! amino_heaven_age_gate_enabled() ) {
		return;
	}

	$nonce = isset( $_POST['ah_age_gate_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['ah_age_gate_nonce'] ) )
		: '';

	if ( ! wp_verify_nonce( $nonce, AMINO_HEAVEN_AGE_ACTION ) ) {
		return;
	}

	$is_of_age    = isset( $_POST['ah_confirm_age'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['ah_confirm_age'] ) );
	$research_use = isset( $_POST['ah_confirm_research'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['ah_confirm_research'] ) );

	if ( ! $is_of_age || ! $research_use ) {
		return;
	}

	$redirect = wp_get_referer();

	if ( ! $redirect ) {
		$redirect = home_url( '/' );
	}

	// Session cookie: no expiry, so a new browser session is gated again.
	setcookie(
		AMINO_HEAVEN_AGE_COOKIE,
		'1',
		array(
			'expires'  => 0,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => false, // Must be readable by the client-side gate.
			'samesite' => 'Lax',
		)
	);

	$_COOKIE[ AMINO_HEAVEN_AGE_COOKIE ] = '1';

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'template_redirect', 'amino_heaven_handle_age_gate_submission' );

/**
 * Print the entry gate immediately after <body> opens.
 */
function amino_heaven_render_age_gate() {
	if ( ! amino_heaven_age_gate_should_render() ) {
		return;
	}

	$title_id = 'ah-age-gate-title';
	$desc_id  = 'ah-age-gate-description';
	?>
	<div id="ah-age-gate" class="age-gate" role="presentation" data-ah-age-gate>
		<div
			class="age-dialog"
			role="dialog"
			aria-modal="true"
			aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
			aria-describedby="<?php echo esc_attr( $desc_id ); ?>"
			tabindex="-1"
			data-ah-age-dialog
		>
			<div class="age-dialog-mark" aria-hidden="true">
				<?php amino_heaven_the_icon( 'flask' ); ?>
			</div>

			<p class="eyebrow"><?php esc_html_e( 'Restricted access', 'amino-heaven' ); ?></p>
			<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php esc_html_e( 'Age Verification', 'amino-heaven' ); ?></h2>
			<p id="<?php echo esc_attr( $desc_id ); ?>">
				<?php esc_html_e( 'Amino Heaven sells research peptides exclusively for laboratory and analytical research. You must be 21 or older to enter.', 'amino-heaven' ); ?>
			</p>

			<div class="age-confirmations ah-gate-js">
				<label for="ah-confirm-age">
					<input type="checkbox" id="ah-confirm-age" data-ah-confirm-age>
					<span><?php esc_html_e( 'I confirm that I am 21 years of age or older.', 'amino-heaven' ); ?></span>
				</label>
				<label for="ah-confirm-research">
					<input type="checkbox" id="ah-confirm-research" data-ah-confirm-research>
					<span><?php esc_html_e( 'I acknowledge that all products are for laboratory research use only and are not for human consumption.', 'amino-heaven' ); ?></span>
				</label>
			</div>

			<div class="age-actions ah-gate-js">
				<button type="button" class="ah-button" data-ah-age-enter disabled aria-disabled="true">
					<?php esc_html_e( 'I Agree and Enter', 'amino-heaven' ); ?>
				</button>
				<button type="button" class="ah-button is-outline" data-ah-age-exit>
					<?php esc_html_e( 'Exit', 'amino-heaven' ); ?>
				</button>
			</div>

			<p class="ah-gate-status screen-reader-text" role="status" aria-live="polite" data-ah-age-status></p>

			<noscript>
				<form class="ah-gate-nojs" method="post" action="<?php echo esc_url( home_url( add_query_arg( array() ) ) ); ?>">
					<?php wp_nonce_field( AMINO_HEAVEN_AGE_ACTION, 'ah_age_gate_nonce' ); ?>
					<div class="age-confirmations">
						<label for="ah-nojs-age">
							<input type="checkbox" id="ah-nojs-age" name="ah_confirm_age" value="1" required>
							<span><?php esc_html_e( 'I confirm that I am 21 years of age or older.', 'amino-heaven' ); ?></span>
						</label>
						<label for="ah-nojs-research">
							<input type="checkbox" id="ah-nojs-research" name="ah_confirm_research" value="1" required>
							<span><?php esc_html_e( 'I acknowledge that all products are for laboratory research use only and are not for human consumption.', 'amino-heaven' ); ?></span>
						</label>
					</div>
					<div class="age-actions">
						<button type="submit" class="ah-button" name="ah_age_gate_submit" value="1">
							<?php esc_html_e( 'I Agree and Enter', 'amino-heaven' ); ?>
						</button>
						<a class="ah-button is-outline" href="about:blank"><?php esc_html_e( 'Exit', 'amino-heaven' ); ?></a>
					</div>
					<p class="ah-gate-noscript-note">
						<?php esc_html_e( 'JavaScript is disabled in this browser. Confirming will set a session cookie and reload the page.', 'amino-heaven' ); ?>
					</p>
				</form>
			</noscript>
		</div>
	</div>
	<?php
}
add_action( 'wp_body_open', 'amino_heaven_render_age_gate', 5 );

/**
 * Research-use reminder paragraph for order emails and the thank-you page.
 *
 * Kept here (rather than in the WooCommerce module) because it is compliance
 * copy rather than commerce behaviour.
 *
 * @return string
 */
function amino_heaven_research_use_email_notice() {
	return (string) apply_filters(
		'amino_heaven_research_use_email_notice',
		__( 'Research use only: all products are supplied exclusively for in vitro laboratory research and analytical purposes. They are not for human or animal consumption, and are not for therapeutic or veterinary use.', 'amino-heaven' )
	);
}
