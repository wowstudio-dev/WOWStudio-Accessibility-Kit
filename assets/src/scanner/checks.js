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
 * The smallest a pointer target may be, from WCAG 2.5.8.
 */
const MIN_TARGET = 24;

/**
 * Formats a measured dimension without rounding away the reason it failed.
 *
 * A box 23.6 pixels tall is a real failure, and `Math.round` turns it into the
 * sentence "this control is 24 tall, it needs to be 24 tall" — which reads as a
 * bug in the tool rather than a problem on the page. Fractions are kept where
 * they exist and dropped where they do not.
 *
 * @param {number} value Measurement in pixels.
 * @return {string} The number, written the way it measured.
 */
export function px( value ) {
	if ( Number.isInteger( value ) ) {
		return String( value );
	}

	/*
	 * Rounded down, not to nearest. A control 23.98 tall rounds to "24.0", and
	 * the sentence built from it read "24.0 pixels, so it is not quite 24 tall
	 * enough" — a finding that appears to contradict itself in the same breath.
	 * Nobody debugs that; they decide the tool is wrong and stop reading the
	 * ones next to it. Flooring keeps a failing measurement visibly below the
	 * threshold it failed.
	 */
	return ( Math.floor( value * 10 ) / 10 ).toFixed( 1 );
}

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

	/*
	 * What could not be measured, grouped by the thing that stopped it.
	 *
	 * A hero section with a photograph behind it defeats the measurement for
	 * every piece of text inside it, and one finding per text node turned a
	 * single unanswerable question into two hundred and thirteen of them on one
	 * site — enough to bury the seventy-one contrast failures we did measure.
	 * The reader has one decision to make about that section, so they get one
	 * finding, attributed to the section rather than to the words on it.
	 */
	const unmeasured = new Map();

	doc.body.querySelectorAll( '*' ).forEach( ( element ) => {
		if ( ownText( element ) === '' || ! isRendered( element, view ) ) {
			return;
		}

		const measured = measureContrast( element, view );

		// Text nobody can see is not a contrast problem. See measureContrast().
		if ( measured.invisible ) {
			return;
		}

		if ( measured.uncertain ) {
			const source = measured.source ?? element;
			const group = unmeasured.get( source );

			if ( group ) {
				group.count += 1;
			} else {
				unmeasured.set( source, { reason: measured.reason, count: 1 } );
			}

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

	unmeasured.forEach( ( group, source ) => {
		found.push(
			finding(
				'colour-contrast',
				source,
				unmeasuredMessage( group ),
				false
			)
		);
	} );

	return found;
}

/**
 * Says what could not be measured here, and how much of it.
 *
 * The count is stated rather than implied. "Some text on this" leaves somebody
 * guessing how much of the page the sentence covers, and a person deciding
 * whether to open a design tool wants to know whether it is one caption or a
 * whole section.
 *
 * @param {{reason: string, count: number}} group What stopped the measurement.
 * @return {string} The message.
 */
function unmeasuredMessage( group ) {
	// Whole sentences per plural form rather than a subject glued to a fixed
	// verb, which is how the first version of this produced "The 13 pieces of
	// text sits on an image".
	const one = group.count === 1;

	if ( group.reason === 'background-image' ) {
		return one
			? 'The text here sits on an image or gradient, so its contrast cannot be measured automatically. Someone needs to look at it.'
			: `The ${ group.count } pieces of text here sit on an image or gradient, so their contrast cannot be measured automatically. Someone needs to look at them.`;
	}

	if ( group.reason === 'opacity' ) {
		return one
			? 'The text here is inside something partly transparent, so its contrast cannot be measured automatically. Someone needs to look at it.'
			: `The ${ group.count } pieces of text here are inside something partly transparent, so their contrast cannot be measured automatically. Someone needs to look at them.`;
	}

	return one
		? 'The background colour behind this text could not be determined, so its contrast was not measured. Someone needs to look at it.'
		: `The background colour behind these ${ group.count } pieces of text could not be determined, so their contrast was not measured. Someone needs to look at them.`;
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

		if ( rect.width >= MIN_TARGET && rect.height >= MIN_TARGET ) {
			return;
		}

		// Which dimension actually failed, rather than restating the rule. A
		// nav link 170 wide and 23.6 tall fails on one measurement, and being
		// told "it needs to be 24 by 24" sends somebody looking for a width
		// problem that is not there.
		const short = [];

		if ( rect.width < MIN_TARGET ) {
			short.push( 'wide' );
		}

		if ( rect.height < MIN_TARGET ) {
			short.push( 'tall' );
		}

		found.push(
			finding(
				'target-too-small',
				element,
				`This control is ${ px( rect.width ) } by ${ px(
					rect.height
				) } pixels, so it is not quite ${ MIN_TARGET } ${ short.join(
					' or '
				) } enough. Give it a minimum size of ${ MIN_TARGET } by ${ MIN_TARGET }, or enough clear space around it that a near miss does not hit something else.`
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
