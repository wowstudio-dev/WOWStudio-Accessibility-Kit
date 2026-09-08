/**
 * The whole site at a glance, without pretending to be a verdict.
 */

import { Button } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { fetchOverview } from '../api';
import { BarList, Donut, Sparkline, Stat, severityRows } from './charts';
import NextStep from './next-step';
import { EmptyState } from './states';

/**
 * The overview.
 *
 * @param {Object}   props           Component props.
 * @param {Function} props.onGo      Opens another screen.
 * @param {Function} [props.onDrill] Opens the findings behind a figure.
 * @return {Element} The screen.
 */
export default function Overview( { onGo, onDrill } ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let live = true;

		fetchOverview()
			.then( ( result ) => live && setData( result ) )
			.catch(
				( err ) =>
					live &&
					setError(
						err?.message ||
							__(
								'The summary could not be loaded.',
								'wowstudio-accessibility-kit'
							)
					)
			);

		return () => {
			live = false;
		};
	}, [] );

	if ( error ) {
		return <EmptyState title={ error } body="" />;
	}

	if ( ! data ) {
		return (
			<p className="wsak-overview__loading">
				{ __( 'Adding it up…', 'wowstudio-accessibility-kit' ) }
			</p>
		);
	}

	const { scanned, score, issues, by_band: byBand, by_rule: byRule } = data;
	const { history, worst, coverage } = data;

	if ( ! scanned.pages ) {
		return (
			<>
				<EmptyState
					title={ __(
						'Nothing has been scanned yet',
						'wowstudio-accessibility-kit'
					) }
					body={ __(
						'This fills in once a page has been checked. It counts only what has actually been scanned, so it starts empty rather than starting at a hundred.',
						'wowstudio-accessibility-kit'
					) }
				/>

				<NextStep step={ data.next_step } onGo={ onGo } />

				<p className="wsak-overview__door">
					<Button variant="link" onClick={ () => onGo( 'coverage' ) }>
						{ __(
							'What these checks cover, and what they cannot',
							'wowstudio-accessibility-kit'
						) }
					</Button>
				</p>
			</>
		);
	}

	const ruleRows = ( byRule || [] ).map( ( row ) => ( {
		key: row.rule,
		label: row.title,
		tone: row.severity,
		count: row.count,
	} ) );

	const worstRows = ( worst || [] ).map( ( row ) => ( {
		key: String( row.post_id ),
		label: row.title,
		count: row.count,
	} ) );

	return (
		<div className="wsak-overview">
			<NextStep step={ data.next_step } onGo={ onGo } />

			<div className="wsak-overview__top">
				<section
					className="wsak-card wsak-overview__score"
					aria-labelledby="wsak-ov-score"
				>
					<h3 id="wsak-ov-score" className="wsak-card__title">
						{ __(
							'Across what has been scanned',
							'wowstudio-accessibility-kit'
						) }
					</h3>

					<Donut
						value={ score ?? 0 }
						label={ __(
							'Mean score across scanned pages',
							'wowstudio-accessibility-kit'
						) }
					/>

					{ /*
					 * The qualifier sits with the number rather than in a
					 * footnote. This is the figure most likely to be screenshotted
					 * and shown to somebody who did not run the scan.
					 */ }
					<p className="wsak-overview__qualifier">
						{ sprintf(
							/* translators: 1: pages scanned. 2: pages published. */
							__(
								'The average of the latest score for each of %1$d scanned pages, out of %2$d published. It counts only what automated checks can settle, which is part of WCAG rather than all of it, and it does not tell you whether your site meets any legal requirement.',
								'wowstudio-accessibility-kit'
							),
							scanned.pages,
							scanned.published
						) }
					</p>
				</section>

				<div className="wsak-overview__stats">
					{ /*
					 * Two figures rather than one. Every finding is already
					 * tagged auto-detected or needs-manual-review; adding them
					 * together for the headline undid that tagging, and the
					 * headline is the number that gets screenshotted. "I could
					 * not check this" and "this is broken" are different claims
					 * and are counted separately.
					 */ }
					<Stat
						label={ __(
							'Barriers found',
							'wowstudio-accessibility-kit'
						) }
						value={ issues.found ?? issues.open }
						note={ __(
							'Settled by an automated check. These are wrong.',
							'wowstudio-accessibility-kit'
						) }
					/>
					<Stat
						label={ __(
							'Needs a person to look',
							'wowstudio-accessibility-kit'
						) }
						value={ issues.needs_a_look ?? 0 }
						note={ __(
							'Automation could not settle these. They may be fine.',
							'wowstudio-accessibility-kit'
						) }
					/>
					<Stat
						label={ __( 'Fixed', 'wowstudio-accessibility-kit' ) }
						value={ issues.fixed }
						tone="good"
						/*
						 * Only when there is something to say. A note reading
						 * "0 findings set aside" under a figure that is also
						 * zero is two noughts explaining each other.
						 */
						note={
							issues.ignored
								? sprintf(
										/* translators: %d: how many findings have been set aside as not a problem. */
										_n(
											'%d finding set aside as not a problem.',
											'%d findings set aside as not a problem.',
											issues.ignored,
											'wowstudio-accessibility-kit'
										),
										issues.ignored
								  )
								: undefined
						}
					/>
					<Stat
						label={ __(
							'Checks available',
							'wowstudio-accessibility-kit'
						) }
						value={ coverage.rules_total }
						note={ sprintf(
							/* translators: 1: server-pass checks. 2: browser-pass checks. 3: pages that have had a browser pass. */
							__(
								'%1$d run on the server, %2$d need the page view — which %3$d scanned pages have had.',
								'wowstudio-accessibility-kit'
							),
							coverage.rules_server,
							coverage.rules_browser,
							coverage.pages_browser
						) }
					/>
				</div>
			</div>

			<div className="wsak-overview__grid">
				<section
					className="wsak-card"
					aria-labelledby="wsak-ov-severity"
				>
					<h3 id="wsak-ov-severity" className="wsak-card__title">
						{ __(
							'Open findings by severity',
							'wowstudio-accessibility-kit'
						) }
					</h3>
					<BarList
						items={ severityRows( byBand ) }
						label={ __(
							'Open findings by severity',
							'wowstudio-accessibility-kit'
						) }
					/>
				</section>

				<section className="wsak-card" aria-labelledby="wsak-ov-rules">
					<h3 id="wsak-ov-rules" className="wsak-card__title">
						{ __(
							'What comes up most',
							'wowstudio-accessibility-kit'
						) }
					</h3>
					<BarList
						items={ ruleRows }
						label={ __(
							'Open findings by check',
							'wowstudio-accessibility-kit'
						) }
						onSelect={
							onDrill
								? ( rule ) => onDrill( { rule } )
								: undefined
						}
					/>
				</section>

				<section className="wsak-card" aria-labelledby="wsak-ov-worst">
					<h3 id="wsak-ov-worst" className="wsak-card__title">
						{ __(
							'Pages with the most to do',
							'wowstudio-accessibility-kit'
						) }
					</h3>
					<BarList
						items={ worstRows }
						label={ __(
							'Open findings by page',
							'wowstudio-accessibility-kit'
						) }
						onSelect={
							onDrill
								? ( postId ) =>
										onDrill( { post: Number( postId ) } )
								: undefined
						}
					/>
				</section>

				<section className="wsak-card" aria-labelledby="wsak-ov-trend">
					<h3 id="wsak-ov-trend" className="wsak-card__title">
						{ __( 'Score by scan', 'wowstudio-accessibility-kit' ) }
					</h3>
					<Sparkline points={ history } />
					<p className="wsak-card__note">
						{ sprintf(
							/* translators: %d: how many scans are plotted. */
							_n(
								'The last %d scan, in the order it ran. This plugin does not re-scan on its own, so the spacing is however often somebody pressed the button — it is a sequence, not a timeline.',
								'The last %d scans, in the order they ran. This plugin does not re-scan on its own, so the spacing is however often somebody pressed the button — it is a sequence, not a timeline.',
								history.length,
								'wowstudio-accessibility-kit'
							),
							history.length
						) }
					</p>
				</section>
			</div>

			<p className="wsak-overview__door">
				<Button variant="link" onClick={ () => onGo( 'coverage' ) }>
					{ __(
						'What these checks cover, and what they cannot',
						'wowstudio-accessibility-kit'
					) }
				</Button>
			</p>
		</div>
	);
}
