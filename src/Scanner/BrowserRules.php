<?php
/**
 * The checks the browser pass performs.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * The browser pass's ruleset, kept deliberately small.
 *
 * Every check here needs something the server pass cannot have: a colour that
 * was actually painted, a box that was actually laid out. Nothing here could be
 * moved into PHP by writing a cleverer parser.
 *
 * The set is scoped rather than "everything axe offers", for two reasons. Two
 * engines reporting the same problem twice would be worse than useless to the
 * person reading the list. And every check we run is a check we have to be able
 * to explain in our own words and stand behind in the coverage panel — a rule
 * we merely forwarded from axe without deciding its severity or writing its
 * remediation advice is not one we can honestly claim to have made.
 *
 * @since 0.10.0
 */
final class BrowserRules {

	/**
	 * Returns every browser-pass check.
	 *
	 * @since 0.10.0
	 *
	 * @return BrowserRule[]
	 */
	public static function all(): array {
		$rules = array(
			new BrowserRule(
				'colour-contrast',
				'color-contrast',
				'1.4.3',
				Severity::Serious,
				Detection::Auto,
				__( 'Text has too little contrast with its background', 'wowstudio-accessibility-kit' ),
				__( 'The text and the colour behind it are too close in lightness for the text to be comfortably readable. This affects far more people than it might sound: everyone with low vision, anyone with colour vision deficiency, and every sighted person reading on a phone in daylight. Darken the text or lighten the background until the ratio reaches 4.5:1, or 3:1 for text at 24px, or 19px bold.', 'wowstudio-accessibility-kit' )
			),
			new BrowserRule(
				'link-marked-by-colour-alone',
				'link-in-text-block',
				'1.4.1',
				Severity::Moderate,
				Detection::Auto,
				__( 'Link inside text is marked only by colour', 'wowstudio-accessibility-kit' ),
				__( 'This link sits inside a paragraph and is distinguished from the surrounding words by colour alone. Anyone who cannot separate those two colours cannot tell it is a link. Add an underline, or another visible difference that does not depend on colour.', 'wowstudio-accessibility-kit' )
			),
			new BrowserRule(
				'target-too-small',
				'target-size',
				'2.5.8',
				Severity::Moderate,
				Detection::Auto,
				__( 'Clickable target is smaller than 24 by 24 pixels', 'wowstudio-accessibility-kit' ),
				__( 'This control is small enough to be hard to hit accurately, which matters for anyone with a tremor, limited dexterity, or simply a phone in one hand on a moving bus. Make it at least 24 by 24 CSS pixels, or leave enough clear space around it that a near miss does not activate something else.', 'wowstudio-accessibility-kit' )
			),
			new BrowserRule(
				'scrolling-region-not-reachable',
				'scrollable-region-focusable',
				'2.1.1',
				Severity::Serious,
				Detection::Auto,
				__( 'Scrollable area cannot be reached by keyboard', 'wowstudio-accessibility-kit' ),
				__( 'This area scrolls, but nothing inside it can be focused, so somebody using a keyboard rather than a mouse can never scroll it and cannot read whatever is out of sight. Give the container tabindex="0", and an accessible name so it announces as something meaningful when focus lands on it.', 'wowstudio-accessibility-kit' )
			),
			new BrowserRule(
				'hidden-element-still-focusable',
				'aria-hidden-focus',
				'4.1.2',
				Severity::Serious,
				Detection::Auto,
				__( 'Element is hidden from screen readers but still focusable', 'wowstudio-accessibility-kit' ),
				__( 'This element is marked aria-hidden="true", so a screen reader will not describe it, but it can still receive keyboard focus. The result is a focus stop that announces nothing — the user tabs, something happens, and there is no way to tell what. Either remove aria-hidden or take the element out of the tab order.', 'wowstudio-accessibility-kit' )
			),
		);

		/**
		 * Filters the browser pass ruleset.
		 *
		 * @since 0.10.0
		 *
		 * @param BrowserRule[] $rules Browser-pass checks.
		 */
		return (array) apply_filters( 'wsak_browser_rules', $rules );
	}

	/**
	 * Returns the map from axe rule ID to our rule ID.
	 *
	 * Used to validate incoming findings. An axe ID that is not a key here is
	 * one we never asked for and cannot describe, so its findings are dropped.
	 *
	 * @since 0.10.0
	 *
	 * @return array<string, string>
	 */
	public static function axe_map(): array {
		$map = array();

		foreach ( self::all() as $rule ) {
			if ( $rule instanceof BrowserRule ) {
				$map[ $rule->axe_id() ] = $rule->id();
			}
		}

		return $map;
	}
}
