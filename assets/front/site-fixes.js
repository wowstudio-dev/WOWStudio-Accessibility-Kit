/**
 * The site fixes that can only be made once the page exists.
 *
 * Five corrections, each switched on individually in the plugin's settings and
 * each named in `window.wsakSiteFixes`. Nothing here renders anything, adds any
 * control, or changes how the page looks. There is no widget and no toolbar:
 * this file finds specific, named faults in markup the theme already printed
 * and corrects them, then stops.
 *
 * Shipped unbundled and unminified on purpose. It runs on every visitor's page,
 * so a site owner who wants to know exactly what this plugin does to their
 * front end should be able to read it without a source map.
 */

( function () {
	'use strict';

	const enabled = window.wsakSiteFixes;

	if ( ! Array.isArray( enabled ) || ! enabled.length ) {
		return;
	}

	/**
	 * Reports whether one fix is switched on.
	 *
	 * @param {string} id Fix identifier.
	 * @return {boolean} Whether to run it.
	 */
	function on( id ) {
		return enabled.indexOf( id ) !== -1;
	}

	/**
	 * Lets a page be zoomed after the theme said it could not.
	 *
	 * Rewrites the viewport rather than replacing it, so that width,
	 * initial-scale and anything else the theme set are all left as they were.
	 */
	function viewportScalable() {
		const meta = document.querySelector( 'meta[name="viewport"]' );

		if ( ! meta ) {
			return;
		}

		const content = meta.getAttribute( 'content' ) || '';

		const next = content
			.replace( /user-scalable\s*=\s*(no|0)\s*,?/gi, '' )
			.replace(
				/maximum-scale\s*=\s*([0-9.]+)/gi,
				function ( match, value ) {
					// 1.4.4 asks for 200%, so anything under 2 fails for the same
					// reason switching zoom off does. 5 is the browser default.
					return parseFloat( value ) < 2 ? 'maximum-scale=5' : match;
				}
			)
			.replace( /,\s*,/g, ',' )
			.replace( /\s{2,}/g, ' ' )
			.replace( /^[\s,]+|[\s,]+$/g, '' );

		if ( next !== content ) {
			meta.setAttribute( 'content', next );
		}
	}

	/**
	 * Returns the tab order to the order things appear in.
	 */
	function stripPositiveTabindex() {
		const elements = document.querySelectorAll( '[tabindex]' );

		Array.prototype.forEach.call( elements, function ( element ) {
			const value = parseInt( element.getAttribute( 'tabindex' ), 10 );

			// Zero and minus one are both correct and both common. Only a
			// positive number rebuilds the page's focus order.
			if ( ! isNaN( value ) && value > 0 ) {
				element.setAttribute( 'tabindex', '0' );
			}
		} );
	}

	/**
	 * Removes a title that only repeats the visible text.
	 *
	 * The selector is the safeguard. Iframes and abbreviations are not in it,
	 * because on those the title is the element's real name and removing it
	 * would create the fault this plugin reports elsewhere.
	 */
	function stripRedundantTitle() {
		const elements = document.querySelectorAll(
			'a[title], button[title], [role="button"][title]'
		);

		Array.prototype.forEach.call( elements, function ( element ) {
			const title = ( element.getAttribute( 'title' ) || '' ).trim();
			const text = ( element.textContent || '' )
				.replace( /\s+/g, ' ' )
				.trim();

			if ( ! title || ! text ) {
				return;
			}

			if ( title.toLowerCase() === text.toLowerCase() ) {
				element.removeAttribute( 'title' );
			}
		} );
	}

	/**
	 * Reports whether a control already has a name from somewhere.
	 *
	 * Uses the DOM's own `labels` collection rather than searching for a
	 * matching `label[for=...]`. It already resolves both ways a label can be
	 * attached — the `for` attribute and wrapping the field — and it does it
	 * without having to escape an id that may contain almost anything.
	 *
	 * @param {Element} field The form control.
	 * @return {boolean} Whether it is already named.
	 */
	function isNamed( field ) {
		if (
			field.getAttribute( 'aria-label' ) ||
			field.getAttribute( 'aria-labelledby' )
		) {
			return true;
		}

		return Boolean( field.labels && field.labels.length );
	}

	/**
	 * Gives an unnamed field the name its own placeholder already carries.
	 *
	 * Never invents one. A field with no placeholder is left alone and keeps
	 * its finding, because a label nobody wrote is worse than none — it stops
	 * anybody looking again.
	 */
	function labelFormFields() {
		const fields = document.querySelectorAll(
			'input[placeholder], textarea[placeholder]'
		);

		Array.prototype.forEach.call( fields, function ( field ) {
			const type = (
				field.getAttribute( 'type' ) || 'text'
			).toLowerCase();

			if ( type === 'hidden' || type === 'submit' || type === 'button' ) {
				return;
			}

			const placeholder = (
				field.getAttribute( 'placeholder' ) || ''
			).trim();

			if ( ! placeholder || isNamed( field ) ) {
				return;
			}

			field.setAttribute( 'aria-label', placeholder );
		} );
	}

	/**
	 * Refuses an empty search, and says so where the field is.
	 *
	 * The message is a live region beside the input rather than a new page,
	 * and focus goes back to the field, which is what a validation error is
	 * supposed to do and what this form never did.
	 */
	function emptySearchMessage() {
		const forms = document.querySelectorAll(
			'form[role="search"], form.search-form'
		);

		Array.prototype.forEach.call( forms, function ( form ) {
			const field = form.querySelector(
				'input[name="s"], input[type="search"]'
			);

			if ( ! field || form.hasAttribute( 'data-wsak-search' ) ) {
				return;
			}

			// Marked so that a form matched by both selectors, or a script that
			// runs this twice, cannot end up with two handlers.
			form.setAttribute( 'data-wsak-search', '1' );

			form.addEventListener( 'submit', function ( event ) {
				if ( field.value.trim() !== '' ) {
					return;
				}

				event.preventDefault();

				let message = form.querySelector( '.wsak-search-message' );

				if ( ! message ) {
					message = document.createElement( 'p' );
					message.className = 'wsak-search-message';
					message.setAttribute( 'role', 'alert' );
					field.insertAdjacentElement( 'afterend', message );
				}

				message.textContent =
					window.wsakSearchMessage || 'Type something to search for.';

				field.setAttribute( 'aria-invalid', 'true' );
				field.focus();
			} );

			field.addEventListener( 'input', function () {
				const message = form.querySelector( '.wsak-search-message' );

				if ( message ) {
					message.remove();
				}

				field.removeAttribute( 'aria-invalid' );
			} );
		} );
	}

	/**
	 * Runs the fixes that need the whole document.
	 */
	function run() {
		if ( on( 'strip-positive-tabindex' ) ) {
			stripPositiveTabindex();
		}

		if ( on( 'strip-redundant-title' ) ) {
			stripRedundantTitle();
		}

		if ( on( 'label-form-fields' ) ) {
			labelFormFields();
		}

		if ( on( 'empty-search-message' ) ) {
			emptySearchMessage();
		}
	}

	/*
	 * The viewport is corrected straight away rather than on DOMContentLoaded.
	 * This script is loaded in the head, after the theme's own viewport tag, so
	 * the element already exists — and waiting would mean the first paint used
	 * the setting that blocks zooming, which is the one moment a reader who
	 * needs to zoom is most likely to try.
	 */
	if ( on( 'viewport-scalable' ) ) {
		viewportScalable();
	}

	// Everything else needs the body, which does not exist yet.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
