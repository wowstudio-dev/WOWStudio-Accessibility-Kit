/**
 * The score, presented so it cannot be read as a verdict.
 */

import { __, _n, sprintf } from '@wordpress/i18n';

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
 * @param {Object} props        Component props.
 * @param {number} props.score  Score out of 100.
 * @param {number} props.auto   Auto-detected findings.
 * @param {number} props.manual Findings needing review.
 * @return {Element} The score card.
 */
export default function ScoreCard( { score, auto, manual } ) {
	return (
		<div className="wsak-score">
			{ /*
			 * The dial is drawn from this custom property. It is decoration
			 * over a number that is already written out beside it, so nothing
			 * here is the only way to read the score.
			 */ }
			<div
				className="wsak-score__figure"
				style={ {
					'--wsak-score': Math.max( 0, Math.min( 100, score ) ),
				} }
			>
				<span className="wsak-score__value">{ score }</span>
				<span className="wsak-score__outof" aria-hidden="true">
					/ 100
				</span>
				<span className="screen-reader-text">
					{ sprintf(
						/* translators: %d: score out of 100. */
						__( '%d out of 100', 'wowstudio-accessibility-kit' ),
						score
					) }
				</span>
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
