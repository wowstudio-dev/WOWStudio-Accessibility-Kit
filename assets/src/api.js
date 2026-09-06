/**
 * REST calls, in one place.
 *
 * Every call goes through apiFetch, which carries the nonce WordPress already
 * put on the page. Errors are normalised here so components never have to
 * guess at the shape of a failure.
 */

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const namespace = window.wsakSettings?.namespace ?? 'wsak/v1';

/**
 * Turns anything thrown by apiFetch into a readable message.
 *
 * @param {unknown} error Whatever was thrown.
 * @return {string} A sentence worth showing someone.
 */
export function readableError( error ) {
	if ( typeof error === 'string' ) {
		return error;
	}

	if ( error?.message ) {
		return error.message;
	}

	return __(
		'Something went wrong and we could not say what. Please try again.',
		'wowstudio-accessibility-kit'
	);
}

/**
 * Lists content that can be scanned.
 *
 * @param {Object} options        Query options.
 * @param {string} options.search Title filter.
 * @return {Promise<Object>} The response.
 */
export function fetchScannable( { search = '' } = {} ) {
	const query = search ? `?search=${ encodeURIComponent( search ) }` : '';

	return apiFetch( { path: `/${ namespace }/scannable${ query }` } );
}

/**
 * Runs a scan on one post.
 *
 * @param {number} postId Post to scan.
 * @return {Promise<Object>} The scan result.
 */
export function runScan( postId ) {
	return apiFetch( {
		path: `/${ namespace }/scan`,
		method: 'POST',
		data: { post_id: postId },
	} );
}

/**
 * Reads a stored scan.
 *
 * @param {number} scanId Scan to read.
 * @return {Promise<Object>} The scan and its findings.
 */
export function fetchScan( scanId ) {
	return apiFetch( { path: `/${ namespace }/scans/${ scanId }` } );
}

/**
 * Reads what the rule set can and cannot settle automatically.
 *
 * @return {Promise<Object>} The coverage report.
 */
export function fetchCoverage() {
	return apiFetch( { path: `/${ namespace }/coverage` } );
}

/**
 * Reads the log of findings somebody has set aside.
 *
 * @param {number} limit How many to return.
 * @return {Promise<Object>} The log, and how many there are in total.
 */
export function fetchDismissed( limit = 50 ) {
	return apiFetch( { path: `/${ namespace }/dismissed?limit=${ limit }` } );
}

/**
 * Reads the site-wide fixes and which are switched on.
 *
 * @return {Promise<Object>} Every fix, with its state.
 */
export function fetchSiteFixes() {
	return apiFetch( { path: `/${ namespace }/site-fixes` } );
}

/**
 * Switches one site-wide fix on or off.
 *
 * Returns the whole set rather than the one row, so the screen never has to
 * merge a partial response into what it already had — a fix that a filter
 * forces on or off reports what is actually true, not what was asked for.
 *
 * @param {string}  id      The fix.
 * @param {boolean} enabled Whether it should be on.
 * @return {Promise<Object>} Every fix, with its state.
 */
export function toggleSiteFix( id, enabled ) {
	return apiFetch( {
		path: `/${ namespace }/site-fixes/${ id }`,
		method: 'POST',
		data: { enabled },
	} );
}

/**
 * Reads the accessibility statement and its settings.
 *
 * @return {Promise<Object>} Settings, preview, and what is still missing.
 */
export function fetchStatement() {
	return apiFetch( { path: `/${ namespace }/statement` } );
}

/**
 * Saves statement settings.
 *
 * Any change withdraws an existing sign-off, so the caller should expect
 * `attested` to come back false.
 *
 * @param {Object} data Fields to change.
 * @return {Promise<Object>} The statement as it now stands.
 */
export function saveStatement( data ) {
	return apiFetch( {
		path: `/${ namespace }/statement`,
		method: 'POST',
		data,
	} );
}

/**
 * Records that a named person stands behind the statement.
 *
 * @param {string} name Person taking responsibility.
 * @param {string} role Their role.
 * @return {Promise<Object>} The statement as it now stands.
 */
export function attestStatement( name, role ) {
	return apiFetch( {
		path: `/${ namespace }/statement/attest`,
		method: 'POST',
		data: { name, role },
	} );
}

/**
 * Withdraws sign-off, returning the statement to a draft.
 *
 * @return {Promise<Object>} The statement as it now stands.
 */
export function withdrawStatement() {
	return apiFetch( {
		path: `/${ namespace }/statement/withdraw`,
		method: 'POST',
	} );
}

/**
 * Reports what the browser pass found for a scan.
 *
 * Everything sent here is checked on the server against the published rule set
 * before any of it is stored — this is a report, not an instruction.
 *
 * @param {number} scanId   Scan the pass belongs to.
 * @param {string} status   Either "ran" or "blocked".
 * @param {Array}  findings Findings the pass produced.
 * @return {Promise<Object>} The updated scan issues.
 */
export function recordBrowserPass( scanId, status, findings = [] ) {
	return apiFetch( {
		path: `/${ namespace }/scans/${ scanId }/browser`,
		method: 'POST',
		data: { status, findings },
	} );
}

/**
 * Lists the style rules currently written for findings.
 *
 * @return {Promise<Object>} Rules keyed by issue, and the theme they belong to.
 */
export function fetchCssFixes() {
	return apiFetch( { path: `/${ namespace }/fixes/css` } );
}

/**
 * Writes a reviewed style rule into the site's Additional CSS.
 *
 * @param {number} issueId      Finding being answered.
 * @param {string} selector     Selector the rule applies to.
 * @param {Array}  declarations Property and value pairs.
 * @return {Promise<Object>} The stored rule.
 */
export function applyCssFix( issueId, selector, declarations ) {
	return apiFetch( {
		path: `/${ namespace }/fixes/css`,
		method: 'POST',
		data: { issue_id: issueId, selector, declarations },
	} );
}

/**
 * Removes the style rule written for one finding.
 *
 * @param {number} issueId Finding whose rule should go.
 * @return {Promise<Object>} The response.
 */
export function revertCssFix( issueId ) {
	return apiFetch( {
		path: `/${ namespace }/fixes/css/${ issueId }`,
		method: 'DELETE',
	} );
}

/**
 * Lists the post types worth scanning, and what is known about loopback.
 *
 * @return {Promise<Object>} Types with counts, and the loopback verdict.
 */
export function fetchContentTypes() {
	return apiFetch( { path: `/${ namespace }/content/types` } );
}

/**
 * Lists one page of content, with each item's badge.
 *
 * @param {Object} options        Query options.
 * @param {string} options.type   Post type.
 * @param {number} options.page   One-based page number.
 * @param {string} options.search Title filter.
 * @return {Promise<Object>} Items and paging.
 */
export function fetchContent( { type, page = 1, search = '' } = {} ) {
	const query = new URLSearchParams( { type, page: String( page ), search } );

	return apiFetch( { path: `/${ namespace }/content?${ query }` } );
}

/**
 * Starts a bulk run over the selected content.
 *
 * @param {Array} postIds Content to scan.
 * @return {Promise<Object>} The run.
 */
export function startRun( postIds ) {
	return apiFetch( {
		path: `/${ namespace }/runs`,
		method: 'POST',
		data: { post_ids: postIds },
	} );
}

/**
 * Reads a run's progress and results.
 *
 * @param {number} runId Run to read.
 * @return {Promise<Object>} The run.
 */
export function fetchRun( runId ) {
	return apiFetch( { path: `/${ namespace }/runs/${ runId }` } );
}

/**
 * Stops a run, keeping whatever it already found.
 *
 * @param {number} runId Run to stop.
 * @return {Promise<Object>} The run.
 */
export function cancelRun( runId ) {
	return apiFetch( {
		path: `/${ namespace }/runs/${ runId }`,
		method: 'DELETE',
	} );
}

/**
 * Reads what is known about the theme.
 *
 * @return {Promise<Object>} The theme profile and its findings.
 */
export function fetchTheme() {
	return apiFetch( { path: `/${ namespace }/theme` } );
}

/**
 * Checks the theme against a few representative pages.
 *
 * @return {Promise<Object>} The theme profile and its findings.
 */
export function checkTheme() {
	return apiFetch( { path: `/${ namespace }/theme`, method: 'POST' } );
}

/**
 * Sets a finding aside, with the reason that will be kept beside it.
 *
 * @param {number} issueId Finding to dismiss.
 * @param {string} note    Why it is not a problem.
 * @return {Promise<Object>} The finding's review state.
 */
export function ignoreIssue( issueId, note ) {
	return apiFetch( {
		path: `/${ namespace }/issues/${ issueId }/ignore`,
		method: 'POST',
		data: { note },
	} );
}

/**
 * Puts a dismissed finding back on the list.
 *
 * @param {number} issueId Finding to restore.
 * @return {Promise<Object>} The finding's review state.
 */
export function reopenIssue( issueId ) {
	return apiFetch( {
		path: `/${ namespace }/issues/${ issueId }/reopen`,
		method: 'POST',
	} );
}

/**
 * Lists images that have never been described.
 *
 * "Never described" is narrower than "has no alt text", and the difference
 * matters: an image whose alt is an empty string has been described, as
 * decorative, by somebody who decided it carries no meaning. That is a correct
 * answer, and listing it again would invite overwriting it.
 *
 * @param {number} page    One-based page number.
 * @param {number} perPage How many per page.
 * @return {Promise<Object>} Images and paging.
 */
export function fetchUndescribedMedia( page = 1, perPage = 25 ) {
	return apiFetch( {
		path: `/${ namespace }/media?page=${ page }&per_page=${ perPage }`,
	} );
}

/**
 * Writes descriptions onto images.
 *
 * Takes a list even when there is one, because the screen saves a row and saves
 * a page through the same path, and each item reports its own outcome. One
 * image deleted in another tab should not throw away nineteen descriptions
 * somebody just typed.
 *
 * @param {Array} items Rows of { id, text, decorative }.
 * @return {Promise<Object>} A result per row.
 */
export function saveAltText( items ) {
	return apiFetch( {
		path: `/${ namespace }/media/alt`,
		method: 'POST',
		data: { items },
	} );
}

/**
 * Reads the site-wide summary behind the overview screen.
 *
 * One request rather than several: every figure on that screen is counted from
 * the same two tables, and computing them separately would let the parts of one
 * picture disagree with each other.
 *
 * @return {Promise<Object>} Scanned counts, score, issue totals and breakdowns.
 */
export function fetchOverview() {
	return apiFetch( { path: `/${ namespace }/overview` } );
}
