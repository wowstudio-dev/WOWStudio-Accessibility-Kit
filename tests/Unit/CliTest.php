<?php
/**
 * Command-line wiring tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Guards the command line's shape rather than its output.
 *
 * The commands themselves are exercised against a real WordPress; what is
 * worth guarding here is the wiring, because every one of these failures is
 * silent. A command registered on the wrong hook simply does not appear, and
 * `wp wsak` answers "command not found" with nothing to say why.
 *
 * @covers \WOWStudio\AccessibilityKit\Cli\Command
 */
final class CliTest extends TestCase {

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
	 * The command is registered, and only when WP-CLI is running.
	 *
	 * Registering unconditionally would fatal on every web request, because
	 * WP_CLI does not exist there.
	 *
	 * @return void
	 */
	public function test_the_command_registers_only_under_wp_cli(): void {
		$main = $this->source( 'wowstudio-accessibility-kit.php' );

		$this->assertStringContainsString( "defined( 'WP_CLI' ) && WP_CLI", $main );
		$this->assertStringContainsString( "WP_CLI::add_command( 'wsak'", $main );
	}

	/**
	 * Bulk is not held back from the command line.
	 *
	 * The whole reason to have a CLI is continuous integration and staging
	 * audits, and a command that can only do one page at a time is useless for
	 * both. If --all ever disappears, it should be because somebody decided
	 * that on purpose.
	 *
	 * @return void
	 */
	public function test_the_scan_command_can_do_the_whole_site(): void {
		$command = $this->source( 'src/Cli/Command.php' );

		$this->assertStringContainsString( '[--all]', $command );
		$this->assertStringContainsString( "isset( \$assoc_args['all'] )", $command );
	}

	/**
	 * Every subcommand documents itself, because WP-CLI shows that as help.
	 *
	 * @return void
	 */
	public function test_every_subcommand_has_a_synopsis(): void {
		$command = $this->source( 'src/Cli/Command.php' );

		foreach ( array( 'scan', 'issues', 'checks', 'fixes' ) as $subcommand ) {
			$this->assertStringContainsString(
				'public function ' . $subcommand . '(',
				$command,
				$subcommand . ' is missing.'
			);
		}

		$this->assertSame(
			4,
			substr_count( $command, '## OPTIONS' ),
			'Each subcommand needs an OPTIONS block; WP-CLI renders it as the help text.'
		);
	}

	/**
	 * An empty result does not claim the pages are accessible.
	 *
	 * The same honesty the interface keeps. "No findings" from a set of checks
	 * that cover part of WCAG is not a clean bill of health, and a terminal is
	 * no place to start implying otherwise.
	 *
	 * @return void
	 */
	public function test_no_findings_is_not_reported_as_a_pass(): void {
		$this->assertStringContainsString(
			'automated checks cover part of WCAG',
			$this->source( 'src/Cli/Command.php' )
		);
	}

	/**
	 * A scan says how much of the page it managed to read.
	 *
	 * A content-only scan covers less than a full-page one, and a score that
	 * does not say so invites being compared against one that means something
	 * different.
	 *
	 * @return void
	 */
	public function test_the_scan_output_states_its_coverage(): void {
		$command = $this->source( 'src/Cli/Command.php' );

		$this->assertStringContainsString( "'coverage'", $command );
		$this->assertStringContainsString( 'content only', $command );
	}
}
