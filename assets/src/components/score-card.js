/**
 * The score, presented so it cannot be read as a verdict.
 */

import { __, _n, sprintf } from '@wordpress/i18n';

import { BarList, Donut, severityRows } from './charts';

/**
 * Describes what a score band means, in plain words.
 *
 * @param {number} score Score out of 100.
 * @return {string} A short characterisation.
 */
function band( score ) {
	if ( score >= 90 ) {
		return __(
			'Few automated issues found',
			'wowstudio-accessibility-kit'
		);
	}

	if ( score >= 70 ) {
		return __(
			'Some automated issues found',
			'wowstudio-accessibility-kit'
		);
	}

	if ( score >= 40 ) {
		return __(
			'Several automated issues found',
			'wowstudio-accessibility-kit'
		);
	}

	return __( 'Many automated issues found', 'wowstudio-accessibility-kit' );
}

/**
 * Shows the score alongside what it does and does not account for.
 *
 * The number is never shown on its own. It reflects only findings a machine
 * settled, so it is always paired with the count of items still needing a
 * person, and with a plain statement that it is not a measure of compliance.
 * A lone number would invite exactly the reading this product exists to avoid.
 *
 * @param {Object} props            Component props.
 * @param {number} props.score      Score out of 100.
 * @param {number} props.auto       Auto-detected findings.
 * @param {number} props.manual     Findings needing review.
 * @param {Object} [props.severity] This page's findings, keyed by severity.
 * @return {Element} The score card.
 */
export default function ScoreCard( { score, auto, manual, severity } ) {
	const rows = severityRows( severity );

	return (
		<div className="wsak-score">
			{ /*
			 * The same ring the overview uses. One vocabulary for "a score out
			 * of a hundred" across the plugin, and one place where its text
			 * alternative and its reduced-motion behaviour are decided.
			 */ }
			<div className="wsak-score__figure">
				<Donut
					value={ score }
					label={ __(
						'Score for this page',
						'wowstudio-accessibility-kit'
					) }
				/>
			</div>

			<div className="wsak-score__detail">
				<p className="wsak-score__band">{ band( score ) }</p>
				<ul className="wsak-score__counts">
					<li>
						{ sprintf(
							/* translators: %d: number of auto-detected issues. */
							_n(
								'%d issue detected automatically',
								'%d issues detected automatically',
								auto,
								'wowstudio-accessibility-kit'
							),
							auto
						) }
					</li>
					<li>
						{ sprintf(
							/* translators: %d: number of issues needing human review. */
							_n(
								'%d item still needs a person to check',
								'%d items still need a person to check',
								manual,
								'wowstudio-accessibility-kit'
							),
							manual
						) }
					</li>
				</ul>
				{ rows.length > 0 && (
					<div className="wsak-score__breakdown">
						<BarList
							items={ rows }
							label={ __(
								'This page\u2019s findings by severity',
								'wowstudio-accessibility-kit'
							) }
						/>
					</div>
				) }

				<p className="wsak-score__caveat">
					{ __(
						'This score counts only what automated testing can settle, which is a part of WCAG rather than all of it. It does not tell you whether your site meets any legal requirement.',
						'wowstudio-accessibility-kit'
					) }
				</p>
			</div>
		</div>
	);
}
