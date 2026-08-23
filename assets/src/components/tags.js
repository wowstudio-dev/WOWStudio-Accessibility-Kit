/**
 * Severity and detection tags.
 */

import { __ } from '@wordpress/i18n';

/**
 * Shows how a finding was reached.
 *
 * The honesty tag. It is deliberately text, not a colour or an icon alone:
 * the distinction between what a machine settled and what still needs a person
 * is the most important thing on this screen, and it has to survive being read
 * aloud, printed, or viewed by someone who cannot distinguish the colours.
 *
 * @param {Object} props           Component props.
 * @param {string} props.detection Either "auto" or "manual".
 * @param {string} props.label     Server-provided translated label.
 * @return {Element} The tag.
 */
export function DetectionTag( { detection, label } ) {
	const isAuto = detection === 'auto';

	return (
		<span
			className={ `wsak-tag wsak-tag--detection wsak-tag--${
				isAuto ? 'auto' : 'manual'
			}` }
		>
			<span aria-hidden="true" className="wsak-tag__mark">
				{ isAuto ? '◆' : '?' }
			</span>
			{ label ||
				( isAuto
					? __( 'Auto-detected', 'wowstudio-accessibility-kit' )
					: __(
							'Needs manual review',
							'wowstudio-accessibility-kit'
					  ) ) }
		</span>
	);
}

/**
 * Shows how much a finding hurts.
 *
 * @param {Object} props          Component props.
 * @param {string} props.severity Severity slug.
 * @param {string} props.label    Server-provided translated label.
 * @return {Element} The tag.
 */
export function SeverityTag( { severity, label } ) {
	return (
		<span
			className={ `wsak-tag wsak-tag--severity wsak-tag--${ severity }` }
		>
			{ label || severity }
		</span>
	);
}
