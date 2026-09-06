<?php
/**
 * Putting the tab order back the way the page looks.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\RunsInBrowser;

defined( 'ABSPATH' ) || exit;

/**
 * Resets any `tabindex` above zero to zero.
 *
 * A positive tabindex does not nudge an element slightly earlier in the tab
 * order. It moves it into a separate queue that the browser visits *before*
 * everything else on the page, so a single `tabindex="1"` in a form means the
 * first Tab press from the top of the document jumps straight to it, past the
 * skip link and the whole navigation.
 *
 * The correction is deterministic and there is only one sensible answer:
 * `tabindex="0"` keeps the element focusable, which is almost always what
 * whoever wrote the number was reaching for, and returns it to the position it
 * visibly occupies. Elements at `0` and `-1` are left alone — both are correct
 * and both are common.
 *
 * @since 0.20.0
 */
final class StripPositiveTabindex implements RunsInBrowser {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'strip-positive-tabindex';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Put the tab order back in reading order', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Finds elements given a tabindex above zero and sets it to zero, so tabbing follows the order things appear on the page. A positive tabindex pulls an element ahead of everything else, which makes the first Tab press jump somewhere unexpected.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array( 'tabindex-positive' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'Runs in the browser after the page loads, so it cannot help a reader with JavaScript off, and it is a repair rather than a substitute for correcting the markup. If something on your site deliberately relies on a custom tab order, this will undo it.', 'wowstudio-accessibility-kit' );
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
