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
 * Version:           0.3.0
 * Requires at least: 6.6
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

define( 'WSAK_VERSION', '0.3.0' );
define( 'WSAK_FILE', __FILE__ );
define( 'WSAK_PATH', plugin_dir_path( __FILE__ ) );
define( 'WSAK_URL', plugin_dir_url( __FILE__ ) );
define( 'WSAK_BASENAME', plugin_basename( __FILE__ ) );
define( 'WSAK_MIN_PHP', '8.1' );
define( 'WSAK_MIN_WP', '6.6' );

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

// The SDK is vendored, but a stripped or partial install must degrade to a
// working free plugin rather than a fatal error.
if ( ! function_exists( 'wsak_fs' ) && file_exists( WSAK_PATH . 'freemius/start.php' ) ) {
	/**
	 * Freemius SDK accessor.
	 *
	 * Renamed from the dashboard-generated wak_fs() so that every global symbol
	 * carries the wsak_ prefix mandated by SPEC.md and enforced by PHPCS.
	 *
	 * @since 0.1.0
	 *
	 * @return Freemius
	 */
	function wsak_fs() {
		global $wsak_fs;

		if ( ! isset( $wsak_fs ) ) {
			require_once WSAK_PATH . 'freemius/start.php';

			$wsak_fs = fs_dynamic_init(
				array(
					'id'                  => '37652',
					'slug'                => 'wowstudio-accessibility-kit',
					'type'                => 'plugin',
					'public_key'          => 'pk_191e8a177939d7b8c20be46d3aca9',
					'is_premium'          => true,
					'premium_suffix'      => 'Pro',
					'has_premium_version' => true,
					'has_addons'          => false,
					'has_paid_plans'      => true,
					// wsak:claim-reviewed -- Freemius SDK argument name (wp.org distribution), not a claim about the user's site.
					'is_org_compliant'    => true,
					// Mirrors the dashboard trial setting; the dashboard is authoritative.
					'trial'               => array(
						'days'               => 7,
						'is_require_payment' => true,
					),
					// Automatically removed from the free version by Freemius on deploy.
					'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
					'menu'                => array(
						'slug'    => 'wowstudio-accessibility-kit',
						'support' => false,
					),
				)
			);
		}

		return $wsak_fs;
	}

	wsak_fs();
	do_action( 'wsak_fs_loaded' );
}

/**
 * Reports whether Pro code paths may run.
 *
 * Always use this rather than calling wsak_fs() directly: if the Freemius SDK
 * is absent the accessor does not exist, and every Pro check would be fatal.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function wsak_can_use_premium_code() {
	return function_exists( 'wsak_fs' ) && wsak_fs()->can_use_premium_code();
}

require_once WSAK_PATH . 'vendor/autoload.php';

register_activation_hook( __FILE__, array( 'WOWStudio\AccessibilityKit\Core\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WOWStudio\AccessibilityKit\Core\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	function () {
		\WOWStudio\AccessibilityKit\Core\Plugin::instance()->boot();
	}
);
