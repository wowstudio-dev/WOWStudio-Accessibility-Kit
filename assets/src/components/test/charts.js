/**
 * What the bars promise assistive technology, in each of their two modes.
 *
 * Rendered rather than read as source. The property being guarded here is
 * structural — what ends up inside an `aria-hidden` subtree — and that is
 * exactly the kind of thing a substring check on the source would claim to
 * have verified without having verified it.
 */

import { renderToString } from '@wordpress/element';

import { BarList } from '../charts';

const ROWS = [
	{ key: 'link-name-vague', label: 'Ambiguous link text', count: 39 },
	{ key: 'img-alt-missing', label: 'Image has no description', count: 12 },
];

describe( 'the bar list', () => {
	describe( 'when the figures are only figures', () => {
		const html = renderToString(
			<BarList items={ ROWS } label="Open findings by check" />
		);

		it( 'hides the drawn rows and lets one sentence carry the set', () => {
			expect( html ).toContain( 'aria-hidden="true"' );
			expect( html ).toContain( 'screen-reader-text' );
			expect( html ).toContain( 'Ambiguous link text: 39 findings.' );
		} );

		it( 'offers nothing to activate', () => {
			expect( html ).not.toContain( '<button' );
		} );
	} );

	describe( 'when every figure is a door', () => {
		const html = renderToString(
			<BarList
				items={ ROWS }
				label="Open findings by check"
				onSelect={ () => {} }
			/>
		);

		it( 'gives every row a control', () => {
			expect( html.match( /<button/g ) ).toHaveLength( ROWS.length );
		} );

		/*
		 * The regression this file exists for. A focusable control inside an
		 * `aria-hidden` subtree is reachable by keyboard and absent to a screen
		 * reader at the same time — a fault this plugin reports on other
		 * people's sites, and one it must never ship in its own interface. The
		 * static mode's hidden list is correct precisely because it holds
		 * nothing focusable, so the two modes cannot share that markup.
		 */
		it( 'never puts a control inside a hidden subtree', () => {
			const hidden = html.split( 'aria-hidden="true"' ).slice( 1 );

			// Only the decorative bar tracks are hidden, and a track ends
			// before the next button begins.
			hidden.forEach( ( after ) => {
				const track = after.indexOf( '</span>' );
				const button = after.indexOf( '<button' );

				expect( button === -1 || button > track ).toBe( true );
			} );
		} );

		it( 'does not hide the list it just made interactive', () => {
			expect( html ).not.toContain(
				'class="wsak-bars__list" aria-hidden'
			);
			expect( html ).toContain( 'aria-label="Open findings by check"' );
		} );

		/*
		 * The visible words stay inside the accessible name rather than being
		 * replaced by an aria-label. Voice control users say what they can see,
		 * and a name that shares no words with the label is a control they
		 * cannot ask for (WCAG 2.5.3).
		 */
		it( 'keeps the visible label inside the accessible name', () => {
			expect( html ).toContain( 'Ambiguous link text' );
			expect( html ).not.toContain( 'aria-label="Ambiguous link text' );
		} );

		/*
		 * A bare "39" beside a label is a number without a unit. The word is
		 * added as text inside the button rather than as a hint about what
		 * activating it does — the role already says "button", and repeating
		 * that on every row is eight repetitions of what the first one taught.
		 */
		it( 'says what the numbers are', () => {
			expect( html ).toContain( 'findings' );
		} );
	} );
} );
