/**
 * Building the same selectors the server pass produces.
 *
 * Findings from both passes end up in one list and are placed by one resolver,
 * so a browser finding has to name an element the same way a server finding
 * does. PHP's `DOMNode::getNodePath()` is the reference, and its one subtlety is
 * that the positional index is omitted when an element is the only one of its
 * tag among its siblings — `/html/body/main/h4`, not `/html/body/main/h4[1]`.
 * Emitting the index unconditionally would produce paths that are still valid
 * XPath but no longer match what the server stored, and the two passes would
 * silently stop being able to talk about the same element.
 */

/**
 * Builds the path to an element, mirroring PHP's getNodePath().
 *
 * @param {Element} element Element to describe.
 * @return {string} An absolute XPath.
 */
export function xpathFor( element ) {
	if ( ! element || element.nodeType !== 1 ) {
		return '';
	}

	const steps = [];
	let node = element;

	while ( node && node.nodeType === 1 ) {
		const tag = node.nodeName.toLowerCase();
		const parent = node.parentElement;

		if ( ! parent ) {
			steps.unshift( tag );
			break;
		}

		const siblings = Array.from( parent.children ).filter(
			( child ) => child.nodeName === node.nodeName
		);

		steps.unshift(
			siblings.length > 1
				? `${ tag }[${ siblings.indexOf( node ) + 1 }]`
				: tag
		);

		node = parent;
	}

	return '/' + steps.join( '/' );
}

/**
 * Returns an element's own markup, collapsed and truncated like the server does.
 *
 * The stored context is what `locate()` verifies against, so it has to be
 * recognisable to the same comparison. Length matches the server's cap.
 *
 * @param {Element} element Element to render.
 * @return {string} Truncated markup.
 */
export function contextFor( element ) {
	if ( ! element || ! element.outerHTML ) {
		return '';
	}

	const html = element.outerHTML.replace( /\s+/g, ' ' ).trim();

	return html.length > 500 ? html.slice( 0, 500 ) + '…' : html;
}
