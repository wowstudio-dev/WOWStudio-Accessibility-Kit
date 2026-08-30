/**
 * Tests for the browser pass's colour engine.
 *
 * Two things are being guarded here, and the second matters more.
 *
 * The arithmetic is fixed by the WCAG specification, and the numbers below are
 * cross-checked against `bin/check-contrast.js` — a separate implementation
 * that runs in CI against our own admin UI. Two implementations agreeing on
 * seven known pairs is worth more than either one asserting itself.
 *
 * The uncertainty rules are the reason we own this code. A contrast checker
 * that guesses at a background it cannot see produces confident, wrong passes,
 * and somebody ships unreadable text believing it was checked. Every test in
 * the second half asserts that we decline to answer rather than guess.
 */

import {
	parseColour,
	composite,
	contrastRatio,
	isLargeText,
	effectiveBackground,
	measureContrast,
	AA_NORMAL,
	AA_LARGE,
} from '../colour';

const rgb = ( value ) => parseColour( value );

/**
 * Builds a detached element tree from outermost to innermost.
 *
 * @param {Array<string>} styles Inline style strings, outermost first.
 * @return {Element} The innermost element, attached to the document.
 */
function nest( styles ) {
	let outer = null;
	let current = null;

	styles.forEach( ( style ) => {
		const node = document.createElement( 'div' );
		node.setAttribute( 'style', style );

		if ( current ) {
			current.appendChild( node );
		} else {
			outer = node;
		}

		current = node;
	} );

	document.body.appendChild( outer );

	return current;
}

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'contrast arithmetic', () => {
	it( 'matches the WCAG anchors', () => {
		expect(
			contrastRatio( rgb( 'rgb(0,0,0)' ), rgb( 'rgb(255,255,255)' ) )
		).toBeCloseTo( 21, 2 );
		expect(
			contrastRatio(
				rgb( 'rgb(255,255,255)' ),
				rgb( 'rgb(255,255,255)' )
			)
		).toBeCloseTo( 1, 2 );
	} );

	it( 'agrees with bin/check-contrast.js on our own palette', () => {
		// Same pairs that guard the admin UI in CI. If these ever disagree,
		// one of the two implementations has drifted and both are suspect.
		expect(
			contrastRatio( rgb( 'rgb(79,70,229)' ), rgb( 'rgb(255,255,255)' ) )
		).toBeCloseTo( 6.29, 1 );
		expect(
			contrastRatio( rgb( 'rgb(181,71,8)' ), rgb( 'rgb(255,246,237)' ) )
		).toBeCloseTo( 5.08, 1 );
		expect(
			contrastRatio( rgb( 'rgb(14,21,37)' ), rgb( 'rgb(255,255,255)' ) )
		).toBeCloseTo( 18.22, 1 );
	} );

	it( 'composites translucent colours over their backdrop', () => {
		const result = composite(
			rgb( 'rgba(0,0,0,0.5)' ),
			rgb( 'rgb(255,255,255)' )
		);

		expect( result.r ).toBeCloseTo( 127.5, 1 );
		expect( result.a ).toBe( 1 );
	} );

	it( 'applies the large-text threshold at the right sizes', () => {
		expect( isLargeText( 24, 400 ) ).toBe( true );
		expect( isLargeText( 19, 700 ) ).toBe( true );
		expect( isLargeText( 19, 400 ) ).toBe( false );
		expect( isLargeText( 23.9, 400 ) ).toBe( false );
	} );
} );

describe( 'colour parsing refuses to guess', () => {
	it( 'reads the forms getComputedStyle returns', () => {
		expect( rgb( 'rgba(1, 2, 3, 0.4)' ).a ).toBe( 0.4 );
		expect( rgb( 'rgb(1 2 3 / 40%)' ).a ).toBeCloseTo( 0.4, 5 );
		expect( rgb( 'rgb(10, 20, 30)' ) ).toMatchObject( {
			r: 10,
			g: 20,
			b: 30,
			a: 1,
		} );
	} );

	it( 'returns null for anything it does not recognise', () => {
		// Guessing here would mean inventing a contrast ratio for a colour we
		// could not read, which is worse than reporting nothing.
		expect( rgb( 'color(display-p3 1 0 0)' ) ).toBeNull();
		expect( rgb( 'transparent' ) ).toBeNull();
		expect( rgb( '#ffffff' ) ).toBeNull();
		expect( rgb( '' ) ).toBeNull();
		expect( rgb( undefined ) ).toBeNull();
	} );
} );

describe( 'background resolution', () => {
	it( 'finds the nearest opaque ancestor background', () => {
		const el = nest( [
			'background-color: rgb(0, 0, 255)',
			'background-color: rgb(255, 0, 0)',
			'',
		] );

		const backdrop = effectiveBackground( el, window );

		expect( backdrop.uncertain ).toBe( false );
		expect( backdrop.colour ).toMatchObject( { r: 255, g: 0, b: 0 } );
	} );

	it( 'falls through to the white canvas when nothing sets a background', () => {
		// Not a guess: with no background image anywhere in the chain, the
		// canvas underneath really is white in every browser.
		const el = nest( [ '', '' ] );
		const backdrop = effectiveBackground( el, window );

		expect( backdrop.uncertain ).toBe( false );
		expect( backdrop.colour ).toMatchObject( { r: 255, g: 255, b: 255 } );
	} );

	it( 'declines when a background image is in the way', () => {
		const el = nest( [ 'background-image: url(photo.jpg)', '' ] );

		const backdrop = effectiveBackground( el, window );

		expect( backdrop.uncertain ).toBe( true );
		expect( backdrop.reason ).toBe( 'background-image' );
		expect( backdrop.colour ).toBeNull();
	} );

	it( 'still answers when an opaque background shields the image behind it', () => {
		// The walk stops at the first opaque layer, so a hero image three
		// ancestors up is irrelevant to text sitting on a solid white card.
		// Getting this wrong in the cautious direction would be its own kind of
		// dishonesty: every page with a background image would report its whole
		// body as unmeasurable, and the warning would become noise people learn
		// to ignore.
		const el = nest( [
			'background-image: url(hero.jpg)',
			'background-color: rgb(255,255,255)',
			'',
		] );

		const backdrop = effectiveBackground( el, window );

		expect( backdrop.uncertain ).toBe( false );
		expect( backdrop.colour ).toMatchObject( { r: 255, g: 255, b: 255 } );
	} );

	it( 'declines when an ancestor is translucent', () => {
		const el = nest( [
			'background-color: rgb(255,255,255)',
			'opacity: 0.5',
			'',
		] );

		const backdrop = effectiveBackground( el, window );

		expect( backdrop.uncertain ).toBe( true );
		expect( backdrop.reason ).toBe( 'opacity' );
	} );
} );

describe( 'measuring an element', () => {
	it( 'fails black-on-black and reports the ratio', () => {
		const el = nest( [
			'background-color: rgb(0,0,0)',
			'color: rgb(10,10,10); font-size: 16px',
		] );

		const result = measureContrast( el, window );

		expect( result.uncertain ).toBe( false );
		expect( result.passes ).toBe( false );
		expect( result.required ).toBe( AA_NORMAL );
		expect( result.ratio ).toBeLessThan( 1.5 );
	} );

	it( 'passes black-on-white', () => {
		const el = nest( [
			'background-color: rgb(255,255,255)',
			'color: rgb(0,0,0); font-size: 16px',
		] );

		const result = measureContrast( el, window );

		expect( result.passes ).toBe( true );
		expect( result.ratio ).toBeCloseTo( 21, 1 );
	} );

	it( 'holds large text to the lower threshold', () => {
		const el = nest( [
			'background-color: rgb(255,255,255)',
			'color: rgb(117,117,117); font-size: 30px',
		] );

		const result = measureContrast( el, window );

		expect( result.required ).toBe( AA_LARGE );
	} );

	it( 'reports uncertainty rather than a pass when it cannot see the backdrop', () => {
		// The case the whole design exists for. Light grey text over a
		// photograph is the classic false pass: a tool that assumed white
		// would call this fine.
		const el = nest( [
			'background-image: url(hero.jpg)',
			'color: rgb(200,200,200); font-size: 16px',
		] );

		const result = measureContrast( el, window );

		expect( result.uncertain ).toBe( true );
		expect( result.passes ).toBeNull();
		expect( result.ratio ).toBeNull();
		expect( result.reason ).toBe( 'background-image' );
	} );
} );
