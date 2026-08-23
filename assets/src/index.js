/**
 * Admin app entry point.
 */

import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import App from './app';
import './style.scss';

domReady( () => {
	const container = document.getElementById( 'wsak-app' );

	if ( ! container ) {
		return;
	}

	createRoot( container ).render( <App /> );
} );
