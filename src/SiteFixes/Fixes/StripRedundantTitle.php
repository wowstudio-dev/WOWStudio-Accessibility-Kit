<?php
/**
 * Removing title attributes that only repeat the link text.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\RunsInBrowser;

defined( 'ABSPATH' ) || exit;

/**
 * Removes a `title` attribute that says the same thing as the visible text.
 *
 * A `title` on a link or a button does not add a name, it adds a second one.
 * Screen readers differ on what to do with two: some read both, so the reader
 * hears "Contact us, Contact us"; some prefer the title and quietly discard the
 * visible text. Neither is useful, and the tooltip it produces is invisible to
 * touch users and unreachable by keyboard, so the sighted benefit people
 * imagine it has is mostly imaginary too.
 *
 * Only exact duplication is removed, and only where the element has visible text
 * of its own. A `title` saying something different is left alone — it may be
 * doing real work, and deciding otherwise would mean judging content.
 *
 * `<iframe>` is excluded, and that exclusion matters: on an iframe the `title`
 * *is* the accessible name, and removing it would create the very fault this
 * plugin reports elsewhere. `<abbr>` is excluded for the same reason — there the
 * title is the expansion, which is the entire point of the element.
 *
 * @since 0.20.0
 */
final class StripRedundantTitle implements RunsInBrowser {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'strip-redundant-title';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Remove tooltips that repeat the link text', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Removes a title attribute from a link or button when it says exactly what the visible text already says. Two identical names mean some screen readers announce it twice and others drop the visible text; the tooltip itself is invisible on touch and unreachable by keyboard.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'Only exact duplicates are removed, and never from an iframe or an abbreviation, where the title is the element\'s real name. Runs in the browser, so it does nothing with JavaScript off.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		// Carried out in the browser. The manager enqueues the one script that
		// runs every enabled fix of this kind, and tells it this one is on.
	}
}
