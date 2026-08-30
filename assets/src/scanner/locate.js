/**
 * Finding an element in the live page, and refusing to guess which one.
 *
 * A finding stores an XPath produced from the HTML the server fetched. The
 * preview frame shows the page as the browser rendered it, and the two can
 * differ: scripts rewrite the DOM, a plugin injects a banner, the fetch and the
 * render happened seconds apart.
 *
 * So resolving a path is not the same as resolving it correctly. An index-based
 * XPath almost always resolves to *something* — the question is whether that
 * something is the element the scanner was looking at. Highlighting the wrong
 * element is worse than highlighting nothing: it is silent, it looks
 * authoritative, and once someone notices it happening they stop trusting the
 * highlights that were right.
 *
 * Everything here therefore verifies before it points, and reports plainly when
 * it cannot.
 */

/**
 * The element was found and matches what the scanner recorded.
 */
export const FOUND = 'found';

/**
 * The path did not resolve at all — the page has changed shape.
 */
export const NOT_FOUND = 'not-found';

/**
 * Something resolved, but it is not the element that was scanned.
 */
export const MISMATCH = 'mismatch';

/**
 * Attributes worth comparing, because they identify an element rather than
 * describing how it looks. Deliberately excludes class and style, which
 * scripts rewrite constantly on pages that are otherwise unchanged.
 */
const IDENTIFYING = [
	'id',
	'name',
	'href',
	'src',
	'type',
	'alt',
	'title',
	'for',
];

/**
 * Reads the tag and identifying attributes out of stored context markup.
 *
 * The markup is parsed, never inserted. `DOMParser` builds an inert document —
 * scripts do not run and resources are not fetched — so this is safe even
 * though the string ultimately came from somebody's page.
 *
 * @param {string} context Stored markup for the element.
 * @return {?{tag: string, attrs: Object}} What the scanner saw, or null if unreadable.
 */
export function describeContext( context ) {
	if ( typeof context !== 'string' || context.trim() === '' ) {
		return null;
	}

	const parsed = new DOMParser().parseFromString( context, 'text/html' );
	const element = parsed.body.firstElementChild;

	if ( ! element ) {
		return null;
	}

	const attrs = {};

	IDENTIFYING.forEach( ( name ) => {
		if ( element.hasAttribute( name ) ) {
			attrs[ name ] = element.getAttribute( name );
		}
	} );

	return { tag: element.tagName.toLowerCase(), attrs };
}

/**
 * Resolves an XPath against a document.
 *
 * @param {Document} doc   Document to search.
 * @param {string}   xpath Path recorded by the scanner.
 * @return {?Element} The element, or null.
 */
export function resolveXPath( doc, xpath ) {
	if ( typeof xpath !== 'string' || xpath.trim() === '' ) {
		return null;
	}

	let result;

	try {
		result = doc.evaluate(
			xpath,
			doc,
			null,
			XPathResult.FIRST_ORDERED_NODE_TYPE,
			null
		);
	} catch {
		// A malformed path is a bug in the scanner, not something to crash the
		// inspector over. Treated as "not found" so the UI can say so.
		return null;
	}

	const node = result ? result.singleNodeValue : null;

	return node && node.nodeType === 1 ? node : null;
}

/**
 * Decides whether a resolved element really is the one that was scanned.
 *
 * Compares the tag and the identifying attributes rather than the markup as a
 * whole. Whole-markup comparison would be stricter but wrong in practice:
 * browsers reorder and re-quote attributes, and WordPress adds things like
 * `decoding="async"` on the way out, so a correct match would routinely fail
 * a string comparison and the inspector would refuse to highlight anything.
 *
 * @param {Element} element  Element the path resolved to.
 * @param {?Object} expected Description from describeContext().
 * @return {boolean} True when this is the scanned element.
 */
export function matchesContext( element, expected ) {
	if ( ! expected ) {
		// No context stored, so there is nothing to check against. The tag is
		// all we have and the path resolved; accept it rather than refusing to
		// highlight findings recorded before context was captured.
		return true;
	}

	if ( element.tagName.toLowerCase() !== expected.tag ) {
		return false;
	}

	return Object.keys( expected.attrs ).every( ( name ) => {
		if ( ! element.hasAttribute( name ) ) {
			return false;
		}

		const seen = element.getAttribute( name );
		const want = expected.attrs[ name ];

		// URLs are compared by their tail, because the scanner stores what the
		// markup said and the browser reports what it resolved to — a relative
		// src becomes absolute between the two.
		if ( name === 'href' || name === 'src' ) {
			return seen.endsWith( want ) || want.endsWith( seen );
		}

		return seen === want;
	} );
}

/**
 * The finding is about the document as a whole, not a place in it.
 */
export const PAGE_LEVEL = 'page-level';

/**
 * Selectors that name the document rather than something inside it.
 *
 * A missing `lang` attribute is a real fault, but drawing a box around `<html>`
 * means drawing a box around everything, which tells the reader nothing and
 * makes the highlight look broken. These are reported as page-level instead.
 */
const WHOLE_DOCUMENT = [
	'/html',
	'/html/head',
	'/html/body',
	'/html/head/title',
];

/**
 * Reports whether a selector refers to the document rather than a location.
 *
 * @param {string} selector Stored selector.
 * @return {boolean} True for a page-level finding.
 */
export function isPageLevel( selector ) {
	return WHOLE_DOCUMENT.includes(
		String( selector || '' ).replace( /\/$/, '' )
	);
}

/**
 * Locates a finding's element in a document.
 *
 * @param {Document} doc     Document to search, usually the preview frame's.
 * @param {Object}   finding Stored finding, with selector and context.
 * @return {{status: string, element: ?Element}} What was found.
 */
export function locate( doc, finding ) {
	if ( isPageLevel( finding.selector ) ) {
		return { status: PAGE_LEVEL, element: null };
	}

	const element = resolveXPath( doc, finding.selector );

	if ( ! element ) {
		return { status: NOT_FOUND, element: null };
	}

	if ( ! matchesContext( element, describeContext( finding.context ) ) ) {
		return { status: MISMATCH, element: null };
	}

	return { status: FOUND, element };
}
