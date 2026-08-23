<?php
/**
 * Admin menu tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Admin\Menu;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the top-level admin menu.
 *
 * @covers \WOWStudio\AccessibilityKit\Admin\Menu
 */
final class MenuTest extends TestCase {

	/**
	 * The menu is registered on admin_menu.
	 *
	 * @return void
	 */
	public function test_register_hooks_admin_menu(): void {
		Actions\expectAdded( 'admin_menu' )->once();

		( new Menu() )->register();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * The menu slug must match the one handed to Freemius.
	 *
	 * This is the regression test for a real failure: Freemius shows its own
	 * temporary top-level menu while the opt-in is pending, then removes it once
	 * the user opts in or skips and expects the plugin's menu to exist at that
	 * slug. When the two drift apart, the plugin disappears from the sidebar
	 * and the Freemius Account and Upgrade pages are orphaned.
	 *
	 * @return void
	 */
	public function test_slug_matches_the_freemius_menu_slug(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source file in a unit test; WordPress is not loaded.
		$bootstrap = (string) file_get_contents( WSAK_PATH . 'wowstudio-accessibility-kit.php' );

		$this->assertSame(
			1,
			preg_match( "/'menu'\s*=>\s*array\(\s*'slug'\s*=>\s*'([^']+)'/", $bootstrap, $matches ),
			'Could not find the Freemius menu slug in the bootstrap file.'
		);

		$this->assertSame(
			Menu::SLUG,
			$matches[1],
			'Menu::SLUG and the Freemius menu slug have drifted apart. The plugin will vanish from the admin sidebar once a user skips or completes the opt-in.'
		);
	}

	/**
	 * The menu is added with the plugin's own capability, not manage_options.
	 *
	 * @return void
	 */
	public function test_menu_uses_least_privilege_capability(): void {
		Functions\expect( 'add_menu_page' )
			->once()
			->withArgs(
				static function ( $page_title, $menu_title, $capability, $slug ): bool {
					return Capabilities::VIEW_REPORTS === $capability && Menu::SLUG === $slug;
				}
			);

		( new Menu() )->register_menu();

		$this->addToAssertionCount( 1 );
	}
}
