/**
 * The same markup, wherever it appears, decided once.
 */

import { Button, Notice, TextareaControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { ignoreMarkup, readableError } from '../api';
import { EmptyState } from './states';

/**
 * The pages a group reaches, named rather than counted.
 *
 * Behind a disclosure, but the count is outside it. A decision covering
 * thirty-seven pages should say so before it is taken; "37" is not something
 * anybody can check, so the list is one keystroke away rather than absent.
 *
 * The finding count is here too. Eight identical buttons on one page is a group
 * worth deciding once, and a summary that said only "on 1 page" would make it
 * look like a group of one.
 *
 * @param {Object} props           Component props.
 * @param {Array}  props.pages     Pages carrying this markup.
 * @param {number} props.instances How many findings the group holds.
 * @return {Element} The list.
 */
function PageList( { pages, instances } ) {
	return (
		<details className="wsak-markup__pages">
			<summary>
				{ sprintf(
					/* translators: 1: how many findings this group holds. 2: how many pages carry the markup. */
					__( '%1$s across %2$s', 'wowstudio-accessibility-kit' ),
					sprintf(
						/* translators: %d: number of findings. */
						_n(
							'%d finding',
							'%d findings',
							instances,
							'wowstudio-accessibility-kit'
						),
						instances
					),
					sprintf(
						/* translators: %d: how many pages. */
						_n(
							'%d page',
							'%d pages',
							pages.length,
							'wowstudio-accessibility-kit'
						),
						pages.length
					)
				) }
			</summary>
			<ul className="wsak-markup__page-list">
				{ pages.map( ( page ) => (
					<li key={ page.id }>
						{ page.edit_link ? (
							<a href={ page.edit_link }>{ page.title }</a>
						) : (
							page.title
						) }
					</li>
				) ) }
			</ul>
		</details>
	);
}

/**
 * Setting one piece of markup aside everywhere it appears.
 *
 * More careful than the per-page version rather than less, because it retires a
 * finding on pages nobody has opened. The confirmation names the count and the
 * reason cannot be skipped.
 *
 * Withdrawing one is not offered here, because there is nothing here to offer
 * it on: once taken, the findings it covers are set aside and the group leaves
 * this list, which only ever shows what is still open. The decision log is
 * where site-wide decisions are listed and undone.
 *
 * @param {Object}   props           Component props.
 * @param {Object}   props.group     The markup group.
 * @param {Function} props.onChange  Called when the decision changes.
 * @param {boolean}  props.mayDecide Whether this person may decide site-wide.
 * @return {Element} The action.
 */
function DecideEverywhere( { group, onChange, mayDecide } ) {
	const [ open, setOpen ] = useState( false );
	const [ note, setNote ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	const pages = group.pages?.length ?? 0;

	if ( ! mayDecide ) {
		return (
			<p className="wsak-markup__cannot">
				{ __(
					'Marking this a false positive everywhere needs permission to edit other people’s content. You can still do it one page at a time, from the list of every instance.',
					'wowstudio-accessibility-kit'
				) }
			</p>
		);
	}

	if ( ! open ) {
		return (
			<Button
				variant="link"
				className="wsak-dismiss__open"
				onClick={ () => setOpen( true ) }
			>
				{ __(
					'Add to false positives, everywhere',
					'wowstudio-accessibility-kit'
				) }
			</Button>
		);
	}

	return (
		<div className="wsak-dismiss">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ /*
			 * What is about to happen, in the sentence above the button that
			 * does it. The pages are listed a few lines up; this is the part
			 * that says the decision reaches all of them, including the ones
			 * nobody has opened.
			 */ }
			<Notice status="warning" isDismissible={ false }>
				{ sprintf(
					/* translators: %d: how many pages the decision covers. */
					_n(
						'This closes the finding on %d page, including pages you have not opened.',
						'This closes the finding on %d pages, including pages you have not opened.',
						pages,
						'wowstudio-accessibility-kit'
					),
					pages
				) }
			</Notice>

			<TextareaControl
				__nextHasNoMarginBottom
				label={ __(
					'Why is this a false positive everywhere it appears?',
					'wowstudio-accessibility-kit'
				) }
				help={ __(
					'Required at this scope, not optional. A decision about one page is your shortcut; a decision about the whole site is a claim somebody else will inherit, and the reason is what lets them check it.',
					'wowstudio-accessibility-kit'
				) }
				value={ note }
				rows={ 3 }
				onChange={ setNote }
			/>

			<div className="wsak-dismiss__actions">
				<Button
					variant="secondary"
					disabled={ busy }
					onClick={ () => {
						setBusy( true );
						setError( '' );

						ignoreMarkup( group.fingerprint, group.rule_id, note )
							.then( ( data ) => {
								setOpen( false );
								setNote( '' );
								onChange( data );
							} )
							.catch( ( caught ) =>
								setError( readableError( caught ) )
							)
							.finally( () => setBusy( false ) );
					} }
				>
					{ __(
						'Add to false positives everywhere',
						'wowstudio-accessibility-kit'
					) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ () => {
						setOpen( false );
						setError( '' );
					} }
				>
					{ __( 'Cancel', 'wowstudio-accessibility-kit' ) }
				</Button>
			</div>
		</div>
	);
}

/**
 * One distinct piece of markup, and everywhere it appears.
 *
 * @param {Object}   props           Component props.
 * @param {Object}   props.group     The group.
 * @param {Function} props.onChange  Called when its decision changes.
 * @param {boolean}  props.mayDecide Whether this person may decide site-wide.
 * @return {Element} The card.
 */
function GroupCard( { group, onChange, mayDecide } ) {
	const sample = group.sample ?? {};

	return (
		<li className="wsak-markup">
			<p className="wsak-markup__message">{ sample.message }</p>

			{ sample.context && (
				<pre
					className="wsak-issue__context"
					tabIndex="0"
					role="group"
					aria-label={ sprintf(
						/* translators: %s: name of the accessibility check. */
						__( 'Markup for: %s', 'wowstudio-accessibility-kit' ),
						sample.rule_title ?? group.rule_id
					) }
				>
					<code>{ sample.context }</code>
				</pre>
			) }

			{ group.pages?.length > 0 && (
				<PageList pages={ group.pages } instances={ group.instances } />
			) }

			<DecideEverywhere
				group={ group }
				onChange={ onChange }
				mayDecide={ mayDecide }
			/>
		</li>
	);
}

/**
 * The findings of one check, collapsed to the distinct markup behind them.
 *
 * A theme prints its social icons into every footer, so the same fault arrives
 * once per page and asks to be judged once per page. After the fortieth
 * identical decision people stop reading findings and start clearing them,
 * which is the point at which the list has taught somebody to ignore it.
 *
 * @param {Object}   props           Component props.
 * @param {Array}    props.groups    The groups.
 * @param {boolean}  props.mayDecide Whether this person may decide site-wide.
 * @param {Function} props.onChange  Called when any decision changes.
 * @return {Element} The list.
 */
export default function MarkupGroups( { groups, mayDecide, onChange } ) {
	if ( ! groups.length ) {
		return (
			<EmptyState
				title={ __(
					'Nothing open here',
					'wowstudio-accessibility-kit'
				) }
				body={ __(
					'Everything this check found has been fixed or marked a false positive.',
					'wowstudio-accessibility-kit'
				) }
			/>
		);
	}

	return (
		<ul className="wsak-markups">
			{ groups.map( ( group ) => (
				<GroupCard
					key={ group.fingerprint }
					group={ group }
					mayDecide={ mayDecide }
					onChange={ onChange }
				/>
			) ) }
		</ul>
	);
}
