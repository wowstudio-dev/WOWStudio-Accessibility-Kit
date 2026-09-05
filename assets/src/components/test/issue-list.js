/**
 * Which action the findings list offers.
 *
 * There is no component-render setup in this project, and adding one to assert
 * a couple of branches would be a great deal of machinery for very little
 * cover. So this reads the source, the way the PHP suite already guards wiring
 * that fails silently rather than loudly.
 *
 * What it protects has changed shape once already. The list used to choose
 * between fix actions on `detection === 'auto'` alone, so the three findings a
 * stylesheet answers were handed the AI button and died on "No AI provider is
 * set up yet" — under a heading promising there was one correct answer. The AI
 * button is gone entirely now, which means the failure to guard against is the
 * opposite one: a finding being offered a button that no longer exists, or a
 * missing description being offered anything other than the screen that writes
 * one.
 */

import { readFileSync } from 'fs';
import { join } from 'path';

const source = readFileSync( join( __dirname, '..', 'issue-list.js' ), 'utf8' );

describe( 'the findings list', () => {
	it( 'decides from the shared list of style-answerable rules', () => {
		expect( source ).toContain( "from '../scanner/repair'" );
		expect( source ).toContain( 'CSS_FIXABLE.includes( issue.rule_id )' );
	} );

	it( 'sends a missing description to the screen that writes one', () => {
		expect( source ).toContain( '<AltTextHandoff' );
	} );

	it( 'offers no generated fix, because nothing generates one', () => {
		// Removed in 0.16.0 with the AI layer. A button that reaches a route
		// which no longer exists fails at the click rather than at the build,
		// which is the worst place for it to fail.
		expect( source ).not.toContain( 'FixAction' );
		expect( source ).not.toContain( 'AltTextAction' );
	} );

	it( 'does not pick an action on detection alone', () => {
		// Detection says whether a machine settled the finding; it says nothing
		// about whether anything here can answer it, which is the question this
		// component is actually asking.
		expect( source ).not.toContain( "issue.detection === 'auto' &&" );
	} );
} );
