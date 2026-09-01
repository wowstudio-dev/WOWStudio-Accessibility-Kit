<?php
/**
 * The checks the browser pass performs.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

use WOWStudio\AccessibilityKit\Remediation\FixKind;
use WOWStudio\AccessibilityKit\Remediation\FixPlan;
use WOWStudio\AccessibilityKit\Remediation\FixTarget;

defined( 'ABSPATH' ) || exit;

/**
 * The browser pass's ruleset, kept deliberately small.
 *
 * Every check here needs something the server pass cannot have: a colour that
 * was actually painted, a box that was actually laid out. Nothing here could be
 * moved into PHP by writing a cleverer parser.
 *
 * The set is small on purpose, and we implement it rather than importing an
 * engine. Two passes reporting the same problem twice would be worse than
 * useless to the person reading the list. And every check we run has to be one
 * we can explain in our own words and stand behind in the coverage panel — a
 * rule forwarded from someone else's library, with their severity and their
 * phrasing, is not one we could honestly claim to have made.
 *
 * Owning the implementation also buys the thing the product is actually for.
 * The hard part of contrast is not the arithmetic, it is the cases where the
 * effective background cannot be determined — an image behind the text, a
 * gradient, stacked translucency. Those get reported as needing a person, which
 * is the honest answer and the one a borrowed engine would not give.
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
				'1.4.3',
				Severity::Serious,
				Detection::Auto,
				__( 'Text has too little contrast with its background', 'wowstudio-accessibility-kit' ),
				__( 'The text and the colour behind it are too close in lightness for the text to be comfortably readable. This affects far more people than it might sound: everyone with low vision, anyone with colour vision deficiency, and every sighted person reading on a phone in daylight. Darken the text or lighten the background until the ratio reaches 4.5:1, or 3:1 for text at 24px, or 19px bold.', 'wowstudio-accessibility-kit' ),
				new FixPlan(
					FixKind::Deterministic,
					FixTarget::Css,
					__( 'A colour that clears the required ratio can be computed, and it is written into the site\'s own Additional CSS. The proposal keeps the hue and saturation already chosen and moves only as far as it must.', 'wowstudio-accessibility-kit' )
				),
				__( 'Anyone with low vision or colour blindness — and anyone reading on a phone in daylight — will struggle to make this out.', 'wowstudio-accessibility-kit' )
			),
			new BrowserRule(
				'link-marked-by-colour-alone',
				'1.4.1',
				Severity::Moderate,
				Detection::Auto,
				__( 'Link inside text is marked only by colour', 'wowstudio-accessibility-kit' ),
				__( 'This link sits inside a paragraph and is distinguished from the surrounding words by colour alone. Anyone who cannot separate those two colours cannot tell it is a link. Add an underline, or another visible difference that does not depend on colour.', 'wowstudio-accessibility-kit' ),
				new FixPlan(
					FixKind::Deterministic,
					FixTarget::Css,
					__( 'An underline is what the criterion asks for and what readers already recognise. One rule, no judgement.', 'wowstudio-accessibility-kit' )
				),
				__( 'Anyone who cannot tell those two colours apart cannot tell there is a link here at all.', 'wowstudio-accessibility-kit' )
			),
			new BrowserRule(
				'target-too-small',
				'2.5.8',
				Severity::Moderate,
				Detection::Auto,
				__( 'Clickable target is smaller than 24 by 24 pixels', 'wowstudio-accessibility-kit' ),
				__( 'This control is small enough to be hard to hit accurately, which matters for anyone with a tremor, limited dexterity, or simply a phone in one hand on a moving bus. Make it at least 24 by 24 CSS pixels, or leave enough clear space around it that a near miss does not activate something else.', 'wowstudio-accessibility-kit' ),
				new FixPlan(
					FixKind::Deterministic,
					FixTarget::Css,
					__( 'A minimum size of 24 by 24 is the criterion itself. Applied as a floor, so the control can still grow.', 'wowstudio-accessibility-kit' )
				),
				__( 'Hard to hit accurately for anyone with a tremor, limited dexterity, or a phone in one hand on a moving bus.', 'wowstudio-accessibility-kit' )
			),
			new BrowserRule(
				'scrolling-region-not-reachable',
				'2.1.1',
				Severity::Serious,
				Detection::Auto,
				__( 'Scrollable area cannot be reached by keyboard', 'wowstudio-accessibility-kit' ),
				__( 'This area scrolls, but nothing inside it can be focused, so somebody using a keyboard rather than a mouse can never scroll it and cannot read whatever is out of sight. Give the container tabindex="0", and an accessible name so it announces as something meaningful when focus lands on it.', 'wowstudio-accessibility-kit' ),
				new FixPlan(
					FixKind::Manual,
					FixTarget::Theme,
					__( 'The container needs tabindex and an accessible name. The attribute is mechanical; the name is a description of what is inside, and both live in the theme.', 'wowstudio-accessibility-kit' )
				),
				__( 'Anyone using a keyboard rather than a mouse can never scroll this, so whatever is out of sight stays out of reach.', 'wowstudio-accessibility-kit' )
			),
			new BrowserRule(
				'hidden-element-still-focusable',
				'4.1.2',
				Severity::Serious,
				Detection::Auto,
				__( 'Element is hidden from screen readers but still focusable', 'wowstudio-accessibility-kit' ),
				__( 'This element is marked aria-hidden="true", so a screen reader will not describe it, but it can still receive keyboard focus. The result is a focus stop that announces nothing — the user tabs, something happens, and there is no way to tell what. Either remove aria-hidden or take the element out of the tab order.', 'wowstudio-accessibility-kit' ),
				new FixPlan(
					FixKind::Manual,
					FixTarget::Theme,
					__( 'Either the element should be revealed or it should leave the tab order, and only somebody who knows why it is hidden can say which.', 'wowstudio-accessibility-kit' )
				),
				__( 'Keyboard users land on something that announces nothing. Focus moves and there is no way to tell where it went.', 'wowstudio-accessibility-kit' )
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
	 * Returns the rule IDs the browser pass is allowed to report.
	 *
	 * The browser is an untrusted caller. A finding naming anything outside this
	 * list is dropped rather than stored, so the pass cannot invent checks that
	 * were never described in the coverage panel.
	 *
	 * @since 0.10.0
	 *
	 * @return string[]
	 */
	public static function ids(): array {
		return array_map(
			static fn( RuleDescriptor $rule ): string => $rule->id(),
			self::all()
		);
	}
}
