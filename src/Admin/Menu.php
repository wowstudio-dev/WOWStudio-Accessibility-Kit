<?php
/**
 * Admin menu.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Admin;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's top-level admin menu.
 *
 * The slug is a stable public identifier, not an implementation detail. It is
 * what `add_submenu_page()` needs in order to hang anything beneath this menu,
 * which is how the planned Pro add-on will attach its own screens. Renaming it
 * would orphan every page anything else has hung off it, so it is a constant
 * rather than a string typed in twice.
 *
 * @since 0.1.0
 */
final class Menu implements Registrable {

	/**
	 * Top-level menu slug.
	 *
	 * Matches the plugin's own directory and text domain, so that one name
	 * identifies the plugin everywhere. MenuTest asserts it has not drifted.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const SLUG = 'wowstudio-accessibility-kit';

	/**
	 * Hooks the menu registration.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Adds the top-level menu.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_menu(): void {
		/**
		 * Filters the position of the plugin's top-level admin menu.
		 *
		 * @since 0.1.0
		 *
		 * @param int $position Menu position.
		 */
		$position = (int) apply_filters( 'wsak_menu_position', 58 );

		add_menu_page(
			__( 'WOWStudio Accessibility Kit', 'wowstudio-accessibility-kit' ),
			__( 'Accessibility Kit', 'wowstudio-accessibility-kit' ),
			Capabilities::VIEW_REPORTS,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-universal-access-alt',
			$position
		);
	}

	/**
	 * Renders the mount point for the admin app.
	 *
	 * The heading is rendered by the app rather than here, so the page has
	 * exactly one h1. A noscript block carries a heading of its own for the
	 * case where the app never runs.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_REPORTS ) ) {
			wp_die( esc_html__( 'You do not have permission to view accessibility reports.', 'wowstudio-accessibility-kit' ) );
		}
		?>
		<div class="wrap wsak-wrap">
			<div id="wsak-app" class="wsak-app">
				<p class="wsak-boot" role="status">
					<?php esc_html_e( 'Loading the accessibility dashboard…', 'wowstudio-accessibility-kit' ); ?>
				</p>
			</div>
			<noscript>
				<h1><?php esc_html_e( 'WOWStudio Accessibility Kit', 'wowstudio-accessibility-kit' ); ?></h1>
				<p>
					<?php esc_html_e( 'This dashboard needs JavaScript to run a scan and show results. Everything it does is also available through the REST API if you would rather not enable it.', 'wowstudio-accessibility-kit' ); ?>
				</p>
			</noscript>
		</div>
		<?php
	}
}
