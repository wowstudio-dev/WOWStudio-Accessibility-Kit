/**
 * Working through pages in a frame, so the browser checks reach a whole run.
 */

import { Button, Notice } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, _x, sprintf } from '@wordpress/i18n';

import { readableError, recordBrowserPass } from '../api';
import { frameDocument } from '../scanner/highlight';
import { runBrowserPass, BLOCKED } from '../scanner/run';

/**
 * How long to give a page before calling it refused.
 *
 * A frame that a security header blocked leaves an empty document behind rather
 * than raising anything, so silence is the failure signal and has to be timed.
 */
const LOAD_TIMEOUT_MS = 12000;

/**
 * A moment between pages, so the site is not asked for twenty at once.
 */
const BETWEEN_MS = 400;

/**
 * Runs the browser checks over a list of pages, one frame at a time.
 *
 * The checks that need a rendered page cannot run in a background job, so bulk
 * scanning reaches only content. This closes that gap using the one browser
 * already available: the administrator's own. It carries their session, so
 * drafts and private pages work with no token scheme and no authentication
 * bypass to design — decision F9.
 *
 * The cost is that it cannot be scheduled. A cron job has no browser, and this
 * stops when the tab closes. Findings are recorded as each page finishes, so
 * closing it loses the remaining queue and nothing already found.
 *
 * @param {Object}   props           Component props.
 * @param {Array}    props.pages     Pages to check: scan_id, post_id, title, preview_url.
 * @param {Function} props.onDismiss Called when the reader closes the finished queue.
 * @return {Element} The queue.
 */
export default function RenderedQueue( { pages, onDismiss } ) {
	const [ index, setIndex ] = useState( 0 );
	const [ done, setDone ] = useState( [] );
	const [ error, setError ] = useState( '' );
	const [ announcement, setAnnouncement ] = useState( '' );
	const [ stopped, setStopped ] = useState( false );

	const frameRef = useRef( null );
	const timer = useRef( null );
	const settled = useRef( false );

	const current = pages[ index ] ?? null;

	// Each page is finished exactly once, whether it loaded, refused, or ran
	// out of time. Without the guard a slow page that then loads would report
	// twice and the queue would skip one.
	const finish = useCallback( ( page, outcome ) => {
		if ( settled.current ) {
			return;
		}

		settled.current = true;
		clearTimeout( timer.current );

		recordBrowserPass( page.scan_id, outcome.status, outcome.findings )
			.then( ( data ) => {
				setDone( ( list ) => [
					...list,
					{
						title: page.title,
						status: outcome.status,
						stored: data?.stored ?? 0,
					},
				] );
			} )
			.catch( ( caught ) => {
				setError( readableError( caught ) );
				setDone( ( list ) => [
					...list,
					{ title: page.title, status: 'error', stored: 0 },
				] );
			} )
			.finally( () => {
				setTimeout( () => setIndex( ( at ) => at + 1 ), BETWEEN_MS );
			} );
	}, [] );

	useEffect( () => {
		if ( ! current || stopped ) {
			return undefined;
		}

		settled.current = false;

		setAnnouncement(
			sprintf(
				/* translators: 1: page title, 2: position, 3: total pages. */
				__(
					'Checking %1$s — %2$d of %3$d.',
					'wowstudio-accessibility-kit'
				),
				current.title,
				index + 1,
				pages.length
			)
		);

		timer.current = setTimeout(
			() => finish( current, { status: BLOCKED, findings: [] } ),
			LOAD_TIMEOUT_MS
		);

		return () => clearTimeout( timer.current );
	}, [ current, index, pages.length, stopped, finish ] );

	const onLoad = () => {
		if ( ! current || stopped ) {
			return;
		}

		const doc = frameDocument( frameRef.current );
		const view = doc ? doc.defaultView : null;

		finish( current, runBrowserPass( doc, view ) );
	};

	const finished = index >= pages.length;
	const blocked = done.filter( ( one ) => one.status === BLOCKED ).length;

	return (
		<div className="wsak-rendered">
			<div className="wsak-rendered__head">
				<h4 className="wsak-rendered__title">
					{ finished
						? __(
								'Colour and layout checked',
								'wowstudio-accessibility-kit'
						  )
						: __(
								'Checking colour and layout…',
								'wowstudio-accessibility-kit'
						  ) }
				</h4>
				{ /*
				 * The finished queue stays on screen until it is dismissed. It
				 * used to unmount the moment the last page reported, which meant
				 * the one thing worth reading — how many pages refused to open,
				 * and so were never checked for colour at all — appeared for a
				 * few milliseconds and then vanished.
				 */ }
				{ finished ? (
					<Button variant="secondary" onClick={ onDismiss }>
						{ __( 'Done', 'wowstudio-accessibility-kit' ) }
					</Button>
				) : (
					<Button
						variant="secondary"
						onClick={ () => setStopped( true ) }
					>
						{ __( 'Stop', 'wowstudio-accessibility-kit' ) }
					</Button>
				) }
			</div>

			<p className="screen-reader-text" role="status" aria-live="polite">
				{ announcement }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<progress
				className="wsak-run__bar"
				max={ pages.length }
				value={ Math.min( index, pages.length ) }
			/>

			<p className="wsak-run__counts">
				{ sprintf(
					/* translators: 1: pages checked, 2: pages in the queue. */
					_x(
						'%1$d of %2$d',
						'pages checked in the browser',
						'wowstudio-accessibility-kit'
					),
					Math.min( index, pages.length ),
					pages.length
				) }
			</p>

			{ /*
			 * Stated up front rather than after the tab is closed. This is the
			 * one thing in the plugin that cannot be left to run.
			 */ }
			{ ! finished && (
				<p className="wsak-rendered__note">
					{ __(
						'This one needs your browser, so it only runs while this tab is open. Everything already checked is saved as it goes.',
						'wowstudio-accessibility-kit'
					) }
				</p>
			) }

			{ blocked > 0 && finished && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: number of pages that would not open in a frame. */
						_n(
							'%d page would not open here, so its colour and layout were not checked.',
							'%d pages would not open here, so their colour and layout were not checked.',
							blocked,
							'wowstudio-accessibility-kit'
						),
						blocked
					) }{ ' ' }
					{ __(
						'Some sites refuse to be shown inside another page, which is a reasonable security setting and not a fault.',
						'wowstudio-accessibility-kit'
					) }
				</Notice>
			) }

			{ current && ! stopped && (
				// Kept on screen rather than hidden. A display:none frame is
				// throttled or never painted by some browsers, and the checks
				// need a real layout — a hidden frame would report every
				// element as zero-sized and quietly find nothing.
				<iframe
					ref={ frameRef }
					className="wsak-rendered__frame"
					src={ current.preview_url }
					title={ sprintf(
						/* translators: %s: page title. */
						__( 'Checking: %s', 'wowstudio-accessibility-kit' ),
						current.title
					) }
					onLoad={ onLoad }
				/>
			) }

			{ done.length > 0 && (
				<ul className="wsak-rendered__done">
					{ done.map( ( one, at ) => (
						<li key={ at } className="wsak-rendered__done-item">
							<span>{ one.title }</span>
							<span className="wsak-rendered__done-meta">
								{ one.status === BLOCKED
									? __(
											'would not open',
											'wowstudio-accessibility-kit'
									  )
									: sprintf(
											/* translators: %d: number of further findings. */
											_n(
												'%d more finding',
												'%d more findings',
												one.stored,
												'wowstudio-accessibility-kit'
											),
											one.stored
									  ) }
							</span>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}
