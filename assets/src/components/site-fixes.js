/**
 * The site-wide fixes, and the switches that turn them on.
 */

import { Notice, ToggleControl } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { fetchSiteFixes, readableError, toggleSiteFix } from '../api';
import { Skeleton } from './states';

/**
 * One fix, with its switch and everything worth knowing before flipping it.
 *
 * The caveat is always shown rather than hidden behind a disclosure. These
 * change every page for every visitor, and "what might this disturb" is not a
 * detail somebody should have to go looking for after the fact.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.fix      The fix.
 * @param {boolean}  props.busy     Whether a change is in flight.
 * @param {Function} props.onToggle Flips the switch.
 * @return {Element} The row.
 */
function FixRow( { fix, busy, onToggle } ) {
	return (
		<li className="wsak-fix-row">
			<ToggleControl
				className="wsak-fix-row__toggle"
				label={ fix.title }
				help={ fix.description }
				checked={ fix.enabled }
				disabled={ busy }
				onChange={ ( next ) => onToggle( fix.id, next ) }
				__nextHasNoMarginBottom
			/>

			{ fix.caveat && (
				<p className="wsak-fix-row__caveat">
					<span className="wsak-fix-row__caveat-label">
						{ __(
							'Worth knowing:',
							'wowstudio-accessibility-kit'
						) }
					</span>{ ' ' }
					{ fix.caveat }
				</p>
			) }
		</li>
	);
}

/**
 * The site-wide fixes screen.
 *
 * These are a different kind of thing from the fixes on a finding, and the
 * screen says so rather than mixing them: a finding is corrected on one page,
 * where you can see what changed before and after. These are switches that
 * change every page at once, and there is no "before" to show, because what
 * they mostly fix is an absence.
 *
 * @return {Element} The screen.
 */
export default function SiteFixes() {
	const [ fixes, setFixes ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const [ announcement, setAnnouncement ] = useState( '' );

	const live = useRef( true );

	useEffect( () => {
		live.current = true;

		fetchSiteFixes()
			.then(
				( result ) => live.current && setFixes( result.fixes || [] )
			)
			.catch(
				( err ) => live.current && setError( readableError( err ) )
			);

		return () => {
			live.current = false;
		};
	}, [] );

	const toggle = useCallback( ( id, enabled ) => {
		setBusy( id );
		setError( '' );

		toggleSiteFix( id, enabled )
			.then( ( result ) => {
				if ( ! live.current ) {
					return;
				}

				const next = result.fixes || [];
				setFixes( next );

				const changed = next.find( ( row ) => row.id === id );

				setAnnouncement(
					sprintf(
						/* translators: 1: the name of the fix. 2: on or off, already translated. */
						__(
							'%1$s is now %2$s.',
							'wowstudio-accessibility-kit'
						),
						changed?.title || id,
						changed?.enabled
							? __( 'on', 'wowstudio-accessibility-kit' )
							: __( 'off', 'wowstudio-accessibility-kit' )
					)
				);
			} )
			.catch(
				( err ) => live.current && setError( readableError( err ) )
			)
			.finally( () => live.current && setBusy( '' ) );
	}, [] );

	if ( ! fixes && ! error ) {
		return (
			<Skeleton
				label={ __(
					'Loading the site-wide fixes…',
					'wowstudio-accessibility-kit'
				) }
			/>
		);
	}

	const on = ( fixes || [] ).filter( ( fix ) => fix.enabled ).length;

	return (
		<section className="wsak-fixes" aria-labelledby="wsak-fixes-title">
			<h2 id="wsak-fixes-title" className="wsak-fixes__title">
				{ __(
					'Fixes for the whole site',
					'wowstudio-accessibility-kit'
				) }
			</h2>

			<p className="wsak-fixes__lede">
				{ __(
					'These are switches rather than edits. Each one supplies something your theme leaves out — a skip link, a visible focus outline, a page title — on every page at once. Nothing is written into your content, and switching one off leaves your site exactly as it was.',
					'wowstudio-accessibility-kit'
				) }
			</p>

			<p className="wsak-fixes__lede">
				{ __(
					'They also cover only part of what a scan finds. A switch can supply what is missing everywhere; it cannot decide what one of your images is for, or what a link should be called. Those stay on the findings list.',
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

			{ fixes && (
				<>
					<p className="wsak-fixes__count">
						{ sprintf(
							/* translators: 1: how many are on. 2: how many there are. */
							_n(
								'%1$d of %2$d switched on.',
								'%1$d of %2$d switched on.',
								fixes.length,
								'wowstudio-accessibility-kit'
							),
							on,
							fixes.length
						) }
					</p>

					<ul className="wsak-fixes__list">
						{ fixes.map( ( fix ) => (
							<FixRow
								key={ fix.id }
								fix={ fix }
								busy={ busy === fix.id }
								onToggle={ toggle }
							/>
						) ) }
					</ul>

					<p className="wsak-fixes__note">
						{ __(
							'Most of these take effect the next time a page is loaded, because they are decided early in the request. If you have a page open in another tab, reload it before checking.',
							'wowstudio-accessibility-kit'
						) }
					</p>
				</>
			) }
		</section>
	);
}
