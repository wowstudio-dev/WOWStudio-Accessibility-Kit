<?php
/**
 * The first run.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Admin;

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
 * It is reached by opening the plugin, and by nothing else. There was a redirect
 * on activation once — one-shot, capability-checked, skipped on bulk activation
 * — and it went in the 1.0.1 review round. Guideline 11 asks plugins not to
 * hijack the admin, and taking over the screen somebody was already on is the
 * plainest reading of that whatever the safeguards around it. So the dashboard
 * simply opens on the setup while the setup has not been done, which is the
 * same first-run experience without reaching outside our own screens for it.
 *
 * This class therefore registers no hooks at all. Every method is static and
 * called from the places that need the answer.
 *
 * @since 0.29.0
 */
final class Onboarding {

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
		$asked = isset( $_GET[ self::QUERY_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_ARG ] ) ) : '';

		return '' !== $asked;
	}
}
