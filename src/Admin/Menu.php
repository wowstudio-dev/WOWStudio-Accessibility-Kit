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
	 * The screens hanging off the top-level menu.
	 *
	 * Four pages rather than one, because eight tabs across a single screen
	 * asked somebody to hold the whole plugin in their head before they could
	 * find anything in it. WordPress already has a place for this — the
	 * submenu — and using it puts the plugin's shape where people look for a
	 * plugin's shape.
	 *
	 * The first entry deliberately reuses the parent slug. WordPress repeats a
	 * top-level menu as its own first child otherwise, and that child would be
	 * labelled with the plugin's name rather than with what the screen is.
	 *
	 * Slugs are public identifiers: the planned add-on hangs its own pages off
	 * this menu, and renaming one orphans whatever was pointing at it.
	 *
	 * @since 0.29.0
	 * @var array<int, array{slug: string, view: string}>
	 */
	private const SCREENS = array(
		array(
			'slug' => self::SLUG,
			'view' => 'overview',
		),
		array(
			'slug' => 'wsak-scan-fix',
			'view' => 'scan',
		),
		array(
			'slug' => 'wsak-settings',
			'view' => 'fixes',
		),
		array(
			'slug' => 'wsak-statement',
			'view' => 'statement',
		),
	);

	/**
	 * Returns which view each screen opens on, keyed by page slug.
	 *
	 * Read by Assets so the app knows what it was asked for, and so the bundle
	 * loads on these four screens and nowhere else.
	 *
	 * @since 0.29.0
	 *
	 * @return array<string, string>
	 */
	public static function views(): array {
		$views = array();

		foreach ( self::SCREENS as $screen ) {
			$views[ $screen['slug'] ] = $screen['view'];
		}

		return $views;
	}

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

		/*
		 * Titles live here rather than in SCREENS because they have to be
		 * translated at the moment the menu is built, and a class constant is
		 * evaluated long before any text domain is loaded.
		 */
		$titles = array(
			self::SLUG       => __( 'Dashboard', 'wowstudio-accessibility-kit' ),
			'wsak-scan-fix'  => __( 'Scan & Fix', 'wowstudio-accessibility-kit' ),
			'wsak-settings'  => __( 'Settings', 'wowstudio-accessibility-kit' ),
			'wsak-statement' => __( 'Statement', 'wowstudio-accessibility-kit' ),
		);

		foreach ( self::SCREENS as $screen ) {
			$title = $titles[ $screen['slug'] ] ?? $screen['slug'];

			add_submenu_page(
				self::SLUG,
				sprintf(
					/* translators: %s: name of the screen, e.g. Dashboard. */
					__( '%s — Accessibility Kit', 'wowstudio-accessibility-kit' ),
					$title
				),
				$title,
				Capabilities::VIEW_REPORTS,
				$screen['slug'],
				array( $this, 'render' )
			);
		}
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
