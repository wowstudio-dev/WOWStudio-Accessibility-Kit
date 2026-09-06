/**
 * The record of what has been set aside, and why.
 */
import { Notice } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { fetchDismissed, readableError } from '../api';
import { EmptyState, Skeleton } from './states';
import { SeverityTag } from './tags';

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

	const live = useRef( true );

	useEffect( () => {
		live.current = true;

		fetchDismissed( 100 )
			.then( ( result ) => live.current && setData( result ) )
			.catch(
				( err ) => live.current && setError( readableError( err ) )
			);

		return () => {
			live.current = false;
		};
	}, [] );

	if ( ! data && ! error ) {
		return (
			<Skeleton
				label={ __(
					'Loading what has been set aside…',
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
				{ __( 'Set aside', 'wowstudio-accessibility-kit' ) }
			</h2>

			<p className="wsak-dismissed__lede">
				{ __(
					'Findings somebody decided not to act on, with the reason they gave. Setting something aside is a judgement rather than a fix — the barrier is still there — so this screen exists to make those judgements reviewable by somebody other than whoever made them.',
					'wowstudio-accessibility-kit'
				) }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ data && data.dismissed.length === 0 ? (
				<EmptyState
					title={ __(
						'Nothing has been set aside',
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
								/* translators: %d: how many findings are set aside. */
								_n(
									'%d finding is currently set aside.',
									'%d findings are currently set aside.',
									data.total,
									'wowstudio-accessibility-kit'
								),
								data.total
							) }
						</p>

						<ul className="wsak-dismissed__list">
							{ data.dismissed.map( ( row ) => (
								<li
									className="wsak-dismissed__row"
									key={ row.id }
								>
									<div className="wsak-dismissed__head">
										<h3 className="wsak-dismissed__rule">
											{ row.message }
										</h3>
										<SeverityTag
											severity={ row.severity }
											label={ row.severity }
										/>
									</div>

									<p className="wsak-dismissed__where">
										{ row.edit_url ? (
											<a href={ row.edit_url }>
												{ row.post_title ||
													__(
														'(no title)',
														'wowstudio-accessibility-kit'
													) }
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
												'Set aside by %1$s on %2$s',
												'wowstudio-accessibility-kit'
											),
											row.by,
											row.at
										) }
									</p>
								</li>
							) ) }
						</ul>
					</>
				)
			) }
		</section>
	);
}
