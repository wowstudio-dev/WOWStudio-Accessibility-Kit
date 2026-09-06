<?php
/**
 * WOWStudio Accessibility Kit
 *
 * @package           WOWStudio\AccessibilityKit
 * @author            WOWStudio
 * @copyright         2026 WOWStudio
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WOWStudio Accessibility Kit
 * Plugin URI:        https://wowstudio.dev/accessibility-kit/
 * Description:       Helps you find, fix, document, and monitor WCAG accessibility issues at the code level. Real markup fixes with preview and undo — not an overlay.
 * Version:           0.21.0
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * Author:            WOWStudio
 * Author URI:        https://wowstudio.dev/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wowstudio-accessibility-kit
 * Domain Path:       /languages
 */

/*
 * NOTE: This bootstrap file is deliberately written in conservative PHP syntax
 * (no typed properties, enums, or 8.x-only features). It has to parse on old PHP
 * versions so that an unsupported site sees a readable admin notice instead of a
 * white screen. Everything under src/ is autoloaded lazily and may use PHP 8.1.
 */

defined( 'ABSPATH' ) || exit;

define( 'WSAK_VERSION', '0.21.0' );
define( 'WSAK_FILE', __FILE__ );
define( 'WSAK_PATH', plugin_dir_path( __FILE__ ) );
define( 'WSAK_URL', plugin_dir_url( __FILE__ ) );
define( 'WSAK_BASENAME', plugin_basename( __FILE__ ) );
define( 'WSAK_MIN_PHP', '8.1' );
define( 'WSAK_MIN_WP', '6.8' );

/**
 * Collects unmet runtime requirements.
 *
 * WordPress blocks activation using the plugin headers, but a site can be
 * downgraded after activation. This is the runtime safety net.
 *
 * Returns raw data rather than translated strings on purpose: this runs while
 * plugins load, and calling a translation function before init triggers
 * WordPress 6.7's "translation loaded too early" notice. The text is built
 * later, inside the admin_notices callback.
 *
 * @since 0.1.0
 *
 * @return array<int, array{code: string, required: string, actual: string}> Unmet requirements. Empty when satisfied.
 */
function wsak_unmet_requirements() {
	$unmet = array();

	if ( version_compare( PHP_VERSION, WSAK_MIN_PHP, '<' ) ) {
		$unmet[] = array(
			'code'     => 'php',
			'required' => WSAK_MIN_PHP,
			'actual'   => PHP_VERSION,
		);
	}

	$wp_version = get_bloginfo( 'version' );

	if ( version_compare( $wp_version, WSAK_MIN_WP, '<' ) ) {
		$unmet[] = array(
			'code'     => 'wp',
			'required' => WSAK_MIN_WP,
			'actual'   => $wp_version,
		);
	}

	if ( ! file_exists( WSAK_PATH . 'vendor/autoload.php' ) ) {
		$unmet[] = array(
			'code'     => 'autoloader',
			'required' => '',
			'actual'   => '',
		);
	}

	return $unmet;
}

/**
 * Turns one unmet requirement into a readable sentence.
 *
 * Only called from admin_notices, which runs after init, so translating here is
 * safe.
 *
 * @since 0.1.0
 *
 * @param array{code: string, required: string, actual: string} $requirement One entry from wsak_unmet_requirements().
 * @return string
 */
function wsak_requirement_message( $requirement ) {
	switch ( $requirement['code'] ) {
		case 'php':
			return sprintf(
				/* translators: 1: required PHP version, 2: PHP version running on the server. */
				__( 'PHP %1$s or newer is required. This server runs PHP %2$s.', 'wowstudio-accessibility-kit' ),
				$requirement['required'],
				$requirement['actual']
			);

		case 'wp':
			return sprintf(
				/* translators: 1: required WordPress version, 2: WordPress version on this site. */
				__( 'WordPress %1$s or newer is required. This site runs WordPress %2$s.', 'wowstudio-accessibility-kit' ),
				$requirement['required'],
				$requirement['actual']
			);

		default:
			return __( 'The plugin files are incomplete: the Composer autoloader is missing. Reinstall the plugin.', 'wowstudio-accessibility-kit' );
	}
}

/**
 * Renders the unmet-requirements notice and deactivates the plugin.
 *
 * @since 0.1.0
 *
 * @param array<int, array{code: string, required: string, actual: string}> $unmet Requirement failures from wsak_unmet_requirements().
 * @return void
 */
function wsak_halt( $unmet ) {
	add_action(
		'admin_notices',
		function () use ( $unmet ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'WOWStudio Accessibility Kit could not start.', 'wowstudio-accessibility-kit' ); ?></strong>
				</p>
				<ul class="ul-disc">
					<?php foreach ( $unmet as $requirement ) : ?>
						<li><?php echo esc_html( wsak_requirement_message( $requirement ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		}
	);

	add_action(
		'admin_init',
		function () {
			if ( is_plugin_active( WSAK_BASENAME ) ) {
				deactivate_plugins( WSAK_BASENAME );
			}
		}
	);
}

$wsak_unmet = wsak_unmet_requirements();

if ( ! empty( $wsak_unmet ) ) {
	wsak_halt( $wsak_unmet );
	return;
}

unset( $wsak_unmet );

require_once WSAK_PATH . 'vendor/autoload.php';

/*
 * Action Scheduler is a plugin pretending to be a library, so it is required by
 * path rather than autoloaded: it registers its own hooks and its own version
 * negotiation at include time, and Composer's autoloader would never reach it
 * because nothing in our code names one of its classes directly.
 *
 * It has to be included here, at file scope, rather than on plugins_loaded.
 * Several plugins on a site may each bundle a copy; they all register, and
 * Action Scheduler itself decides which version runs. That negotiation happens
 * on plugins_loaded, so anything registering later is simply not considered.
 */
require_once WSAK_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';

register_activation_hook( __FILE__, array( 'WOWStudio\AccessibilityKit\Core\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WOWStudio\AccessibilityKit\Core\Deactivator', 'deactivate' ) );

/*
 * Uninstall cleanup, wired here at file scope rather than in a service.
 *
 * WordPress runs the uninstall hooks by including this file and firing
 * uninstall_{plugin} — plugins_loaded never fires, so Plugin::boot() and every
 * service it registers are absent. Anything that must survive an uninstall has
 * to be attached before that point, which is here.
 *
 * There must be no uninstall.php beside this file. WordPress runs that file
 * *instead of* the uninstall hooks, so its mere presence would stop this
 * callback ever being reached.
 */
register_uninstall_hook( __FILE__, array( 'WOWStudio\AccessibilityKit\Uninstaller', 'run' ) );

add_action(
	'plugins_loaded',
	function () {
		\WOWStudio\AccessibilityKit\Core\Plugin::instance()->boot();
	}
);
