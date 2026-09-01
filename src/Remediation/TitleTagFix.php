<?php
/**
 * Making WordPress write the page title a theme forgot.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

use WOWStudio\AccessibilityKit\Core\Registrable;

defined( 'ABSPATH' ) || exit;

/**
 * The one deterministic markup fix this plugin can actually apply.
 *
 * A page with no `<title>` has exactly one correct answer, and WordPress
 * already knows it — `wp_get_document_title()` has composed it for years. The
 * only reason it is missing is that the theme never declared support for
 * `title-tag`, and a plugin may declare that on the theme's behalf.
 *
 * That makes this the proof that the whole deterministic path is real rather
 * than theoretical: no model, no diff, no review, and nothing written into
 * anybody's content. A stored flag, and core does the rest.
 *
 * Two things about the timing, both verified against core rather than assumed.
 * `add_theme_support( 'title-tag' )` refuses once `wp_loaded` has fired, so this
 * hooks `after_setup_theme`, which runs before it. And core's
 * `_wp_render_title_tag` is already attached to `wp_head` — it returns early
 * unless the support is declared, so declaring it is the entire fix.
 *
 * @since 0.13.0
 */
final class TitleTagFix implements Registrable {

	/**
	 * Where the decision is kept.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const OPTION = 'wsak_force_title_tag';

	/**
	 * Declares the support when it has been asked for.
	 *
	 * @since 0.13.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'after_setup_theme', array( $this, 'apply_support' ), 20 );
	}

	/**
	 * Adds the theme support, if this fix is switched on.
	 *
	 * @since 0.13.0
	 *
	 * @return void
	 */
	public function apply_support(): void {
		if ( ! $this->is_applied() ) {
			return;
		}

		// A theme that already declares it needs nothing from us, and saying so
		// twice is harmless — but leaving the flag set on a theme that has since
		// fixed itself would hide that it did.
		add_theme_support( 'title-tag' );
	}

	/**
	 * Reports whether this fix is switched on.
	 *
	 * @since 0.13.0
	 *
	 * @return bool
	 */
	public function is_applied(): bool {
		return (bool) get_option( self::OPTION, false );
	}

	/**
	 * Reports whether the theme needs this at all.
	 *
	 * Read after our own hook has run, so it answers "does the page have a
	 * title" rather than "did the theme ask for one" — which are the same thing
	 * from a reader's point of view and different from ours.
	 *
	 * @since 0.13.0
	 *
	 * @return bool
	 */
	public function theme_declares_it(): bool {
		return (bool) current_theme_supports( 'title-tag' );
	}

	/**
	 * Switches the fix on.
	 *
	 * @since 0.13.0
	 *
	 * @return bool
	 */
	public function apply(): bool {
		$stored = update_option( self::OPTION, true, true );

		/*
		 * Deliberately not declared in this request. Applying arrives over
		 * REST, which is always past wp_loaded, and core refuses title-tag
		 * support after that point — it returns false and emits a
		 * _doing_it_wrong notice, so the "helpful" immediate call achieved
		 * nothing except a warning in the response on any site with debugging
		 * on. The flag is stored; the next page load declares it.
		 */

		/**
		 * Fires when the title fix is switched on.
		 *
		 * @since 0.13.0
		 */
		do_action( 'wsak_title_tag_applied' );

		return $stored || $this->is_applied();
	}

	/**
	 * Switches it off again.
	 *
	 * @since 0.13.0
	 *
	 * @return bool
	 */
	public function revert(): bool {
		$removed = delete_option( self::OPTION );

		/**
		 * Fires when the title fix is switched off.
		 *
		 * @since 0.13.0
		 */
		do_action( 'wsak_title_tag_reverted' );

		return $removed;
	}

	/**
	 * Describes the fix's state for the interface.
	 *
	 * @since 0.13.0
	 *
	 * @return array<string, mixed>
	 */
	public function state(): array {
		return array(
			'applied'        => $this->is_applied(),
			'theme_declares' => $this->theme_declares_it(),
			'summary'        => __( 'WordPress composes the title itself; your theme just never asked for it. This asks on its behalf, and changes nothing in your content.', 'wowstudio-accessibility-kit' ),
			// Neither switching this on nor off can take effect in the request
			// that did it: theme support is declared long before a REST call
			// arrives. Said plainly, so a button that worked does not look like
			// a button that did nothing.
			'takes_effect'   => __( 'This takes effect the next time a page is loaded.', 'wowstudio-accessibility-kit' ),
		);
	}
}
