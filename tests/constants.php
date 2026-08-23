<?php
/**
 * Runtime constants, declared for tooling.
 *
 * Loaded by both the PHPUnit bootstrap and PHPStan. Unit tests do not load
 * WordPress, so ABSPATH is defined here: every plugin file guards on it and
 * would otherwise exit the process silently.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/fixtures/wordpress/' );
defined( 'WSAK_VERSION' ) || define( 'WSAK_VERSION', '0.2.0' );
defined( 'WSAK_FILE' ) || define( 'WSAK_FILE', __DIR__ . '/../wowstudio-accessibility-kit.php' );
defined( 'WSAK_PATH' ) || define( 'WSAK_PATH', dirname( __DIR__ ) . '/' );
defined( 'WSAK_URL' ) || define( 'WSAK_URL', 'https://example.test/wp-content/plugins/wowstudio-accessibility-kit/' );
defined( 'WSAK_BASENAME' ) || define( 'WSAK_BASENAME', 'wowstudio-accessibility-kit/wowstudio-accessibility-kit.php' );
defined( 'WSAK_MIN_PHP' ) || define( 'WSAK_MIN_PHP', '8.1' );
defined( 'WSAK_MIN_WP' ) || define( 'WSAK_MIN_WP', '6.6' );
