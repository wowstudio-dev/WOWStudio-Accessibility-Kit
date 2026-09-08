/**
 * The findings, split by whether a machine settled them.
 */

import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { CSS_FIXABLE } from '../scanner/repair';
import AltTextHandoff from './alt-text-handoff';
import DismissAction from './dismiss-action';
import { DetectionTag, SeverityTag } from './tags';
import { EmptyState } from './states';

/**
 * Sends a style-answerable finding to the inspector.
 *
 * These have one correct answer, but the declarations are measured off the live
 * element — its computed colours, its rendered size — and this view has no
 * rendered page to measure. So the finding is handed to the view that does,
 * rather than being given a button here that could not compute anything.
 *
 * @param {Object}   props             Component props.
 * @param {Function} [props.onInspect] Switches to the inspector.
 * @return {Element} The handoff row.
 */
function CssHandoff( { onInspect } ) {
	if ( ! onInspect ) {
		return (
			<p className="wsak-issue__handoff">
				<span className="wsak-issue__handoff-note">
					{ __(
						'This one is answered by a style rule measured off the element itself, so it is fixed in the page view — which needs a preview of this page, and this scan has none.',
						'wowstudio-accessibility-kit'
					) }
				</span>
			</p>
		);
	}

	return (
		<p className="wsak-issue__handoff">
			<Button variant="secondary" onClick={ onInspect }>
				{ __( 'Fix this on the page', 'wowstudio-accessibility-kit' ) }
			</Button>
			<span className="wsak-issue__handoff-note">
				{ __(
					'Answered by a style rule measured off the element itself, so it is fixed in the page view where the element can be measured.',
					'wowstudio-accessibility-kit'
				) }
			</span>
		</p>
	);
}

/**
 * One finding.
 *
 * How to fix it is behind a disclosure rather than always open: a page with
 * thirty findings would otherwise be a wall of text nobody reads.
 *
 * When the whole list is already one check, everything the card would say about
 * that check has just been said once above it. Repeating the name, the tags,
 * the consequence and the fix on all forty-one rows buries the one line that
 * differs — which markup this one is about — and turns the list into the wall
 * of text the disclosure below exists to avoid.
 *
 * @param {Object}   props                Component props.
 * @param {Object}   props.issue          The finding.
 * @param {Function} [props.onInspect]    Switches to the inspector, when there is
 *                                        a preview to switch to.
 * @param {boolean}  [props.ruleIsStated] The rule has already been explained
 *                                        above this list.
 * @param {boolean}  [props.showPage]     The list spans more than one page, so
 *                                        each finding has to say which.
 * @return {Element} The card.
 */
function IssueCard( { issue, onInspect, ruleIsStated, showPage } ) {
	// Worked out before the markup rather than as a chain of conditions inside
	// it, because which action a finding gets is the decision this component
	// exists to make and it should be readable in one place.
	let action = null;

	if ( 'img-alt-missing' === issue.rule_id ) {
		action = <AltTextHandoff attachmentId={ issue.attachment_id } />;
	} else if ( CSS_FIXABLE.includes( issue.rule_id ) ) {
		action = <CssHandoff onInspect={ onInspect } />;
	}

	return (
		<li className="wsak-issue">
			{ ! ruleIsStated && (
				<div className="wsak-issue__head">
					<h4 className="wsak-issue__title">{ issue.rule_title }</h4>
					<div className="wsak-issue__tags">
						<SeverityTag
							severity={ issue.severity }
							label={ issue.severity_label }
						/>
						{ /*
						 * This used to sit on the group heading, when the groups
						 * were "settled" and "needs a person". The groups now say
						 * what a finding asks of you instead — but whether the
						 * scanner decided a thing or merely noticed it is not a
						 * detail we get to drop when the layout changes, so it
						 * moves onto the card.
						 */ }
						<DetectionTag
							detection={ issue.detection }
							label={ issue.detection_label }
						/>
						<span className="wsak-tag wsak-tag--sc">
							{ sprintf(
								/* translators: %s: WCAG success criterion number. */
								__( 'WCAG %s', 'wowstudio-accessibility-kit' ),
								issue.wcag_sc
							) }
						</span>
					</div>
				</div>
			) }

			{ ! ruleIsStated && issue.consequence && (
				<p className="wsak-issue__consequence">{ issue.consequence }</p>
			) }

			<p className="wsak-issue__message">{ issue.message }</p>

			{ /*
			 * What a rule says can be done about it, in its own words. Shown for
			 * the findings that offer no button, because "no fix here" without a
			 * reason reads as the tool giving up rather than as an accurate
			 * statement about where the problem lives.
			 */ }
			{ issue.fix?.summary && ! issue.fix?.reviewable && (
				<p className="wsak-issue__plan">
					<span className="wsak-issue__plan-where">
						{ issue.fix.kind_label }
					</span>{ ' ' }
					{ issue.fix.summary }
				</p>
			) }

			{ issue.context && (
				// Focusable so the markup can be scrolled without a mouse, and
				// named so that landing on it announces what it is rather than
				// reading out anonymous code. A group rather than a region:
				// a page with thirty findings would otherwise add thirty
				// landmarks to the list a screen reader user navigates by.
				<pre
					className="wsak-issue__context"
					tabIndex="0"
					role="group"
					aria-label={ sprintf(
						/* translators: %s: name of the accessibility check. */
						__( 'Markup for: %s', 'wowstudio-accessibility-kit' ),
						issue.rule_title
					) }
				>
					<code>{ issue.context }</code>
				</pre>
			) }

			{ action }

			{ ! ruleIsStated && issue.how_to_fix && (
				<details className="wsak-issue__fix">
					<summary>
						{ __(
							'How to fix this',
							'wowstudio-accessibility-kit'
						) }
					</summary>
					<p>{ issue.how_to_fix }</p>
					{ issue.selector && (
						<p className="wsak-issue__selector">
							<span className="wsak-issue__selector-label">
								{ __(
									'Element:',
									'wowstudio-accessibility-kit'
								) }
							</span>{ ' ' }
							<code>{ issue.selector }</code>
						</p>
					) }
				</details>
			) }

			{ /*
			 * Where this one is, above the path to it inside the page. A list
			 * narrowed to a single check gathers findings from the whole site,
			 * and a row that says what is wrong without saying where leaves the
			 * reader holding an XPath and nothing to apply it to.
			 *
			 * Only when the list spans pages. On a single page's own scan every
			 * row would carry the same line, which is forty copies of something
			 * the heading already said.
			 */ }
			{ showPage && issue.page && (
				<p className="wsak-issue__page">
					<span className="wsak-issue__selector-label">
						{ __( 'Page:', 'wowstudio-accessibility-kit' ) }
					</span>{ ' ' }
					{ issue.page.edit_link ? (
						<a href={ issue.page.edit_link }>
							{ issue.page.title }
						</a>
					) : (
						issue.page.title
					) }
					{ issue.page.view_link && (
						<>
							{ ' · ' }
							<a href={ issue.page.view_link }>
								{ __( 'View', 'wowstudio-accessibility-kit' ) }
							</a>
						</>
					) }
				</p>
			) }

			{ ruleIsStated && issue.selector && (
				<p className="wsak-issue__selector">
					<span className="wsak-issue__selector-label">
						{ __( 'Element:', 'wowstudio-accessibility-kit' ) }
					</span>{ ' ' }
					<code>{ issue.selector }</code>
				</p>
			) }

			{ /*
			 * Offered on everything, including findings with a fix. A suggestion
			 * that turns out to be wrong for this page is exactly the case that
			 * needs setting aside, and hiding the option behind "we could not
			 * help" would put it furthest from where it is most needed.
			 */ }
			<DismissAction issue={ issue } />
		</li>
	);
}

/**
 * The four things a finding can ask of you, in the order they are worked through.
 *
 * Findings used to be split by how the scanner decided them — settled, or
 * needing a person. That is a true and important distinction, and it answers a
 * question about the tool rather than about the reader's afternoon. It stays,
 * on every card, as the honesty tag it always was.
 *
 * What sorts the list now is what each finding asks of you, cheapest first. Not
 * because those matter most — they often matter least — but because a list that
 * opens with something you can finish is a list people finish, and one that
 * opens with "ask your developer" is a list people close.
 */
const BANDS = [
	{
		id: 'now',
		title: __( 'Fix these now', 'wowstudio-accessibility-kit' ),
		blurb: __(
			'There is one correct answer and we already know it. Nothing here is a guess, so nothing here needs checking first.',
			'wowstudio-accessibility-kit'
		),
	},
	{
		id: 'review',
		title: __( 'Read, then apply', 'wowstudio-accessibility-kit' ),
		blurb: __(
			'We can draft these, but a draft is not an answer. Read what it says before you apply it — it was written by a model that cannot see why your page exists.',
			'wowstudio-accessibility-kit'
		),
	},
	{
		id: 'decide',
		title: __( 'Needs a decision from you', 'wowstudio-accessibility-kit' ),
		blurb: __(
			'These depend on what your content means, which is not something any tool can work out for you. Each one explains what to weigh up.',
			'wowstudio-accessibility-kit'
		),
	},
	{
		id: 'delegate',
		title: __(
			'For whoever looks after your theme',
			'wowstudio-accessibility-kit'
		),
		blurb: __(
			'These are in your theme rather than your content, so nothing here can reach them. Each one comes with what to change and where.',
			'wowstudio-accessibility-kit'
		),
	},
];

/**
 * One group of findings, with a heading that says what the group means.
 *
 * @param {Object}   props                Component props.
 * @param {string}   props.id             Heading id, for aria-labelledby.
 * @param {string}   props.title          Group heading.
 * @param {string}   props.blurb          What this group means.
 * @param {Array}    props.issues         Findings in the group.
 * @param {Function} [props.onInspect]    Switches to the inspector.
 * @param {boolean}  [props.ruleIsStated] The rule is already explained above.
 * @param {boolean}  [props.showPage]     Each finding says which page it is on.
 * @return {?Element} The group, or nothing when empty.
 */
function IssueGroup( {
	id,
	title,
	blurb,
	issues,
	onInspect,
	ruleIsStated,
	showPage,
} ) {
	if ( ! issues.length ) {
		return null;
	}

	return (
		<section className="wsak-group" aria-labelledby={ id }>
			<h3 className="wsak-group__title" id={ id }>
				{ title }{ ' ' }
				<span className="wsak-group__count">
					{ sprintf(
						/* translators: %d: number of items in this group. */
						__( '(%d)', 'wowstudio-accessibility-kit' ),
						issues.length
					) }
				</span>
			</h3>
			<p className="wsak-group__blurb">{ blurb }</p>
			<ul className="wsak-issues">
				{ issues.map( ( issue ) => (
					<IssueCard
						issue={ issue }
						onInspect={ onInspect }
						ruleIsStated={ ruleIsStated }
						showPage={ showPage }
						key={ issue.id }
					/>
				) ) }
			</ul>
		</section>
	);
}

/**
 * The full findings list.
 *
 * Split into the two honesty groups rather than one flat list, because the
 * difference between "this is wrong" and "somebody needs to look at this" is
 * the difference between a tool that helps and a tool that misleads.
 *
 * @param {Object}   props                Component props.
 * @param {Array}    props.issues         Findings.
 * @param {Function} [props.onInspect]    Switches to the inspector.
 * @param {Function} [props.onGo]         Opens another screen.
 * @param {boolean}  [props.showPage]     The list spans more than one page.
 * @param {boolean}  [props.ruleIsStated] Every finding here is the same check,
 *                                        and it has been explained above.
 * @return {Element} The list.
 */
export default function IssueList( {
	issues,
	onInspect,
	onGo,
	ruleIsStated,
	showPage,
} ) {
	// The server has already ordered these and told each one which band it is
	// in. Re-deriving that here would mean two answers to the same question,
	// and the one on this side would be the one that drifts.
	const bands = BANDS.map( ( band ) => ( {
		...band,
		issues: issues.filter( ( issue ) => issue.band === band.id ),
	} ) ).filter( ( band ) => band.issues.length > 0 );

	if ( ! issues.length ) {
		return (
			<EmptyState
				title={ __(
					'Nothing found by the automated checks',
					'wowstudio-accessibility-kit'
				) }
				body={ __(
					'None of the checks we can run automatically flagged anything on this page. That is a good sign, and it is not the same as the page being accessible — most of WCAG needs a person. The manual checklists are the next step.',
					'wowstudio-accessibility-kit'
				) }
			/>
		);
	}

	return (
		<div className="wsak-results">
			{ bands.map( ( band ) => (
				<IssueGroup
					key={ band.id }
					id={ `wsak-group-${ band.id }` }
					title={ band.title }
					blurb={ band.blurb }
					issues={ band.issues }
					onInspect={ onInspect }
					ruleIsStated={ ruleIsStated }
					showPage={ showPage }
				/>
			) ) }

			{ /*
			 * At the foot of the findings rather than the top. Somebody who has
			 * just read a list of problems is the person most likely to want to
			 * know what was not looked for — and least likely to have wanted it
			 * before they started.
			 */ }
			{ onGo && (
				<p className="wsak-results__door">
					<Button variant="link" onClick={ () => onGo( 'coverage' ) }>
						{ __(
							'What these checks cover, and what they cannot',
							'wowstudio-accessibility-kit'
						) }
					</Button>
				</p>
			) }
		</div>
	);
}
