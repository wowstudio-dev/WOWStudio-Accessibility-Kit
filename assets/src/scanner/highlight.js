/**
 * Drawing attention to an element inside the preview frame.
 *
 * The frame is same-origin, so this reaches into its document directly rather
 * than posting messages back and forth. Nothing is added to the page the
 * visitor sees — the frame exists only inside our admin screen, and everything
 * drawn here is removed when the inspector closes.
 */

const OVERLAY_ID = 'wsak-inspector-highlight';

/**
 * Styles for the marker.
 *
 * Two rings, not one. A single coloured outline disappears against a page that
 * happens to use the same colour, which on an accessibility tool would be a
 * particularly poor joke — and the pages most likely to defeat a highlight are
 * exactly the ones with contrast problems worth looking at. A dark ring inside
 * a light one is visible on any background either could fail against.
 */
const STYLES = `
#${ OVERLAY_ID } {
	position: absolute;
	z-index: 2147483647;
	pointer-events: none;
	border-radius: 2px;
	outline: 3px solid #0b0d12;
	box-shadow: 0 0 0 6px #fff, 0 0 0 8px #0b0d12;
	transition: top .12s ease, left .12s ease, width .12s ease, height .12s ease;
}
@media (prefers-reduced-motion: reduce) {
	#${ OVERLAY_ID } { transition: none; }
}
`;

/**
 * Returns the frame's document, or null when it cannot be reached.
 *
 * A frame that refused to load, or that a security policy blocked, leaves an
 * inaccessible or empty document behind. Callers need to tell that apart from
 * "loaded and simply has nothing in it", so this reports null rather than
 * throwing.
 *
 * @param {HTMLIFrameElement} frame The preview frame.
 * @return {?Document} The framed document.
 */
export function frameDocument( frame ) {
	if ( ! frame ) {
		return null;
	}

	try {
		const doc = frame.contentDocument;

		// A blocked frame commonly lands on about:blank with an empty body,
		// which is indistinguishable from success unless we look.
		if ( ! doc || ! doc.body || doc.body.childElementCount === 0 ) {
			return null;
		}

		return doc;
	} catch {
		// Cross-origin, which means a redirect took the preview somewhere we
		// did not expect.
		return null;
	}
}

/**
 * Removes any marker previously drawn.
 *
 * @param {Document} doc The framed document.
 * @return {void}
 */
export function clearHighlight( doc ) {
	if ( ! doc ) {
		return;
	}

	const existing = doc.getElementById( OVERLAY_ID );

	if ( existing ) {
		existing.remove();
	}
}

/**
 * Marks an element and scrolls it into view.
 *
 * @param {Document} doc     The framed document.
 * @param {Element}  element Element to mark.
 * @return {boolean} True when the element could be marked.
 */
export function highlight( doc, element ) {
	if ( ! doc || ! element || ! element.isConnected ) {
		return false;
	}

	clearHighlight( doc );

	const rect = element.getBoundingClientRect();

	// A zero-sized box means the element is present but not rendered — display
	// none, or an empty inline element. Scrolling to it would move the page for
	// no visible reason, so the caller is told nothing was marked and can say
	// why instead.
	if ( rect.width === 0 && rect.height === 0 ) {
		return false;
	}

	if ( ! doc.getElementById( OVERLAY_ID + '-styles' ) ) {
		const style = doc.createElement( 'style' );
		style.id = OVERLAY_ID + '-styles';
		style.textContent = STYLES;
		doc.head.appendChild( style );
	}

	const view = doc.defaultView;
	const marker = doc.createElement( 'div' );

	marker.id = OVERLAY_ID;
	marker.style.top = `${ rect.top + view.scrollY - 2 }px`;
	marker.style.left = `${ rect.left + view.scrollX - 2 }px`;
	marker.style.width = `${ rect.width + 4 }px`;
	marker.style.height = `${ rect.height + 4 }px`;

	doc.body.appendChild( marker );

	const reduced = view.matchMedia
		? view.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
		: false;

	element.scrollIntoView( {
		block: 'center',
		inline: 'nearest',
		behavior: reduced ? 'auto' : 'smooth',
	} );

	return true;
}
