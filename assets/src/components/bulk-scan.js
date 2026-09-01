/**
 * Choosing content to check, and watching it happen.
 */

import {
	Button,
	CheckboxControl,
	Notice,
	SearchControl,
} from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import {
	cancelRun,
	fetchContent,
	fetchContentTypes,
	fetchRun,
	readableError,
	startRun,
} from '../api';
import CoverageBadge from './coverage-badge';
import { Busy, ErrorState, Skeleton } from './states';

/**
 * How often to ask a running job how it is getting on.
 *
 * Slow enough not to hammer the site that is currently busy scanning itself.
 */
const POLL_MS = 2000;

/**
 * The shortcuts offered above a long list.
 *
 * A site with four hundred posts is the normal case, not the edge case, and
 * "select all" on four hundred is a decision nobody can make responsibly. These
 * are the sizes somebody can actually watch finish.
 */
const QUICK_PICKS = [ 5, 10, 25 ];

/**
 * Content selection and bulk scanning.
 *
 * @param {Object}   props           Component props.
 * @param {Function} props.onInspect Opens one page in the inspector.
 * @return {Element} The screen.
 */
export default function BulkScan( { onInspect } ) {
	const [ types, setTypes ] = useState( null );
	const [ type, setType ] = useState( '' );
	const [ loopback, setLoopback ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ listing, setListing ] = useState( {
		status: 'loading',
		data: null,
	} );
	const [ selected, setSelected ] = useState( [] );
	const [ run, setRun ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ announcement, setAnnouncement ] = useState( '' );

	const timer = useRef( null );

	useEffect( () => {
		fetchContentTypes()
			.then( ( data ) => {
				setTypes( data.types ?? [] );
				setLoopback( data.loopback ?? null );

				if ( data.types?.length ) {
					setType( data.types[ 0 ].name );
				}
			} )
			.catch( ( caught ) => setError( readableError( caught ) ) );
	}, [] );

	useEffect( () => {
		if ( ! type ) {
			return;
		}

		setListing( { status: 'loading', data: null } );

		fetchContent( { type, search } )
			.then( ( data ) => setListing( { status: 'ready', data } ) )
			.catch( ( caught ) =>
				setListing( {
					status: 'error',
					data: null,
					error: readableError( caught ),
				} )
			);
	}, [ type, search ] );

	// Polling stops the moment the run settles, and on unmount. A timer left
	// behind would keep asking a finished job how it is getting on for as long
	// as the tab is open.
	const poll = useCallback( ( runId ) => {
		timer.current = setTimeout( () => {
			fetchRun( runId )
				.then( ( data ) => {
					// A reply that is not recognisably a run is ignored rather
					// than shown. Replacing good state with an empty object
					// blanks a run somebody is watching — the failure looks
					// like the work vanished rather than like a hiccup, and the
					// catch below does not cover it because the request
					// succeeded.
					if ( ! data?.run_id ) {
						poll( runId );

						return;
					}

					setRun( data );

					if ( ! data.progress?.finished ) {
						poll( runId );
					} else {
						setAnnouncement(
							sprintf(
								/* translators: %d: number of pages checked. */
								__(
									'Finished. %d pages checked.',
									'wowstudio-accessibility-kit'
								),
								data.progress?.complete ?? 0
							)
						);
					}
				} )
				.catch( () => {} );
		}, POLL_MS );
	}, [] );

	useEffect( () => () => clearTimeout( timer.current ), [] );

	const items = listing.data?.items ?? [];
	const toggle = ( id ) =>
		setSelected( ( current ) =>
			current.includes( id )
				? current.filter( ( one ) => one !== id )
				: [ ...current, id ]
		);

	const pick = ( count ) =>
		setSelected( items.slice( 0, count ).map( ( item ) => item.id ) );

	const begin = () => {
		setError( '' );

		startRun( selected )
			.then( ( data ) => {
				setRun( data );
				setSelected( [] );
				setAnnouncement(
					sprintf(
						/* translators: %d: number of pages queued. */
						__(
							'%d pages queued. This runs in the background — you can leave this page.',
							'wowstudio-accessibility-kit'
						),
						data.progress?.total ?? 0
					)
				);
				poll( data.run_id );
			} )
			.catch( ( caught ) => setError( readableError( caught ) ) );
	};

	const stop = () => {
		clearTimeout( timer.current );

		cancelRun( run.run_id )
			.then( ( data ) => {
				setRun( data );
				setAnnouncement(
					__(
						'Stopped. Everything already checked has been kept.',
						'wowstudio-accessibility-kit'
					)
				);
			} )
			.catch( ( caught ) => setError( readableError( caught ) ) );
	};

	if ( null === types ) {
		return (
			<Skeleton
				label={ __(
					'Loading your content…',
					'wowstudio-accessibility-kit'
				) }
			/>
		);
	}

	return (
		<section className="wsak-bulk" aria-labelledby="wsak-bulk-title">
			<h2 className="wsak-bulk__title" id="wsak-bulk-title">
				{ __( 'Check your content', 'wowstudio-accessibility-kit' ) }
			</h2>

			<p className="screen-reader-text" role="status" aria-live="polite">
				{ announcement }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ /*
			 * Said before the button rather than discovered when the run comes
			 * back thin. A host that blocks loopback still gets a useful bulk
			 * scan; what it does not get is the checks that need a browser, and
			 * that is worth knowing in advance rather than inferring from an
			 * absence of findings.
			 */ }
			{ false === loopback && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This site cannot fetch its own pages, which is a common host setting and not a fault. Bulk checking still works and reads the content of every page you select. What it cannot do is check colour, text size and layout — those need the page open in a browser, so open a page in the inspector to check those.',
						'wowstudio-accessibility-kit'
					) }
				</Notice>
			) }

			<div
				className="wsak-bulk__types"
				role="tablist"
				aria-label={ __(
					'Content types',
					'wowstudio-accessibility-kit'
				) }
			>
				{ types.map( ( one ) => (
					<Button
						key={ one.name }
						role="tab"
						aria-selected={ type === one.name }
						variant={ type === one.name ? 'primary' : 'tertiary' }
						onClick={ () => {
							setType( one.name );
							setSelected( [] );
						} }
					>
						{ one.label }{ ' ' }
						<span className="wsak-bulk__count">{ one.count }</span>
					</Button>
				) ) }
			</div>

			<SearchControl
				__nextHasNoMarginBottom
				label={ __(
					'Search your content',
					'wowstudio-accessibility-kit'
				) }
				value={ search }
				onChange={ setSearch }
			/>

			{ listing.status === 'error' && (
				<ErrorState
					message={ listing.error }
					onRetry={ () => setSearch( search ) }
				/>
			) }

			{ listing.status === 'loading' && (
				<Skeleton
					label={ __( 'Loading…', 'wowstudio-accessibility-kit' ) }
				/>
			) }

			{ listing.status === 'ready' && items.length > 0 && (
				<>
					<div className="wsak-bulk__picks">
						<span className="wsak-bulk__picks-label">
							{ __(
								'Select the first',
								'wowstudio-accessibility-kit'
							) }
						</span>
						{ QUICK_PICKS.map( ( count ) => (
							<Button
								key={ count }
								variant="tertiary"
								disabled={ items.length < count }
								onClick={ () => pick( count ) }
							>
								{ count }
							</Button>
						) ) }
						<Button
							variant="tertiary"
							disabled={ ! selected.length }
							onClick={ () => setSelected( [] ) }
						>
							{ __( 'Clear', 'wowstudio-accessibility-kit' ) }
						</Button>
					</div>

					<ul className="wsak-bulk__list">
						{ items.map( ( item ) => (
							<li key={ item.id } className="wsak-bulk__item">
								<CheckboxControl
									__nextHasNoMarginBottom
									label={ item.title }
									checked={ selected.includes( item.id ) }
									onChange={ () => toggle( item.id ) }
								/>
								<span className="wsak-bulk__item-meta">
									<CoverageBadge
										coverage={ item.coverage }
										label={ item.coverage_label }
										stale={ item.stale }
									/>
									{ null !== item.score && (
										<span className="wsak-bulk__score">
											{ sprintf(
												/* translators: %d: score out of 100. */
												__(
													'%d / 100',
													'wowstudio-accessibility-kit'
												),
												item.score
											) }
										</span>
									) }
								</span>
							</li>
						) ) }
					</ul>

					<div className="wsak-bulk__actions">
						<Button
							variant="primary"
							disabled={
								! selected.length ||
								( run && ! run.progress?.finished )
							}
							onClick={ begin }
						>
							{ selected.length
								? sprintf(
										/* translators: %d: number of selected items. */
										_n(
											'Check %d item',
											'Check %d items',
											selected.length,
											'wowstudio-accessibility-kit'
										),
										selected.length
								  )
								: __(
										'Select something to check',
										'wowstudio-accessibility-kit'
								  ) }
						</Button>
					</div>
				</>
			) }

			{ run && (
				<RunProgress
					run={ run }
					onStop={ stop }
					onInspect={ onInspect }
				/>
			) }
		</section>
	);
}

/**
 * How a run is getting on, and what it found.
 *
 * @param {Object}   props           Component props.
 * @param {Object}   props.run       The run.
 * @param {Function} props.onStop    Stops it.
 * @param {Function} props.onInspect Opens one page in the inspector.
 * @return {Element} The panel.
 */
function RunProgress( { run, onStop, onInspect } ) {
	const progress = run.progress ?? {};
	const finished = progress.finished;

	return (
		<div className="wsak-run">
			<div className="wsak-run__head">
				<h3 className="wsak-run__title">
					{ finished
						? __( 'Finished', 'wowstudio-accessibility-kit' )
						: __( 'Checking…', 'wowstudio-accessibility-kit' ) }
				</h3>
				{ ! finished && (
					<Button variant="secondary" onClick={ onStop }>
						{ __( 'Stop', 'wowstudio-accessibility-kit' ) }
					</Button>
				) }
			</div>

			{ /*
			 * A native progress element, so it is announced and styled by the
			 * browser rather than mimed with a div. The numbers are written out
			 * beside it because a bar alone says nothing to anyone not looking
			 * at the screen.
			 */ }
			<progress
				className="wsak-run__bar"
				max={ progress.total ?? 0 }
				value={ progress.settled ?? 0 }
			/>

			<p className="wsak-run__counts">
				{ sprintf(
					/* translators: 1: pages settled, 2: pages in the run. */
					__( '%1$d of %2$d', 'wowstudio-accessibility-kit' ),
					progress.settled ?? 0,
					progress.total ?? 0
				) }
				{ progress.failed > 0 &&
					', ' +
						sprintf(
							/* translators: %d: number of pages that could not be checked. */
							_n(
								'%d could not be checked',
								'%d could not be checked',
								progress.failed,
								'wowstudio-accessibility-kit'
							),
							progress.failed
						) }
				{ progress.cancelled > 0 &&
					', ' +
						sprintf(
							/* translators: %d: number of pages not checked because the run was stopped. */
							__( '%d stopped', 'wowstudio-accessibility-kit' ),
							progress.cancelled
						) }
			</p>

			{ ! finished && (
				<Busy
					label={ __(
						'This runs in the background. You can leave this page and come back.',
						'wowstudio-accessibility-kit'
					) }
				/>
			) }

			<ul className="wsak-run__pages">
				{ ( run.pages ?? [] ).map( ( page ) => (
					<li key={ page.scan_id } className="wsak-run__page">
						<span className="wsak-run__page-title">
							{ page.title }
						</span>
						<span className="wsak-run__page-meta">
							{ page.error ? (
								<span className="wsak-run__page-error">
									{ page.error }
								</span>
							) : (
								<>
									<CoverageBadge
										coverage={ page.coverage }
										label={ page.coverage_label }
									/>
									<span className="wsak-run__page-count">
										{ sprintf(
											/* translators: %d: number of findings. */
											_n(
												'%d finding',
												'%d findings',
												page.findings?.length ?? 0,
												'wowstudio-accessibility-kit'
											),
											page.findings?.length ?? 0
										) }
									</span>
									{ onInspect && (
										<Button
											variant="link"
											onClick={ () =>
												onInspect( page.post_id )
											}
										>
											{ __(
												'Check the rest',
												'wowstudio-accessibility-kit'
											) }
										</Button>
									) }
								</>
							) }
						</span>
					</li>
				) ) }
			</ul>
		</div>
	);
}
