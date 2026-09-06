<?php
/**
 * A visible focus indicator, for themes that removed theirs.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\ProvidesCss;

defined( 'ABSPATH' ) || exit;

/**
 * Restores a visible outline on whatever currently has keyboard focus.
 *
 * Browsers ship a focus indicator. A great many themes then remove it, because
 * `outline: none` makes the design look tidier to somebody using a mouse, and
 * the cost is invisible to that person: for anybody navigating by keyboard the
 * page becomes a form they are filling in blindfold, with no way to tell where
 * they are or what pressing Enter will do.
 *
 * The rule below is deliberately blunt. It uses `:focus-visible`, so it appears
 * for keyboard use and not on mouse clicks — which is the behaviour that made
 * designers remove the outline in the first place, and removes the reason to.
 * It is `!important` because it exists specifically to beat a theme rule that
 * already said `outline: none`, and it is printed before the theme's stylesheet
 * so that anything more specific still wins.
 *
 * Two colours, one dark and one light, drawn as a doubled outline. A single
 * colour is invisible against a background that happens to match it, and a focus
 * indicator that disappears on one button is not much better than none.
 *
 * @since 0.19.0
 */
final class FocusOutline implements ProvidesCss {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'focus-outline';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Show where the keyboard focus is', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Draws a clear outline around whatever has keyboard focus. Many themes switch the browser\'s own outline off for the sake of appearance; without it, anybody navigating by keyboard cannot tell where they are on the page. This appears for keyboard use only, so mouse clicks look exactly as they do now.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'This one is deliberately forceful, because its whole job is to overrule a theme that switched the outline off. If your theme already has a focus style you like, leave this off — two indicators at once is worse than either.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		// Nothing beyond the stylesheet.
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function css(): string {
		return '/* Focus indicator. :focus-visible means keyboard use, not mouse clicks. */
:focus-visible {
	outline: 3px solid #0b3d91 !important;
	outline-offset: 2px !important;
	box-shadow: 0 0 0 6px #ffd23f !important;
	border-radius: 2px;
}

/* Anything that turned the outline off keeps a shadow that still reads. */
:focus:not(:focus-visible) {
	outline: none;
}';
	}
}
