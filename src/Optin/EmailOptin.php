<?php
/**
 * Letting somebody opt in with an address they actually read.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Optin;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a "use a different email address" option to the Freemius opt-in screen.
 *
 * The SDK's own connect screen has no email field. It reads
 * `$current_user->user_email` and offers no filter to change it, so whichever
 * address happens to be on the WordPress account is the address that gets the
 * confirmation mail. On a site where that address is a placeholder — the
 * `wordpress@example.com` that ships with a local environment, a role account
 * nobody reads, a developer's address on a client's site — the mail goes
 * nowhere and the opt-in can never complete. There is no way out of that state
 * from the screen itself.
 *
 * So this adds the field the SDK does not have, and hands what it collects to
 * `opt_in()`, which accepts an email precisely for this purpose and passes it
 * through as `$override_with['user_email']`. Everything after that is still the
 * SDK's: it calls the API, stores the pending state, renders its own "check
 * your mailbox" notice with a re-send button, and completes the registration
 * when the confirmation link comes back. None of that is worth reimplementing,
 * and reimplementing it would mean maintaining a copy of it.
 *
 * Two deliberate limits.
 *
 * This is *additional*, not a replacement. The SDK's screen renders exactly as
 * before and this appears underneath it, so if a future SDK version changes the
 * markup or drops the hook, the worst case is that this option disappears and
 * the normal opt-in still works.
 *
 * And it does not gate anything. Skipping the opt-in entirely leaves the plugin
 * fully usable, which is both a WordPress.org requirement — a plugin may not
 * demand registration to function — and the promise made in SPEC decision F1,
 * that checking and fixing one page stays free and unlimited.
 *
 * @since 0.15.0
 */
final class EmailOptin implements Registrable {

	/**
	 * The admin-post action that receives the submitted address.
	 *
	 * @since 0.15.0
	 * @var string
	 */
	public const ACTION = 'wsak_email_optin';

	/**
	 * Nonce action for the form.
	 *
	 * @since 0.15.0
	 * @var string
	 */
	public const NONCE = 'wsak_email_optin';

	/**
	 * Query argument carrying a validation failure back to the screen.
	 *
	 * @since 0.15.0
	 * @var string
	 */
	public const ERROR_ARG = 'wsak_optin_error';

	/**
	 * Hooks the form onto the opt-in screen and its handler onto admin-post.
	 *
	 * The Freemius hook is registered through the SDK's own add_action() so the
	 * tag gets the per-module suffix the SDK expects. Guarded because the SDK
	 * is absent from a stripped or partial install, and this plugin is built to
	 * come up as a working free plugin in that case rather than fatal.
	 *
	 * @since 0.15.0
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'wsak_fs' ) ) {
			return;
		}

		wsak_fs()->add_action( 'connect/after_actions', array( $this, 'render_form' ) );

		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Renders the disclosure and the email field inside the opt-in screen.
	 *
	 * A `<details>` rather than a scripted toggle: it is keyboard operable and
	 * announced as expandable without a line of JavaScript, which is the
	 * behaviour this plugin asks other people's themes to get right.
	 *
	 * @since 0.15.0
	 *
	 * @return void
	 */
	public function render_form(): void {
		if ( ! current_user_can( Capabilities::RUN_SCAN ) ) {
			return;
		}

		$error = $this->current_error();
		?>
		<details class="wsak-optin-email">
			<summary><?php esc_html_e( 'Use a different email address', 'wowstudio-accessibility-kit' ); ?></summary>

			<p id="wsak-optin-email-help">
				<?php
				esc_html_e(
					'By default the confirmation goes to the address on your WordPress account. If you do not read that mailbox, enter one you do — we will send the confirmation there instead.',
					'wowstudio-accessibility-kit'
				);
				?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />

				<p>
					<label for="wsak-optin-email">
						<?php esc_html_e( 'Email address', 'wowstudio-accessibility-kit' ); ?>
					</label><br />
					<input
						type="email"
						id="wsak-optin-email"
						name="wsak_optin_email"
						class="regular-text"
						autocomplete="email"
						required
						aria-describedby="wsak-optin-email-help<?php echo '' !== $error ? ' wsak-optin-email-error' : ''; ?>"
						<?php echo '' !== $error ? 'aria-invalid="true"' : ''; ?>
					/>
				</p>

				<?php if ( '' !== $error ) : ?>
					<div id="wsak-optin-email-error" role="alert" class="notice notice-error inline">
						<p><?php echo esc_html( $error ); ?></p>
					</div>
				<?php endif; ?>

				<p>
					<button type="submit" class="button button-secondary">
						<?php esc_html_e( 'Send the confirmation here', 'wowstudio-accessibility-kit' ); ?>
					</button>
				</p>
			</form>
		</details>
		<?php
	}

	/**
	 * Validates the submitted address and hands it to the SDK.
	 *
	 * The SDK's opt_in() performs the redirect itself — to the pending-confirmation
	 * screen, or straight to the dashboard when the address needs no
	 * confirmation. The redirect after it is the path where it returned instead
	 * of redirecting, which happens when the API refused the request.
	 *
	 * @since 0.15.0
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( Capabilities::RUN_SCAN ) ) {
			wp_die(
				esc_html__( 'You are not allowed to do that.', 'wowstudio-accessibility-kit' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::NONCE );

		if ( ! function_exists( 'wsak_fs' ) ) {
			$this->bounce( __( 'The licensing component is not available on this site.', 'wowstudio-accessibility-kit' ) );
		}

		$submitted = isset( $_POST['wsak_optin_email'] )
			? sanitize_email( wp_unslash( $_POST['wsak_optin_email'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- sanitize_email is the sanitiser, and is_email validates below.
			: '';

		if ( ! is_email( $submitted ) ) {
			$this->bounce( __( 'That does not look like an email address. Please check it and try again.', 'wowstudio-accessibility-kit' ) );
		}

		wsak_fs()->opt_in( $submitted );

		// Only reached when opt_in() returned rather than redirecting.
		$this->bounce( __( 'We could not start the confirmation just now. Please try again in a moment.', 'wowstudio-accessibility-kit' ) );
	}

	/**
	 * Sends the user back to the opt-in screen carrying a message.
	 *
	 * @since 0.15.0
	 *
	 * @param string $message What went wrong, already translated.
	 * @return never
	 */
	private function bounce( string $message ) {
		$referer = wp_get_referer();

		wp_safe_redirect(
			add_query_arg(
				self::ERROR_ARG,
				rawurlencode( $message ),
				false !== $referer ? $referer : admin_url( 'admin.php?page=wowstudio-accessibility-kit' )
			)
		);

		exit;
	}

	/**
	 * Returns the message bounced back from a failed submission, if any.
	 *
	 * @since 0.15.0
	 *
	 * @return string Empty when the screen was not reached from a failure.
	 */
	private function current_error(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a message this plugin put in the URL; nothing is changed.
		if ( ! isset( $_GET[ self::ERROR_ARG ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
		return sanitize_text_field( wp_unslash( $_GET[ self::ERROR_ARG ] ) );
	}
}
