/**
 * Running the browser pass over a framed page.
 */

import { CHECKS } from './checks';

/**
 * The pass completed and its findings are included.
 */
export const RAN = 'ran';

/**
 * The page could not be read, so nothing was checked.
 */
export const BLOCKED = 'blocked';

/**
 * Runs every browser check against a document.
 *
 * A check that throws is contained rather than allowed to abandon the pass:
 * one rule tripping over an unusual page should cost that rule's findings, not
 * the other four's. But it must not be silent either, because a pass that
 * quietly checked less than it claims is the failure this whole design is
 * built to avoid — so the names of any checks that failed come back with the
 * findings, and the caller is expected to say so.
 *
 * @param {Document} doc  Document to check, usually the preview frame's.
 * @param {Window}   view Window to read computed style from.
 * @return {{status: string, findings: Array, failed: Array}} The outcome.
 */
export function runBrowserPass( doc, view ) {
	if ( ! doc || ! doc.body || ! view ) {
		return { status: BLOCKED, findings: [], failed: [] };
	}

	const findings = [];
	const failed = [];

	CHECKS.forEach( ( check ) => {
		try {
			findings.push( ...check( doc, view ) );
		} catch {
			failed.push( check.name );
		}
	} );

	return { status: RAN, findings, failed };
}
