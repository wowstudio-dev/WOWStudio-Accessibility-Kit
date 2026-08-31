/**
 * The inspector: findings beside the page they were found on.
 */

import { Button, Notice } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import {
	locate,
	FOUND,
	NOT_FOUND,
	MISMATCH,
	PAGE_LEVEL,
} from '../scanner/locate';
import { frameDocument, highlight, clearHighlight } from '../scanner/highlight';
import { runBrowserPass } from '../scanner/run';
import { fetchCssFixes, recordBrowserPass, readableError } from '../api';
import AltTextAction from './alt-text-action';
import CssFixAction from './css-fix-action';
import FixAction from './fix-action';
import { DetectionTag, SeverityTag } from './tags';

/**
 * How long to wait for the preview to load before calling it blocked.
 *
 * Generous, because a cold cache on a slow host is not the same thing as a
 * refusal, and calling it one when it is the other would put a false warning in
 * front of someone whose page is fine.
 */
const LOAD_TIMEOUT_MS = 15000;

/**
 * How long the pointer has to rest on a finding before the page reacts.
 *
 * Sweeping the mouse down the list crosses every finding on the way, and
 * reacting to each one turns a glance into a slideshow. Waiting for the pointer
 * to settle means only a finding somebody actually paused on is shown. Keyboard
 * focus and clicks are deliberate, so they act at once.
 */
const HOVER_SETTLE_MS = 180;

/**
 * Explains why an element could not be marked.
 *
 * Each of these is a real thing that happens to real pages, and each gets its
 * own sentence rather than a shared "something went wrong" — the whole value of
 * the inspector is telling somebody where a problem is, so when we cannot, the
 * least we owe them is an accurate account of why.
 *
 * @param {string} status Result from locate().
 * @return {string} A sentence for the reader.
 */
function locateMessage( status ) {
	switch ( status ) {
		case PAGE_LEVEL:
			return __(
				'This one is about the page as a whole rather than one spot on it, so there is nowhere in particular to point.',
				'wowstudio-accessibility-kit'
			);
		case NOT_FOUND:
			return __(
				'This element is not in the page any more. It may have been edited since the scan, or it may only appear for some visitors.',
				'wowstudio-accessibility-kit'
			);
		case MISMATCH:
			return __(
				'The page has changed shape since the scan, so we cannot be sure which element this refers to. Rather than point at the wrong one, we are pointing at none. Re-scan to place it again.',
				'wowstudio-accessibility-kit'
			);
		default:
			return __(
				'This element is in the page but is not being displayed, so there is nothing to point at.',
				'wowstudio-accessibility-kit'
			);
	}
}

/**
 * Findings on the left, the page they came from on the right.
 *
 * @param {Object}   props            Component props.
 * @param {Array}    props.issues     Findings to list.
 * @param {string}   props.previewUrl URL to frame.
 * @param {string}   props.title      Title of the content being inspected.
 * @param {number}   props.scanId     Scan the browser pass reports against.
 * @param {boolean}  props.placeable  Whether server findings can be located here.
 * @param {Function} props.onExit     Called when the user leaves the inspector.
 * @param {Function} props.onFindings Called with the updated scan after the pass.
 * @return {Element} The inspector.
 */
export default function Inspector( {
	issues,
	previewUrl,
	title,
	scanId,
	placeable,
	onExit,
	onFindings,
} ) {
	const frameRef = useRef( null );
	const [ frameState, setFrameState ] = useState( 'loading' );
	const [ activeId, setActiveId ] = useState( null );
	const [ locateStatus, setLocateStatus ] = useState( '' );
	const [ announcement, setAnnouncement ] = useState( '' );
	const [ passState, setPassState ] = useState( 'idle' );
	const [ passError, setPassError ] = useState( '' );
	const [ activeElement, setActiveElement ] = useState( null );

	// Which findings already have a rule in the site's Additional CSS, and
	// whether this account may write one at all. Both come from the server
	// rather than being assumed: a rule may have been written in an earlier
	// session, or deleted by hand in the Customiser since.
	const [ cssFixes, setCssFixes ] = useState( {} );
	const [ cssTheme, setCssTheme ] = useState( '' );
	const [ cssAllowed, setCssAllowed ] = useState( false );

	const hoverTimer = useRef( null );
	const pendingReload = useRef( null );

	// A frame that a security policy refused leaves an empty document behind
	// rather than raising an error, so silence is the failure signal and has to
	// be timed rather than caught.
	useEffect( () => {
		if ( frameState !== 'loading' ) {
			return undefined;
		}

		const timer = setTimeout( () => {
			setFrameState( ( current ) =>
				current === 'loading' ? 'blocked' : current
			);
		}, LOAD_TIMEOUT_MS );

		return () => clearTimeout( timer );
	}, [ frameState ] );

	const readCssFixes = useCallback(
		() =>
			fetchCssFixes()
				.then( ( data ) => {
					setCssFixes( data.rules ?? {} );
					setCssTheme( data.theme ?? '' );
					setCssAllowed( true );
				} )
				// A refusal here is a permission answer, not a fault: an editor
				// who may scan but not change site-wide CSS gets told what they
				// can do instead, rather than an error about a route.
				.catch( () => setCssAllowed( false ) ),
		[]
	);

	useEffect( () => {
		readCssFixes();
	}, [ readCssFixes ] );

	const onFrameLoad = useCallback( () => {
		const doc = frameDocument( frameRef.current );

		setFrameState( doc ? 'ready' : 'blocked' );

		if ( pendingReload.current ) {
			const resolve = pendingReload.current;
			pendingReload.current = null;
			resolve( true );
		}
	}, [] );

	// The frame exists, so the checks that need a rendered page can finally
	// run. A frame that never opened is reported too: the server has to know
	// the difference between "checked and found nothing" and "never looked".
	useEffect( () => {
		if ( passState !== 'idle' || ! scanId ) {
			return;
		}

		if ( frameState === 'blocked' ) {
			setPassState( 'done' );
			recordBrowserPass( scanId, 'blocked' ).catch( () => {} );

			return;
		}

		if ( frameState !== 'ready' ) {
			return;
		}

		const doc = frameDocument( frameRef.current );
		const view = doc ? doc.defaultView : null;

		setPassState( 'running' );
		setAnnouncement(
			__(
				'Checking colour, size and layout on the page…',
				'wowstudio-accessibility-kit'
			)
		);

		const outcome = runBrowserPass( doc, view );

		recordBrowserPass( scanId, outcome.status, outcome.findings )
			.then( ( data ) => {
				setPassState( 'done' );

				if ( onFindings ) {
					onFindings( data );
				}

				setAnnouncement(
					sprintf(
						/* translators: %d: number of additional findings. */
						__(
							'Page checks finished. %d further findings.',
							'wowstudio-accessibility-kit'
						),
						data.stored ?? 0
					)
				);
			} )
			.catch( ( error ) => {
				setPassState( 'done' );
				setPassError( readableError( error ) );
			} );
	}, [ frameState, passState, scanId, onFindings ] );

	const cancelPendingHover = useCallback( () => {
		if ( hoverTimer.current ) {
			clearTimeout( hoverTimer.current );
			hoverTimer.current = null;
		}
	}, [] );

	// Nothing should still be pending once the inspector is gone.
	useEffect( () => cancelPendingHover, [ cancelPendingHover ] );

	const show = useCallback( ( issue ) => {
		const doc = frameDocument( frameRef.current );

		if ( ! doc ) {
			return;
		}

		setActiveId( issue.id );

		const found = locate( doc, issue );

		if ( found.status !== FOUND ) {
			clearHighlight( doc );
			setActiveElement( null );
			setLocateStatus( found.status );
			setAnnouncement( locateMessage( found.status ) );

			return;
		}

		// Kept because the fix proposal is measured from the element itself —
		// the colour the browser painted and the box it laid out — rather than
		// from anything the scan recorded earlier.
		setActiveElement( found.element );

		if ( ! highlight( doc, found.element ) ) {
			setLocateStatus( 'not-visible' );
			setAnnouncement( locateMessage( 'not-visible' ) );

			return;
		}

		setLocateStatus( '' );
		setAnnouncement(
			sprintf(
				/* translators: %s: name of the accessibility check. */
				__( 'Showing: %s', 'wowstudio-accessibility-kit' ),
				issue.rule_title
			)
		);
	}, [] );

	/**
	 * Reloads the framed page and finds the element again.
	 *
	 * This is what makes an applied rule verifiable rather than merely stored.
	 * The rule went into the site's own stylesheet, so the only way to know it
	 * survived the theme's specificity is to let the browser load the page
	 * again and measure what it painted this time.
	 *
	 * @param {Object} issue The finding to re-locate.
	 * @return {Promise<?{element: Element, view: Window}>} The element, or null.
	 */
	const reloadAndLocate = useCallback( ( issue ) => {
		const frame = frameRef.current;

		if ( ! frame || ! frame.contentWindow ) {
			return Promise.resolve( null );
		}

		return new Promise( ( resolve ) => {
			// A reload that never reports back must not leave the interface
			// waiting on it forever; the caller treats null as "could not
			// measure", which is the truth.
			const timer = setTimeout( () => {
				pendingReload.current = null;
				resolve( false );
			}, LOAD_TIMEOUT_MS );

			pendingReload.current = ( loaded ) => {
				clearTimeout( timer );
				resolve( loaded );
			};

			frame.contentWindow.location.reload();
		} ).then( ( loaded ) => {
			if ( ! loaded ) {
				return null;
			}

			const doc = frameDocument( frameRef.current );

			if ( ! doc ) {
				return null;
			}

			const found = locate( doc, issue );

			if ( found.status !== FOUND ) {
				return null;
			}

			setActiveElement( found.element );
			highlight( doc, found.element );

			return { element: found.element, view: doc.defaultView };
		} );
	}, [] );

	if ( ! previewUrl ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'This content has no public address, so it cannot be shown here. The findings below still apply.',
					'wowstudio-accessibility-kit'
				) }
			</Notice>
		);
	}

	return (
		<section
			className="wsak-inspector"
			aria-labelledby="wsak-inspector-title"
		>
			<div className="wsak-inspector__head">
				<h2 className="wsak-inspector__title" id="wsak-inspector-title">
					{ title
						? sprintf(
								/* translators: %s: content title. */
								__(
									'Inspecting “%s”',
									'wowstudio-accessibility-kit'
								),
								title
						  )
						: __( 'Inspecting', 'wowstudio-accessibility-kit' ) }
				</h2>
				{ onExit && (
					<Button variant="secondary" onClick={ onExit }>
						{ __(
							'Back to results',
							'wowstudio-accessibility-kit'
						) }
					</Button>
				) }
			</div>

			<p className="screen-reader-text" role="status" aria-live="polite">
				{ announcement }
			</p>

			<div className="wsak-inspector__panes">
				<div className="wsak-inspector__list">
					{ passState === 'running' && (
						<p className="wsak-inspector__pass">
							{ __(
								'Checking colour, size and layout on the page…',
								'wowstudio-accessibility-kit'
							) }
						</p>
					) }

					{ passError && (
						<Notice status="error" isDismissible={ false }>
							{ passError }
						</Notice>
					) }

					<p className="wsak-inspector__hint">
						{ __(
							'Choose a finding to see it on the page. Moving through this list with the keyboard works the same way.',
							'wowstudio-accessibility-kit'
						) }
					</p>

					{ ! placeable && (
						<p className="wsak-inspector__unplaceable-note">
							{ __(
								'This scan read only your content, not the whole page, so findings from the markup cannot be pointed at here. Anything found on the page itself — colour, size, layout — still can be.',
								'wowstudio-accessibility-kit'
							) }
						</p>
					) }

					<ul className="wsak-inspector__issues">
						{ issues.map( ( issue ) => (
							<li key={ issue.id }>
								<button
									type="button"
									className={ `wsak-inspector__issue${
										activeId === issue.id
											? ' is-active'
											: ''
									}` }
									aria-current={
										activeId === issue.id
											? 'true'
											: undefined
									}
									disabled={ frameState !== 'ready' }
									onClick={ () => {
										cancelPendingHover();
										show( issue );
									} }
									onFocus={ () => {
										cancelPendingHover();
										show( issue );
									} }
									onMouseEnter={ () => {
										cancelPendingHover();
										hoverTimer.current = setTimeout(
											() => show( issue ),
											HOVER_SETTLE_MS
										);
									} }
									onMouseLeave={ cancelPendingHover }
								>
									<span className="wsak-inspector__issue-title">
										{ issue.rule_title }
									</span>
									<span className="wsak-inspector__issue-tags">
										<SeverityTag
											severity={ issue.severity }
											label={ issue.severity_label }
										/>
										<DetectionTag
											detection={ issue.detection }
											label={ issue.detection_label }
										/>
									</span>
									<span className="wsak-inspector__issue-message">
										{ issue.message }
									</span>
								</button>

								{ activeId === issue.id && locateStatus && (
									<p className="wsak-inspector__unplaced">
										{ locateMessage( locateStatus ) }
									</p>
								) }

								{ /*
								 * Remediation lives with the selected finding
								 * rather than on every row: thirty open fix
								 * panels would bury the list this pane exists
								 * to make readable. It sits outside the button
								 * because a button cannot contain buttons.
								 */ }
								{ activeId === issue.id && (
									<div className="wsak-inspector__actions">
										{ issue.rule_id ===
										'img-alt-missing' ? (
											<AltTextAction
												attachmentId={
													issue.attachment_id
												}
												postId={ issue.post_id }
											/>
										) : (
											issue.detection === 'auto' &&
											issue.found_by !== 'browser' && (
												<FixAction
													issueId={ issue.id }
												/>
											)
										) }

										{ issue.found_by === 'browser' &&
											( cssAllowed ? (
												<CssFixAction
													issue={ issue }
													element={ activeElement }
													doc={ frameDocument(
														frameRef.current
													) }
													view={
														frameDocument(
															frameRef.current
														)?.defaultView
													}
													applied={ Boolean(
														cssFixes[ issue.id ]
													) }
													theme={ cssTheme }
													onChange={ readCssFixes }
													onReVerify={ () =>
														reloadAndLocate( issue )
													}
												/>
											) : (
												<p className="wsak-inspector__no-fix">
													{ __(
														'This one is about how the page is styled. Fixing it writes a rule into your site’s Additional CSS, which your account is not allowed to change — an administrator can apply it, or make the change in Appearance → Customise.',
														'wowstudio-accessibility-kit'
													) }
												</p>
											) ) }
									</div>
								) }
							</li>
						) ) }
					</ul>
				</div>

				<div className="wsak-inspector__preview">
					{ frameState === 'blocked' && (
						<Notice status="warning" isDismissible={ false }>
							<strong>
								{ __(
									'Your page would not open here.',
									'wowstudio-accessibility-kit'
								) }
							</strong>{ ' ' }
							{ __(
								'Some sites refuse to be shown inside another page, which is a reasonable security setting and not a fault. Findings from the markup are still listed, but nothing that depends on seeing the page — colour, text size, layout — was checked on this scan.',
								'wowstudio-accessibility-kit'
							) }
						</Notice>
					) }

					<iframe
						ref={ frameRef }
						className="wsak-inspector__frame"
						src={ previewUrl }
						title={ __(
							'Preview of the page being inspected',
							'wowstudio-accessibility-kit'
						) }
						onLoad={ onFrameLoad }
						hidden={ frameState === 'blocked' }
					/>
				</div>
			</div>
		</section>
	);
}
