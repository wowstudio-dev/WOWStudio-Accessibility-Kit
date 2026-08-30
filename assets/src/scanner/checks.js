/**
 * The checks the browser pass performs.
 *
 * Every one of these needs something the server pass cannot have: a colour that
 * was actually painted, or a box that was actually laid out. None of them could
 * be moved into PHP by writing a cleverer parser.
 *
 * Each check returns findings shaped for storage, and each may report a finding
 * as uncertain. Uncertain means "a person needs to look", never "probably
 * fine" — the server decides what to do with that, and the one thing it will
 * never do is round it to a pass.
 */

import { measureContrast } from './colour';
import { xpathFor, contextFor } from './xpath';

/**
 * Selector for things a person can operate.
 */
const INTERACTIVE =
	'a[href], button, input:not([type="hidden"]), select, textarea, summary, [role="button"], [role="link"], [tabindex]:not([tabindex="-1"])';

/**
 * Reports whether an element is rendered at all.
 *
 * Nothing here can say anything useful about an element with no box. Treating
 * "not rendered" as "fails the size check" would fill the report with findings
 * about hidden menus, so an unrendered element is skipped rather than judged.
 *
 * @param {Element} element Element to test.
 * @param {Window}  view    Window to read style from.
 * @return {boolean} True when the element occupies space.
 */
function isRendered( element, view ) {
	const rect = element.getBoundingClientRect();

	if ( rect.width === 0 && rect.height === 0 ) {
		return false;
	}

	const style = view.getComputedStyle( element );

	return (
		style.visibility !== 'hidden' &&
		style.display !== 'none' &&
		style.opacity !== '0'
	);
}

/**
 * Returns the element's own text, ignoring text belonging to its children.
 *
 * Contrast applies to the text an element paints itself. Using textContent
 * would attribute a child's words to its parent and report the same sentence
 * once per ancestor.
 *
 * @param {Element} element Element to read.
 * @return {string} Direct text, collapsed.
 */
function ownText( element ) {
	return Array.from( element.childNodes )
		.filter( ( node ) => node.nodeType === 3 )
		.map( ( node ) => node.textContent )
		.join( '' )
		.replace( /\s+/g, ' ' )
		.trim();
}

/**
 * Builds a finding in the shape the REST route expects.
 *
 * @param {string}  ruleId  Our rule identifier.
 * @param {Element} element Element at fault.
 * @param {string}  message Plain-language description of this occurrence.
 * @param {boolean} certain Whether this was settled or needs a person.
 * @return {Object} The finding.
 */
function finding( ruleId, element, message, certain = true ) {
	return {
		rule_id: ruleId,
		selector: xpathFor( element ),
		context: contextFor( element ),
		message,
		certain,
	};
}

/**
 * Text that does not stand out enough from what is behind it.
 *
 * @param {Document} doc  Document to check.
 * @param {Window}   view Window to read style from.
 * @return {Array} Findings.
 */
export function checkContrast( doc, view ) {
	const found = [];

	doc.body.querySelectorAll( '*' ).forEach( ( element ) => {
		if ( ownText( element ) === '' || ! isRendered( element, view ) ) {
			return;
		}

		const measured = measureContrast( element, view );

		if ( measured.uncertain ) {
			found.push(
				finding(
					'colour-contrast',
					element,
					measured.reason === 'background-image'
						? 'This text sits on an image or gradient, so its contrast cannot be measured automatically. Someone needs to look at it.'
						: 'The colours behind this text could not be determined, so its contrast was not measured. Someone needs to look at it.',
					false
				)
			);

			return;
		}

		if ( ! measured.passes ) {
			found.push(
				finding(
					'colour-contrast',
					element,
					`This text has a contrast ratio of ${ measured.ratio.toFixed(
						2
					) }:1 against its background. It needs at least ${
						measured.required
					}:1.`
				)
			);
		}
	} );

	return found;
}

/**
 * Links inside a paragraph that are distinguished only by their colour.
 *
 * @param {Document} doc  Document to check.
 * @param {Window}   view Window to read style from.
 * @return {Array} Findings.
 */
export function checkLinkColourOnly( doc, view ) {
	const found = [];

	doc.body.querySelectorAll( 'a[href]' ).forEach( ( link ) => {
		const parent = link.parentElement;

		if ( ! parent || ! isRendered( link, view ) ) {
			return;
		}

		// Only links surrounded by text are covered. A link that is on its own
		// reads as a link from its position, which is why the criterion is
		// about links "in a block of text".
		if ( ownText( parent ) === '' ) {
			return;
		}

		const style = view.getComputedStyle( link );

		const underlined =
			style.textDecorationLine.includes( 'underline' ) ||
			parseFloat( style.borderBottomWidth ) > 0 ||
			style.textDecoration.includes( 'underline' );

		if ( underlined ) {
			return;
		}

		const around = view.getComputedStyle( parent );
		const differsInWeight = style.fontWeight !== around.fontWeight;
		const differsInStyle = style.fontStyle !== around.fontStyle;

		if ( differsInWeight || differsInStyle ) {
			return;
		}

		if ( style.color === around.color ) {
			// Same colour and no other difference: not marked at all, which is
			// a different fault and not one this check claims to find.
			return;
		}

		found.push(
			finding(
				'link-marked-by-colour-alone',
				link,
				'This link is inside a block of text and is set apart from it only by colour. Anyone who cannot tell those two colours apart cannot tell it is a link.'
			)
		);
	} );

	return found;
}

/**
 * Controls too small to hit reliably.
 *
 * @param {Document} doc  Document to check.
 * @param {Window}   view Window to read style from.
 * @return {Array} Findings.
 */
export function checkTargetSize( doc, view ) {
	const found = [];

	doc.body.querySelectorAll( INTERACTIVE ).forEach( ( element ) => {
		if ( ! isRendered( element, view ) ) {
			return;
		}

		// WCAG 2.5.8 exempts a link sitting in a sentence, because making it
		// 24px tall would break the line it belongs to.
		if (
			element.tagName === 'A' &&
			element.parentElement &&
			ownText( element.parentElement ) !== ''
		) {
			return;
		}

		const rect = element.getBoundingClientRect();

		if ( rect.width >= 24 && rect.height >= 24 ) {
			return;
		}

		found.push(
			finding(
				'target-too-small',
				element,
				`This control is ${ Math.round( rect.width ) } by ${ Math.round(
					rect.height
				) } pixels. It needs to be at least 24 by 24, or have enough clear space around it that a near miss does not hit something else.`
			)
		);
	} );

	return found;
}

/**
 * Scrollable areas a keyboard cannot reach.
 *
 * @param {Document} doc  Document to check.
 * @param {Window}   view Window to read style from.
 * @return {Array} Findings.
 */
export function checkScrollableRegions( doc, view ) {
	const found = [];

	doc.body.querySelectorAll( '*' ).forEach( ( element ) => {
		const style = view.getComputedStyle( element );
		const scrolls =
			[ 'auto', 'scroll' ].includes( style.overflowY ) ||
			[ 'auto', 'scroll' ].includes( style.overflowX );

		if ( ! scrolls || ! isRendered( element, view ) ) {
			return;
		}

		const overflowing =
			element.scrollHeight > element.clientHeight + 1 ||
			element.scrollWidth > element.clientWidth + 1;

		if ( ! overflowing ) {
			return;
		}

		const reachable =
			element.matches( '[tabindex]' ) ||
			element.querySelector( INTERACTIVE ) !== null;

		if ( reachable ) {
			return;
		}

		found.push(
			finding(
				'scrolling-region-not-reachable',
				element,
				'This area scrolls, but nothing inside it can be focused, so it cannot be scrolled from a keyboard and anything out of sight cannot be read.'
			)
		);
	} );

	return found;
}

/**
 * Elements hidden from screen readers that can still be tabbed to.
 *
 * @param {Document} doc  Document to check.
 * @param {Window}   view Window to read style from.
 * @return {Array} Findings.
 */
export function checkAriaHiddenFocus( doc, view ) {
	const found = [];

	doc.body.querySelectorAll( '[aria-hidden="true"]' ).forEach( ( hidden ) => {
		// An element that is genuinely not rendered cannot be focused
		// either, so aria-hidden on it is correct rather than a fault.
		if ( ! isRendered( hidden, view ) ) {
			return;
		}

		const focusable = hidden.matches( INTERACTIVE )
			? hidden
			: hidden.querySelector( INTERACTIVE );

		if ( ! focusable ) {
			return;
		}

		found.push(
			finding(
				'hidden-element-still-focusable',
				focusable,
				'This can be reached with the Tab key, but it is inside something marked aria-hidden, so a screen reader will not describe it. Focus lands somewhere that announces nothing.'
			)
		);
	} );

	return found;
}

/**
 * Every browser check, in the order their findings should be reported.
 */
export const CHECKS = [
	checkContrast,
	checkLinkColourOnly,
	checkTargetSize,
	checkScrollableRegions,
	checkAriaHiddenFocus,
];
