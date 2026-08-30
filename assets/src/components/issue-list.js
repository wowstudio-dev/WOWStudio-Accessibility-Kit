/**
 * The findings, split by whether a machine settled them.
 */

import { __, _n, sprintf } from '@wordpress/i18n';

import AltTextAction from './alt-text-action';
import FixAction from './fix-action';
import { DetectionTag, SeverityTag } from './tags';
import { EmptyState } from './states';

/**
 * One finding.
 *
 * How to fix it is behind a disclosure rather than always open: a page with
 * thirty findings would otherwise be a wall of text nobody reads.
 *
 * @param {Object} props        Component props.
 * @param {Object} props.issue  The finding.
 * @param {number} props.postId Page the finding is on.
 * @return {Element} The card.
 */
function IssueCard( { issue, postId } ) {
	return (
		<li className="wsak-issue">
			<div className="wsak-issue__head">
				<h4 className="wsak-issue__title">{ issue.rule_title }</h4>
				<div className="wsak-issue__tags">
					<SeverityTag
						severity={ issue.severity }
						label={ issue.severity_label }
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

			<p className="wsak-issue__message">{ issue.message }</p>

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

			{ 'img-alt-missing' === issue.rule_id ? (
				<AltTextAction
					attachmentId={ issue.attachment_id }
					postId={ postId }
				/>
			) : (
				issue.detection === 'auto' && <FixAction issueId={ issue.id } />
			) }

			{ issue.how_to_fix && (
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
		</li>
	);
}

/**
 * One group of findings, with a heading that says what the group means.
 *
 * @param {Object} props        Component props.
 * @param {string} props.id     Heading id, for aria-labelledby.
 * @param {string} props.title  Group heading.
 * @param {string} props.blurb  What this group means.
 * @param {Array}  props.issues Findings in the group.
 * @param {number} props.postId Page the findings are on.
 * @return {?Element} The group, or nothing when empty.
 */
function IssueGroup( { id, title, blurb, issues, postId } ) {
	if ( ! issues.length ) {
		return null;
	}

	return (
		<section className="wsak-group" aria-labelledby={ id }>
			<h3 className="wsak-group__title" id={ id }>
				{ title }{ ' ' }
				<span className="wsak-group__count">
					{ sprintf(
						/* translators: %d: number of findings in this group. */
						_n(
							'(%d)',
							'(%d)',
							issues.length,
							'wowstudio-accessibility-kit'
						),
						issues.length
					) }
				</span>
			</h3>
			<p className="wsak-group__blurb">{ blurb }</p>
			<ul className="wsak-issues">
				{ issues.map( ( issue ) => (
					<IssueCard
						issue={ issue }
						postId={ postId }
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
 * @param {Object} props        Component props.
 * @param {Array}  props.issues Findings.
 * @param {number} props.postId Page the findings are on.
 * @return {Element} The list.
 */
export default function IssueList( { issues, postId } ) {
	const auto = issues.filter( ( issue ) => issue.detection === 'auto' );
	const manual = issues.filter( ( issue ) => issue.detection !== 'auto' );

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
			<IssueGroup
				id="wsak-group-auto"
				title={
					<>
						<DetectionTag detection="auto" />{ ' ' }
						{ __(
							'Detected automatically',
							'wowstudio-accessibility-kit'
						) }
					</>
				}
				blurb={ __(
					'These are failures the scanner could settle on its own from the page markup.',
					'wowstudio-accessibility-kit'
				) }
				issues={ auto }
				postId={ postId }
			/>

			<IssueGroup
				id="wsak-group-manual"
				title={
					<>
						<DetectionTag detection="manual" />{ ' ' }
						{ __(
							'Needs a person to check',
							'wowstudio-accessibility-kit'
						) }
					</>
				}
				blurb={ __(
					'The scanner spotted something worth a look but cannot decide it alone. These may turn out to be perfectly fine.',
					'wowstudio-accessibility-kit'
				) }
				issues={ manual }
				postId={ postId }
			/>
		</div>
	);
}
