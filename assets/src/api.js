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
 * Reads the AI configuration.
 *
 * Never returns an API key: only whether one is stored, and a masked hint.
 *
 * @return {Promise<Object>} The configuration.
 */
export function fetchAiSettings() {
	return apiFetch( { path: `/${ namespace }/ai/settings` } );
}

/**
 * Saves AI configuration, and optionally a key.
 *
 * @param {Object} data Fields to change.
 * @return {Promise<Object>} The configuration as it now stands.
 */
export function saveAiSettings( data ) {
	return apiFetch( {
		path: `/${ namespace }/ai/settings`,
		method: 'POST',
		data,
	} );
}

/**
 * Asks for a description of one image.
 *
 * @param {number} attachmentId Media item to describe.
 * @param {number} postId       Page it appears on, for context.
 * @return {Promise<Object>} The suggestion and the remaining allowance.
 */
export function generateAltText( attachmentId, postId = 0 ) {
	return apiFetch( {
		path: `/${ namespace }/alt-text`,
		method: 'POST',
		data: { attachment_id: attachmentId, post_id: postId },
	} );
}

/**
 * Saves reviewed alt text onto a media item.
 *
 * @param {number} attachmentId Media item to update.
 * @param {string} text         The reviewed alt text.
 * @return {Promise<Object>} The saved value and what it replaced.
 */
export function applyAltText( attachmentId, text ) {
	return apiFetch( {
		path: `/${ namespace }/alt-text/apply`,
		method: 'POST',
		data: { attachment_id: attachmentId, text },
	} );
}

/**
 * Asks for a proposed correction, without changing anything.
 *
 * @param {number} issueId Issue to fix.
 * @return {Promise<Object>} Before, after, and the diff between them.
 */
export function previewFix( issueId ) {
	return apiFetch( {
		path: `/${ namespace }/fixes/preview`,
		method: 'POST',
		data: { issue_id: issueId },
	} );
}

/**
 * Applies a reviewed correction as a reversible override.
 *
 * @param {number} issueId Issue being fixed.
 * @param {string} after   The reviewed markup.
 * @return {Promise<Object>} The stored override.
 */
export function applyFix( issueId, after ) {
	return apiFetch( {
		path: `/${ namespace }/fixes/apply`,
		method: 'POST',
		data: { issue_id: issueId, after },
	} );
}

/**
 * Undoes an applied override.
 *
 * @param {number} fixId Override to undo.
 * @return {Promise<Object>} The override, now marked undone.
 */
export function revertFix( fixId ) {
	return apiFetch( {
		path: `/${ namespace }/fixes/${ fixId }/revert`,
		method: 'POST',
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
