/**
 * Tests for marking an element in the preview frame.
 *
 * These are mostly about *not* moving the page. The inspector's whole promise
 * is "look, it is there" — and a highlight that yanks the interface around
 * while you are reading breaks that promise more thoroughly than no highlight
 * at all would.
 */

import { highlight, clearHighlight, isInView } from '../highlight';

/**
 * Gives an element a box at a chosen position, since jsdom will not.
 *
 * @param {Element} element Element to place.
 * @param {number}  top     Distance from the top of the viewport.
 * @param {number}  height  Height in pixels.
 * @param {number}  width   Width in pixels.
 * @return {Element} The same element.
 */
function place( element, top, height, width = 200 ) {
	element.getBoundingClientRect = () => ( {
		top,
		bottom: top + height,
		left: 0,
		right: width,
		width,
		height,
	} );

	return element;
}

let scrolledTo;
let scrolledIntoView;

beforeEach( () => {
	scrolledTo = [];
	scrolledIntoView = 0;

	window.scrollTo = ( options ) => scrolledTo.push( options );
	window.innerHeight = 800;
	window.scrollY = 0;

	// Any call to this is the bug these tests exist to prevent.
	Element.prototype.scrollIntoView = () => {
		scrolledIntoView += 1;
	};
} );

afterEach( () => {
	document.body.innerHTML = '';
} );

/**
 * Installs an element and returns it.
 *
 * @param {string} html Markup for one element.
 * @return {Element} The installed element.
 */
function install( html ) {
	document.body.innerHTML = html;

	return document.body.firstElementChild;
}

describe( 'deciding whether the frame needs to move', () => {
	it( 'treats an element in the middle of the viewport as visible', () => {
		const element = place( install( '<p>Here</p>' ), 300, 40 );

		expect( isInView( element, window ) ).toBe( true );
	} );

	it( 'treats an element below the fold as not visible', () => {
		const element = place( install( '<p>Down there</p>' ), 1200, 40 );

		expect( isInView( element, window ) ).toBe( false );
	} );

	it( 'treats an element scrolled off the top as not visible', () => {
		const element = place( install( '<p>Up there</p>' ), -200, 40 );

		expect( isInView( element, window ) ).toBe( false );
	} );
} );

describe( 'marking an element', () => {
	it( 'never uses scrollIntoView', () => {
		// The bug this replaced. scrollIntoView scrolls every scrollable
		// ancestor, and the element lives in an iframe — so it dragged the
		// admin page around underneath to bring the frame into view. The
		// preview pane is sticky, which made that lurch especially nasty.
		const element = place( install( '<p>Far below</p>' ), 2000, 40 );

		highlight( document, element );

		expect( scrolledIntoView ).toBe( 0 );
	} );

	it( 'scrolls the frame itself when the element is out of sight', () => {
		const element = place( install( '<p>Far below</p>' ), 2000, 40 );

		highlight( document, element );

		expect( scrolledTo ).toHaveLength( 1 );
		expect( scrolledTo[ 0 ].top ).toBeGreaterThan( 0 );
	} );

	it( 'leaves the page alone when the element is already visible', () => {
		// Moving the page to show something already on screen is pure
		// disruption: the reader loses their place and gains nothing.
		const element = place( install( '<p>Right here</p>' ), 300, 40 );

		highlight( document, element );

		expect( scrolledTo ).toHaveLength( 0 );
	} );

	it( 'can be told not to scroll at all', () => {
		const element = place( install( '<p>Far below</p>' ), 2000, 40 );

		highlight( document, element, { scroll: false } );

		expect( scrolledTo ).toHaveLength( 0 );
	} );

	it( 'refuses to mark an element with no box at all', () => {
		// Present but not rendered — display:none, or an empty inline element.
		// Scrolling to it would move the page for no visible reason, so the
		// caller is told nothing was marked and can explain why instead.
		const element = place( install( '<span></span>' ), 0, 0, 0 );

		expect( highlight( document, element ) ).toBe( false );
		expect( scrolledTo ).toHaveLength( 0 );
	} );

	it( 'still marks an element that is flat but laid out', () => {
		// A full-width, zero-height element is rendered; the marker sits around
		// it and shows where it is. Refusing here would hide real findings
		// about empty containers.
		const element = place( install( '<div></div>' ), 200, 0, 400 );

		expect( highlight( document, element ) ).toBe( true );
	} );

	it( 'replaces the previous mark rather than stacking them', () => {
		const first = place( install( '<p>One</p>' ), 100, 40 );

		highlight( document, first );
		highlight( document, first );

		expect(
			document.querySelectorAll( '#wsak-inspector-highlight' )
		).toHaveLength( 1 );
	} );

	it( 'removes the mark when cleared', () => {
		const element = place( install( '<p>One</p>' ), 100, 40 );

		highlight( document, element );
		clearHighlight( document );

		expect(
			document.getElementById( 'wsak-inspector-highlight' )
		).toBeNull();
	} );
} );
