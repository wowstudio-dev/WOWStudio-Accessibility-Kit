/**
 * What automated testing here can and cannot settle.
 */

import { Button } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { fetchCoverage, readableError } from '../api';
import { DetectionTag } from './tags';
import { ErrorState, Skeleton } from './states';

/**
 * A disclosure listing every check, and what it can decide.
 *
 * Required by the product rules, and genuinely the most useful thing on the
 * screen for anyone deciding how much to trust a clean result. It is collapsed
 * by default but never hidden, and the summary line states the limitation even
 * when the detail is closed.
 *
 * @return {Element} The panel.
 */
export default function CoveragePanel() {
	const [ state, setState ] = useState( {
		status: 'idle',
		rules: [],
		error: '',
	} );
	const [ open, setOpen ] = useState( false );

	useEffect( () => {
		if ( ! open || state.status !== 'idle' ) {
			return;
		}

		setState( ( previous ) => ( { ...previous, status: 'loading' } ) );

		fetchCoverage()
			.then( ( data ) =>
				setState( {
					status: 'ready',
					rules: data.rules ?? [],
					error: '',
				} )
			)
			.catch( ( error ) =>
				setState( {
					status: 'error',
					rules: [],
					error: readableError( error ),
				} )
			);
	}, [ open, state.status ] );

	const auto = state.rules.filter( ( rule ) => rule.detection === 'auto' );
	const manual = state.rules.filter( ( rule ) => rule.detection !== 'auto' );

	return (
		<section
			className="wsak-coverage"
			aria-labelledby="wsak-coverage-title"
		>
			<h2 className="wsak-coverage__title" id="wsak-coverage-title">
				{ __(
					'What these checks cover',
					'wowstudio-accessibility-kit'
				) }
			</h2>

			<p className="wsak-coverage__lede">
				{ __(
					'Automated testing finds a portion of accessibility problems, not all of them. Whether your alt text is accurate, whether the focus order makes sense, whether a page reads sensibly aloud — none of that can be settled by a machine. A clean scan means the automated checks passed, and nothing more.',
					'wowstudio-accessibility-kit'
				) }
			</p>

			<Button
				variant="secondary"
				aria-expanded={ open }
				aria-controls="wsak-coverage-detail"
				onClick={ () => setOpen( ! open ) }
			>
				{ open
					? __(
							'Hide the full list of checks',
							'wowstudio-accessibility-kit'
					  )
					: __(
							'See the full list of checks',
							'wowstudio-accessibility-kit'
					  ) }
			</Button>

			<div id="wsak-coverage-detail" hidden={ ! open }>
				{ state.status === 'loading' && (
					<Skeleton
						label={ __(
							'Loading the list of checks…',
							'wowstudio-accessibility-kit'
						) }
					/>
				) }

				{ state.status === 'error' && (
					<ErrorState
						message={ state.error }
						onRetry={ () =>
							setState( { status: 'idle', rules: [], error: '' } )
						}
					/>
				) }

				{ state.status === 'ready' && (
					<div className="wsak-coverage__lists">
						<CoverageGroup
							title={ __(
								'Settled automatically',
								'wowstudio-accessibility-kit'
							) }
							detection="auto"
							rules={ auto }
						/>
						<CoverageGroup
							title={ __(
								'Flagged for a person',
								'wowstudio-accessibility-kit'
							) }
							detection="manual"
							rules={ manual }
						/>
					</div>
				) }
			</div>
		</section>
	);
}

/**
 * One column of the coverage list.
 *
 * @param {Object} props           Component props.
 * @param {string} props.title     Group heading.
 * @param {string} props.detection Detection slug for the tag.
 * @param {Array}  props.rules     Rules in this group.
 * @return {Element} The group.
 */
function CoverageGroup( { title, detection, rules } ) {
	return (
		<div className="wsak-coverage__group">
			<h3 className="wsak-coverage__group-title">
				<DetectionTag detection={ detection } /> { title }{ ' ' }
				<span className="wsak-coverage__count">
					{ sprintf(
						/* translators: %d: number of checks in this group. */
						__( '(%d)', 'wowstudio-accessibility-kit' ),
						rules.length
					) }
				</span>
			</h3>
			<dl className="wsak-coverage__rules">
				{ rules.map( ( rule ) => (
					<div className="wsak-coverage__rule" key={ rule.id }>
						<dt>
							{ rule.title }{ ' ' }
							<span className="wsak-tag wsak-tag--sc">
								{ sprintf(
									/* translators: %s: WCAG success criterion number. */
									__(
										'WCAG %s',
										'wowstudio-accessibility-kit'
									),
									rule.wcag_sc
								) }
							</span>
						</dt>
						<dd>{ rule.description }</dd>
					</div>
				) ) }
			</dl>
		</div>
	);
}
