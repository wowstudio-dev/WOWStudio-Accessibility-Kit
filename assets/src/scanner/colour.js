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

/**
 * Converts an RGB colour to HSL.
 *
 * Used so a proposed colour can be moved in lightness while its hue and
 * saturation are left exactly as the designer chose them. Fixing contrast by
 * reaching for black would meet the ratio and wreck the design, and a fix
 * somebody reverts because it looks wrong has not fixed anything.
 *
 * @param {Object} colour An opaque RGB colour.
 * @return {{h: number, s: number, l: number}} Hue in degrees, saturation and lightness as fractions.
 */
export function rgbToHsl( colour ) {
	const r = colour.r / 255;
	const g = colour.g / 255;
	const b = colour.b / 255;

	const max = Math.max( r, g, b );
	const min = Math.min( r, g, b );
	const l = ( max + min ) / 2;

	if ( max === min ) {
		return { h: 0, s: 0, l };
	}

	const d = max - min;
	const s = l > 0.5 ? d / ( 2 - max - min ) : d / ( max + min );

	let h;

	if ( max === r ) {
		h = ( g - b ) / d + ( g < b ? 6 : 0 );
	} else if ( max === g ) {
		h = ( b - r ) / d + 2;
	} else {
		h = ( r - g ) / d + 4;
	}

	return { h: h * 60, s, l };
}

/**
 * Converts an HSL colour back to RGB.
 *
 * @param {Object} hsl   The colour in HSL.
 * @param {number} hsl.h Hue in degrees.
 * @param {number} hsl.s Saturation as a fraction.
 * @param {number} hsl.l Lightness as a fraction.
 * @return {{r: number, g: number, b: number, a: number}} The colour.
 */
export function hslToRgb( { h, s, l } ) {
	if ( s === 0 ) {
		const grey = Math.round( l * 255 );

		return { r: grey, g: grey, b: grey, a: 1 };
	}

	const q = l < 0.5 ? l * ( 1 + s ) : l + s - l * s;
	const p = 2 * l - q;

	const channel = ( t ) => {
		let value = t;

		if ( value < 0 ) {
			value += 1;
		}

		if ( value > 1 ) {
			value -= 1;
		}

		if ( value < 1 / 6 ) {
			return p + ( q - p ) * 6 * value;
		}

		if ( value < 1 / 2 ) {
			return q;
		}

		if ( value < 2 / 3 ) {
			return p + ( q - p ) * ( 2 / 3 - value ) * 6;
		}

		return p;
	};

	const hue = ( ( ( h % 360 ) + 360 ) % 360 ) / 360;

	return {
		r: Math.round( channel( hue + 1 / 3 ) * 255 ),
		g: Math.round( channel( hue ) * 255 ),
		b: Math.round( channel( hue - 1 / 3 ) * 255 ),
		a: 1,
	};
}

/**
 * Formats a colour as a six-digit hex string.
 *
 * @param {Object} colour An opaque RGB colour.
 * @return {string} A `#rrggbb` value.
 */
export function toHex( colour ) {
	const pair = ( value ) =>
		Math.max( 0, Math.min( 255, Math.round( value ) ) )
			.toString( 16 )
			.padStart( 2, '0' );

	return `#${ pair( colour.r ) }${ pair( colour.g ) }${ pair( colour.b ) }`;
}

/**
 * How precisely the search below pins down a lightness value.
 *
 * Twenty halvings of the 0–1 range land well inside one step of an eight-bit
 * channel, so the answer is as exact as the colour space allows.
 */
const SEARCH_STEPS = 20;

/**
 * Finds the smallest change to a colour that reaches a contrast ratio.
 *
 * The search moves lightness only, in whichever direction the background allows,
 * and stops at the first value that meets the target — so the result is the
 * closest colour to the original that meets the ratio, rather than a stock
 * dark one. Hue and
 * saturation are untouched, which is what makes the result something a designer
 * will accept rather than immediately undo.
 *
 * At the AA thresholds this is always solvable, which is a more useful fact
 * than it sounds: lightness 0 and 1 are black and white whatever the hue, and
 * the worst background in the whole colour space still leaves one of those at
 * 4.58:1. So a contrast finding on measurable text always has a fix, and the
 * only question is how far the colour has to move.
 *
 * The unreachable branch is kept for a caller asking for more than AA — 7:1
 * genuinely cannot be met on some backgrounds by changing the text alone. It
 * returns the best it found and says the target was missed, because "the
 * closest I can get is 6.2:1, and the background has to change too" is a useful
 * answer and a silent near-miss is not.
 *
 * @param {Object} foreground The colour to move.
 * @param {Object} background The colour it sits on.
 * @param {number} required   Ratio to reach.
 * @return {{colour: Object, hex: string, ratio: number, reached: boolean}} The proposal.
 */
export function nearestAccessible( foreground, background, required ) {
	const hsl = rgbToHsl( foreground );

	// Both directions are tried because neither is reliably available: dark
	// text on a dark background has to get lighter, and against a mid-tone
	// background one direction may not reach the ratio at all.
	const candidates = [ 0, 1 ]
		.map( ( bound ) => {
			let near = hsl.l;
			let far = bound;

			if (
				contrastRatio( hslToRgb( { ...hsl, l: far } ), background ) <
				required
			) {
				return null;
			}

			for ( let step = 0; step < SEARCH_STEPS; step += 1 ) {
				const middle = ( near + far ) / 2;
				const ratio = contrastRatio(
					hslToRgb( { ...hsl, l: middle } ),
					background
				);

				if ( ratio >= required ) {
					far = middle;
				} else {
					near = middle;
				}
			}

			const colour = hslToRgb( { ...hsl, l: far } );

			return {
				colour,
				distance: Math.abs( far - hsl.l ),
				ratio: contrastRatio( colour, background ),
			};
		} )
		.filter( ( candidate ) => candidate && candidate.ratio >= required );

	if ( candidates.length > 0 ) {
		// The smaller move wins: it is the one that changes the design least.
		const best = candidates.sort(
			( a, b ) => a.distance - b.distance
		)[ 0 ];

		return {
			colour: best.colour,
			hex: toHex( best.colour ),
			ratio: best.ratio,
			reached: true,
		};
	}

	// Nothing on this hue reaches the ratio. Offer the strongest end anyway so
	// the reader can see how far short it falls and decide about the background.
	const best = [ 0, 1 ]
		.map( ( l ) => hslToRgb( { ...hsl, l } ) )
		.map( ( colour ) => ( {
			colour,
			ratio: contrastRatio( colour, background ),
		} ) )
		.sort( ( a, b ) => b.ratio - a.ratio )[ 0 ];

	return {
		colour: best.colour,
		hex: toHex( best.colour ),
		ratio: best.ratio,
		reached: false,
	};
}
