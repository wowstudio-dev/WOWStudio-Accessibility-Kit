<?php
/**
 * Uninstall wiring tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Guards the uninstall path against the two ways it has silently broken.
 *
 * @covers \WOWStudio\AccessibilityKit\Uninstaller
 */
final class UninstallTest extends TestCase {

	/**
	 * Reads a file from the plugin root.
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @return string
	 */
	private function source( string $relative ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source file in a unit test; WordPress is not loaded.
		return (string) file_get_contents( __DIR__ . '/../../' . $relative );
	}

	/**
	 * There must be no uninstall.php in the plugin root.
	 *
	 * Freemius refuses the deployment outright when one is present, with
	 * "Please move its logic to the after_uninstall hook and remove the file."
	 * The reason is not cosmetic: WordPress runs uninstall.php *instead of* the
	 * uninstall hooks, so the file stops the SDK ever seeing the uninstall.
	 *
	 * @return void
	 */
	public function test_plugin_root_has_no_uninstall_php(): void {
		$this->assertFileDoesNotExist(
			__DIR__ . '/../../uninstall.php',
			'uninstall.php blocks the Freemius deployment; the logic belongs on an uninstall hook.'
		);
	}

	/**
	 * The uninstaller must not gate itself on WP_UNINSTALL_PLUGIN.
	 *
	 * Core defines that constant only when it includes an uninstall.php. In the
	 * hook branch it is never defined, so a guard on it would make the whole
	 * cleanup exit at once and delete nothing — while still looking correct.
	 *
	 * @return void
	 */
	public function test_uninstaller_does_not_require_the_uninstall_php_constant(): void {
		$this->assertStringNotContainsString(
			"defined( 'WP_UNINSTALL_PLUGIN' ) || exit;",
			$this->source( 'src/Uninstaller.php' ),
			'That constant is absent on the hook path, so the guard would silently skip cleanup.'
		);
	}

	/**
	 * The main file must attach the cleanup to an uninstall hook.
	 *
	 * It has to happen at file scope. WordPress runs an uninstall by including
	 * the main file and firing uninstall_{plugin}; plugins_loaded never fires,
	 * so anything registered from Plugin::boot() is not there to be called.
	 *
	 * @return void
	 */
	public function test_main_file_registers_the_cleanup_at_file_scope(): void {
		$main = $this->source( 'wowstudio-accessibility-kit.php' );

		$this->assertStringContainsString(
			"'after_uninstall'",
			$main,
			'The SDK path should use Freemius\' own hook.'
		);
		$this->assertStringContainsString(
			'register_uninstall_hook( __FILE__',
			$main,
			'Without the SDK there must still be a fallback that cleans up.'
		);
		$this->assertStringNotContainsString(
			'add_action( \'plugins_loaded\', array( \'WOWStudio\\AccessibilityKit\\Uninstaller\'',
			$main,
			'Uninstall wiring must not depend on plugins_loaded.'
		);
	}
}
