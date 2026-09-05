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
	 * The menu slug must match the plugin's text domain.
	 *
	 * One name should identify the plugin everywhere: the directory, the text
	 * domain, the WordPress.org slug, and this menu. The slug is also the
	 * parent that `add_submenu_page()` needs, so anything hanging a screen off
	 * this menu — the planned paid add-on included — is holding a copy of this
	 * string. Changing it here without changing it there orphans those pages
	 * silently, which is why it is asserted rather than trusted.
	 *
	 * @return void
	 */
	public function test_slug_matches_the_text_domain(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source file in a unit test; WordPress is not loaded.
		$bootstrap = (string) file_get_contents( WSAK_PATH . 'wowstudio-accessibility-kit.php' );

		$this->assertSame(
			1,
			preg_match( '/^ \* Text Domain:\s+(\S+)$/m', $bootstrap, $matches ),
			'Could not find the Text Domain header in the bootstrap file.'
		);

		$this->assertSame(
			Menu::SLUG,
			$matches[1],
			'Menu::SLUG and the plugin text domain have drifted apart.'
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
