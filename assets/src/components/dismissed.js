/**
 * The record of what has been set aside, and why.
 */
import { Button, Notice } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import {
	fetchDismissed,
	readableError,
	reopenIssue,
	reopenMarkup,
} from '../api';
import { EmptyState, Skeleton } from './states';
import { SeverityTag } from './tags';

/**
 * The judgements taken for the whole site.
 *
 * Listed here because they are listed nowhere else. Once one is taken the
 * findings it covers are set aside, so it is absent from every screen showing
 * open findings — which would leave a decision reaching thirty-seven pages with
 * nothing displaying it and no way to undo it. A decision that cannot be
 * withdrawn is not one worth offering, and this is where it is withdrawn.
 *
 * @param {Object}   props            Component props.
 * @param {Array}    props.rows       The decisions.
 * @param {boolean}  props.mayDecide  Whether this person may withdraw them.
 * @param {Function} props.onChange   Called after one is withdrawn.
 * @param {Function} props.onAnnounce Says what happened, for anyone not
 *                                    watching the list get shorter.
 * @return {?Element} The section, or nothing when there are none.
 */
function SiteWide( { rows, mayDecide, onChange, onAnnounce } ) {
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );

	if ( ! rows.length ) {
		return null;
	}

	return (
		<section
			className="wsak-dismissed__sitewide"
			aria-labelledby="wsak-sitewide-title"
		>
			<h3 id="wsak-sitewide-title">
				{ __(
					'False positives everywhere',
					'wowstudio-accessibility-kit'
				) }
			</h3>

			<p className="wsak-dismissed__lede">
				{ __(
					'These cover one piece of markup wherever it appears, including on pages added since the decision was taken. Withdrawing one puts every finding it covers back on the list.',
					'wowstudio-accessibility-kit'
				) }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<ul className="wsak-dismissed__list">
				{ rows.map( ( row ) => (
					<li className="wsak-dismissed__row" key={ row.fingerprint }>
						<div className="wsak-dismissed__head">
							<h4 className="wsak-dismissed__rule">
								{ row.rule_title }
							</h4>
						</div>

						{ row.note && (
							<blockquote className="wsak-dismissed__note">
								{ row.note }
							</blockquote>
						) }

						<p className="wsak-dismissed__by">
							{ sprintf(
								/* translators: 1: who set it aside. 2: when. */
								__(
									'Marked a false positive everywhere by %1$s on %2$s',
									'wowstudio-accessibility-kit'
								),
								row.by,
								row.at
							) }
						</p>

						{ mayDecide && (
							<Button
								variant="link"
								disabled={ busy === row.fingerprint }
								onClick={ () => {
									setBusy( row.fingerprint );
									setError( '' );

									reopenMarkup( row.fingerprint )
										.then( () => {
											onAnnounce(
												sprintf(
													/* translators: %s: name of the check. */
													__(
														'Put back everywhere: %s.',
														'wowstudio-accessibility-kit'
													),
													row.rule_title
												)
											);
											onChange();
										} )
										.catch( ( caught ) =>
											setError( readableError( caught ) )
										)
										.finally( () => setBusy( '' ) );
								} }
							>
								{ __(
									'Not a false positive after all',
									'wowstudio-accessibility-kit'
								) }
							</Button>
						) }
					</li>
				) ) }
			</ul>
		</section>
	);
}

/**
 * One finding set aside on one page, and the way back.
 *
 * The control was missing here, and only here. Dismissing a finding shows
 * "Not a false positive after all" on the card you just dismissed — but that
 * card is on the findings list, and the finding leaves that list the moment the
 * decision is taken. Close the screen, or scan the page again, and the only
 * place the finding still exists is this log, which had no way to undo
 * anything. So the way back was available for about as long as it took to
 * change your mind immediately, and not afterwards.
 *
 * Withdrawing is durable rather than cosmetic. The decision is stored in its
 * own table and applied to every future scan, so reopening takes the record out
 * rather than flipping a row that the next scan would overwrite — which is the
 * whole reason that table exists.
 *
 * @param {Object}   props           Component props.
 * @param {Object}   props.row       The dismissed finding.
 * @param {Function} props.onRestore Puts it back.
 * @param {boolean}  props.busy      Whether this row is in flight.
 * @return {Element} The row.
 */
function DismissedRow( { row, onRestore, busy } ) {
	return (
		<li className="wsak-dismissed__row">
			<div className="wsak-dismissed__head">
				<h3 className="wsak-dismissed__rule">{ row.message }</h3>
				<SeverityTag severity={ row.severity } label={ row.severity } />
			</div>

			<p className="wsak-dismissed__where">
				{ row.edit_url ? (
					<a href={ row.edit_url }>
						{ row.post_title ||
							__( '(no title)', 'wowstudio-accessibility-kit' ) }
					</a>
				) : (
					row.post_title
				) }
				{ ' · ' }
				{ row.wcag_sc }
			</p>

			{ row.note && (
				<blockquote className="wsak-dismissed__note">
					{ row.note }
				</blockquote>
			) }

			<p className="wsak-dismissed__by">
				{ sprintf(
					/* translators: 1: who set it aside. 2: when. */
					__(
						'Marked a false positive by %1$s on %2$s',
						'wowstudio-accessibility-kit'
					),
					row.by,
					row.at
				) }
			</p>

			{ row.may_reopen && (
				<Button
					variant="link"
					disabled={ busy }
					onClick={ () => onRestore( row ) }
				>
					{ __(
						'Not a false positive after all',
						'wowstudio-accessibility-kit'
					) }
				</Button>
			) }
		</li>
	);
}

/**
 * The dismissed log.
 *
 * Setting a finding aside is a judgement — that it does not apply, that it was
 * dealt with another way, that the check is wrong about this page. Judgements
 * are worth keeping a record of, and worth somebody else being able to read:
 * the reason this screen is open to anyone who can view reports, rather than
 * only to the people who can dismiss, is that a log only its authors can see is
 * not much of a log.
 *
 * @return {Element} The screen.
 */
export default function Dismissed() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( 0 );
	const [ announcement, setAnnouncement ] = useState( '' );

	const live = useRef( true );

	const load = useCallback( () => {
		fetchDismissed( 100 )
			.then( ( result ) => live.current && setData( result ) )
			.catch(
				( err ) => live.current && setError( readableError( err ) )
			);
	}, [] );

	useEffect( () => {
		live.current = true;

		load();

		return () => {
			live.current = false;
		};
	}, [ load ] );

	/*
	 * The row leaves this screen when it succeeds, which is the whole point and
	 * also means nothing is left to report the outcome. Hence the announcement:
	 * without it, somebody using a screen reader presses a button and the list
	 * silently gets shorter.
	 */
	const restore = useCallback(
		( row ) => {
			setBusy( row.id );
			setError( '' );

			reopenIssue( row.id )
				.then( () => {
					if ( ! live.current ) {
						return;
					}

					setAnnouncement(
						sprintf(
							/* translators: %s: the page the finding is on. */
							__(
								'Put back on the list of open findings for %s.',
								'wowstudio-accessibility-kit'
							),
							row.post_title ||
								__( 'that page', 'wowstudio-accessibility-kit' )
						)
					);

					load();
				} )
				.catch(
					( caught ) =>
						live.current && setError( readableError( caught ) )
				)
				.finally( () => live.current && setBusy( 0 ) );
		},
		[ load ]
	);

	if ( ! data && ! error ) {
		return (
			<Skeleton
				label={ __(
					'Loading your false positives…',
					'wowstudio-accessibility-kit'
				) }
			/>
		);
	}

	return (
		<section
			className="wsak-dismissed"
			aria-labelledby="wsak-dismissed-title"
		>
			<h2 id="wsak-dismissed-title" className="wsak-dismissed__title">
				{ __( 'False positives', 'wowstudio-accessibility-kit' ) }
			</h2>

			<p className="wsak-dismissed__lede">
				{ __(
					'Findings somebody decided not to act on, with the reason they gave. Setting something aside is a judgement rather than a fix — the barrier is still there — so this screen exists to make those judgements reviewable by somebody other than whoever made them.',
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

			{ data && (
				<SiteWide
					rows={ data.site_wide ?? [] }
					mayDecide={ Boolean( data.may_decide ) }
					onChange={ load }
					onAnnounce={ setAnnouncement }
				/>
			) }

			{ data &&
			data.dismissed.length === 0 &&
			( data.site_wide ?? [] ).length === 0 ? (
				<EmptyState
					title={ __(
						'No false positives yet',
						'wowstudio-accessibility-kit'
					) }
					body={ __(
						'When somebody marks a finding as reviewed and not applicable, it appears here with their name, the date, and the reason they gave.',
						'wowstudio-accessibility-kit'
					) }
				/>
			) : (
				data && (
					<>
						<p className="wsak-dismissed__count">
							{ sprintf(
								/* translators: %d: how many findings are marked false positives. */
								_n(
									'%d finding is currently marked a false positive.',
									'%d findings are currently marked false positives.',
									data.total,
									'wowstudio-accessibility-kit'
								),
								data.total
							) }
						</p>

						<ul className="wsak-dismissed__list">
							{ data.dismissed.map( ( row ) => (
								<DismissedRow
									key={ row.id }
									row={ row }
									busy={ busy === row.id }
									onRestore={ restore }
								/>
							) ) }
						</ul>
					</>
				)
			) }
		</section>
	);
}
