/**
 * The one REST call the editor panel makes.
 */

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const namespace = 'wsak/v1';

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
		'The check could not run just now. It will try again as you write.',
		'wowstudio-accessibility-kit'
	);
}

/**
 * Checks a list of blocks.
 *
 * @param {Array} blocks Block id and markup pairs.
 * @return {Promise<Object>} Findings keyed by block.
 */
export function checkBlocks( blocks ) {
	return apiFetch( {
		path: `/${ namespace }/check-blocks`,
		method: 'POST',
		data: { blocks },
	} );
}
