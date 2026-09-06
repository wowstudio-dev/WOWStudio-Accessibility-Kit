<?php
/**
 * Underlines on links inside body text.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\ProvidesCss;

defined( 'ABSPATH' ) || exit;

/**
 * Underlines links in running text, where colour alone is not enough.
 *
 * WCAG 1.4.1 is about not using colour as the only way of conveying something,
 * and a link in a paragraph is the everyday case: if the only thing separating
 * it from the words around it is a different colour, then for a reader with any
 * form of colour blindness it is not separated at all. Around one man in twelve
 * is affected by some degree of it.
 *
 * Scoped to content, not to the whole page, and that scoping is the entire
 * design of this fix. Underlining every link on the site would put a line under
 * every navigation item, every button styled as a link, and every card that
 * happens to be wrapped in an anchor — which looks broken, and would make this
 * the fix people switch straight back off. Navigation is understood as
 * navigation from its position and grouping; a link buried mid-sentence has
 * nothing but its appearance.
 *
 * @since 0.19.0
 */
final class LinkUnderline implements ProvidesCss {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'link-underline';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Underline links in body text', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Underlines links that sit inside paragraphs and lists. If colour is the only thing marking a link, readers who cannot distinguish that colour cannot see it is a link at all. Navigation, buttons and menus are left alone, because those are recognisable from where they sit rather than from how they look.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array( 'link-marked-by-colour-alone' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'This changes how your pages look, which none of the other fixes really do. It targets links inside paragraphs, list items and table cells; a theme that builds body text out of something else will not be covered, and a design that wraps whole cards in a link inside a paragraph may pick up a line you did not want.', 'wowstudio-accessibility-kit' );
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
		return '/* Underline links in running text only: paragraphs, lists, table cells,
   quotations. Navigation and buttons are recognisable from their position. */
p > a[href],
li > a[href],
td > a[href],
dd > a[href],
blockquote a[href],
figcaption > a[href] {
	text-decoration: underline;
	text-underline-offset: 0.15em;
}

/* Except where the link is only wrapping an image, where a line under the
   picture says nothing and looks like a mistake. */
p > a[href]:has(> img:only-child),
li > a[href]:has(> img:only-child) {
	text-decoration: none;
}';
	}
}
