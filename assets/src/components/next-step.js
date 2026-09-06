/**
 * The one thing worth doing next.
 */

import { Button } from '@wordpress/components';

/**
 * A single suggested action, or nothing at all.
 *
 * Deliberately one step rather than a checklist. A list with ticks turns a tool
 * into homework, and its unfinished items sit there accusing somebody for
 * months. And when the server has nothing worth suggesting this renders
 * nothing: a panel that always has something to say becomes furniture within a
 * week, and then it is ignored on the day it matters.
 *
 * @param {Object}   props        Component props.
 * @param {Object}   [props.step] The step, or nothing.
 * @param {Function} props.onGo   Opens the screen the step points at.
 * @return {Element|null} The panel, or nothing.
 */
export default function NextStep( { step, onGo } ) {
	if ( ! step ) {
		return null;
	}

	return (
		<section className="wsak-next" aria-labelledby="wsak-next-title">
			<h3 id="wsak-next-title" className="wsak-next__title">
				{ step.title }
			</h3>

			<p className="wsak-next__body">{ step.body }</p>

			<Button variant="primary" onClick={ () => onGo( step.view ) }>
				{ step.label }
			</Button>
		</section>
	);
}
