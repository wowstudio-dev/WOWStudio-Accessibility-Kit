/**
 * What happens next about one finding.
 */

import { __ } from '@wordpress/i18n';

import { CSS_FIXABLE } from '../scanner/repair';

/**
 * Works out which of four things a finding is asking for.
 *
 * Everything needed was already on the finding — the fix plan's target, whether
 * it is one-click, whether automation settled it — and none of it was ever put
 * into a sentence. A card said "Serious" and "Auto-detected" and left the
 * reader to work out from two pieces of jargon whether the plugin was going to
 * do something, whether they had to, or whether it was even a real problem.
 * "Serious" and "Auto-detected" describe the finding; none of it describes what
 * the person reading is supposed to do about it, which is the only question
 * they actually have.
 *
 * Four answers, and every finding gets exactly one:
 *
 * - **fixable** — we can make this change.
 * - **yours** — it is in your content, so you change it, here is how.
 * - **theme** — it is not in your content and nothing here can reach it.
 * - **check** — automation could not settle it; it may well be fine.
 *
 * @param {Object} issue The finding.
 * @return {{key: string, label: string, help: string}} The status.
 */
export function statusOf( issue ) {
	const fix = issue.fix ?? {};
	const target = fix.target ?? '';

	if ( 'img-alt-missing' === issue.rule_id && issue.attachment_id ) {
		return {
			key: 'fixable',
			label: __( 'We can fix this', 'wowstudio-accessibility-kit' ),
			help: __(
				'Write the description once and it is saved to the image itself, so every page using it is fixed.',
				'wowstudio-accessibility-kit'
			),
		};
	}

	if ( CSS_FIXABLE.includes( issue.rule_id ) ) {
		return {
			key: 'fixable',
			label: __( 'We can fix this', 'wowstudio-accessibility-kit' ),
			help: __(
				'This is answered by a style rule. Open the page view to see the change proposed, and apply it if you agree.',
				'wowstudio-accessibility-kit'
			),
		};
	}

	if ( 'manual' === issue.detection ) {
		return {
			key: 'check',
			label: __(
				'Someone needs to check this',
				'wowstudio-accessibility-kit'
			),
			help: __(
				'Automation could not settle this one either way. It may well be fine — look at it, then either fix it or mark it a false positive.',
				'wowstudio-accessibility-kit'
			),
		};
	}

	if ( 'theme' === target ) {
		return {
			key: 'theme',
			label: __( 'Your theme needs this', 'wowstudio-accessibility-kit' ),
			help: __(
				'This is in your theme rather than your content, so nothing here can reach it. It needs whoever looks after your theme.',
				'wowstudio-accessibility-kit'
			),
		};
	}

	return {
		key: 'yours',
		label: __( 'You fix this', 'wowstudio-accessibility-kit' ),
		help:
			fix.summary ||
			__(
				'This one is in your content, so it is edited on the page itself.',
				'wowstudio-accessibility-kit'
			),
	};
}

/**
 * The status, as a chip with a sentence under it.
 *
 * The sentence is not behind a disclosure. Hiding "what do I do" one click away
 * is what made the list read as a wall of problems with no way out of it — the
 * one line somebody needs is the one line that was collapsed.
 *
 * @param {Object}  props           Component props.
 * @param {Object}  props.issue     The finding.
 * @param {boolean} [props.compact] Chip only, for the narrow inspector list.
 * @return {Element} The status.
 */
export default function FindingStatus( { issue, compact } ) {
	const status = statusOf( issue );

	if ( compact ) {
		return (
			<span
				className={ `wsak-status wsak-status--${ status.key } wsak-status--chip` }
			>
				{ status.label }
			</span>
		);
	}

	return (
		<p className={ `wsak-status wsak-status--${ status.key }` }>
			<span className="wsak-status__label">{ status.label }</span>
			<span className="wsak-status__help">{ status.help }</span>
		</p>
	);
}
