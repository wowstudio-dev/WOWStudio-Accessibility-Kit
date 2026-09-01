/**
 * The badge that says how much of a page has actually been looked at.
 */

import { __ } from '@wordpress/i18n';

/**
 * How much was checked, said as coverage rather than as completion.
 *
 * A bulk run reads content and nothing else, because the checks that need a
 * rendered page cannot run in a background job. "Scanned ✓" after such a run
 * would be a lie by omission — somebody checks a hundred pages, sees no
 * contrast findings, and reasonably concludes they have none.
 *
 * So there are two positive states and they are worded to be told apart at a
 * glance. See decision F4.
 *
 * @param {Object}  props          Component props.
 * @param {string}  props.coverage One of never, content, full.
 * @param {string}  props.label    Wording from the server.
 * @param {boolean} props.stale    Whether the content changed since.
 * @return {Element} The badge.
 */
export default function CoverageBadge( { coverage, label, stale = false } ) {
	if ( stale ) {
		return (
			<span
				className="wsak-tag wsak-badge wsak-badge--stale"
				title={ __(
					'This page has been edited since it was checked, so what we found may no longer be what is there.',
					'wowstudio-accessibility-kit'
				) }
			>
				{ __( 'Edited since checking', 'wowstudio-accessibility-kit' ) }
			</span>
		);
	}

	return (
		<span className={ `wsak-tag wsak-badge wsak-badge--${ coverage }` }>
			{ label }
		</span>
	);
}
