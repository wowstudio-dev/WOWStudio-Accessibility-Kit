/**
 * Tests for the browser pass's checks and the selectors they produce.
 *
 * jsdom has no layout engine: every element reports a zero-sized box and
 * scrollHeight always equals clientHeight. Left alone that would make these
 * tests pass without testing anything, because `isRendered()` would reject
 * every element and each check would return an empty array. So elements are
 * given real boxes explicitly, and the size-dependent checks are only exercised
 * on elements that have been given one.
 */

import { xpathFor, contextFor } from '../xpath';
import {
	checkContrast,
	checkTargetSize,
	checkAriaHiddenFocus,
	checkLinkColourOnly,
} from '../checks';
import { runBrowserPass, RAN, BLOCKED } from '../run';

/**
 * Gives an element a box, since jsdom will not.
 *
 * @param {Element} element Element to size.
 * @param {number}  width   Width in pixels.
 * @param {number}  height  Height in pixels.
 * @return {Element} The same element.
 */
function size( element, width, height ) {
	element.getBoundingClientRect = () => ( {
		width,
		height,
		top: 0,
		left: 0,
		right: width,
		bottom: height,
	} );

	return element;
}

/**
 * Installs markup and gives every element a default box.
 *
 * @param {string} html Markup to install.
 * @return {Document} The document.
 */
function page( html ) {
	document.body.innerHTML = html;
	document.body
		.querySelectorAll( '*' )
		.forEach( ( element ) => size( element, 200, 40 ) );

	return document;
}

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'selectors match the server pass', () => {
	it( 'omits the index when an element is the only one of its tag', () => {
		// PHP's getNodePath() writes /html/body/main/h4, not h4[1]. Emitting
		// the index would be valid XPath that no longer matches what the
		// server stored, and the two passes would stop being able to name the
		// same element.
		const doc = page( '<main><h4>One</h4></main>' );

		expect( xpathFor( doc.querySelector( 'h4' ) ) ).toBe(
			'/html/body/main/h4'
		);
	} );

	it( 'includes the index when siblings share a tag', () => {
		const doc = page( '<p>one</p><p>two</p>' );

		expect( xpathFor( doc.querySelectorAll( 'p' )[ 1 ] ) ).toBe(
			'/html/body/p[2]'
		);
	} );

	it( 'describes nested elements all the way up', () => {
		const doc = page( '<p>a</p><p><img src="/cat.png"></p>' );

		expect( xpathFor( doc.querySelector( 'img' ) ) ).toBe(
			'/html/body/p[2]/img'
		);
	} );

	it( 'collapses whitespace in stored context, as the server does', () => {
		const doc = page( '<p>  hello\n\n  world  </p>' );

		expect( contextFor( doc.querySelector( 'p' ) ) ).toBe(
			'<p> hello world </p>'
		);
	} );
} );

describe( 'contrast', () => {
	it( 'reports text that does not stand out from its background', () => {
		const doc = page(
			'<div style="background-color: rgb(255,255,255)"><p style="color: rgb(240,240,240); font-size: 16px">Barely there</p></div>'
		);

		const found = checkContrast( doc, window );

		expect( found ).toHaveLength( 1 );
		expect( found[ 0 ].rule_id ).toBe( 'colour-contrast' );
		expect( found[ 0 ].certain ).toBe( true );
		expect( found[ 0 ].message ).toMatch( /contrast ratio of 1\./ );
		expect( found[ 0 ].selector ).toBe( '/html/body/div/p' );
	} );

	it( 'says nothing about text that passes', () => {
		const doc = page(
			'<div style="background-color: rgb(255,255,255)"><p style="color: rgb(0,0,0); font-size: 16px">Readable</p></div>'
		);

		expect( checkContrast( doc, window ) ).toHaveLength( 0 );
	} );

	it( 'flags text on an image for a person rather than passing it', () => {
		// The case the design exists for. A tool that assumed white here would
		// report a comfortable pass on text that may be invisible.
		const doc = page(
			'<div style="background-image: url(hero.jpg)"><p style="color: rgb(200,200,200); font-size: 16px">Over a photo</p></div>'
		);

		const found = checkContrast( doc, window );

		expect( found ).toHaveLength( 1 );
		expect( found[ 0 ].certain ).toBe( false );
		expect( found[ 0 ].message ).toMatch( /image or gradient/ );
	} );

	it( 'attributes text to the element that paints it, not its ancestors', () => {
		// Using textContent would report the same sentence once per ancestor.
		const doc = page(
			'<div style="background-color: rgb(255,255,255)"><section><p style="color: rgb(250,250,250); font-size: 16px">Once</p></section></div>'
		);

		expect( checkContrast( doc, window ) ).toHaveLength( 1 );
	} );
} );

describe( 'target size', () => {
	it( 'reports a control smaller than 24 by 24', () => {
		const doc = page( '<div><button>x</button></div>' );
		size( doc.querySelector( 'button' ), 16, 16 );

		const found = checkTargetSize( doc, window );

		expect( found ).toHaveLength( 1 );
		expect( found[ 0 ].rule_id ).toBe( 'target-too-small' );
		expect( found[ 0 ].message ).toMatch( /16 by 16/ );
	} );

	it( 'never reports a measurement that contradicts the verdict', () => {
		// A nav link 170 wide and 23.6 tall really does fail. Rounding it to
		// "170 by 24 pixels, needs to be 24 by 24" reads as a bug in the tool,
		// and somebody goes looking for a width problem that is not there.
		const doc = page( '<div><button>x</button></div>' );
		size( doc.querySelector( 'button' ), 170, 23.6 );

		const found = checkTargetSize( doc, window );

		expect( found ).toHaveLength( 1 );
		expect( found[ 0 ].message ).toMatch( /170 by 23\.6 pixels/ );
		expect( found[ 0 ].message ).toMatch( /24 tall enough/ );
		expect( found[ 0 ].message ).not.toMatch( /24 wide/ );
	} );

	it( 'accepts a control that is big enough', () => {
		const doc = page( '<div><button>ok</button></div>' );
		size( doc.querySelector( 'button' ), 44, 44 );

		expect( checkTargetSize( doc, window ) ).toHaveLength( 0 );
	} );

	it( 'exempts a link sitting inside a sentence', () => {
		// WCAG 2.5.8 exempts these, because enlarging a link in running text
		// would break the line it belongs to.
		const doc = page( '<p>Read <a href="/x">the notes</a> first.</p>' );
		size( doc.querySelector( 'a' ), 60, 18 );

		expect( checkTargetSize( doc, window ) ).toHaveLength( 0 );
	} );

	it( 'ignores controls that are not rendered', () => {
		// Judging an unrendered element would fill the report with findings
		// about closed menus.
		const doc = page( '<div><button>x</button></div>' );
		size( doc.querySelector( 'button' ), 0, 0 );

		expect( checkTargetSize( doc, window ) ).toHaveLength( 0 );
	} );
} );

describe( 'aria-hidden and focus', () => {
	it( 'reports a focusable control inside a hidden container', () => {
		const doc = page(
			'<div aria-hidden="true"><button>Invisible to some</button></div>'
		);

		const found = checkAriaHiddenFocus( doc, window );

		expect( found ).toHaveLength( 1 );
		expect( found[ 0 ].rule_id ).toBe( 'hidden-element-still-focusable' );
		// The finding points at the focusable control, not the wrapper, because
		// that is the thing the user will land on.
		expect( found[ 0 ].selector ).toBe( '/html/body/div/button' );
	} );

	it( 'accepts a hidden container with nothing focusable in it', () => {
		const doc = page(
			'<div aria-hidden="true"><span>Decorative</span></div>'
		);

		expect( checkAriaHiddenFocus( doc, window ) ).toHaveLength( 0 );
	} );

	it( 'accepts aria-hidden on something genuinely not rendered', () => {
		const doc = page( '<div aria-hidden="true"><button>x</button></div>' );
		size( doc.querySelector( 'div' ), 0, 0 );

		expect( checkAriaHiddenFocus( doc, window ) ).toHaveLength( 0 );
	} );
} );

describe( 'links marked by colour alone', () => {
	it( 'reports an underline-free link in a sentence', () => {
		const doc = page(
			'<p style="color: rgb(0,0,0)">Read <a href="/x" style="color: rgb(0,0,255); text-decoration-line: none">the notes</a> first.</p>'
		);

		const found = checkLinkColourOnly( doc, window );

		expect( found ).toHaveLength( 1 );
		expect( found[ 0 ].rule_id ).toBe( 'link-marked-by-colour-alone' );
	} );

	it( 'accepts an underlined link', () => {
		const doc = page(
			'<p style="color: rgb(0,0,0)">Read <a href="/x" style="color: rgb(0,0,255); text-decoration-line: underline">the notes</a> first.</p>'
		);

		expect( checkLinkColourOnly( doc, window ) ).toHaveLength( 0 );
	} );

	it( 'accepts a link set apart by weight as well as colour', () => {
		const doc = page(
			'<p style="color: rgb(0,0,0); font-weight: 400">Read <a href="/x" style="color: rgb(0,0,255); font-weight: 700; text-decoration-line: none">the notes</a> first.</p>'
		);

		expect( checkLinkColourOnly( doc, window ) ).toHaveLength( 0 );
	} );

	it( 'ignores a link that is not inside a block of text', () => {
		const doc = page(
			'<p><a href="/x" style="text-decoration-line: none">Standalone</a></p>'
		);

		expect( checkLinkColourOnly( doc, window ) ).toHaveLength( 0 );
	} );
} );

describe( 'running the whole pass', () => {
	it( 'reports blocked when there is no document to read', () => {
		expect( runBrowserPass( null, window ).status ).toBe( BLOCKED );
		expect( runBrowserPass( document, null ).status ).toBe( BLOCKED );
	} );

	it( 'collects findings from every check', () => {
		const doc = page(
			'<div style="background-color: rgb(255,255,255)"><p style="color: rgb(245,245,245); font-size: 16px">Faint</p><span aria-hidden="true"><button>Hidden</button></span></div>'
		);

		const result = runBrowserPass( doc, window );

		expect( result.status ).toBe( RAN );
		expect( result.failed ).toEqual( [] );

		const ids = result.findings.map( ( f ) => f.rule_id );
		expect( ids ).toContain( 'colour-contrast' );
		expect( ids ).toContain( 'hidden-element-still-focusable' );
	} );
} );
