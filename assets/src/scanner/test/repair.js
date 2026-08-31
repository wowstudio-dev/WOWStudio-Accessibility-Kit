/**
 * Tests for the fixes proposed against a rendered page.
 *
 * Two things are being defended here. The first is that a proposed colour
 * actually meets the ratio — arithmetic that is easy to get subtly wrong and
 * impossible to eyeball. The second is restraint: a fix that changes more of
 * the design than it has to gets reverted, and a fix that is offered where it
 * cannot work is worse than no fix at all.
 */

import {
	parseColour,
	contrastRatio,
	nearestAccessible,
	rgbToHsl,
	hslToRgb,
	toHex,
} from '../colour';
import { proposeFix, verifyFix, ruleToCss, CSS_FIXABLE } from '../repair';

/**
 * Installs markup and gives every element a box, since jsdom will not.
 *
 * @param {string} html   Markup to install.
 * @param {number} width  Width in pixels.
 * @param {number} height Height in pixels.
 * @return {Document} The document.
 */
function page( html, width = 200, height = 40 ) {
	document.body.innerHTML = html;
	document.body.querySelectorAll( '*' ).forEach( ( element ) => {
		element.getBoundingClientRect = () => ( {
			width,
			height,
			top: 0,
			left: 0,
			right: width,
			bottom: height,
		} );
	} );

	return document;
}

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'colour conversion', () => {
	it( 'survives a round trip through HSL', () => {
		[
			{ r: 0, g: 0, b: 0, a: 1 },
			{ r: 255, g: 255, b: 255, a: 1 },
			{ r: 123, g: 92, b: 240, a: 1 },
			{ r: 17, g: 200, b: 90, a: 1 },
		].forEach( ( colour ) => {
			const round = hslToRgb( rgbToHsl( colour ) );

			expect( round.r ).toBeCloseTo( colour.r, 0 );
			expect( round.g ).toBeCloseTo( colour.g, 0 );
			expect( round.b ).toBeCloseTo( colour.b, 0 );
		} );
	} );

	it( 'formats a colour as six-digit hex', () => {
		expect( toHex( { r: 123, g: 92, b: 240 } ) ).toBe( '#7b5cf0' );
		expect( toHex( { r: 0, g: 0, b: 0 } ) ).toBe( '#000000' );
	} );
} );

describe( 'finding the nearest accessible colour', () => {
	const white = { r: 255, g: 255, b: 255, a: 1 };
	const black = { r: 0, g: 0, b: 0, a: 1 };

	it( 'reaches the ratio it was asked for', () => {
		const proposed = nearestAccessible(
			{ r: 150, g: 150, b: 255, a: 1 },
			white,
			4.5
		);

		expect( proposed.reached ).toBe( true );
		expect( proposed.ratio ).toBeGreaterThanOrEqual( 4.5 );
	} );

	it( 'keeps the hue the designer chose', () => {
		// Reaching for black would meet the ratio and wreck the design, and a
		// fix somebody reverts on sight has not fixed anything.
		const original = { r: 150, g: 150, b: 255, a: 1 };
		const proposed = nearestAccessible( original, white, 4.5 );

		expect( rgbToHsl( proposed.colour ).h ).toBeCloseTo(
			rgbToHsl( original ).h,
			0
		);
	} );

	it( 'moves no further than it has to', () => {
		// The colour that just clears the threshold, not a stock dark one.
		const proposed = nearestAccessible(
			{ r: 150, g: 150, b: 255, a: 1 },
			white,
			4.5
		);

		expect( proposed.ratio ).toBeLessThan( 5.2 );
	} );

	it( 'goes lighter when the background is dark', () => {
		const proposed = nearestAccessible(
			{ r: 40, g: 40, b: 90, a: 1 },
			black,
			4.5
		);

		expect( proposed.reached ).toBe( true );
		expect( rgbToHsl( proposed.colour ).l ).toBeGreaterThan(
			rgbToHsl( { r: 40, g: 40, b: 90, a: 1 } ).l
		);
	} );

	it( 'always finds an answer at the AA threshold', () => {
		// Worth pinning down, because it means a contrast finding on measurable
		// text always has a fix. Lightness 0 and 1 are black and white whatever
		// the hue, and the worst background in the whole colour space still
		// leaves one of them at 4.58:1 — so the search cannot come back
		// empty-handed here, only with a large move.
		const step = 51;

		for ( let r = 0; r <= 255; r += step ) {
			for ( let g = 0; g <= 255; g += step ) {
				for ( let b = 0; b <= 255; b += step ) {
					const background = { r, g, b, a: 1 };
					const proposed = nearestAccessible(
						{ r: 123, g: 92, b: 240, a: 1 },
						background,
						4.5
					);

					expect( proposed.reached ).toBe( true );
					expect(
						contrastRatio( proposed.colour, background )
					).toBeGreaterThanOrEqual( 4.5 );
				}
			}
		}
	} );

	it( 'says so when a stricter target genuinely cannot be met', () => {
		// AAA on a mid-tone background is not solvable by moving the text
		// alone, and the honest answer is "the background has to change too"
		// rather than a near miss reported as a fix.
		const proposed = nearestAccessible(
			{ r: 128, g: 128, b: 128, a: 1 },
			{ r: 119, g: 119, b: 119, a: 1 },
			7
		);

		expect( proposed.reached ).toBe( false );
		expect( proposed.ratio ).toBeLessThan( 7 );
	} );

	it( 'clears the threshold across a spread of real colours', () => {
		const backgrounds = [ white, { r: 246, g: 245, b: 253, a: 1 } ];

		[
			'rgb(150, 150, 255)',
			'rgb(200, 40, 40)',
			'rgb(0, 160, 120)',
			'rgb(240, 200, 40)',
			'rgb(120, 120, 120)',
		].forEach( ( value ) => {
			backgrounds.forEach( ( background ) => {
				const proposed = nearestAccessible(
					parseColour( value ),
					background,
					4.5
				);

				expect( proposed.reached ).toBe( true );
				expect(
					contrastRatio( proposed.colour, background )
				).toBeGreaterThanOrEqual( 4.5 );
			} );
		} );
	} );
} );

describe( 'proposing a fix', () => {
	it( 'proposes a colour that meets the ratio for failing contrast', () => {
		const doc = page(
			'<div style="background-color: rgb(255,255,255)"><p style="color: rgb(150,150,255); font-size: 16px">Faint</p></div>'
		);

		const fix = proposeFix(
			{ rule_id: 'colour-contrast' },
			doc.querySelector( 'p' ),
			window
		);

		expect( fix.declarations ).toEqual( [
			{
				property: 'color',
				value: expect.stringMatching( /^#[0-9a-f]{6}$/ ),
			},
		] );
		expect( fix.after.ratio ).toBeGreaterThanOrEqual( 4.5 );
		expect( fix.before.ratio ).toBeLessThan( 4.5 );
	} );

	it( 'offers nothing when the background could not be determined', () => {
		// Text over a photograph. A generated colour here would be a guess
		// wearing a measurement's clothes, which is the one thing this whole
		// design exists to avoid.
		const doc = page(
			'<div style="background-image: url(hero.jpg)"><p style="color: rgb(200,200,200); font-size: 16px">Over a photo</p></div>'
		);

		expect(
			proposeFix(
				{ rule_id: 'colour-contrast' },
				doc.querySelector( 'p' ),
				window
			)
		).toBeNull();
	} );

	it( 'underlines a link marked by colour alone', () => {
		const doc = page( '<a href="/x">the notes</a>' );

		expect(
			proposeFix(
				{ rule_id: 'link-marked-by-colour-alone' },
				doc.querySelector( 'a' ),
				window
			).declarations
		).toEqual( [ { property: 'text-decoration', value: 'underline' } ] );
	} );

	it( 'gives a small target a floor of 24 pixels', () => {
		const doc = page( '<button style="display: block">x</button>', 16, 16 );

		const fix = proposeFix(
			{ rule_id: 'target-too-small' },
			doc.querySelector( 'button' ),
			window
		);

		expect( fix.declarations ).toEqual( [
			{ property: 'min-width', value: '24px' },
			{ property: 'min-height', value: '24px' },
		] );
		expect( fix.before.size ).toBe( '16 × 16px' );
	} );

	it( 'switches an inline target to inline-block as well', () => {
		// A browser ignores min-width and min-height on a plain inline box.
		// Without this the rule would apply cleanly, change nothing, and look
		// exactly like a fix that worked.
		const doc = page(
			'<a href="/x" style="display: inline">x</a>',
			16,
			16
		);

		expect(
			proposeFix(
				{ rule_id: 'target-too-small' },
				doc.querySelector( 'a' ),
				window
			).declarations[ 0 ]
		).toEqual( { property: 'display', value: 'inline-block' } );
	} );

	it( 'offers nothing for a finding a stylesheet cannot answer', () => {
		const doc = page( '<div aria-hidden="true"><button>x</button></div>' );

		[
			'hidden-element-still-focusable',
			'scrolling-region-not-reachable',
			'img-alt-missing',
		].forEach( ( ruleId ) => {
			expect(
				proposeFix(
					{ rule_id: ruleId },
					doc.querySelector( 'button' ),
					window
				)
			).toBeNull();
		} );
	} );

	it( 'offers something for every rule it claims to cover', () => {
		// The list is asserted against the server's allowlist as well, so this
		// catches a rule being added to one side and not the other.
		expect( CSS_FIXABLE ).toEqual( [
			'colour-contrast',
			'link-marked-by-colour-alone',
			'target-too-small',
		] );
	} );
} );

describe( 'writing the rule out', () => {
	it( 'formats a rule the way somebody would write it by hand', () => {
		expect(
			ruleToCss( '.entry a', [
				{ property: 'color', value: '#1a4d8f' },
				{ property: 'text-decoration', value: 'underline' },
			] )
		).toBe(
			'.entry a {\n\tcolor: #1a4d8f;\n\ttext-decoration: underline;\n}'
		);
	} );
} );

describe( 'verifying a fix after it is applied', () => {
	it( 'reports success when the page now measures well', () => {
		const doc = page(
			'<div style="background-color: rgb(255,255,255)"><p style="color: rgb(0,0,0); font-size: 16px">Readable</p></div>'
		);

		const outcome = verifyFix(
			{ rule_id: 'colour-contrast' },
			doc.querySelector( 'p' ),
			window
		);

		expect( outcome.resolved ).toBe( true );
		expect( outcome.detail ).toMatch( /21\.00:1/ );
	} );

	it( 'reports failure when the theme beat the rule', () => {
		// The case that makes this feature honest rather than merely useful. A
		// rule can be written perfectly, land in the stylesheet, and lose to a
		// more specific selector — and "applied" is not "fixed".
		const doc = page(
			'<div style="background-color: rgb(255,255,255)"><p style="color: rgb(240,240,240); font-size: 16px">Still faint</p></div>'
		);

		expect(
			verifyFix(
				{ rule_id: 'colour-contrast' },
				doc.querySelector( 'p' ),
				window
			).resolved
		).toBe( false );
	} );

	it( 'measures a target again rather than assuming', () => {
		const doc = page( '<button>x</button>', 24, 24 );

		expect(
			verifyFix(
				{ rule_id: 'target-too-small' },
				doc.querySelector( 'button' ),
				window
			).resolved
		).toBe( true );
	} );

	it( 'notices an underline that did not take', () => {
		const doc = page(
			'<a href="/x" style="text-decoration-line: none">x</a>'
		);

		expect(
			verifyFix(
				{ rule_id: 'link-marked-by-colour-alone' },
				doc.querySelector( 'a' ),
				window
			).resolved
		).toBe( false );
	} );
} );
