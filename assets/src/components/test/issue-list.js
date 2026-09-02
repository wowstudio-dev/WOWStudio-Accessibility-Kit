/**
 * Which action the findings list offers.
 *
 * There is no component-render setup in this project, and adding one to assert
 * a single branch would be a great deal of machinery for very little cover. So
 * this reads the source, the way the PHP suite already guards wiring that fails
 * silently rather than loudly.
 *
 * What it protects is a real regression: the list used to choose between the
 * fix actions on `detection === 'auto'` alone, so the three findings a
 * stylesheet answers were handed the AI button and died on "No AI provider is
 * set up yet" — under a heading promising there was one correct answer and
 * nothing here was a guess.
 */

import { readFileSync } from 'fs';
import { join } from 'path';

const source = readFileSync( join( __dirname, '..', 'issue-list.js' ), 'utf8' );

describe( 'the findings list', () => {
	it( 'decides from the shared list of style-answerable rules', () => {
		expect( source ).toContain( "from '../scanner/repair'" );
		expect( source ).toContain( 'CSS_FIXABLE.includes( issue.rule_id )' );
	} );

	it( 'does not pick the fix action on detection alone', () => {
		// The exact shape of the old bug. Detection says whether a machine
		// settled the finding; it says nothing about whether answering it
		// needs a model, which is the question being asked here.
		expect( source ).not.toContain(
			"issue.detection === 'auto' && <FixAction"
		);
	} );

	it( 'still offers the model where a model is the answer', () => {
		expect( source ).toContain( '<FixAction issueId={ issue.id } />' );
	} );
} );
