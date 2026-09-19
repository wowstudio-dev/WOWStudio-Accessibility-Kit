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
	 * WordPress runs uninstall.php *instead of* the uninstall hooks, so adding
	 * that file back would silently stop the registered callback ever running
	 * and the plugin would leave its tables behind on every uninstall.
	 *
	 * @return void
	 */
	public function test_plugin_root_has_no_uninstall_php(): void {
		$this->assertFileDoesNotExist(
			__DIR__ . '/../../uninstall.php',
			'uninstall.php overrides the uninstall hook; the cleanup belongs on the hook.'
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
		$main = $this->source( 'wowstudio-accessibility-remediation.php' );

		$this->assertStringContainsString(
			'register_uninstall_hook( __FILE__',
			$main,
			'Nothing would clean up on uninstall without this.'
		);
		$this->assertStringNotContainsString(
			'add_action( \'plugins_loaded\', array( \'WOWStudio\\AccessibilityKit\\Uninstaller\'',
			$main,
			'Uninstall wiring must not depend on plugins_loaded.'
		);
	}

	/**
	 * Every option this plugin owns goes, and the add-on's stay.
	 *
	 * Source-reading, because the failure is invisible from here: the leak this
	 * replaced was four options named nowhere in the uninstaller, and a test
	 * asserting the four that *were* named would have passed throughout.
	 *
	 * Four options survived an uninstall the owner had opted into — the
	 * site-wide fixes' settings, the simplified-summary switch, the cached
	 * theme profile, and a legacy migration flag. Each was added to the plugin
	 * later than the uninstaller and nobody went back. A list of things to
	 * delete is a list somebody has to remember to extend; a prefix is not.
	 *
	 * The reserved prefix matters as much as the sweep. The paid add-on stores
	 * its own options under `wsak_pro_`, and uninstalling this plugin is not
	 * consent to delete the data of a different one that is still installed.
	 *
	 * @return void
	 */
	public function test_options_go_by_prefix_and_the_add_ons_are_spared(): void {
		$source = $this->source( 'src/Uninstaller.php' );

		$this->assertStringContainsString(
			'option_name LIKE %s AND option_name NOT LIKE %s',
			$source,
			'Options must be swept by prefix, not deleted from a list that goes stale.'
		);

		$this->assertStringContainsString(
			"RESERVED_PREFIX = 'wsak_pro_'",
			$source,
			"The add-on's own options must be reserved from the sweep."
		);

		$this->assertStringNotContainsString(
			'delete_option( Installer::VERSION_OPTION )',
			$source,
			'The named-option list is what leaked; it should not come back alongside the sweep.'
		);
	}
}
