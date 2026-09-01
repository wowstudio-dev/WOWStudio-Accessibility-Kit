/**
 * The accessibility panel inside the block editor.
 *
 * The content is already here, structured, in the browser. So this needs no
 * permalink, no preview nonce and no loopback request — which matters most on
 * exactly the hosts where loopback fails. See decision F5.
 */

import domReady from '@wordpress/dom-ready';
import { registerPlugin } from '@wordpress/plugins';

import Panel from './panel';
import './editor.scss';

domReady( () => {
	registerPlugin( 'wowstudio-accessibility-kit', {
		render: Panel,
		icon: 'universal-access-alt',
	} );
} );
