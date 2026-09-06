<?php
/**
 * Extension seam tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Guards the four points a separate add-on is meant to attach to.
 *
 * These exist so the paid add-on can be a plugin that attaches rather than a
 * fork, which is what keeps the free plugin complete: nothing ever has to be
 * taken away from somebody who already had it. Retrofitting a seam once the
 * screens around it have grown is expensive, which is why they are here before
 * anything uses them.
 *
 * Source-reading, like the other wiring tests in this suite. What is being
 * guarded is that the hook exists and is named what an add-on will be written
 * against; a renamed filter is a silent break for every add-on at once, with no
 * error anywhere.
 *
 * @coversNothing
 */
final class ExtensionSeamsTest extends TestCase {

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
	 * The scan runner can be driven from outside.
	 *
	 * A scheduler needs somewhere to start a run and somewhere to learn it
	 * finished. Both are public and both stayed behind when monitoring moved to
	 * the paid side, which is the whole point of them.
	 *
	 * @return void
	 */
	public function test_the_scan_runner_is_reachable(): void {
		$bulk = $this->source( 'src/Jobs/BulkScan.php' );

		$this->assertStringContainsString( 'public function start( array $post_ids', $bulk );
		$this->assertStringContainsString( "do_action( 'wsak_bulk_scan_finished'", $bulk );
	}

	/**
	 * A fix can be registered without this codebase knowing it exists.
	 *
	 * @return void
	 */
	public function test_the_fix_pipeline_is_open(): void {
		$this->assertStringContainsString(
			"apply_filters( 'wsak_site_fixes'",
			$this->source( 'src/SiteFixes/SiteFixes.php' )
		);
	}

	/**
	 * An exporter can take the report, and register a format for it.
	 *
	 * Two hooks rather than one, deliberately. The data hook lets an exporter
	 * take exactly what is on screen instead of reassembling it from the
	 * tables and drifting out of step; the format hook is how the control
	 * appears at all, so the free plugin shows no export button rather than a
	 * locked one.
	 *
	 * @return void
	 */
	public function test_the_report_can_be_exported(): void {
		$overview = $this->source( 'src/Rest/OverviewController.php' );

		$this->assertStringContainsString( "apply_filters( 'wsak_report_data'", $overview );
		$this->assertStringContainsString( "apply_filters( 'wsak_report_formats'", $overview );
	}

	/**
	 * Dismissal can be restricted, and only restricted.
	 *
	 * The filter narrows and cannot widen: the capability check runs first and
	 * is combined with `&&`, so a filter returning true cannot hand somebody
	 * the right to make decisions about content they may not edit. That check
	 * is WordPress's, and passing responsibility for it to a third party would
	 * make it possible for another plugin to open a door this one is
	 * answerable for.
	 *
	 * @return void
	 */
	public function test_dismissal_can_be_restricted_but_not_widened(): void {
		$review = $this->source( 'src/Remediation/IssueReview.php' );

		$this->assertStringContainsString( "apply_filters( 'wsak_can_dismiss'", $review );
		$this->assertStringContainsString(
			"\$allowed = \$allowed && (bool) apply_filters( 'wsak_can_dismiss'",
			$review,
			'The filter must be combined with the capability check, never replace it.'
		);
	}

	/**
	 * The site-wide dismissal filter narrows too, and cannot widen.
	 *
	 * The same rule as `wsak_can_dismiss`, and it matters more here: this
	 * decision reaches every page carrying one piece of markup, including pages
	 * belonging to people who are not asking for it. A filter that could return
	 * true and be believed would hand a third party the right to close findings
	 * across content its user may not edit.
	 *
	 * @return void
	 */
	public function test_site_wide_dismissal_can_be_restricted_but_not_widened(): void {
		$review = $this->source( 'src/Remediation/SiteWideReview.php' );

		$this->assertStringContainsString( "apply_filters( 'wsak_can_dismiss_site_wide'", $review );
		$this->assertStringContainsString(
			"return \$allowed && (bool) apply_filters( 'wsak_can_dismiss_site_wide'",
			$review,
			'The filter must be combined with the capability check, never replace it.'
		);
		$this->assertStringContainsString(
			"current_user_can( 'edit_others_posts' )",
			$review,
			'Deciding for the whole site is the right to edit other people\'s content.'
		);
	}

	/**
	 * The free plugin registers no export format of its own.
	 *
	 * The rule from CLAUDE.md: no locked controls. A format advertised here and
	 * not produced would be exactly that.
	 *
	 * @return void
	 */
	public function test_the_free_plugin_offers_no_export(): void {
		$this->assertStringContainsString(
			"apply_filters( 'wsak_report_formats', array()",
			$this->source( 'src/Rest/OverviewController.php' ),
			'The default must be an empty list.'
		);
	}
}
