<?php
/**
 * The first run.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Admin;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Where somebody lands the first time they activate this plugin.
 *
 * A new install opens on an empty report. That has been the case since the
 * first version and it has never read well: nothing here scans on its own, so
 * the honest state of a fresh install is "no data yet", and a screen full of
 * zeroes says "broken" far more loudly than it says "waiting". The empty state
 * on the dashboard patched over it with a sentence and two buttons. This
 * replaces that first moment with a setup somebody walks through.
 *
 * Three steps, and each one does something real:
 *
 * 1. What this plugin is, and — the part that matters more — what it will not
 *    claim on your behalf. Better said once, plainly, at the start than
 *    discovered later in a footnote.
 * 2. The site-wide fixes, switched on by the person rather than for them. The
 *    real screen is rendered here rather than a summary of it, so every caveat
 *    somebody is agreeing to is in front of them at the moment they agree.
 * 3. The first scan.
 *
 * Nothing is mandatory and nothing is written without being asked for. Leaving
 * halfway through is a supported outcome, not an abandoned funnel: the plugin
 * works exactly the same either way, and the flow is still there afterwards for
 * anybody who wants to come back to it.
 *
 * @since 0.29.0
 */
final class Onboarding implements Registrable {

	/**
	 * The query argument that opens the setup.
	 *
	 * A view on the dashboard page rather than a page of its own, and that is
	 * WordPress's decision rather than a preference. A page with no menu entry
	 * has to be registered and then removed from the menu, and
	 * `user_can_access_admin_page()` resolves a page's parent by walking the
	 * menu it was just taken out of: with no entry to find, it looks the page
	 * up under an empty parent, misses it in `$_registered_pages`, and refuses
	 * access. The page exists and nobody may open it.
	 *
	 * The alternative is a fifth item in a menu whose four items are the whole
	 * shape of this plugin, for a screen most people open once.
	 *
	 * @since 0.29.0
	 * @var string
	 */
	public const QUERY_ARG = 'welcome';

	/**
	 * Option recording how far the setup got.
	 *
	 * @since 0.29.0
	 * @var string
	 */
	public const OPTION = 'wsak_onboarding';

	/**
	 * Transient set at activation and read on the next admin request.
	 *
	 * A transient rather than an option because the thing it records is true
	 * for one request. If the redirect never happens — a bulk activation, a
	 * WP-CLI activation, somebody who cannot manage settings — it expires
	 * rather than waiting to ambush a later page load.
	 *
	 * @since 0.29.0
	 * @var string
	 */
	public const REDIRECT_TRANSIENT = 'wsak_welcome_redirect';

	/**
	 * Notes that the plugin has just been activated on this site.
	 *
	 * Called from the activation hook rather than hooked to `wsak_activated`,
	 * and that is not a style choice. WordPress includes the plugin file inside
	 * the activation request, by which point `plugins_loaded` has already
	 * fired — so `Plugin::boot()` never runs and no service of ours has
	 * registered a listener for anything. A hook here would be a hook nobody is
	 * on the other end of.
	 *
	 * @since 0.29.0
	 *
	 * @return void
	 */
	public static function note_activation(): void {
		if ( self::is_done() ) {
			return;
		}

		set_transient( self::REDIRECT_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Reports whether somebody has been through the setup on this site.
	 *
	 * @since 0.29.0
	 *
	 * @return bool
	 */
	public static function is_done(): bool {
		$state = get_option( self::OPTION, array() );

		return is_array( $state ) && ! empty( $state['done'] );
	}

	/**
	 * Records that the setup has been finished, or reopens it.
	 *
	 * @since 0.29.0
	 *
	 * @param bool $done Whether the setup is finished.
	 * @return void
	 */
	public static function set_done( bool $done ): void {
		update_option(
			self::OPTION,
			array(
				'done'    => $done,
				'version' => WSAK_VERSION,
			),
			false
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.29.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
	}

	/**
	 * Opens the setup once, on the first admin request after activation.
	 *
	 * @since 0.29.0
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		if ( ! $this->should_open_setup() ) {
			return;
		}

		wp_safe_redirect( self::url() );

		exit;
	}

	/**
	 * Decides whether this request is the one to open the setup on.
	 *
	 * Separate from the redirect itself so that all of it can be tested: the
	 * two lines above end in `exit`, which in a test run ends the test run.
	 *
	 * Every `false` below is a case where taking over somebody's screen would
	 * be wrong rather than merely unnecessary, and the flag is spent before any
	 * of them are reached. A flag that outlives the request it was meant for is
	 * a flag that ambushes a later one.
	 *
	 * @since 0.29.0
	 *
	 * @return bool
	 */
	public function should_open_setup(): bool {
		if ( ! get_transient( self::REDIRECT_TRANSIENT ) ) {
			return false;
		}

		delete_transient( self::REDIRECT_TRANSIENT );

		if ( wp_doing_ajax() || wp_doing_cron() || is_network_admin() ) {
			return false;
		}

		/*
		 * Activating several plugins at once. WordPress is mid-loop and every
		 * plugin after this one still has to be activated; redirecting would
		 * abandon them half done. Nobody activating six plugins at once wants a
		 * tour of the second one either.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading a flag WordPress itself put in the URL; nothing is written.
		if ( isset( $_GET['activate-multi'] ) ) {
			return false;
		}

		return current_user_can( Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * Where the setup lives.
	 *
	 * @since 0.29.0
	 *
	 * @return string
	 */
	public static function url(): string {
		return admin_url( 'admin.php?page=' . Menu::SLUG . '&' . self::QUERY_ARG . '=1' );
	}

	/**
	 * Reports whether this request asked for the setup.
	 *
	 * @since 0.29.0
	 *
	 * @return bool
	 */
	public static function is_requested(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Chooses which read-only view renders; nothing is written and no capability is decided by it.
		return isset( $_GET[ self::QUERY_ARG ] ) && '' !== (string) $_GET[ self::QUERY_ARG ];
	}
}
