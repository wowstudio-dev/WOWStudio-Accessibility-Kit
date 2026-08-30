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
 * This menu is not optional decoration. Freemius is configured to hang its own
 * pages (Account, Upgrade, Contact) off this exact slug, and while the opt-in
 * is pending it creates a temporary top-level menu of its own to host the
 * connect screen. The moment a user opts in or skips, Freemius removes that
 * placeholder and expects the plugin's real menu to be there. If it is not, the
 * plugin disappears from the sidebar entirely and its Freemius pages are
 * orphaned.
 *
 * @since 0.1.0
 */
final class Menu implements Registrable {

	/**
	 * Top-level menu slug.
	 *
	 * Must stay identical to the 'menu' => 'slug' value passed to Freemius in
	 * the main plugin file. MenuTest asserts they match.
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
