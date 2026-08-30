/**
 * ESLint configuration.
 *
 * Exists for one reason: to switch on @wordpress/eslint-plugin's i18n rules,
 * which ship with the plugin but are NOT part of the config `wp-scripts
 * lint-js` uses by default.
 *
 * Without them, a translator function called without the text domain — a
 * one-character slip — passes lint, passes the build, and is then silently
 * dropped by `wp i18n make-pot`, because make-pot extracts only calls carrying
 * the domain it was given. The string ends up permanently untranslatable and
 * nothing anywhere says so. PHPCS has caught this on the PHP side since day
 * one; this is the JavaScript half.
 *
 * Adding a config here means wp-scripts stops using its own, so its default is
 * spread in first rather than replaced.
 */

const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );
const wpPlugin = require( '@wordpress/eslint-plugin' );

module.exports = [
	...defaultConfig,

	{
		ignores: [ '**/build/**', '**/dist/**', '**/node_modules/**', '**/vendor/**', '**/freemius/**' ],
	},

	// Translator-function correctness: text domain, translator comments,
	// sprintf placeholder validity, no variables as translatable strings.
	...wpPlugin.configs.i18n,

	// The browser pass runs inside the preview frame, not in the admin app, so
	// it touches DOM APIs the shared config does not declare. Scoped to that
	// directory rather than declared globally, so a stray DOMParser in a React
	// component is still caught.
	{
		files: [ 'assets/src/scanner/**/*.js' ],
		languageOptions: {
			globals: {
				DOMParser: 'readonly',
				Element: 'readonly',
				XPathResult: 'readonly',
				document: 'readonly',
				window: 'readonly',
				setTimeout: 'readonly',
				clearTimeout: 'readonly',
			},
		},
	},

	{
		rules: {
			'@wordpress/i18n-text-domain': [
				'error',
				{ allowedTextDomain: [ 'wowstudio-accessibility-kit' ] },
			],
		},
	},
];
