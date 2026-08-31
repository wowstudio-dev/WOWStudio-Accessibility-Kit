/**
 * Proposing a CSS selector for an element, and being honest about how well it
 * will hold.
 *
 * A markup override touches one element in one post. A CSS rule touches
 * everything its selector matches, on every page, until somebody removes it.
 * That is a categorically larger blast radius, and the selector is therefore
 * part of the fix rather than an implementation detail of it — it is shown,
 * explained, and editable before anything is written.
 *
 * The ranking below is about durability, not brevity. A selector that matches
 * today and stops matching the next time a paragraph is added has not fixed
 * anything; it has left a dead rule in somebody's stylesheet and a problem they
 * think is solved.
 */

/**
 * The element has an id: the most stable thing a selector can hang on.
 */
export const ID = 'id';

/**
 * A class combination: usually the *right* answer as well as a stable one,
 * because a colour that is wrong on one element is normally wrong everywhere
 * that class is used.
 */
export const CLASS = 'class';

/**
 * Position in the document. Correct now, and fragile by construction.
 */
export const POSITIONAL = 'positional';

/**
 * How deep a positional selector is allowed to reach before we stop.
 *
 * A chain longer than this is both unreadable and certain to break, and
 * offering it would dress up a guess as a fix.
 */
const MAX_DEPTH = 6;

/**
 * Patterns for names a build tool generated rather than a person chose.
 *
 * These change on the next deploy, so a selector built from one is a rule with
 * an expiry date nobody is told about. The matching is deliberately
 * conservative: wrongly rejecting a real class costs a fallback to a positional
 * selector, while wrongly accepting a generated one costs a silent breakage
 * weeks later.
 */
const GENERATED = [
	// css-in-js and CSS-module output: a short prefix and a hash.
	/^(sc|css|jsx|emotion|styled|svelte|glamor)-[a-z0-9]{5,}$/i,
	// A hash on the end of an otherwise readable name.
	/[-_][0-9a-f]{8,}$/i,
	// Page-builder element ids.
	/^elementor-element-[0-9a-f]+$/i,
	/^(e-con|e-child|elementor-widget-container)$/i,
	// Anything that is mostly hexadecimal and contains digits.
	/^[0-9a-f]{6,}$/i,
];

/**
 * Class prefixes that describe a moment rather than a thing.
 *
 * Scripts add and remove these constantly — a menu is `is-open` for as long as
 * it is open. Anchoring a permanent stylesheet rule to one would make the fix
 * come and go with the interaction.
 */
const STATEFUL =
	/^(is|has|js|active|open|current|hover|focus|selected)([-_]|$)/i;

/**
 * Reports whether a name looks machine-generated.
 *
 * @param {string} name An id or class name.
 * @return {boolean} True when it should not be built into a selector.
 */
export function isGenerated( name ) {
	return GENERATED.some( ( pattern ) => pattern.test( name ) );
}

/**
 * Escapes a name for use in a selector.
 *
 * Uses the browser's own CSS.escape where it exists, because the rules for what
 * needs escaping are not worth reimplementing.
 *
 * @param {string} name Identifier to escape.
 * @param {Window} view Window whose CSS object to borrow.
 * @return {string} The escaped identifier.
 */
export function escapeName( name, view ) {
	if ( view && view.CSS && typeof view.CSS.escape === 'function' ) {
		return view.CSS.escape( name );
	}

	return name.replace( /([^\w-])/g, '\\$1' );
}

/**
 * Returns the classes worth building a selector from, best first.
 *
 * @param {Element} element Element to read.
 * @return {string[]} Usable class names.
 */
export function usableClasses( element ) {
	const all = Array.from( element.classList || [] );
	const usable = all.filter(
		( name ) => ! isGenerated( name ) && ! STATEFUL.test( name )
	);

	// Longer names are more specific to a purpose — `entry-content-link` says
	// more about intent than `link` — so they make the better anchor.
	return usable.sort( ( a, b ) => b.length - a.length );
}

/**
 * Counts how many elements a selector matches in a document.
 *
 * An invalid selector returns null rather than throwing, because the field is
 * editable and somebody is going to mistype in it while we are watching.
 *
 * @param {Document} doc      Document to search.
 * @param {string}   selector Selector to count.
 * @return {?number} The count, or null when the selector will not parse.
 */
export function matchCount( doc, selector ) {
	if ( ! doc || typeof selector !== 'string' || selector.trim() === '' ) {
		return null;
	}

	try {
		return doc.querySelectorAll( selector ).length;
	} catch {
		return null;
	}
}

/**
 * Builds a positional selector by walking up from the element.
 *
 * Stops at the nearest ancestor with a usable id, so the chain is anchored
 * where it can be rather than running all the way to the root.
 *
 * @param {Element} element Element to describe.
 * @param {Window}  view    Window whose CSS object to borrow.
 * @return {?string} A selector, or null when the element is too deep to describe.
 */
function positionalSelector( element, view ) {
	const parts = [];
	let node = element;

	while ( node && node.nodeType === 1 && node.tagName !== 'HTML' ) {
		const tag = node.tagName.toLowerCase();

		if ( node.id && ! isGenerated( node.id ) ) {
			parts.unshift( `#${ escapeName( node.id, view ) }` );
			break;
		}

		const parent = node.parentElement;

		if ( ! parent ) {
			parts.unshift( tag );
			break;
		}

		const siblings = Array.from( parent.children ).filter(
			( child ) => child.tagName === node.tagName
		);

		parts.unshift(
			siblings.length > 1
				? `${ tag }:nth-of-type(${ siblings.indexOf( node ) + 1 })`
				: tag
		);

		node = parent;

		if ( parts.length >= MAX_DEPTH ) {
			return null;
		}
	}

	return parts.length > 0 ? parts.join( ' > ' ) : null;
}

/**
 * Proposes a selector for one element.
 *
 * Returns the whole reasoning, not just the string: which kind of selector it
 * is, how many elements it matches on this page, and — when the answer is
 * "position in the document" — that it is expected to break. The interface
 * shows all of it, because the person approving a site-wide rule is entitled to
 * know what they are approving.
 *
 * The bias towards the class rather than the instance is deliberate. One
 * paragraph failing contrast almost always means a colour is wrong everywhere
 * that class appears, and fixing the class fixes the pages nobody has scanned
 * yet. Where that is the wrong call, the field is editable.
 *
 * @param {Element}  element Element the finding is about.
 * @param {Document} doc     Document it lives in.
 * @param {Window}   view    Window whose CSS object to borrow.
 * @return {?{selector: string, kind: string, matches: ?number, stable: boolean}} The proposal.
 */
export function proposeSelector( element, doc, view ) {
	if ( ! element || element.nodeType !== 1 ) {
		return null;
	}

	if ( element.id && ! isGenerated( element.id ) ) {
		const selector = `#${ escapeName( element.id, view ) }`;

		return {
			selector,
			kind: ID,
			matches: matchCount( doc, selector ),
			stable: true,
		};
	}

	const tag = element.tagName.toLowerCase();
	const classes = usableClasses( element );

	if ( classes.length > 0 ) {
		// One class, not the whole list. Chaining every class narrows the rule
		// to this element's exact combination, which is the opposite of what a
		// class selector is for — and it is the combination most likely to gain
		// or lose a member later.
		const selector = `${ tag }.${ escapeName( classes[ 0 ], view ) }`;

		return {
			selector,
			kind: CLASS,
			matches: matchCount( doc, selector ),
			stable: true,
		};
	}

	const positional = positionalSelector( element, view );

	if ( ! positional ) {
		return null;
	}

	return {
		selector: positional,
		kind: POSITIONAL,
		matches: matchCount( doc, positional ),
		stable: false,
	};
}
