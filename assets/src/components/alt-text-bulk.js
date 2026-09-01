/**
 * Describing many images at once, then reading every one before it is saved.
 */

import {
	Button,
	CheckboxControl,
	Notice,
	TextareaControl,
} from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, _x, sprintf } from '@wordpress/i18n';

import {
	approveAltText,
	cancelAltTextRun,
	fetchAltTextRun,
	fetchUndescribedMedia,
	readableError,
	rejectAltText,
	startAltTextRun,
} from '../api';
import { Busy, EmptyState, Skeleton } from './states';

/**
 * How often to ask a running job how it is getting on.
 */
const POLL_MS = 2500;

/**
 * The sizes somebody can actually sit and review afterwards.
 */
const QUICK_PICKS = [ 5, 10, 25 ];

/**
 * Bulk alt text: select, generate in the background, then read every one.
 *
 * This is the most valuable thing the plugin does and the only bulk write it
 * makes that cannot land in the wrong place — alt text describes the image
 * itself, so it applies wherever that image appears, under any theme, and it
 * outlives this plugin.
 *
 * It is also the one most obviously tempting to automate completely, which is
 * why it does not. What a model writes about an image whose purpose it cannot
 * see is a guess with good grammar. Bulk here means one screen instead of forty
 * modals; it does not mean nobody reads them.
 *
 * @return {Element} The screen.
 */
export default function AltTextBulk() {
	const [ listing, setListing ] = useState( null );
	const [ selected, setSelected ] = useState( [] );
	const [ run, setRun ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ announcement, setAnnouncement ] = useState( '' );

	const timer = useRef( null );

	const load = useCallback( () => {
		fetchUndescribedMedia()
			.then( setListing )
			.catch( ( caught ) => setError( readableError( caught ) ) );
	}, [] );

	useEffect( load, [ load ] );
	useEffect( () => () => clearTimeout( timer.current ), [] );

	const poll = useCallback( ( runId ) => {
		timer.current = setTimeout( () => {
			fetchAltTextRun( runId )
				.then( ( data ) => {
					// An unrecognisable reply is ignored rather than shown.
					// Replacing good state with an empty object would blank a
					// run somebody is watching, and the catch does not cover it
					// because the request succeeded.
					if ( ! data?.run_id ) {
						poll( runId );

						return;
					}

					setRun( data );

					if ( data.progress?.finished ) {
						setAnnouncement(
							__(
								'Descriptions are ready to read.',
								'wowstudio-accessibility-kit'
							)
						);
					} else {
						poll( runId );
					}
				} )
				.catch( () => {} );
		}, POLL_MS );
	}, [] );

	const begin = () => {
		setError( '' );

		startAltTextRun( selected )
			.then( ( data ) => {
				setRun( data );
				setSelected( [] );
				setAnnouncement(
					sprintf(
						/* translators: %d: number of images queued. */
						__(
							'%d images queued. Nothing is saved until you have read each one.',
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

		cancelAltTextRun( run.run_id )
			.then( setRun )
			.catch( ( caught ) => setError( readableError( caught ) ) );
	};

	if ( null === listing ) {
		return (
			<Skeleton
				label={ __(
					'Looking through your media library…',
					'wowstudio-accessibility-kit'
				) }
			/>
		);
	}

	const items = listing.items ?? [];
	const billing = run?.billing ?? listing.billing ?? {};

	return (
		<section className="wsak-bulk" aria-labelledby="wsak-alt-title">
			<h2 className="wsak-bulk__title" id="wsak-alt-title">
				{ __( 'Describe your images', 'wowstudio-accessibility-kit' ) }
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
			 * What this costs, said as precisely as it can honestly be said. The
			 * number of requests is exact. The price is not ours to state — it
			 * depends on the provider, the model and a pricing page that changes
			 * without telling us, and being confidently wrong about somebody's
			 * money is not a thing this plugin does.
			 */ }
			{ billing.per_image && (
				<p className="wsak-alt-bulk__billing">
					{ billing.per_image }
					{ ! billing.unlimited && (
						<>
							{ ' ' }
							{ sprintf(
								/* translators: 1: generations left today, 2: daily cap. */
								__(
									'You have %1$d of today’s %2$d free descriptions left.',
									'wowstudio-accessibility-kit'
								),
								billing.remaining ?? 0,
								billing.cap ?? 0
							) }
						</>
					) }
				</p>
			) }

			{ ! run && items.length === 0 && (
				<EmptyState
					title={ __(
						'Every image has been described',
						'wowstudio-accessibility-kit'
					) }
					body={ __(
						'Nothing in your media library is missing a description. Images deliberately marked as decorative are left alone — an empty description is a decision, not a gap.',
						'wowstudio-accessibility-kit'
					) }
				/>
			) }

			{ ! run && items.length > 0 && (
				<>
					<p className="wsak-bulk__lede">
						{ sprintf(
							/* translators: %d: number of images with no description. */
							_n(
								'%d image in your library has never been described.',
								'%d images in your library have never been described.',
								listing.total ?? items.length,
								'wowstudio-accessibility-kit'
							),
							listing.total ?? items.length
						) }
					</p>

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
								onClick={ () =>
									setSelected(
										items
											.slice( 0, count )
											.map( ( one ) => one.id )
									)
								}
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

					<ul className="wsak-media">
						{ items.map( ( item ) => (
							<li key={ item.id } className="wsak-media__item">
								<CheckboxControl
									__nextHasNoMarginBottom
									label={ item.title || item.filename }
									checked={ selected.includes( item.id ) }
									onChange={ () =>
										setSelected( ( current ) =>
											current.includes( item.id )
												? current.filter(
														( one ) =>
															one !== item.id
												  )
												: [ ...current, item.id ]
										)
									}
								/>
								{ item.thumbnail && (
									// Decorative here: the file name is already
									// in the label beside it, so describing the
									// thumbnail would make a screen reader read
									// the same image twice.
									<img
										className="wsak-media__thumb"
										src={ item.thumbnail }
										alt=""
									/>
								) }
							</li>
						) ) }
					</ul>

					<div className="wsak-bulk__actions">
						<Button
							variant="primary"
							disabled={ ! selected.length }
							onClick={ begin }
						>
							{ selected.length
								? sprintf(
										/* translators: %d: number of selected images. */
										_n(
											'Describe %d image',
											'Describe %d images',
											selected.length,
											'wowstudio-accessibility-kit'
										),
										selected.length
								  )
								: __(
										'Select some images',
										'wowstudio-accessibility-kit'
								  ) }
						</Button>
					</div>
				</>
			) }

			{ run && (
				<ReviewQueue
					run={ run }
					onStop={ stop }
					onDone={ () => {
						setRun( null );
						load();
					} }
				/>
			) }
		</section>
	);
}

/**
 * The suggestions, one screen, each editable before it is saved.
 *
 * @param {Object}   props        Component props.
 * @param {Object}   props.run    The run.
 * @param {Function} props.onStop Stops it.
 * @param {Function} props.onDone Returns to the library listing.
 * @return {Element} The queue.
 */
function ReviewQueue( { run, onStop, onDone } ) {
	const progress = run.progress ?? {};

	return (
		<div className="wsak-run">
			<div className="wsak-run__head">
				<h3 className="wsak-run__title">
					{ progress.finished
						? __(
								'Read each one before it is saved',
								'wowstudio-accessibility-kit'
						  )
						: __( 'Describing…', 'wowstudio-accessibility-kit' ) }
				</h3>
				{ progress.finished ? (
					<Button variant="secondary" onClick={ onDone }>
						{ __( 'Done', 'wowstudio-accessibility-kit' ) }
					</Button>
				) : (
					<Button variant="secondary" onClick={ onStop }>
						{ __( 'Stop', 'wowstudio-accessibility-kit' ) }
					</Button>
				) }
			</div>

			<progress
				className="wsak-run__bar"
				max={ progress.total ?? 0 }
				value={ progress.settled ?? 0 }
			/>

			<p className="wsak-run__counts">
				{ sprintf(
					/* translators: 1: images settled, 2: images in the run. */
					_x(
						'%1$d of %2$d',
						'images described',
						'wowstudio-accessibility-kit'
					),
					progress.settled ?? 0,
					progress.total ?? 0
				) }
				{ progress.failed > 0 &&
					', ' +
						sprintf(
							/* translators: %d: number of images that could not be described. */
							__(
								'%d could not be described',
								'wowstudio-accessibility-kit'
							),
							progress.failed
						) }
			</p>

			{ ! progress.finished && (
				<Busy
					label={ __(
						'This runs in the background. You can leave this page and come back.',
						'wowstudio-accessibility-kit'
					) }
				/>
			) }

			<ul className="wsak-review">
				{ ( run.items ?? [] ).map( ( item ) => (
					<SuggestionCard key={ item.id } item={ item } />
				) ) }
			</ul>
		</div>
	);
}

/**
 * One suggestion, with the image beside it.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.item The image and its suggestion.
 * @return {Element} The card.
 */
function SuggestionCard( { item } ) {
	const [ text, setText ] = useState( item.suggestion ?? '' );
	const [ state, setState ] = useState( item.state );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	const save = () => {
		setBusy( true );
		setError( '' );

		approveAltText( item.id, text )
			.then( ( data ) => setState( data.state ) )
			.catch( ( caught ) => setError( readableError( caught ) ) )
			.finally( () => setBusy( false ) );
	};

	const discard = () => {
		setBusy( true );

		rejectAltText( item.id )
			.then( ( data ) => setState( data.state ) )
			.catch( ( caught ) => setError( readableError( caught ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<li className="wsak-review__item">
			{ item.thumbnail && (
				<img
					className="wsak-review__thumb"
					src={ item.thumbnail }
					alt=""
				/>
			) }

			<div className="wsak-review__body">
				<p className="wsak-review__file">{ item.filename }</p>

				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }

				{ 'failed' === state && (
					<p className="wsak-review__error">{ item.error }</p>
				) }

				{ 'queued' === state && (
					<Busy
						label={ __(
							'Waiting…',
							'wowstudio-accessibility-kit'
						) }
					/>
				) }

				{ 'applied' === state && (
					<p className="wsak-review__saved">
						{ __(
							'Saved to your media library. It now describes this image everywhere it appears.',
							'wowstudio-accessibility-kit'
						) }
					</p>
				) }

				{ 'ready' === state && (
					<>
						{ item.decorative && (
							<Notice status="info" isDismissible={ false }>
								{ __(
									'This looks decorative — an image that adds nothing a reader would miss. If that is right, save it with the box empty, which tells a screen reader to skip it.',
									'wowstudio-accessibility-kit'
								) }
							</Notice>
						) }

						<TextareaControl
							__nextHasNoMarginBottom
							label={ __(
								'Description',
								'wowstudio-accessibility-kit'
							) }
							help={ __(
								'Written by a model that cannot see why this image is on your page. Change anything that is wrong before saving.',
								'wowstudio-accessibility-kit'
							) }
							value={ text }
							rows={ 2 }
							onChange={ setText }
						/>

						<div className="wsak-review__actions">
							<Button
								variant="primary"
								disabled={ busy }
								onClick={ save }
							>
								{ __(
									'Save this',
									'wowstudio-accessibility-kit'
								) }
							</Button>
							<Button
								variant="tertiary"
								disabled={ busy }
								onClick={ discard }
							>
								{ __(
									'Discard',
									'wowstudio-accessibility-kit'
								) }
							</Button>
						</div>
					</>
				) }
			</div>
		</li>
	);
}
