/**
 * Colour maths and background resolution for the browser pass.
 *
 * The arithmetic here is the easy half and is fixed by the specification. The
 * hard half — and the reason this file exists rather than a dependency — is
 * deciding when we genuinely cannot know what colour is behind some text.
 *
 * A tool that guesses at that produces confident, wrong answers: it reports a
 * pass on text sitting over a photograph, and the person trusting it ships an
 * unreadable page. Every function below that cannot determine an answer returns
 * one that says so, and the caller is expected to surface that as work for a
 * person rather than quietly rounding it to a pass.
 */

/**
 * The contrast ratio WCAG 1.4.3 requires of normal-size text.
 */
export const AA_NORMAL = 4.5;

/**
 * The ratio required of large text, and of non-text visual information.
 */
export const AA_LARGE = 3;

/**
 * Parses a CSS colour into RGBA.
 *
 * Only handles the forms getComputedStyle actually returns — rgb() and rgba().
 * Anything else is a colour we did not expect and must not guess at.
 *
 * @param {string} value Computed colour value.
 * @return {?{r: number, g: number, b: number, a: number}} The colour, or null if unparseable.
 */
export function parseColour( value ) {
	if ( typeof value !== 'string' ) {
		return null;
	}

	const match = value
		.trim()
		.match(
			/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:[\s,/]+([\d.]+%?))?\s*\)$/i
		);

	if ( ! match ) {
		return null;
	}

	let alpha = 1;

	if ( match[ 4 ] !== undefined ) {
		alpha = match[ 4 ].endsWith( '%' )
			? parseFloat( match[ 4 ] ) / 100
			: parseFloat( match[ 4 ] );
	}

	return {
		r: parseFloat( match[ 1 ] ),
		g: parseFloat( match[ 2 ] ),
		b: parseFloat( match[ 3 ] ),
		a: Number.isFinite( alpha ) ? alpha : 1,
	};
}

/**
 * Composites a colour over an opaque backdrop.
 *
 * @param {Object} top    Foreground colour, possibly translucent.
 * @param {Object} bottom Opaque colour behind it.
 * @return {Object} The resulting opaque colour.
 */
export function composite( top, bottom ) {
	const a = top.a;

	return {
		r: top.r * a + bottom.r * ( 1 - a ),
		g: top.g * a + bottom.g * ( 1 - a ),
		b: top.b * a + bottom.b * ( 1 - a ),
		a: 1,
	};
}

/**
 * Relative luminance, exactly as WCAG defines it.
 *
 * @param {Object} colour An opaque RGB colour.
 * @return {number} Luminance between 0 and 1.
 */
export function luminance( colour ) {
	const channel = ( value ) => {
		const c = value / 255;

		return c <= 0.03928
			? c / 12.92
			: Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
	};

	return (
		0.2126 * channel( colour.r ) +
		0.7152 * channel( colour.g ) +
		0.0722 * channel( colour.b )
	);
}

/**
 * Contrast ratio between two opaque colours.
 *
 * @param {Object} one The first colour.
 * @param {Object} two The second colour.
 * @return {number} Ratio between 1 and 21.
 */
export function contrastRatio( one, two ) {
	const a = luminance( one );
	const b = luminance( two );
	const lighter = Math.max( a, b );
	const darker = Math.min( a, b );

	return ( lighter + 0.05 ) / ( darker + 0.05 );
}

/**
 * Reports whether text at this size and weight counts as large.
 *
 * WCAG's threshold is 18pt, or 14pt bold, which in CSS pixels at the default
 * 96dpi mapping is 24px and 18.66px.
 *
 * @param {number} fontSizePx Computed font size in pixels.
 * @param {number} weight     Computed numeric font weight.
 * @return {boolean} True when the large-text threshold applies.
 */
export function isLargeText( fontSizePx, weight ) {
	return fontSizePx >= 24 || ( weight >= 700 && fontSizePx >= 18.66 );
}

/**
 * Works out what colour is actually behind an element.
 *
 * Walks up the ancestor chain compositing translucent layers until it reaches
 * something opaque. Returns a reason instead of a colour whenever the honest
 * answer is that we cannot tell.
 *
 * The uncertain cases are the point of this function, so they are listed
 * explicitly rather than left to fall through:
 *
 * - A background image or gradient anywhere in the chain. We cannot sample the
 *   pixels behind the text, so any number we produced would be invented.
 * - Opacity below 1 on the element or any ancestor, which blends the text
 *   itself with whatever is behind in a way this walk does not model.
 * - A colour in a form we could not parse, which means the browser returned
 *   something we did not anticipate.
 *
 * @param {Element} element The element whose backdrop is wanted.
 * @param {Window}  view    The window to read computed style from.
 * @return {{colour: ?Object, uncertain: boolean, reason: string}} The backdrop.
 */
export function effectiveBackground( element, view ) {
	const layers = [];
	let node = element;

	while ( node && node.nodeType === 1 ) {
		const style = view.getComputedStyle( node );

		if ( style.backgroundImage && style.backgroundImage !== 'none' ) {
			return {
				colour: null,
				uncertain: true,
				reason: 'background-image',
			};
		}

		const opacity = parseFloat( style.opacity );

		if ( Number.isFinite( opacity ) && opacity < 1 ) {
			return { colour: null, uncertain: true, reason: 'opacity' };
		}

		const background = parseColour( style.backgroundColor );

		if ( background === null && style.backgroundColor ) {
			return {
				colour: null,
				uncertain: true,
				reason: 'unreadable-colour',
			};
		}

		if ( background && background.a > 0 ) {
			if ( background.a >= 1 ) {
				// Opaque: this is the backdrop. Composite everything we
				// collected on the way down onto it.
				return {
					colour: layers.reduceRight(
						( under, over ) => composite( over, under ),
						background
					),
					uncertain: false,
					reason: '',
				};
			}

			layers.push( background );
		}

		node = node.parentElement;
	}

	// Nothing in the chain was opaque. The canvas underneath is white in every
	// browser, so that is a fact rather than a guess — but only once we know no
	// image was involved, which the loop above has already established.
	return {
		colour: layers.reduceRight(
			( under, over ) => composite( over, under ),
			{ r: 255, g: 255, b: 255, a: 1 }
		),
		uncertain: false,
		reason: '',
	};
}

/**
 * Measures the contrast of one element's text against its backdrop.
 *
 * @param {Element} element The element carrying the text.
 * @param {Window}  view    The window to read computed style from.
 * @return {{ratio: ?number, required: number, passes: ?boolean, uncertain: boolean, reason: string}} The measurement.
 */
export function measureContrast( element, view ) {
	const style = view.getComputedStyle( element );
	const foreground = parseColour( style.color );

	if ( ! foreground ) {
		return {
			ratio: null,
			required: AA_NORMAL,
			passes: null,
			uncertain: true,
			reason: 'unreadable-colour',
		};
	}

	const size = parseFloat( style.fontSize );
	const weight = parseInt( style.fontWeight, 10 ) || 400;
	const required = isLargeText( size, weight ) ? AA_LARGE : AA_NORMAL;

	const backdrop = effectiveBackground( element, view );

	if ( backdrop.uncertain || ! backdrop.colour ) {
		return {
			ratio: null,
			required,
			passes: null,
			uncertain: true,
			reason: backdrop.reason,
		};
	}

	// Translucent text is composited over its own backdrop before measuring,
	// which is what the eye actually sees.
	const resolved =
		foreground.a < 1
			? composite( foreground, backdrop.colour )
			: foreground;

	const ratio = contrastRatio( resolved, backdrop.colour );

	return {
		ratio,
		required,
		passes: ratio >= required,
		uncertain: false,
		reason: '',
	};
}
