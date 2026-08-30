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
	[ '.wsak body text', 'text', 'surface', 14, false ],
	[ '.wsak__lede', 'muted', 'surface', 14, false ],
	[ '.wsak__footer disclaimer', 'muted', 'surface', 13, false ],
	[ '.wsak__title', 'ink', 'surface', 26, true ],
	[ '.wsak-score__value', 'ink', 'raised', 46, true ],
	[ '.wsak-score__outof', 'muted', 'raised', 16, false ],
	[ '.wsak-score__band', 'ink', 'raised', 14, true ],
	[ '.wsak-score__caveat', 'muted', 'raised', 13, false ],
	[ '.wsak-tag--auto', '#3730a3', '#eef2ff', 12, true ],
	[ '.wsak-tag--manual', '#6b21a8', '#f3e8ff', 12, true ],
	[ '.wsak-tag--sc on raised', 'muted', 'raised', 12, false ],
	[ '.wsak-tag--sc on surface', 'muted', 'surface', 12, false ],
	[ '.wsak-tag--critical', 'critical', 'critical-bg', 12, true ],
	[ '.wsak-tag--serious', 'serious', 'serious-bg', 12, true ],
	[ '.wsak-tag--moderate', 'moderate', 'moderate-bg', 12, true ],
	[ '.wsak-tag--minor', 'minor', 'minor-bg', 12, true ],
	[ '.wsak-table thead th', 'muted', 'surface', 12, false ],
	[ '.wsak-table__type', 'muted', 'surface', 12, false ],
	[ '.wsak-table__never', 'muted', 'surface', 14, false ],
	[ '.wsak-issue__title', 'ink', 'surface', 14, true ],
	[ '.wsak-issue__context', '#e7eaf3', 'ink', 12, false ],
	[ '.wsak-issue__fix summary', 'indigo', 'surface', 14, true ],
	[ '.wsak-coverage__lede', 'muted', 'surface', 14, false ],
	[ '.wsak-coverage__rule dd', 'muted', 'surface', 13, false ],
	[ '.wsak-empty__body', 'muted', 'raised', 14, false ],
	[ '.wsak-alt__unavailable', 'muted', 'raised', 13, false ],
	[ '.wsak-alt__saved', 'muted', 'raised', 13, false ],
	[ '.wsak-fix__note', 'muted', 'raised', 13, false ],
	[ '.wsak-fix__applied p', 'muted', 'raised', 14, false ],
	[ '.wsak-diff__body', '#e7eaf3', 'ink', 12, false ],
	[ '.wsak-diff__added', '#a7f3c4', '#10391f', 12, false ],
	[ '.wsak-diff__removed', '#fecdd3', '#451319', 12, false ],
	[ '.wsak-settings__lede', 'muted', 'surface', 14, false ],
	[ '.wsak-settings__usage', 'muted', 'surface', 14, false ],
	[ '.wsak-boot', 'muted', 'surface', 14, false ],
	[ 'statement draft, admin preview', 'moderate', 'moderate-bg', 14, false ],
	// SC 1.4.11. The focus ring sits in the 2px outline-offset gap, so the
	// colour behind it is the parent's background, never the element's own.
	[ 'focus ring on a light panel', 'indigo', 'surface', 0, false, true ],
	[ 'focus ring on a raised panel', 'indigo', 'raised', 0, false, true ],
	// Severity is also carried by the card's leading border, so that border
	// does convey state and is held to 1.4.11.
	[ 'issue border, critical', 'critical', 'surface', 0, false, true ],
	[ 'issue border, serious', 'serious', 'surface', 0, false, true ],
	[ 'issue border, moderate', 'moderate', 'surface', 0, false, true ],
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
