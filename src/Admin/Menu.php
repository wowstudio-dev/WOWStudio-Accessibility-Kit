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
			__( 'Accessibility', 'wowstudio-accessibility-kit' ),
			Capabilities::VIEW_REPORTS,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-universal-access-alt',
			$position
		);
	}

	/**
	 * Renders the screen.
	 *
	 * A holding screen until the React dashboard lands. It still carries the
	 * coverage disclaimer, because every screen that talks about accessibility
	 * findings has to be honest about what automation can and cannot see.
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
		<div class="wrap">
			<h1><?php esc_html_e( 'WOWStudio Accessibility Kit', 'wowstudio-accessibility-kit' ); ?></h1>

			<p>
				<?php esc_html_e( 'Helps you find, fix, document, and monitor accessibility issues in your site at the code level.', 'wowstudio-accessibility-kit' ); ?>
			</p>

			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e( 'Automated testing detects only part of WCAG. This plugin always separates what it checked automatically from what still needs a person, and it does not decide whether your site meets any legal requirement.', 'wowstudio-accessibility-kit' ); ?>
				</p>
			</div>

			<h2><?php esc_html_e( 'Not ready yet', 'wowstudio-accessibility-kit' ); ?></h2>
			<p>
				<?php esc_html_e( 'Scanning, alt text, and the accessibility statement generator are still being built. This screen is where they will appear.', 'wowstudio-accessibility-kit' ); ?>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: plugin version number. */
					esc_html__( 'Version %s', 'wowstudio-accessibility-kit' ),
					esc_html( WSAK_VERSION )
				);
				?>
			</p>
		</div>
		<?php
	}
}
