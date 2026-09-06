/**
 * Charts, drawn so they are not the only way to read the number.
 *
 * Hand-rolled SVG rather than a charting library, for three reasons that all
 * point the same way. The canvas-based libraries draw pixels a screen reader
 * cannot see, which is not a defensible thing to ship inside an accessibility
 * plugin. The SVG-based ones are heavy enough to notice in a WordPress.org
 * download, for charts this simple. And every one of them wants to own the
 * animation, which has to be surrendered to `prefers-reduced-motion` here.
 *
 * So each chart below is an `img` to assistive technology, carrying a sentence
 * that says what it shows, and every one is followed by the same figures as
 * text. The drawing is the decoration; the text is the content.
 */

import { __, sprintf } from '@wordpress/i18n';

/**
 * Text that only assistive technology reads.
 *
 * @param {Object} props          Component props.
 * @param {*}      props.children What to announce.
 * @return {Element} The hidden text.
 */
function ScreenReaderText( { children } ) {
	return <span className="screen-reader-text">{ children }</span>;
}

/**
 * Severities, in the order they should be read.
 *
 * Ordered by how much each matters rather than by how many there are. A chart
 * that reorders itself as counts change is one you have to re-read every time,
 * and these four have a fixed meaning that the ordering should carry.
 */
const SEVERITIES = [
	[ 'critical', __( 'Critical', 'wowstudio-accessibility-kit' ) ],
	[ 'serious', __( 'Serious', 'wowstudio-accessibility-kit' ) ],
	[ 'moderate', __( 'Moderate', 'wowstudio-accessibility-kit' ) ],
	[ 'minor', __( 'Minor', 'wowstudio-accessibility-kit' ) ],
];

/**
 * Turns severity counts into ordered, labelled, toned rows.
 *
 * Accepts either shape the server sends: the overview groups them as a list of
 * { key, count }, a single scan reports them as an object keyed by severity.
 * Normalising here keeps that difference out of both screens.
 *
 * @param {Array|Object} counts Severity counts in either shape.
 * @return {Array} Rows ready for BarList, empty ones dropped.
 */
export function severityRows( counts ) {
	const found = new Map(
		Array.isArray( counts )
			? counts.map( ( row ) => [ row.key, row.count ] )
			: Object.entries( counts || {} )
	);

	return SEVERITIES.map( ( [ key, label ] ) => ( {
		key,
		label,
		tone: key,
		count: found.get( key ) || 0,
	} ) ).filter( ( row ) => row.count > 0 );
}

/**
 * A ring showing one number out of a hundred.
 *
 * The ring is drawn from a single circle with a dash pattern rather than an arc
 * path, because a dash offset animates cleanly and an arc does not.
 *
 * @param {Object} props           Component props.
 * @param {number} props.value     The number to show.
 * @param {string} props.label     What the number is.
 * @param {string} [props.caption] A line under the number.
 * @return {Element} The ring.
 */
export function Donut( { value, label, caption } ) {
	const clamped = Math.max( 0, Math.min( 100, Number( value ) || 0 ) );
	const radius = 52;
	const circumference = 2 * Math.PI * radius;
	const filled = ( clamped / 100 ) * circumference;

	return (
		<div className="wsak-donut">
			<svg
				className="wsak-donut__svg"
				viewBox="0 0 120 120"
				role="img"
				aria-label={ sprintf(
					/* translators: 1: what the number measures. 2: the number, out of 100. */
					__(
						'%1$s: %2$d out of 100.',
						'wowstudio-accessibility-kit'
					),
					label,
					clamped
				) }
			>
				<circle
					className="wsak-donut__track"
					cx="60"
					cy="60"
					r={ radius }
					fill="none"
					strokeWidth="12"
				/>
				{ /*
				 * Zero draws nothing at all. A round cap on a zero-length arc
				 * still paints a dot, which reads as a small amount of
				 * something rather than as none of it.
				 */ }
				{ clamped > 0 && (
					<circle
						className="wsak-donut__fill"
						cx="60"
						cy="60"
						r={ radius }
						fill="none"
						strokeWidth="12"
						strokeLinecap="round"
						strokeDasharray={ `${ filled } ${ circumference }` }
						// The ring starts at three o'clock without this, which
						// reads as a progress bar that began somewhere arbitrary.
						transform="rotate(-90 60 60)"
						style={ { '--wsak-donut-length': circumference } }
					/>
				) }
			</svg>

			<div className="wsak-donut__centre" aria-hidden="true">
				<span className="wsak-donut__value">{ clamped }</span>
				<span className="wsak-donut__of">
					{ __( '/ 100', 'wowstudio-accessibility-kit' ) }
				</span>
			</div>

			{ caption && <p className="wsak-donut__caption">{ caption }</p> }
		</div>
	);
}

/**
 * The bar itself. Decoration in both modes, so never announced.
 *
 * @param {Object} props         Component props.
 * @param {number} props.count   This row's figure.
 * @param {number} props.largest The biggest figure in the set.
 * @param {string} [props.tone]  Severity colouring, if any.
 * @return {Element} The track and its fill.
 */
function Bar( { count, largest, tone } ) {
	return (
		<span className="wsak-bars__track" aria-hidden="true">
			<span
				className={ `wsak-bars__fill${
					tone ? ` wsak-bars__fill--${ tone }` : ''
				}` }
				style={ {
					'--wsak-bar-width': `${
						( ( count || 0 ) / largest ) * 100
					}%`,
				} }
			/>
		</span>
	);
}

/**
 * Horizontal bars, one per row, each with its own count.
 *
 * Bars are sized against the largest row rather than the total, so a row worth
 * two percent is still wide enough to see and to click.
 *
 * Two modes, and the difference is structural rather than cosmetic. Without
 * `onSelect` the figures are decoration: the list is hidden from assistive
 * technology and one sentence carries the whole set, which reads far better
 * than nine list items each holding a number.
 *
 * With `onSelect` every row becomes a control, and that summary has to go. A
 * focusable button inside an `aria-hidden` subtree is reachable by keyboard and
 * invisible to a screen reader at the same time — the exact fault this plugin
 * exists to find, and one it would be shipping in its own interface. So the
 * interactive list is announced normally, each row naming itself, and the
 * sentence is dropped rather than duplicating what the rows now say.
 *
 * @param {Object}   props            Component props.
 * @param {Array}    props.items      Rows of { key, label, count, tone }.
 * @param {string}   props.label      What the set of bars shows.
 * @param {string}   [props.unit]     What the counts are, for the announcement.
 * @param {Function} [props.onSelect] Opens a row. Given the row's key.
 * @return {Element} The bars.
 */
export function BarList( { items, label, unit, onSelect } ) {
	const rows = Array.isArray( items ) ? items : [];

	if ( ! rows.length ) {
		return (
			<p className="wsak-chart__empty">
				{ __( 'Nothing to show yet.', 'wowstudio-accessibility-kit' ) }
			</p>
		);
	}

	const largest = Math.max( ...rows.map( ( row ) => row.count || 0 ), 1 );
	const noun = unit || __( 'findings', 'wowstudio-accessibility-kit' );

	if ( onSelect ) {
		return (
			<div className="wsak-bars wsak-bars--interactive">
				<ul className="wsak-bars__list" aria-label={ label }>
					{ rows.map( ( row ) => (
						<li className="wsak-bars__row" key={ row.key }>
							<button
								type="button"
								className="wsak-bars__button"
								onClick={ () => onSelect( row.key ) }
							>
								<span className="wsak-bars__label">
									{ row.label }
								</span>

								<Bar
									count={ row.count }
									largest={ largest }
									tone={ row.tone }
								/>

								<span className="wsak-bars__count">
									{ row.count }
								</span>

								{ /*
								 * The unit sits inside the button as text rather
								 * than as an aria-label, so the accessible name
								 * still contains the visible words. Replacing
								 * the name wholesale would break voice control,
								 * which needs the name to match what somebody
								 * can see well enough to say it out loud.
								 *
								 * Nothing is added about what activating it
								 * does. The role already says "button", and
								 * spelling it out again on every row is eight
								 * repetitions of something the first one taught.
								 */ }
								<ScreenReaderText>{ noun }</ScreenReaderText>
							</button>
						</li>
					) ) }
				</ul>
			</div>
		);
	}

	return (
		<div className="wsak-bars">
			<ScreenReaderText>
				{ sprintf(
					/* translators: 1: what the chart shows. 2: the figures, already joined into a sentence. */
					__( '%1$s. %2$s', 'wowstudio-accessibility-kit' ),
					label,
					rows
						.map( ( row ) =>
							sprintf(
								/* translators: 1: row name. 2: how many. 3: what is being counted. */
								__(
									'%1$s: %2$d %3$s.',
									'wowstudio-accessibility-kit'
								),
								row.label,
								row.count,
								noun
							)
						)
						.join( ' ' )
				) }
			</ScreenReaderText>

			<ul className="wsak-bars__list" aria-hidden="true">
				{ rows.map( ( row ) => (
					<li className="wsak-bars__row" key={ row.key }>
						<span className="wsak-bars__label">{ row.label }</span>
						<Bar
							count={ row.count }
							largest={ largest }
							tone={ row.tone }
						/>
						<span className="wsak-bars__count">{ row.count }</span>
					</li>
				) ) }
			</ul>
		</div>
	);
}

/**
 * A line through every score recorded so far.
 *
 * Deliberately unlabelled on the x axis. The plugin does not re-scan on its
 * own, so the gaps between these points are however often somebody pressed the
 * button — spacing them evenly and calling it time would be a chart that lies.
 * It is a sequence of scans, and the caption says so.
 *
 * @param {Object} props        Component props.
 * @param {Array}  props.points Scans of { id, score, at }, oldest first.
 * @return {Element} The line.
 */
export function Sparkline( { points } ) {
	const data = Array.isArray( points ) ? points : [];

	if ( data.length < 2 ) {
		return (
			<p className="wsak-chart__empty">
				{ __(
					'A line appears here once a page has been scanned more than once.',
					'wowstudio-accessibility-kit'
				) }
			</p>
		);
	}

	const width = 320;
	const height = 90;
	const pad = 6;
	const step = ( width - pad * 2 ) / ( data.length - 1 );

	const coords = data.map( ( point, index ) => {
		const score = Math.max( 0, Math.min( 100, point.score || 0 ) );
		return {
			x: pad + index * step,
			y: pad + ( ( 100 - score ) / 100 ) * ( height - pad * 2 ),
		};
	} );

	const line = coords
		.map( ( c, i ) => `${ i === 0 ? 'M' : 'L' }${ c.x } ${ c.y }` )
		.join( ' ' );

	const area = `${ line } L${ coords[ coords.length - 1 ].x } ${
		height - pad
	} L${ coords[ 0 ].x } ${ height - pad } Z`;

	const first = data[ 0 ].score;
	const last = data[ data.length - 1 ].score;

	return (
		<div className="wsak-spark">
			<svg
				className="wsak-spark__svg"
				viewBox={ `0 0 ${ width } ${ height }` }
				preserveAspectRatio="none"
				role="img"
				aria-label={ sprintf(
					/* translators: 1: how many scans. 2: the first score. 3: the most recent score. */
					__(
						'Scores across the last %1$d scans, from %2$d to %3$d out of 100.',
						'wowstudio-accessibility-kit'
					),
					data.length,
					first,
					last
				) }
			>
				<path className="wsak-spark__area" d={ area } />
				<path
					className="wsak-spark__line"
					d={ line }
					fill="none"
					pathLength="1"
				/>
			</svg>
		</div>
	);
}

/**
 * One figure, stated plainly.
 *
 * @param {Object} props        Component props.
 * @param {string} props.label  What the figure is.
 * @param {*}      props.value  The figure.
 * @param {string} [props.note] A qualifier under it.
 * @param {string} [props.tone] Severity tone, when the figure carries one.
 * @return {Element} The tile.
 */
export function Stat( { label, value, note, tone } ) {
	return (
		<div className={ `wsak-stat${ tone ? ` wsak-stat--${ tone }` : '' }` }>
			<span className="wsak-stat__value">{ value }</span>
			<span className="wsak-stat__label">{ label }</span>
			{ note && <span className="wsak-stat__note">{ note }</span> }
		</div>
	);
}
