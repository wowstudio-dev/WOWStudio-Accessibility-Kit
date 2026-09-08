/**
 * Tests for proposing a selector, and for admitting when one will not hold.
 *
 * The stakes here are different from the rest of the scanner. Every other part
 * of this codebase reports; this part writes into somebody's stylesheet, and a
 * selector that is subtly wrong leaves a rule that matches the wrong thing on
 * pages nobody here has looked at. So these tests are mostly about restraint.
 */

import {
	proposeSelector,
	usableClasses,
	isGenerated,
	matchCount,
	ID,
	CLASS,
	POSITIONAL,
	specificityOf,
	beats,
	outweigh,
} from '../selector';

/**
 * Installs markup and returns the document.
 *
 * @param {string} html Markup to install.
 * @return {Document} The document.
 */
function page( html ) {
	document.body.innerHTML = html;

	return document;
}

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'recognising generated names', () => {
	it( 'accepts names a person chose', () => {
		[ 'entry-content', 'site-header', 'wp-block-button', 'nav' ].forEach(
			( name ) => expect( isGenerated( name ) ).toBe( false )
		);
	} );

	it( 'rejects names a build tool produced', () => {
		// Each of these changes on the next deploy, so a rule built from one
		// would quietly stop applying and nobody would be told.
		[
			'css-1a2b3c4d',
			'sc-fzXfMB1',
			'header_8f3a91b2',
			'elementor-element-4b2c1a9',
			'a1b2c3d4',
		].forEach( ( name ) => expect( isGenerated( name ) ).toBe( true ) );
	} );

	it( 'skips classes that describe a moment rather than a thing', () => {
		// `is-open` is true for as long as a menu is open. Anchoring a
		// permanent rule to it would make the fix come and go.
		const doc = page( '<a class="is-active nav-link">Home</a>' );

		expect( usableClasses( doc.querySelector( 'a' ) ) ).toEqual( [
			'nav-link',
		] );
	} );
} );

describe( 'proposing a selector', () => {
	it( 'prefers an id', () => {
		const doc = page( '<div id="masthead" class="header"></div>' );

		const proposed = proposeSelector(
			doc.querySelector( '#masthead' ),
			doc,
			window
		);

		expect( proposed.selector ).toBe( '#masthead' );
		expect( proposed.kind ).toBe( ID );
		expect( proposed.stable ).toBe( true );
	} );

	it( 'ignores a generated id and falls through to the class', () => {
		const doc = page( '<div id="block-8f3a91b2" class="entry"></div>' );

		expect(
			proposeSelector( doc.querySelector( 'div' ), doc, window ).kind
		).toBe( CLASS );
	} );

	it( 'uses one class rather than the whole list', () => {
		// Chaining every class narrows the rule to this element's exact
		// combination — which is the opposite of what a class selector is for,
		// and the combination most likely to gain a member later.
		const doc = page( '<a class="btn nav-link wide">Home</a>' );

		const proposed = proposeSelector(
			doc.querySelector( 'a' ),
			doc,
			window
		);

		expect( proposed.selector ).toBe( 'a.nav-link' );
		expect( proposed.selector.split( '.' ) ).toHaveLength( 2 );
	} );

	it( 'reports how many elements the rule would reach', () => {
		const doc = page(
			'<a class="nav-link">A</a><a class="nav-link">B</a><a class="nav-link">C</a>'
		);

		expect(
			proposeSelector( doc.querySelector( 'a' ), doc, window ).matches
		).toBe( 3 );
	} );

	it( 'falls back to position, and says it will not hold', () => {
		const doc = page( '<main><p>one</p><p>two</p></main>' );

		const proposed = proposeSelector(
			doc.querySelectorAll( 'p' )[ 1 ],
			doc,
			window
		);

		expect( proposed.kind ).toBe( POSITIONAL );
		expect( proposed.stable ).toBe( false );
		expect( proposed.matches ).toBe( 1 );
		expect( doc.querySelector( proposed.selector ) ).toBe(
			doc.querySelectorAll( 'p' )[ 1 ]
		);
	} );

	it( 'anchors a positional selector to the nearest usable id', () => {
		const doc = page(
			'<main id="content"><span></span><span></span></main>'
		);

		expect(
			proposeSelector( doc.querySelectorAll( 'span' )[ 1 ], doc, window )
				.selector
		).toBe( '#content > span:nth-of-type(2)' );
	} );

	it( 'refuses to name an element buried too deep to describe', () => {
		// A seven-level positional chain is unreadable and certain to break.
		// Offering it would dress a guess up as a fix.
		const doc = page(
			'<div><div><div><div><div><div><div><em>x</em></div></div></div></div></div></div></div>'
		);

		expect(
			proposeSelector( doc.querySelector( 'em' ), doc, window )
		).toBeNull();
	} );

	it( 'always produces a selector that matches the element it describes', () => {
		const doc = page(
			'<section id="main"><ul class="menu"><li><a class="is-active nav-link">A</a></li><li><a class="nav-link">B</a></li></ul><p>text</p></section>'
		);

		doc.querySelectorAll( '*' ).forEach( ( element ) => {
			const proposed = proposeSelector( element, doc, window );

			if ( ! proposed ) {
				return;
			}

			expect(
				Array.from( doc.querySelectorAll( proposed.selector ) )
			).toContain( element );
		} );
	} );
} );

describe( 'counting matches', () => {
	it( 'returns null for a selector that will not parse', () => {
		// The field is editable, so somebody is going to mistype in it while we
		// are watching. That has to read as "cannot count", not as a crash.
		expect( matchCount( page( '<p></p>' ), '.a >' ) ).toBeNull();
	} );

	it( 'returns zero for a selector that matches nothing', () => {
		// Distinct from null on purpose: a valid rule that reaches nothing is
		// a different problem from a rule that will not parse.
		expect( matchCount( page( '<p></p>' ), '.nothing-here' ) ).toBe( 0 );
	} );
} );

describe( 'specificity', () => {
	it( 'counts ids, classes and elements the way the cascade does', () => {
		expect( specificityOf( 'span.first-title' ) ).toEqual( [ 0, 1, 1 ] );
		expect( specificityOf( '#intro' ) ).toEqual( [ 1, 0, 0 ] );
		expect(
			specificityOf(
				'.eb-advance-heading-wrapper.eb-advance-heading-yj6uz .eb-ah-title .first-title'
			)
		).toEqual( [ 0, 4, 0 ] );
	} );

	it( 'knows which of two selectors wins', () => {
		expect( beats( [ 0, 4, 0 ], [ 0, 1, 1 ] ) ).toBe( true );
		expect( beats( [ 0, 1, 1 ], [ 0, 4, 0 ] ) ).toBe( false );
		expect( beats( [ 0, 1, 1 ], [ 0, 1, 1 ] ) ).toBe( false );
		expect( beats( [ 1, 0, 0 ], [ 0, 9, 9 ] ) ).toBe( true );
	} );

	it( 'weights a selector until it outranks the rule already in place', () => {
		// The real case: a page builder styled one heading with four chained
		// classes, our one-class rule was written, was valid, matched, and lost.
		const incumbent = specificityOf(
			'.eb-advance-heading-wrapper.eb-advance-heading-yj6uz .eb-ah-title .first-title'
		);

		const weighted = outweigh( 'span.first-title', incumbent );

		expect( beats( specificityOf( weighted ), incumbent ) ).toBe( true );
		expect( weighted.startsWith( 'span.first-title' ) ).toBe( true );
	} );

	it( 'leaves a selector alone when it already wins', () => {
		expect( outweigh( 'span.first-title', [ 0, 0, 1 ] ) ).toBe(
			'span.first-title'
		);
	} );

	it( 'matches exactly what it matched before being weighted', () => {
		// The whole reason for repeating the class rather than reaching for an
		// ancestor chain: the weighted selector must not widen or narrow the
		// set of elements the person approved.
		document.body.innerHTML =
			'<div><span class="first-title">a</span><span class="other">b</span></div>';

		const before = document.querySelectorAll( 'span.first-title' ).length;
		const weighted = outweigh( 'span.first-title', [ 0, 4, 0 ] );

		expect( document.querySelectorAll( weighted ) ).toHaveLength( before );
	} );

	it( 'gives up rather than growing without bound', () => {
		const weighted = outweigh( '.a', [ 0, 99, 0 ] );

		expect( weighted.length ).toBeLessThan( 40 );
	} );
} );
