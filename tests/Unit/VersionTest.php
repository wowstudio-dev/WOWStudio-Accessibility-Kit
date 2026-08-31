<?php
/**
 * Version consistency across the files that carry it.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Checks that everything claiming a version claims the same one.
 *
 * Four files carry it — the plugin header, the constant the code reads,
 * package.json, and readme.txt's stable tag — and nothing was checking they
 * agreed. They had already drifted: the headers said 0.9.0 while the newest code
 * documented itself as 0.11.0. That is the quiet kind of wrong. WordPress.org
 * serves whatever the stable tag names, the updater compares the header, and a
 * mismatch between them ships the wrong code to everybody without erroring
 * anywhere.
 *
 * @coversNothing
 */
final class VersionTest extends TestCase {

	/**
	 * Reads a file from the repository root.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	private function read( string $name ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a file from this repository in a test; there is no HTTP here.
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $name );
	}

	/**
	 * Extracts the one capture group a pattern is expected to find.
	 *
	 * @param string $pattern Pattern with a single capture group.
	 * @param string $subject Text to search.
	 * @param string $label   What is being looked for, for the failure message.
	 * @return string
	 */
	private function capture( string $pattern, string $subject, string $label ): string {
		$this->assertSame( 1, preg_match( $pattern, $subject, $matches ), 'Could not find ' . $label . '.' );

		return $matches[1];
	}

	/**
	 * Every file that names a version names the same one.
	 *
	 * @return void
	 */
	public function test_every_file_agrees_on_the_version(): void {
		$plugin = $this->read( 'wowstudio-accessibility-kit.php' );

		$header   = $this->capture( '/^ \* Version:\s+(\S+)$/m', $plugin, 'the plugin header version' );
		$constant = $this->capture( "/define\( 'WSAK_VERSION', '([^']+)' \);/", $plugin, 'the WSAK_VERSION constant' );
		$package  = $this->capture( '/"version": "([^"]+)"/', $this->read( 'package.json' ), 'the package.json version' );
		$stable   = $this->capture( '/^Stable tag: (\S+)$/m', $this->read( 'readme.txt' ), "readme.txt's stable tag" );

		$this->assertSame( $header, $constant, 'The header and the constant must agree; the code reads one and the updater reads the other.' );
		$this->assertSame( $header, $package, 'package.json must agree with the plugin header.' );
		$this->assertSame( $header, $stable, "readme.txt's stable tag decides what WordPress.org actually serves." );
	}

	/**
	 * The version being shipped has a changelog entry.
	 *
	 * A release nobody wrote down is one nobody can tell you what changed in,
	 * which is the whole point of shipping honestly.
	 *
	 * @return void
	 */
	public function test_the_current_version_is_written_down(): void {
		$version = $this->capture(
			"/define\( 'WSAK_VERSION', '([^']+)' \);/",
			$this->read( 'wowstudio-accessibility-kit.php' ),
			'the WSAK_VERSION constant'
		);

		$this->assertStringContainsString(
			'## [' . $version . ']',
			$this->read( 'CHANGELOG.md' ),
			'CHANGELOG.md has no entry for ' . $version . '.'
		);

		$this->assertStringContainsString(
			'= ' . $version . ' =',
			$this->read( 'readme.txt' ),
			'readme.txt has no changelog entry for ' . $version . '.'
		);
	}
}
