/**
 * Build configuration.
 *
 * wp-scripts discovers entry points from block.json files. Once the statement
 * block existed it became the only entry, and the admin app silently stopped
 * being built — a blank dashboard with a successful build. Every entry is
 * therefore declared explicitly rather than left to discovery.
 */

const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const discovered =
	typeof defaultConfig.entry === 'function'
		? defaultConfig.entry()
		: defaultConfig.entry;

module.exports = {
	...defaultConfig,
	entry: {
		...discovered,
		index: path.resolve( __dirname, 'assets/src/index.js' ),
		editor: path.resolve( __dirname, 'assets/src/editor/index.js' ),
	},
};
