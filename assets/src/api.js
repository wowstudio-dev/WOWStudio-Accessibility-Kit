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
