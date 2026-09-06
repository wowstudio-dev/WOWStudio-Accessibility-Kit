/**
 * One number from the overview, opened.
 */

import { Button, Notice } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { fetchIssues, readableError } from '../api';
import IssueList from './issue-list';
import { DetectionTag, SeverityTag } from './tags';
import { EmptyState, ErrorState, Skeleton } from './states';

/**
 * What the check is, who it shuts out, and what to do about it.
 *
 * At the top of the list rather than against every row. Somebody working
 * through forty-one instances of one fault has to understand it once, and
 * repeating the explanation forty-one times is how a list stops being read.
 *
 * The consequence comes first. "Ambiguous anchor text" describes what a checker
 * noticed; "a screen reader reads this as read more and nothing else" describes
 * what happens to somebody, and only one of those tells you why to bother.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.rule The check, as the server describes it.
 * @return {Element} The explanation.
 */
function RuleBrief( { rule } ) {
	return (
		<section
			className="wsak-drilldown__brief"
			aria-labelledby="wsak-drilldown-brief"
		>
			<h3
				id="wsak-drilldown-brief"
				className="wsak-drilldown__brief-title"
			>
				{ sprintf(
					/* translators: %s: the check's name. */
					__( 'About %s', 'wowstudio-accessibility-kit' ),
					rule.title
				) }
			</h3>

			{ rule.consequence && (
				<p className="wsak-drilldown__consequence">
					{ rule.consequence }
				</p>
			) }

			{ rule.description && (
				<p className="wsak-drilldown__how">{ rule.description }</p>
			) }

			<p className="wsak-drilldown__tags">
				<SeverityTag
					severity={ rule.severity }
					label={ rule.severity_label }
				/>
				<DetectionTag
					detection={ rule.detection }
					label={ rule.detection_label }
				/>
				{ rule.wcag_sc && (
					<span className="wsak-drilldown__sc">
						{ sprintf(
							/* translators: %s: a WCAG success criterion number, e.g. 1.1.1. */
							__(
								'WCAG success criterion %s',
								'wowstudio-accessibility-kit'
							),
							rule.wcag_sc
						) }
					</span>
				) }
			</p>
		</section>
	);
}

/**
 * Where a page-scoped list says which page, and how to get to it.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.page The page, as the server describes it.
 * @return {Element} The links.
 */
function PageBrief( { page } ) {
	return (
		<p className="wsak-drilldown__page">
			{ page.edit_link && (
				<Button variant="secondary" href={ page.edit_link }>
					{ __( 'Edit this page', 'wowstudio-accessibility-kit' ) }
				</Button>
			) }
			{ page.view_link && (
				<Button variant="link" href={ page.view_link }>
					{ __( 'View this page', 'wowstudio-accessibility-kit' ) }
				</Button>
			) }
		</p>
	);
}

/**
 * The findings behind one figure on the overview.
 *
 * @param {Object}   props        Component props.
 * @param {Object}   props.filter What was clicked: { rule } or { post }.
 * @param {Function} props.onBack Returns to the overview.
 * @param {Function} [props.onGo] Opens another screen.
 * @return {Element} The screen.
 */
export default function IssueDrilldown( { filter, onBack, onGo } ) {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	const heading = useRef( null );

	const rule = filter?.rule ?? '';
	const post = filter?.post ?? 0;

	useEffect( () => {
		let live = true;

		setLoading( true );
		setError( '' );

		fetchIssues( { rule, post } )
			.then( ( result ) => {
				if ( live ) {
					setData( result );
					setLoading( false );
				}
			} )
			.catch( ( caught ) => {
				if ( live ) {
					setError( readableError( caught ) );
					setLoading( false );
				}
			} );

		return () => {
			live = false;
		};
	}, [ rule, post ] );

	// Somebody who just activated a bar is sitting on a control that has been
	// replaced by a different screen. Without moving focus, a keyboard or
	// screen-reader user is left where the old page was and told nothing.
	useEffect( () => {
		if ( ! loading && heading.current ) {
			heading.current.focus();
		}
	}, [ loading ] );

	const back = (
		<p className="wsak-drilldown__back">
			<Button variant="link" onClick={ onBack }>
				{ __( '← Back to the report', 'wowstudio-accessibility-kit' ) }
			</Button>
		</p>
	);

	if ( loading ) {
		return (
			<div className="wsak-drilldown">
				{ back }
				<Skeleton
					label={ __(
						'Loading these findings…',
						'wowstudio-accessibility-kit'
					) }
				/>
			</div>
		);
	}

	if ( error ) {
		return (
			<div className="wsak-drilldown">
				{ back }
				<ErrorState message={ error } />
			</div>
		);
	}

	const issues = data?.issues ?? [];
	const total = data?.total ?? 0;
	const title = data?.rule?.title || data?.page?.title || '';

	return (
		<div className="wsak-drilldown">
			{ back }

			<h2
				className="wsak-drilldown__title"
				ref={ heading }
				tabIndex={ -1 }
			>
				{ title }
			</h2>

			<p className="wsak-drilldown__count">
				{ sprintf(
					/* translators: %d: how many findings are open. */
					_n(
						'%d open finding',
						'%d open findings',
						total,
						'wowstudio-accessibility-kit'
					),
					total
				) }
			</p>

			{ data?.rule && <RuleBrief rule={ data.rule } /> }
			{ data?.page && <PageBrief page={ data.page } /> }

			{ /*
			 * Said plainly rather than by silently truncating. A list that shows
			 * fifty of two hundred and looks complete is worse than one that
			 * admits what it is holding back.
			 */ }
			{ total > issues.length && (
				<Notice status="info" isDismissible={ false }>
					{ sprintf(
						/* translators: 1: how many are shown. 2: how many there are. */
						__(
							'Showing the first %1$d of %2$d. Work through these and the rest will follow.',
							'wowstudio-accessibility-kit'
						),
						issues.length,
						total
					) }
				</Notice>
			) }

			{ issues.length > 0 ? (
				<IssueList
					issues={ issues }
					onGo={ onGo }
					ruleIsStated={ Boolean( data?.rule ) }
				/>
			) : (
				<EmptyState
					title={ __(
						'Nothing open here',
						'wowstudio-accessibility-kit'
					) }
					body={ __(
						'Everything this filter covers has been fixed or set aside. If that is a surprise, the pages behind it may not have been scanned since they changed.',
						'wowstudio-accessibility-kit'
					) }
				/>
			) }
		</div>
	);
}
