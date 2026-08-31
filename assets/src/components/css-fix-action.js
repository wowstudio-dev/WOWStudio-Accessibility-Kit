/**
 * Reviewing and applying a style rule for one finding.
 *
 * The screen this renders is the whole argument for the feature. A CSS rule is
 * a bigger commitment than a markup override — it applies wherever the selector
 * reaches, on pages nobody here has looked at — so the review has to show the
 * blast radius as prominently as the fix, and the selector has to be editable
 * by the person taking that risk.
 *
 * And then it closes the loop: after the rule is written, the page in the frame
 * is reloaded and the same measurement is taken again. Most tools tell you what
 * to change. This says whether it worked, in your page, seconds later — and
 * says so honestly when a more specific rule in the theme beat it.
 */

import { Button, Notice, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';

import { applyCssFix, readableError, revertCssFix } from '../api';
import { proposeSelector, matchCount, POSITIONAL } from '../scanner/selector';
import {
	proposeFix,
	ruleToCss,
	verifyFix,
	whyNoCssFix,
} from '../scanner/repair';
import { Busy } from './states';

/**
 * Shows a colour as a swatch beside its value.
 *
 * The hex is written out because the swatch alone conveys nothing to anybody
 * who cannot see it — which is a strange thing to get wrong on this screen in
 * particular.
 *
 * @param {Object} props        Component props.
 * @param {string} props.label  What this colour is.
 * @param {string} props.colour Hex value.
 * @param {string} props.ratio  Measured ratio, already formatted.
 * @return {Element} The swatch.
 */
function Swatch( { label, colour, ratio } ) {
	return (
		<div className="wsak-css-fix__swatch">
			<span className="wsak-css-fix__swatch-label">{ label }</span>
			<span className="wsak-css-fix__swatch-value">
				<span
					className="wsak-css-fix__chip"
					style={ { backgroundColor: colour } }
					aria-hidden="true"
				/>
				<code>{ colour }</code>
			</span>
			{ ratio && (
				<span className="wsak-css-fix__swatch-ratio">{ ratio }</span>
			) }
		</div>
	);
}

/**
 * Describes what a selector will reach, in words rather than a number alone.
 *
 * @param {Object}  props         Component props.
 * @param {?number} props.matches How many elements it matches on this page.
 * @param {boolean} props.stable  Whether the selector is expected to hold.
 * @return {Element} The description.
 */
function BlastRadius( { matches, stable } ) {
	if ( matches === null ) {
		return (
			<p className="wsak-css-fix__radius is-warning">
				{ __(
					'That selector will not parse, so nothing can be counted and nothing can be applied.',
					'wowstudio-accessibility-kit'
				) }
			</p>
		);
	}

	return (
		<>
			<p className="wsak-css-fix__radius">
				{ matches === 0
					? __(
							'This selector matches nothing on this page. Applying it would write a rule that does nothing.',
							'wowstudio-accessibility-kit'
					  )
					: sprintf(
							/* translators: %d: number of matching elements. */
							_n(
								'Matches %d element on this page — and every element it matches on pages that have not been scanned.',
								'Matches %d elements on this page — and every element they match on pages that have not been scanned.',
								matches,
								'wowstudio-accessibility-kit'
							),
							matches
					  ) }
			</p>
			{ ! stable && (
				<p className="wsak-css-fix__radius is-warning">
					{ __(
						'This element has no id or usable class, so the selector describes where it sits in the page. It will stop matching the next time the content around it changes. A class added in your theme would make a better anchor.',
						'wowstudio-accessibility-kit'
					) }
				</p>
			) }
		</>
	);
}

/**
 * The propose-review-apply-verify loop for one styled finding.
 *
 * @param {Object}   props            Component props.
 * @param {Object}   props.issue      The finding.
 * @param {Element}  props.element    The element, resolved in the preview frame.
 * @param {Window}   props.view       The frame's window.
 * @param {Document} props.doc        The frame's document.
 * @param {boolean}  props.applied    Whether a rule is already stored for this finding.
 * @param {string}   props.theme      Theme the rules belong to.
 * @param {Function} props.onChange   Called after a rule is written or removed.
 * @param {Function} props.onReVerify Asks the inspector to reload the frame and re-measure.
 * @return {Element} The action.
 */
export default function CssFixAction( {
	issue,
	element,
	view,
	doc,
	applied,
	theme,
	onChange,
	onReVerify,
} ) {
	const [ status, setStatus ] = useState( applied ? 'applied' : 'idle' );
	const [ proposal, setProposal ] = useState( null );
	const [ selector, setSelector ] = useState( '' );
	const [ stable, setStable ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ outcome, setOutcome ] = useState( null );
	const [ announcement, setAnnouncement ] = useState( '' );

	// The stored state is authoritative: a rule may have been written in an
	// earlier session, or removed by hand in the Customiser since.
	useEffect( () => {
		setStatus( applied ? 'applied' : 'idle' );
	}, [ applied ] );

	// Not the same as "this cannot be fixed with CSS", and must not be reported
	// as one. The element could not be found in the frame, so there is nothing
	// to measure — the inspector has already said why, and repeating a
	// confident sentence about the rule on top of that would be wrong twice.
	if ( ! element || ! view || ! doc ) {
		return null;
	}

	const fix = proposeFix( issue, element, view );

	if ( ! fix ) {
		return (
			<p className="wsak-inspector__no-fix">
				{ whyNoCssFix( issue.rule_id ) }
			</p>
		);
	}

	const propose = () => {
		const named = proposeSelector( element, doc, view );

		if ( ! named ) {
			setError(
				__(
					'This element sits too deep in the page to name reliably, so no rule is offered for it. It needs a class in your theme before a stylesheet can reach it.',
					'wowstudio-accessibility-kit'
				)
			);

			return;
		}

		setSelector( named.selector );
		setStable( named.kind !== POSITIONAL );
		setProposal( fix );
		setStatus( 'review' );
		setError( '' );
		setAnnouncement(
			__(
				'A style rule has been proposed. Review the selector and what it will affect before applying it.',
				'wowstudio-accessibility-kit'
			)
		);
	};

	const matches = matchCount( doc, selector );

	const accept = () => {
		setStatus( 'applying' );
		setError( '' );

		applyCssFix( issue.id, selector, proposal.declarations )
			.then( () => {
				setStatus( 'verifying' );
				setAnnouncement(
					__(
						'Rule written. Reloading the page to measure whether it took effect.',
						'wowstudio-accessibility-kit'
					)
				);

				if ( onChange ) {
					onChange();
				}

				return onReVerify();
			} )
			.then( ( reloaded ) => {
				// A reload that could not re-find the element is not a failure
				// of the fix, and must not be reported as one.
				const measured = reloaded
					? verifyFix( issue, reloaded.element, reloaded.view )
					: null;

				setOutcome( measured );
				setStatus( 'applied' );
				setAnnouncement(
					measured
						? measured.detail
						: __(
								'Rule written. It could not be measured again automatically.',
								'wowstudio-accessibility-kit'
						  )
				);
			} )
			.catch( ( caught ) => {
				setError( readableError( caught ) );
				setStatus( 'review' );
			} );
	};

	const undo = () => {
		setStatus( 'reverting' );

		revertCssFix( issue.id )
			.then( () => {
				setProposal( null );
				setOutcome( null );
				setStatus( 'idle' );
				setAnnouncement(
					__(
						'Rule removed from your Additional CSS.',
						'wowstudio-accessibility-kit'
					)
				);

				if ( onChange ) {
					onChange();
				}

				return onReVerify();
			} )
			.catch( ( caught ) => {
				setError( readableError( caught ) );
				setStatus( 'applied' );
			} );
	};

	return (
		<div className="wsak-css-fix">
			<p className="screen-reader-text" role="status" aria-live="polite">
				{ announcement }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ status === 'idle' && (
				<Button variant="secondary" onClick={ propose }>
					{ __(
						'Suggest a style fix',
						'wowstudio-accessibility-kit'
					) }
				</Button>
			) }

			{ ( status === 'review' || status === 'applying' ) && proposal && (
				<div className="wsak-css-fix__review">
					<p className="wsak-css-fix__summary">
						{ proposal.summary }
					</p>

					{ proposal.before && proposal.before.swatch && (
						<div className="wsak-css-fix__swatches">
							<Swatch
								label={ __(
									'Now',
									'wowstudio-accessibility-kit'
								) }
								colour={ proposal.before.swatch }
								ratio={ `${ proposal.before.ratio.toFixed(
									2
								) }:1` }
							/>
							<Swatch
								label={ __(
									'Proposed',
									'wowstudio-accessibility-kit'
								) }
								colour={ proposal.after.swatch }
								ratio={ `${ proposal.after.ratio.toFixed(
									2
								) }:1` }
							/>
						</div>
					) }

					{ proposal.before && proposal.before.size && (
						<p className="wsak-css-fix__sizes">
							{ sprintf(
								/* translators: 1: current size, 2: size after the fix. */
								__(
									'Now %1$s, at least %2$s after the fix.',
									'wowstudio-accessibility-kit'
								),
								proposal.before.size,
								proposal.after.size
							) }
						</p>
					) }

					<TextControl
						__nextHasNoMarginBottom
						label={ __(
							'Selector this rule applies to',
							'wowstudio-accessibility-kit'
						) }
						help={ __(
							'Edit this to widen or narrow what the rule reaches. Broader is often right: a colour that is wrong here is usually wrong everywhere that class is used.',
							'wowstudio-accessibility-kit'
						) }
						value={ selector }
						onChange={ setSelector }
					/>

					<BlastRadius matches={ matches } stable={ stable } />

					<h5 className="wsak-css-fix__title">
						{ __(
							'What would be added to your Additional CSS',
							'wowstudio-accessibility-kit'
						) }
					</h5>
					<pre
						className="wsak-css-fix__code"
						tabIndex="0"
						role="group"
						aria-label={ __(
							'Proposed style rule',
							'wowstudio-accessibility-kit'
						) }
					>
						<code>
							{ ruleToCss( selector, proposal.declarations ) }
						</code>
					</pre>

					<p className="wsak-css-fix__note">
						{ sprintf(
							/* translators: %s: the active theme's name. */
							__(
								'This goes into Appearance → Customise → Additional CSS, where you can read, edit or delete it without this plugin. WordPress stores that per theme, so it belongs to %s and will stop applying if you switch themes.',
								'wowstudio-accessibility-kit'
							),
							theme
						) }
					</p>

					<div className="wsak-css-fix__actions">
						<Button
							variant="primary"
							disabled={
								status === 'applying' ||
								matches === null ||
								matches === 0
							}
							onClick={ accept }
						>
							{ __(
								'Apply this rule',
								'wowstudio-accessibility-kit'
							) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => {
								setProposal( null );
								setStatus( 'idle' );
							} }
						>
							{ __( 'Discard', 'wowstudio-accessibility-kit' ) }
						</Button>
					</div>
				</div>
			) }

			{ status === 'verifying' && (
				<Busy
					label={ __(
						'Reloading the page and measuring again…',
						'wowstudio-accessibility-kit'
					) }
				/>
			) }

			{ status === 'reverting' && (
				<Busy
					label={ __( 'Removing…', 'wowstudio-accessibility-kit' ) }
				/>
			) }

			{ status === 'applied' && (
				<div className="wsak-css-fix__applied">
					{ outcome && (
						<Notice
							status={ outcome.resolved ? 'success' : 'warning' }
							isDismissible={ false }
						>
							<strong>
								{ outcome.resolved
									? __(
											'Applied, and it took effect.',
											'wowstudio-accessibility-kit'
									  )
									: __(
											'Applied, but it did not take effect.',
											'wowstudio-accessibility-kit'
									  ) }
							</strong>{ ' ' }
							{ outcome.detail }
							{ ! outcome.resolved &&
								' ' +
									__(
										'Something in your theme is more specific than this rule. Narrowing the selector to the element itself usually wins.',
										'wowstudio-accessibility-kit'
									) }
						</Notice>
					) }

					{ ! outcome && (
						<p>
							{ __(
								'A rule for this finding is in your Additional CSS.',
								'wowstudio-accessibility-kit'
							) }
						</p>
					) }

					<Button variant="secondary" onClick={ undo }>
						{ __(
							'Remove this rule',
							'wowstudio-accessibility-kit'
						) }
					</Button>
				</div>
			) }
		</div>
	);
}
