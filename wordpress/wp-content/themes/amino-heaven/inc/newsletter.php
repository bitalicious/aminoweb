<?php
/**
 * Newsletter sign-up.
 *
 * The source storefront's newsletter form was client-side only
 * (`onSubmit={e => e.preventDefault()}`) and had no destination. Rather than
 * inventing credentials for a third-party service, the theme:
 *
 *   1. renders a real, nonce-protected form;
 *   2. validates server-side with a honeypot for bots;
 *   3. forwards the address to the endpoint configured in the Customizer, when
 *      one is set (so API keys stay server-side); and
 *   4. otherwise notifies the store address by email, which works out of the box.
 *
 * The `amino_heaven_newsletter_signup` action lets a plugin take over storage
 * later without touching this file.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

const AMINO_HEAVEN_NEWSLETTER_ACTION = 'amino_heaven_newsletter';

/**
 * Render the newsletter form.
 *
 * @param array $args {
 *     Optional. Rendering arguments.
 *
 *     @type string $class Extra CSS classes.
 * }
 */
function amino_heaven_newsletter_form( $args = array() ) {
	$args = wp_parse_args( $args, array( 'class' => '' ) );

	$status = isset( $_GET['ah_newsletter'] ) ? sanitize_key( wp_unslash( $_GET['ah_newsletter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.

	$messages = array(
		'ok'      => array( 'is-success', __( 'Thanks — you are on the list.', 'amino-heaven' ) ),
		'invalid' => array( 'is-error', __( 'Please enter a valid email address.', 'amino-heaven' ) ),
		'error'   => array( 'is-error', __( 'Something went wrong. Please try again later.', 'amino-heaven' ) ),
	);
	?>
	<form class="ah-newsletter <?php echo esc_attr( $args['class'] ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( AMINO_HEAVEN_NEWSLETTER_ACTION ); ?>" />
		<?php wp_nonce_field( AMINO_HEAVEN_NEWSLETTER_ACTION, 'ah_newsletter_nonce' ); ?>

		<label class="screen-reader-text" for="ah-newsletter-email"><?php esc_html_e( 'Email address', 'amino-heaven' ); ?></label>
		<input
			type="email"
			id="ah-newsletter-email"
			name="ah_email"
			required
			placeholder="<?php esc_attr_e( 'Email address', 'amino-heaven' ); ?>"
			autocomplete="email"
		/>
		<button type="submit" class="ah-button"><?php esc_html_e( 'Subscribe', 'amino-heaven' ); ?></button>

		<div class="ah-honeypot" aria-hidden="true">
			<label for="ah-newsletter-website"><?php esc_html_e( 'Website', 'amino-heaven' ); ?></label>
			<input type="text" id="ah-newsletter-website" name="ah_website" tabindex="-1" autocomplete="off" />
		</div>
	</form>

	<?php if ( isset( $messages[ $status ] ) ) : ?>
		<p class="ah-newsletter-status <?php echo esc_attr( $messages[ $status ][0] ); ?>" role="status">
			<?php echo esc_html( $messages[ $status ][1] ); ?>
		</p>
	<?php endif; ?>
	<?php
}

/**
 * Handle the newsletter submission.
 */
function amino_heaven_handle_newsletter_submission() {
	if ( ! isset( $_POST['ah_newsletter_nonce'] ) ) {
		return;
	}

	check_admin_referer( AMINO_HEAVEN_NEWSLETTER_ACTION, 'ah_newsletter_nonce' );

	$redirect = wp_get_referer();

	if ( ! $redirect ) {
		$redirect = home_url( '/' );
	}

	$redirect = remove_query_arg( 'ah_newsletter', $redirect );

	// Honeypot: pretend success so bots do not learn anything.
	$honeypot = isset( $_POST['ah_website'] ) ? sanitize_text_field( wp_unslash( $_POST['ah_website'] ) ) : '';
	if ( '' !== $honeypot ) {
		wp_safe_redirect( add_query_arg( 'ah_newsletter', 'ok', $redirect ) );
		exit;
	}

	$email = isset( $_POST['ah_email'] ) ? sanitize_email( wp_unslash( $_POST['ah_email'] ) ) : '';

	if ( ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'ah_newsletter', 'invalid', $redirect ) );
		exit;
	}

	/**
	 * Fires after a newsletter address has been validated.
	 *
	 * @param string $email Validated email address.
	 */
	do_action( 'amino_heaven_newsletter_signup', $email );

	$delivered = amino_heaven_deliver_newsletter_signup( $email );

	wp_safe_redirect( add_query_arg( 'ah_newsletter', $delivered ? 'ok' : 'error', $redirect ) );
	exit;
}
add_action( 'admin_post_nopriv_' . AMINO_HEAVEN_NEWSLETTER_ACTION, 'amino_heaven_handle_newsletter_submission' );
add_action( 'admin_post_' . AMINO_HEAVEN_NEWSLETTER_ACTION, 'amino_heaven_handle_newsletter_submission' );

/**
 * Forward a validated sign-up to its destination.
 *
 * @param string $email Validated email address.
 * @return bool Whether the sign-up was delivered.
 */
function amino_heaven_deliver_newsletter_signup( $email ) {
	$endpoint = (string) get_theme_mod( 'amino_heaven_newsletter_endpoint', '' );

	if ( '' !== $endpoint ) {
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode(
					array(
						'email'  => $email,
						'source' => home_url( '/' ),
						'site'   => get_bloginfo( 'name', 'display' ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return $code >= 200 && $code < 300;
	}

	// No integration configured: notify the store so nothing is silently lost.
	$to = get_theme_mod( 'amino_heaven_contact_email', 'hello@aminoheaven.com' );

	if ( ! is_email( $to ) ) {
		$to = get_option( 'admin_email' );
	}

	$subject = sprintf(
		/* translators: %s: site name. */
		__( '[%s] Newsletter sign-up', 'amino-heaven' ),
		wp_specialchars_decode( get_bloginfo( 'name', 'display' ), ENT_QUOTES )
	);

	$body = sprintf(
		/* translators: 1: email address, 2: site URL. */
		__( "New newsletter sign-up.\n\nEmail: %1\$s\nSite: %2\$s", 'amino-heaven' ),
		$email,
		home_url( '/' )
	);

	/**
	 * Filter the newsletter notification before it is sent.
	 *
	 * @param array  $mail    Mail arguments (to, subject, body).
	 * @param string $email   Subscriber address.
	 */
	$mail = apply_filters(
		'amino_heaven_newsletter_notification',
		array(
			'to'      => $to,
			'subject' => $subject,
			'body'    => $body,
		),
		$email
	);

	return wp_mail( $mail['to'], $mail['subject'], $mail['body'] );
}
