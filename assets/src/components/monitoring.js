/**
 * Re-checking on a schedule, and what moved since last time.
 */
import { Notice, SelectControl } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import {
	fetchMonitoring,
	readableError,
	saveMonitoringFrequency,
} from '../api';
import { EmptyState, Skeleton } from './states';

/**
 * One page's movement between its last two scans.
 *
 * The score delta is shown next to what actually changed, never on its own. A
 * score that moved without any finding appearing usually means the page got
 * longer, and a number presented alone invites being read as a verdict.
 *
 * @param {Object} props        Component props.
 * @param {Object} props.change The change.
 * @return {Element} The row.
 */
function ChangeRow( { change } ) {
	const delta = change.delta;
	const tone =
		change.regressed || ( delta !== null && delta < 0 )
			? 'worse'
			: 'better';

	return (
		<li className={ `wsak-change wsak-change--${ tone }` }>
			<div className="wsak-change__head">
				<h3 className="wsak-change__title">
					{ change.title ||
						__( '(no title)', 'wowstudio-accessibility-kit' ) }
				</h3>

				{ delta !== null && delta !== 0 && (
					<span className="wsak-change__delta">
						{ sprintf(
							/* translators: %s: the score movement, already signed. */
							__( '%s points', 'wowstudio-accessibility-kit' ),
							delta > 0 ? `+${ delta }` : String( delta )
						) }
					</span>
				) }
			</div>

			{ change.appeared.length > 0 && (
				<p className="wsak-change__appeared">
					<strong>
						{ __(
							'Started failing:',
							'wowstudio-accessibility-kit'
						) }
					</strong>{ ' ' }
					{ change.appeared
						.map( ( row ) => `${ row.title } (${ row.count })` )
						.join( ', ' ) }
				</p>
			) }

			{ change.resolved.length > 0 && (
				<p className="wsak-change__resolved">
					<strong>
						{ __(
							'No longer failing:',
							'wowstudio-accessibility-kit'
						) }
					</strong>{ ' ' }
					{ change.resolved
						.map( ( row ) => `${ row.title } (${ row.count })` )
						.join( ', ' ) }
				</p>
			) }
		</li>
	);
}

/**
 * The monitoring screen.
 *
 * @return {Element} The screen.
 */
export default function Monitoring() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ announcement, setAnnouncement ] = useState( '' );

	const live = useRef( true );

	useEffect( () => {
		live.current = true;

		fetchMonitoring()
			.then( ( result ) => live.current && setData( result ) )
			.catch(
				( err ) => live.current && setError( readableError( err ) )
			);

		return () => {
			live.current = false;
		};
	}, [] );

	const change = useCallback( ( frequency ) => {
		setBusy( true );
		setError( '' );

		saveMonitoringFrequency( frequency )
			.then( ( result ) => {
				if ( ! live.current ) {
					return;
				}

				setData( result );
				setAnnouncement(
					result.next_run_h
						? sprintf(
								/* translators: %s: when the next scan is due. */
								__(
									'Saved. The next check is due %s.',
									'wowstudio-accessibility-kit'
								),
								result.next_run_h
						  )
						: __(
								'Saved. Scheduled checking is off.',
								'wowstudio-accessibility-kit'
						  )
				);
			} )
			.catch(
				( err ) => live.current && setError( readableError( err ) )
			)
			.finally( () => live.current && setBusy( false ) );
	}, [] );

	if ( ! data && ! error ) {
		return (
			<Skeleton
				label={ __(
					'Loading the schedule…',
					'wowstudio-accessibility-kit'
				) }
			/>
		);
	}

	const choices = data
		? Object.keys( data.choices ).map( ( value ) => ( {
				value,
				label: data.choices[ value ],
		  } ) )
		: [];

	return (
		<section className="wsak-monitor" aria-labelledby="wsak-monitor-title">
			<h2 id="wsak-monitor-title" className="wsak-monitor__title">
				{ __(
					'Checking on a schedule',
					'wowstudio-accessibility-kit'
				) }
			</h2>

			<p className="wsak-monitor__lede">
				{ __(
					'Every score elsewhere in this plugin is the result of somebody pressing a button. Switch this on and the site re-checks itself, so a problem introduced by an edit, a plugin update or a theme change is noticed without anybody having to remember to look.',
					'wowstudio-accessibility-kit'
				) }
			</p>

			<p className="screen-reader-text" role="status" aria-live="polite">
				{ announcement }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ data && ! data.available && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Background jobs are not running on this site, so a scheduled check cannot start. That is usually WordPress cron being switched off, or blocked by the host.',
						'wowstudio-accessibility-kit'
					) }
				</Notice>
			) }

			{ data && (
				<>
					<div className="wsak-monitor__control">
						<SelectControl
							label={ __(
								'Re-check this site',
								'wowstudio-accessibility-kit'
							) }
							value={ data.frequency }
							options={ choices }
							disabled={ busy || ! data.available }
							onChange={ change }
							__nextHasNoMarginBottom
						/>

						<p className="wsak-monitor__note">
							{ sprintf(
								/* translators: %d: how many pages a scheduled run covers. */
								_n(
									'Each run covers the %d most recently updated page, in the background. It does not scan the whole site: a full scan of a large site is a lot of work to start on a schedule nobody is watching.',
									'Each run covers the %d most recently updated pages, in the background. It does not scan the whole site: a full scan of a large site is a lot of work to start on a schedule nobody is watching.',
									data.page_limit,
									'wowstudio-accessibility-kit'
								),
								data.page_limit
							) }
						</p>

						{ data.next_run_h && (
							<p className="wsak-monitor__next">
								{ sprintf(
									/* translators: %s: when the next check is due. */
									__(
										'Next check: %s.',
										'wowstudio-accessibility-kit'
									),
									data.next_run_h
								) }
							</p>
						) }

						<p className="wsak-monitor__note">
							{ __(
								'Nothing is emailed and nothing is sent anywhere — this plugin contacts no outside service. What a check finds appears here, and on the pages themselves.',
								'wowstudio-accessibility-kit'
							) }
						</p>
					</div>

					<h3 className="wsak-monitor__subtitle">
						{ __(
							'What has changed',
							'wowstudio-accessibility-kit'
						) }
					</h3>

					{ data.changes.length === 0 ? (
						<EmptyState
							title={ __(
								'Nothing to compare yet',
								'wowstudio-accessibility-kit'
							) }
							body={ __(
								'A page has to be checked twice before anything can be said about what changed. This fills in after the second check of a page — whether that comes from a schedule or from you pressing the button.',
								'wowstudio-accessibility-kit'
							) }
						/>
					) : (
						<ul className="wsak-monitor__changes">
							{ data.changes.map( ( row ) => (
								<ChangeRow key={ row.post_id } change={ row } />
							) ) }
						</ul>
					) }
				</>
			) }
		</section>
	);
}
