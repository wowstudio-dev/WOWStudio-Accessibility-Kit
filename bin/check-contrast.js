#!/usr/bin/env node
/**
 * Contrast guard.
 *
 * We dogfood: an accessibility plugin whose own admin UI fails SC 1.4.3 has no
 * standing to report anybody else's contrast. This computes the real ratio for
 * every foreground/background pairing the admin UI renders, at the size and
 * weight it actually renders them, and fails the build on any that fall short.
 *
 * Two limitations to know about:
 *
 * 1. Colour values are read from the `:root` block of assets/src/style.scss, so
 *    a changed palette is picked up automatically. The PAIRINGS list, though, is
 *    maintained by hand — a new coloured element has to be added here to be
 *    checked. Adding one is the point at which to think about its contrast
 *    anyway, so that is deliberate rather than merely tolerated.
 * 2. It cannot see stacking. A pairing is only meaningful if the background
 *    named is the one actually behind that text; check that when adding a row.
 *
 * Usage:
 *   node bin/check-contrast.js            Fail on anything below its threshold.
 *   node bin/check-contrast.js --verbose  Print every pairing, passing or not.
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const STYLESHEET = path.join( __dirname, '..', 'assets', 'src', 'style.scss' );

/**
 * Reads the custom properties out of the stylesheet's :root block.
 *
 * @return {Object<string,string>} Variable name without the `--wsak-` prefix, to hex.
 */
function palette() {
	const source = fs.readFileSync( STYLESHEET, 'utf8' );
	const root = source.match( /:root\s*\{([^}]*)\}/ );

	if ( ! root ) {
		throw new Error( `Could not find a :root block in ${ STYLESHEET }` );
	}

	const found = {};

	for ( const line of root[ 1 ].split( '\n' ) ) {
		const declaration = line.match( /--wsak-([\w-]+):\s*(#[0-9a-fA-F]{3,8})\s*;/ );

		if ( declaration ) {
			found[ declaration[ 1 ] ] = declaration[ 2 ];
		}
	}

	return found;
}

const P = palette();

/**
 * Resolves a palette name or a literal hex value.
 *
 * @param {string} value Palette key, or a literal `#rrggbb`.
 * @return {string} Hex colour.
 */
function colour( value ) {
	if ( value.startsWith( '#' ) ) {
		return value;
	}

	if ( ! P[ value ] ) {
		throw new Error( `Unknown palette colour "${ value }". Known: ${ Object.keys( P ).join( ', ' ) }` );
	}

	return P[ value ];
}

/**
 * Relative luminance, per the WCAG definition.
 *
 * @param {string} hex Hex colour.
 * @return {number} Luminance between 0 and 1.
 */
function luminance( hex ) {
	let value = hex.replace( '#', '' );

	if ( value.length === 3 ) {
		value = value
			.split( '' )
			.map( ( c ) => c + c )
			.join( '' );
	}

	const channels = [ 0, 2, 4 ].map( ( i ) => {
		const c = parseInt( value.slice( i, i + 2 ), 16 ) / 255;

		return c <= 0.03928 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
	} );

	return 0.2126 * channels[ 0 ] + 0.7152 * channels[ 1 ] + 0.0722 * channels[ 2 ];
}

/**
 * Contrast ratio between two colours.
 *
 * @param {string} a Hex colour.
 * @param {string} b Hex colour.
 * @return {number} Ratio between 1 and 21.
 */
function ratio( a, b ) {
	const one = luminance( a );
	const two = luminance( b );
	const light = Math.max( one, two );
	const dark = Math.min( one, two );

	return ( light + 0.05 ) / ( dark + 0.05 );
}

/**
 * Every coloured pairing the admin UI renders.
 *
 * `px` and `bold` decide the threshold: WCAG counts text as large at 24px, or at
 * 18.66px when bold, and only then drops from 4.5:1 to 3:1. `nonText` marks
 * things judged under SC 1.4.11 instead, which asks for 3:1 — used for the focus
 * indicator and for borders that carry state.
 *
 * Decorative boundaries are deliberately absent: SC 1.4.11 covers what is
 * required to identify a component or its state, and the card and table rules
 * group static content that is not interactive and carries none.
 */
const PAIRINGS = [
	// Shell and headings.
	[ '.wsak body text', 'text', 'ground', 14.5, false ],
	[ '.wsak__title', 'ink', 'ground', 23, true ],
	[ '.wsak__lede', 'muted', 'ground', 13.5, false ],
	[ '.wsak__footer', 'muted', 'ground', 12.5, false ],
	[ '.wsak-boot', 'muted', 'ground', 14, false ],

	// Navigation. The resting state sits on the raised pill track; the
	// selected one sits on white.
	[ 'nav, resting', 'muted', 'raised', 14, false ],
	[ 'nav, selected', 'ink', 'surface', 14, false ],

	// Card headings and body.
	[ 'card titles', 'ink', 'surface', 16, true ],
	[ '.wsak-issue__title', 'ink', 'surface', 14, true ],
	[ '.wsak-issue__message', 'text', 'surface', 13.5, false ],
	[ '.wsak-group__blurb', 'muted', 'surface', 13, false ],
	[ '.wsak-coverage__lede', 'muted', 'surface', 13.5, false ],
	[ '.wsak-coverage__rule dd', 'muted', 'surface', 12.5, false ],
	[ '.wsak-coverage__group-note', 'muted', 'surface', 12.5, false ],
	[ '.wsak-settings__lede', 'muted', 'surface', 13.5, false ],
	[ '.wsak-result__unplaceable', 'muted', 'surface', 13, false ],

	// 0.19.0 site fixes, 0.20.0 the JavaScript note on the browser-run ones.
	[ '.wsak-fixes__lede', 'muted', 'surface', 13.5, false ],
	[ '.wsak-fix-row__caveat', 'muted', 'raised', 12.5, false ],
	[ '.wsak-fix-row__caveat-label', 'text', 'raised', 12.5, true ],
	[ '.wsak-fix-row__browser', 'moderate', 'surface', 12.5, true ],
	[ '.wsak-alt-row__origin', 'muted', 'surface', 12.5, false ],
	[ '.wsak-alt-row__error', 'critical', 'surface', 12.5, false ],
	[ '.wsak-alt-row__saved', 'good', 'surface', 12.5, true ],

	// Score.
	[ '.wsak-score__value', 'ink', 'raised', 34, true ],
	[ '.wsak-score__band', 'ink', 'raised', 15, true ],
	[ '.wsak-score__counts', 'muted', 'raised', 13.5, false ],
	[ '.wsak-score__caveat', 'muted', 'raised', 12.5, false ],
	[ '.wsak-score__outof', 'muted', 'raised', 11, false ],

	// Tags. All small and semibold, so all held to the 4.5 threshold.
	[ '.wsak-tag--auto', '#4c31c4', '#efecfe', 11.5, true ],
	[ '.wsak-tag--manual', 'moderate', 'moderate-bg', 11.5, true ],
	[ '.wsak-tag--sc', 'muted', 'raised', 11.5, false ],
	[ '.wsak-tag--critical', 'critical', 'critical-bg', 11.5, true ],
	[ '.wsak-tag--serious', 'serious', 'serious-bg', 11.5, true ],
	[ '.wsak-tag--moderate', 'moderate', 'moderate-bg', 11.5, true ],
	[ '.wsak-tag--minor', 'minor', 'minor-bg', 11.5, true ],

	// Table.
	[ '.wsak-table thead th', 'muted', 'surface', 11, false ],
	[ '.wsak-table__title', 'ink', 'surface', 14, true ],
	[ '.wsak-table__type', 'muted', 'surface', 11.5, false ],
	[ '.wsak-table__never', 'muted', 'surface', 13, false ],
	[ '.wsak-table row hover', 'ink', 'raised', 14, true ],

	// Code and diffs, light on the brand navy.
	[ '.wsak-issue__context', '#e9e6fb', 'ink', 12, false ],
	[ '.wsak-diff__body', '#e9e6fb', 'ink', 12, false ],
	[ '.wsak-diff__added', '#a7f3c4', '#10391f', 12, false ],
	[ '.wsak-diff__removed', '#fecdd3', '#451319', 12, false ],
	[ '.wsak-issue__fix summary', 'indigo', 'surface', 13, true ],

	// Empty, busy and remediation panels.
	[ '.wsak-empty__title', 'ink', 'raised', 15, true ],
	[ '.wsak-empty__body', 'muted', 'raised', 13.5, false ],
	[ '.wsak-busy', 'muted', 'surface', 13, false ],
	[ '.wsak-alt__unavailable', 'muted', 'raised', 12.5, false ],
	[ '.wsak-fix__note', 'muted', 'raised', 12.5, false ],
	[ '.wsak-settings__usage', 'muted', 'surface', 13.5, false ],

	// Inspector.
	[ '.wsak-inspector__hint', 'muted', 'surface', 12.5, false ],
	[ '.wsak-inspector__issue-title', 'ink', 'surface', 13.5, true ],
	[ '.wsak-inspector__issue-title, selected', 'ink', 'raised', 13.5, true ],
	[ '.wsak-inspector__issue-message', 'muted', 'surface', 12.5, false ],
	[ '.wsak-inspector__issue-message, selected', 'muted', 'raised', 12.5, false ],
	[ '.wsak-inspector__unplaced', 'moderate', 'moderate-bg', 12.5, false ],
	[ '.wsak-inspector__no-fix', 'muted', 'raised', 12.5, false ],
	[ 'statement draft, admin preview', 'moderate', 'moderate-bg', 14, false ],

	// Phase 3 surfaces. Each pairing below is the colour the stylesheet
	// declares, against the panel it actually sits on — a card is surface, a
	// run panel and a dismissal record are raised.
	[ '.wsak-issue__consequence', 'text', 'surface', 13.5, false ],
	[ '.wsak-issue__plan', 'muted', 'surface', 12.5, false ],
	[ '.wsak-issue__plan-where', 'ink', 'surface', 12.5, true ],
	[ '.wsak-dismiss__record', 'muted', 'raised', 12.5, false ],
	[ '.wsak-dismiss__record strong', 'ink', 'raised', 12.5, true ],

	// Bulk scanning.
	[ '.wsak-bulk__title', 'ink', 'surface', 16, true ],
	[ '.wsak-bulk__lede', 'muted', 'surface', 13.5, false ],
	[ '.wsak-bulk__picks-label', 'muted', 'surface', 13, false ],
	[ '.wsak-bulk__score', 'muted', 'surface', 12.5, false ],

	// Coverage badges. Two positive states that have to be told apart, so both
	// are checked rather than assumed to inherit from the tags they resemble.
	[ '.wsak-badge--full', 'good', 'good-bg', 11.5, true ],
	[ '.wsak-badge--content', 'muted', 'raised', 11.5, true ],
	[ '.wsak-badge--never', 'minor', 'minor-bg', 11.5, true ],
	[ '.wsak-badge--stale', 'moderate', 'moderate-bg', 11.5, true ],

	// The run panel sits on a raised background, so everything in it does too.
	[ '.wsak-run__title', 'ink', 'raised', 14.5, true ],
	[ '.wsak-run__counts', 'muted', 'raised', 13, false ],
	[ '.wsak-run__page-title', 'ink', 'raised', 13.5, true ],
	[ '.wsak-run__page-count', 'muted', 'raised', 12.5, false ],
	[ '.wsak-run__page-error', 'serious', 'raised', 12.5, false ],
	[ '.wsak-run__followon-note', 'text', 'surface', 13, false ],

	// The rendered queue, which sits on white inside the run panel.
	[ '.wsak-rendered__title', 'ink', 'surface', 13.5, true ],
	[ '.wsak-rendered__note', 'muted', 'surface', 12.5, false ],
	[ '.wsak-rendered__done-meta', 'muted', 'surface', 12.5, false ],

	// Alt text and its review queue.
	[ '.wsak-alt-bulk__billing', 'muted', 'raised', 12.5, false ],
	[ '.wsak-review__file', 'muted', 'surface', 12, false ],
	[ '.wsak-review__error', 'serious', 'surface', 12.5, false ],
	[ '.wsak-review__saved', 'good', 'surface', 12.5, false ],

	// Theme triage.
	[ '.wsak-theme__title', 'ink', 'surface', 16, true ],
	[ '.wsak-theme__lede', 'muted', 'surface', 13.5, false ],

	// SC 1.4.11 — the focus ring, and borders that carry state.
	[ 'focus ring on white', 'indigo', 'surface', 0, false, true ],
	[ 'focus ring on a raised panel', 'indigo', 'raised', 0, false, true ],
	[ 'focus ring on the ground', 'indigo', 'ground', 0, false, true ],
	[ 'selected finding rule', 'indigo', 'raised', 0, false, true ],
	[ 'issue border, critical', 'critical', 'surface', 0, false, true ],
	[ 'issue border, serious', 'serious', 'surface', 0, false, true ],
	[ 'issue border, moderate', 'moderate', 'surface', 0, false, true ],
	[ 'score dial arc', 'indigo', 'raised', 0, false, true ],

	// The progress bar is information, not decoration: it is the only thing on
	// screen saying how far a run has got, so its filled track has to be
	// distinguishable from its empty one.
	[ 'progress bar fill on its track', 'indigo', 'line', 0, false, true ],
	[ 'progress bar outline on a raised panel', 'violet', 'raised', 0, false, true ],
	[ 'progress bar outline on white', 'violet', 'surface', 0, false, true ],
];

const verbose = process.argv.includes( '--verbose' ) || process.argv.includes( '-v' );
const failures = [];
const lines = [];

for ( const [ label, fg, bg, px, bold, nonText ] of PAIRINGS ) {
	const measured = ratio( colour( fg ), colour( bg ) );
	const large = px >= 24 || ( bold && px >= 18.66 );
	const needed = nonText || large ? 3 : 4.5;
	const passed = measured >= needed;
	const size = px ? ` (${ px }px${ bold ? ' bold' : '' })` : '';
	const line = `${ measured.toFixed( 2 ).padStart( 5 ) }  needs ${ needed.toFixed(
		1
	) }  ${ ( passed ? 'pass' : 'FAIL' ).padEnd( 4 ) }  ${ label }${ size }`;

	lines.push( line );

	if ( ! passed ) {
		failures.push( line );
	}
}

if ( failures.length ) {
	console.log(
		`FAIL: ${ failures.length } pairing(s) below the WCAG 2.2 AA threshold. We do not ship what we would report.`
	);
	failures.forEach( ( line ) => console.log( '  ' + line ) );
	process.exit( 1 );
}

console.log(
	`PASS: ${ PAIRINGS.length } pairing(s) meet WCAG 2.2 AA contrast, using the palette in ${ path.relative(
		process.cwd(),
		STYLESHEET
	) }.`
);

if ( verbose ) {
	lines.forEach( ( line ) => console.log( '  ' + line ) );
}
