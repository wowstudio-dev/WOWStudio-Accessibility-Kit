/**
 * Turning a browser-pass finding into a CSS rule somebody can review.
 *
 * These are the findings the markup override layer cannot reach. Nothing is
 * wrong with the HTML — the element is styled into a problem — so the fix is a
 * style rule, and the honest place to put a style rule is the site's own
 * Additional CSS rather than anything this plugin serves to visitors.
 *
 * Every proposal here is measured against the live page rather than assumed:
 * the colour comes from the pixels the browser actually painted, and the sizes
 * come from the boxes it actually laid out. A proposal that could not be
 * measured is not offered.
 */

import { __, sprintf } from '@wordpress/i18n';

import {
	measureContrast,
	parseColour,
	effectiveBackground,
	nearestAccessible,
	toHex,
} from './colour';
import { px } from './checks';

/**
 * The minimum a pointer target must measure, from WCAG 2.5.8.
 */
const MIN_TARGET_PX = 24;

/**
 * Rules a stylesheet can answer, and how.
 *
 * Kept as data rather than scattered through the interface so the same list can
 * be asserted against the server's allowlist in a test. The two drifting apart
 * would mean either offering fixes the server rejects, or accepting rules the
 * interface never reviewed.
 */
export const CSS_FIXABLE = [
	'colour-contrast',
	'link-marked-by-colour-alone',
	'target-too-small',
];

/**
 * Says why a finding has no CSS answer.
 *
 * Each of these needs an attribute added to the markup, which a stylesheet
 * cannot do at all — not "we have not built it yet" but "CSS is the wrong
 * tool". Saying which is which matters: a reader who is told a fix is coming
 * waits for it, and a reader who is told to edit their template goes and does
 * it.
 *
 * @param {string} ruleId Rule that was found.
 * @return {string} A sentence for the reader.
 */
export function whyNoCssFix( ruleId ) {
	switch ( ruleId ) {
		case 'scrolling-region-not-reachable':
			return __(
				'This one needs tabindex="0" and a label on the scrolling container. Those are attributes in the markup, and no stylesheet can add them — it has to be changed in your theme or the block that produced it.',
				'wowstudio-accessibility-kit'
			);
		case 'hidden-element-still-focusable':
			return __(
				'This one needs either aria-hidden removed or the control taken out of the tab order. Both are attributes in the markup, which a stylesheet cannot change — it has to be done in your theme or the block that produced it.',
				'wowstudio-accessibility-kit'
			);
		default:
			return __(
				'There is no style rule that would answer this one. It has to be changed where the markup is produced.',
				'wowstudio-accessibility-kit'
			);
	}
}

/**
 * Proposes a colour that meets the required ratio and stays close to the original.
 *
 * @param {Element} element Element carrying the text.
 * @param {Window}  view    Window to read computed style from.
 * @return {?Object} The proposal, or null when the page cannot be measured.
 */
function repairContrast( element, view ) {
	const measured = measureContrast( element, view );

	// The uncertain cases — text over a photograph, stacked translucency — are
	// exactly the ones where a generated colour would be a guess dressed as a
	// measurement. They stay with a person.
	if ( measured.uncertain || measured.ratio === null ) {
		return null;
	}

	const style = view.getComputedStyle( element );
	const foreground = parseColour( style.color );
	const backdrop = effectiveBackground( element, view );

	if ( ! foreground || ! backdrop.colour ) {
		return null;
	}

	const proposed = nearestAccessible(
		foreground,
		backdrop.colour,
		measured.required
	);

	return {
		declarations: [ { property: 'color', value: proposed.hex } ],
		before: {
			swatch: toHex( foreground ),
			ratio: measured.ratio,
		},
		after: {
			swatch: proposed.hex,
			ratio: proposed.ratio,
		},
		background: toHex( backdrop.colour ),
		required: measured.required,
		reached: proposed.reached,
		summary: sprintf(
			/* translators: 1: current contrast ratio, 2: proposed contrast ratio. */
			__(
				'Darkens or lightens the text just enough to clear the threshold: %1$s becomes %2$s. The hue and saturation you chose are kept exactly.',
				'wowstudio-accessibility-kit'
			),
			`${ measured.ratio.toFixed( 2 ) }:1`,
			`${ proposed.ratio.toFixed( 2 ) }:1`
		),
	};
}

/**
 * Proposes an underline for a link that is marked by colour alone.
 *
 * The only proposal here that needs no measurement and no arithmetic: an
 * underline is what the criterion asks for and what readers already recognise.
 *
 * @return {Object} The proposal.
 */
function repairLinkColour() {
	return {
		declarations: [ { property: 'text-decoration', value: 'underline' } ],
		summary: __(
			'Underlines the link, so it is recognisable as one without depending on telling two colours apart.',
			'wowstudio-accessibility-kit'
		),
	};
}

/**
 * Proposes a minimum size for a target that is too small to hit reliably.
 *
 * @param {Element} element Element that is too small.
 * @param {Window}  view    Window to read computed style from.
 * @return {?Object} The proposal, or null when the element has no box.
 */
function repairTargetSize( element, view ) {
	const box = element.getBoundingClientRect();

	if ( ! box || ( box.width === 0 && box.height === 0 ) ) {
		return null;
	}

	const declarations = [
		{ property: 'min-width', value: `${ MIN_TARGET_PX }px` },
		{ property: 'min-height', value: `${ MIN_TARGET_PX }px` },
	];

	// A minimum size does nothing to a non-replaced inline box: the browser
	// ignores both properties outright. Without this the rule would apply
	// cleanly, change nothing, and look like a fix that worked.
	const display = view.getComputedStyle( element ).display;
	const inline = display === 'inline';

	if ( inline ) {
		declarations.unshift( {
			property: 'display',
			value: 'inline-block',
		} );
	}

	return {
		declarations,
		before: {
			size: `${ px( box.width ) } × ${ px( box.height ) }px`,
		},
		after: {
			size: `${ px( Math.max( box.width, MIN_TARGET_PX ) ) } × ${ px(
				Math.max( box.height, MIN_TARGET_PX )
			) }px`,
		},
		summary: inline
			? __(
					'Gives the control a floor of 24 by 24 pixels. It also switches it to inline-block, because a browser ignores a minimum size on a plain inline element — without that the rule would apply and change nothing.',
					'wowstudio-accessibility-kit'
			  )
			: __(
					'Gives the control a floor of 24 by 24 pixels, so it stays hittable for anyone whose aim is not exact. It can still grow past that.',
					'wowstudio-accessibility-kit'
			  ),
	};
}

/**
 * Proposes a CSS fix for one finding, measured against the live page.
 *
 * @param {Object}  issue   The finding.
 * @param {Element} element The element it refers to, resolved in the frame.
 * @param {Window}  view    Window to read computed style from.
 * @return {?Object} The proposal, or null when there is nothing to offer.
 */
export function proposeFix( issue, element, view ) {
	if ( ! issue || ! element || ! view ) {
		return null;
	}

	switch ( issue.rule_id ) {
		case 'colour-contrast':
			return repairContrast( element, view );
		case 'link-marked-by-colour-alone':
			return repairLinkColour();
		case 'target-too-small':
			return repairTargetSize( element, view );
		default:
			return null;
	}
}

/**
 * Formats declarations as the body of a rule.
 *
 * @param {Array} declarations Property and value pairs.
 * @return {string} One declaration per line.
 */
export function declarationsToCss( declarations ) {
	return declarations
		.map( ( { property, value } ) => `\t${ property }: ${ value };` )
		.join( '\n' );
}

/**
 * Formats a whole rule, as it will appear in the stylesheet.
 *
 * @param {string} selector     Selector to apply the declarations to.
 * @param {Array}  declarations Property and value pairs.
 * @return {string} The rule.
 */
export function ruleToCss( selector, declarations ) {
	return `${ selector } {\n${ declarationsToCss( declarations ) }\n}`;
}

/**
 * Re-measures a finding after a fix, to say whether it actually took effect.
 *
 * This is the part no competitor does. A rule can be written perfectly, land in
 * the stylesheet, and lose to a more specific selector in the theme — in which
 * case the honest report is "applied, and it did not take", not "fixed".
 *
 * @param {Object}  issue   The finding.
 * @param {Element} element The element, re-resolved after the reload.
 * @param {Window}  view    Window to read computed style from.
 * @return {?{resolved: boolean, detail: string}} The outcome, or null when it cannot be judged.
 */
export function verifyFix( issue, element, view ) {
	if ( ! issue || ! element || ! view ) {
		return null;
	}

	if ( issue.rule_id === 'colour-contrast' ) {
		const measured = measureContrast( element, view );

		if ( measured.uncertain || measured.ratio === null ) {
			return null;
		}

		return {
			resolved: measured.passes,
			detail: sprintf(
				/* translators: 1: measured contrast ratio, 2: required ratio. */
				__(
					'Measured again on the page: %1$s, against the %2$s required.',
					'wowstudio-accessibility-kit'
				),
				`${ measured.ratio.toFixed( 2 ) }:1`,
				`${ measured.required }:1`
			),
		};
	}

	if ( issue.rule_id === 'target-too-small' ) {
		const box = element.getBoundingClientRect();
		const resolved =
			box.width >= MIN_TARGET_PX && box.height >= MIN_TARGET_PX;

		return {
			resolved,
			detail: sprintf(
				/* translators: %s: measured size, such as "24 × 24px". */
				__(
					'Measured again on the page: %s.',
					'wowstudio-accessibility-kit'
				),
				`${ px( box.width ) } × ${ px( box.height ) }px`
			),
		};
	}

	if ( issue.rule_id === 'link-marked-by-colour-alone' ) {
		const decoration = view.getComputedStyle( element ).textDecorationLine;
		const resolved =
			typeof decoration === 'string' &&
			decoration !== 'none' &&
			decoration !== '';

		return {
			resolved,
			detail: resolved
				? __(
						'The link is underlined on the page now.',
						'wowstudio-accessibility-kit'
				  )
				: __(
						'The link is still not underlined on the page, so something in your theme is overriding the rule.',
						'wowstudio-accessibility-kit'
				  ),
		};
	}

	return null;
}
