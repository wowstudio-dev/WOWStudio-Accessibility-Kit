/**
 * Loading, empty, and error states.
 *
 * Every screen needs all three. A screen that only handles the happy path
 * leaves people staring at nothing, guessing whether it is broken or slow.
 */

import { Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * A placeholder shown while something loads.
 *
 * @param {Object} props       Component props.
 * @param {string} props.label What is being waited for.
 * @param {number} props.rows  How many placeholder rows to draw.
 * @return {Element} The skeleton.
 */
export function Skeleton( { label, rows = 3 } ) {
	return (
		<div className="wsak-skeleton" aria-busy="true">
			<p className="screen-reader-text" role="status">
				{ label || __( 'Loading…', 'wowstudio-accessibility-kit' ) }
			</p>
			{ Array.from( { length: rows } ).map( ( _, index ) => (
				<div
					className="wsak-skeleton__row"
					key={ index }
					aria-hidden="true"
				/>
			) ) }
		</div>
	);
}

/**
 * Shown when a request fails, with a way forward.
 *
 * @param {Object}   props         Component props.
 * @param {string}   props.message What went wrong.
 * @param {Function} props.onRetry Retry handler, if retrying makes sense.
 * @return {Element} The notice.
 */
export function ErrorState( { message, onRetry } ) {
	return (
		<Notice status="error" isDismissible={ false } className="wsak-error">
			<p>{ message }</p>
			{ onRetry && (
				<Button variant="secondary" onClick={ onRetry }>
					{ __( 'Try again', 'wowstudio-accessibility-kit' ) }
				</Button>
			) }
		</Notice>
	);
}

/**
 * Shown when there is genuinely nothing to display.
 *
 * @param {Object}  props          Component props.
 * @param {string}  props.title    Heading.
 * @param {string}  props.body     Explanation.
 * @param {Element} props.children Optional action.
 * @return {Element} The empty state.
 */
export function EmptyState( { title, body, children } ) {
	return (
		<div className="wsak-empty">
			<h3 className="wsak-empty__title">{ title }</h3>
			<p className="wsak-empty__body">{ body }</p>
			{ children }
		</div>
	);
}

/**
 * A small inline busy indicator with an accessible label.
 *
 * @param {Object} props       Component props.
 * @param {string} props.label What is happening.
 * @return {Element} The indicator.
 */
export function Busy( { label } ) {
	return (
		<span className="wsak-busy">
			<Spinner />
			<span role="status">{ label }</span>
		</span>
	);
}
