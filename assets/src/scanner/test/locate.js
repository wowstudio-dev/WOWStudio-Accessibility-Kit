/**
 * Tests for locating a finding's element in the live page.
 *
 * The behaviour under test is mostly refusal. An index-based XPath almost
 * always resolves to *something*, so the interesting cases are the ones where
 * it resolves to the wrong thing and we have to notice.
 */

import {
	describeContext,
	resolveXPath,
	matchesContext,
	locate,
	FOUND,
	NOT_FOUND,
	MISMATCH,
	PAGE_LEVEL,
	isPageLevel,
} from '../locate';

/**
 * Replaces the document body with the given markup.
 *
 * @param {string} html Markup to install.
 * @return {Document} The current document.
 */
function page( html ) {
	document.body.innerHTML = html;

	return document;
}

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'reading what the scanner recorded', () => {
	it( 'pulls the tag and identifying attributes out of stored markup', () => {
		const described = describeContext(
			'<img decoding="async" src="/uploads/cat.png">'
		);

		expect( described.tag ).toBe( 'img' );
		expect( described.attrs.src ).toBe( '/uploads/cat.png' );
	} );

	it( 'ignores class and style, which scripts rewrite constantly', () => {
		const described = describeContext(
			'<a href="/x" class="btn is-active" style="color:red">Go</a>'
		);

		expect( described.attrs ).toEqual( { href: '/x' } );
	} );

	it( 'returns null for markup it cannot read', () => {
		expect( describeContext( '' ) ).toBeNull();
		expect( describeContext( 'just text' ) ).toBeNull();
		expect( describeContext( undefined ) ).toBeNull();
	} );
} );

describe( 'resolving a path', () => {
	it( 'finds the element the scanner pointed at', () => {
		const doc = page( '<p>one</p><p>two</p>' );

		expect( resolveXPath( doc, '/html/body/p[2]' ).textContent ).toBe(
			'two'
		);
	} );

	it( 'returns null when the path leads nowhere', () => {
		const doc = page( '<p>one</p>' );

		expect( resolveXPath( doc, '/html/body/p[9]' ) ).toBeNull();
	} );

	it( 'survives a malformed path instead of throwing', () => {
		// A bad path is a scanner bug. It must not take the inspector down
		// with it — the UI needs to be able to say "not found" and carry on.
		const doc = page( '<p>one</p>' );

		expect( resolveXPath( doc, '///[[[' ) ).toBeNull();
		expect( resolveXPath( doc, '' ) ).toBeNull();
	} );
} );

describe( 'verifying it is the right element', () => {
	it( 'accepts a matching tag and attributes', () => {
		const doc = page( '<img src="/uploads/cat.png" alt="">' );
		const expected = describeContext( '<img src="/uploads/cat.png">' );

		expect( matchesContext( doc.querySelector( 'img' ), expected ) ).toBe(
			true
		);
	} );

	it( 'rejects a different tag at the same position', () => {
		// The classic drift: something inserted an element, so the index that
		// used to be a link is now a span.
		const doc = page( '<span>Go</span>' );
		const expected = describeContext( '<a href="/x">Go</a>' );

		expect( matchesContext( doc.querySelector( 'span' ), expected ) ).toBe(
			false
		);
	} );

	it( 'rejects the same tag with different identity', () => {
		const doc = page( '<img src="/uploads/dog.png">' );
		const expected = describeContext( '<img src="/uploads/cat.png">' );

		expect( matchesContext( doc.querySelector( 'img' ), expected ) ).toBe(
			false
		);
	} );

	it( 'tolerates a relative URL becoming absolute', () => {
		// The scanner stores what the markup said; the browser reports what it
		// resolved to. Treating that as a mismatch would break highlighting on
		// essentially every image on every site.
		const doc = page( '<img src="/uploads/cat.png">' );
		const element = doc.querySelector( 'img' );
		const expected = describeContext( '<img src="/uploads/cat.png">' );

		// jsdom resolves src against the document URL, so the live value is
		// already absolute here.
		expect( element.getAttribute( 'src' ) ).toBe( '/uploads/cat.png' );
		expect( matchesContext( element, expected ) ).toBe( true );

		const absolute = describeContext(
			'<img src="http://localhost/uploads/cat.png">'
		);
		expect( matchesContext( element, absolute ) ).toBe( true );
	} );

	it( 'accepts on tag alone when no context was stored', () => {
		// Findings recorded before context capture existed should still
		// highlight rather than being permanently unlocatable.
		const doc = page( '<p>one</p>' );

		expect( matchesContext( doc.querySelector( 'p' ), null ) ).toBe( true );
	} );
} );

describe( 'findings about the page as a whole', () => {
	it( 'recognises selectors that name the document rather than a place in it', () => {
		expect( isPageLevel( '/html' ) ).toBe( true );
		expect( isPageLevel( '/html/head' ) ).toBe( true );
		expect( isPageLevel( '/html/head/title' ) ).toBe( true );
		expect( isPageLevel( '/html/body' ) ).toBe( true );
	} );

	it( 'treats anything inside the document as placeable', () => {
		expect( isPageLevel( '/html/body/p[2]/img' ) ).toBe( false );
		expect( isPageLevel( '/html/body/main/h4' ) ).toBe( false );
		expect( isPageLevel( '' ) ).toBe( false );
	} );

	it( 'declines to highlight a whole-document finding', () => {
		// A missing lang attribute is real, but boxing <html> boxes everything,
		// which tells the reader nothing and makes the highlight look broken.
		const doc = page( '<p>one</p>' );

		const result = locate( doc, {
			selector: '/html',
			context: '<html>',
		} );

		expect( result.status ).toBe( PAGE_LEVEL );
		expect( result.element ).toBeNull();
	} );
} );

describe( 'locating a finding end to end', () => {
	it( 'finds an element that is still where it was', () => {
		const doc = page( '<p>intro</p><img src="/uploads/cat.png">' );

		const result = locate( doc, {
			selector: '/html/body/img',
			context: '<img src="/uploads/cat.png">',
		} );

		expect( result.status ).toBe( FOUND );
		expect( result.element.tagName ).toBe( 'IMG' );
	} );

	it( 'reports not-found when the page no longer has that path', () => {
		const doc = page( '<p>intro</p>' );

		const result = locate( doc, {
			selector: '/html/body/img',
			context: '<img src="/uploads/cat.png">',
		} );

		expect( result.status ).toBe( NOT_FOUND );
		expect( result.element ).toBeNull();
	} );

	it( 'refuses to point at a neighbour when the page has shifted', () => {
		// The failure this whole module exists for. A banner was injected, so
		// the path that used to reach the cat now reaches the dog. Pointing at
		// the dog would be silent, confident and wrong.
		const doc = page(
			'<img src="/uploads/dog.png"><img src="/uploads/cat.png">'
		);

		const result = locate( doc, {
			selector: '/html/body/img[1]',
			context: '<img src="/uploads/cat.png">',
		} );

		expect( result.status ).toBe( MISMATCH );
		expect( result.element ).toBeNull();
	} );
} );
