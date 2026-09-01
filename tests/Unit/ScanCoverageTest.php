<?php
/**
 * Tests for the badge that says how much was looked at.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Db\Scan;
use WOWStudio\AccessibilityKit\Scanner\BrowserPassStatus;
use WOWStudio\AccessibilityKit\Scanner\ScanCoverage;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\Scanner\ScanStatus;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the difference between "we checked this" and "we checked all of this".
 *
 * A bulk run reads content and nothing else. If its badge were the same as the
 * inspector's, somebody would check a hundred pages, see no contrast findings,
 * and reasonably conclude they have none — which is the failure this whole
 * product exists not to commit. See decision F4.
 *
 * @covers \WOWStudio\AccessibilityKit\Scanner\ScanCoverage
 */
final class ScanCoverageTest extends TestCase {

	/**
	 * Builds a scan.
	 *
	 * @param ScanStatus        $status  Where it got to.
	 * @param BrowserPassStatus $browser Whether the render-dependent checks ran.
	 * @return Scan
	 */
	private function scan( ScanStatus $status, BrowserPassStatus $browser ): Scan {
		return new Scan(
			1,
			ScanScope::Page,
			7,
			0,
			$status,
			80,
			$browser,
			array(),
			'2026-09-01 00:00:00',
			'2026-09-01 00:00:10',
			1
		);
	}

	/**
	 * A bulk scan earns the smaller claim.
	 *
	 * @return void
	 */
	public function test_a_markup_only_scan_is_not_a_full_check(): void {
		$coverage = ScanCoverage::of( $this->scan( ScanStatus::Complete, BrowserPassStatus::Skipped ) );

		$this->assertSame( ScanCoverage::Content, $coverage );
		$this->assertFalse( $coverage->is_complete() );
	}

	/**
	 * A scan where the browser pass ran earns the larger one.
	 *
	 * @return void
	 */
	public function test_a_scan_with_the_browser_pass_is_a_full_check(): void {
		$coverage = ScanCoverage::of( $this->scan( ScanStatus::Complete, BrowserPassStatus::Ran ) );

		$this->assertSame( ScanCoverage::Full, $coverage );
		$this->assertTrue( $coverage->is_complete() );
	}

	/**
	 * A browser pass that was blocked does not count as having run.
	 *
	 * The frame refused to open, so nothing was measured. Treating that as a
	 * full check would turn a security header on somebody's site into a claim
	 * about their accessibility.
	 *
	 * @return void
	 */
	public function test_a_blocked_browser_pass_is_not_a_full_check(): void {
		$this->assertSame(
			ScanCoverage::Content,
			ScanCoverage::of( $this->scan( ScanStatus::Complete, BrowserPassStatus::Blocked ) )
		);
	}

	/**
	 * Nothing unfinished earns a badge.
	 *
	 * A failed or cancelled scan has findings that may be a fraction of what is
	 * there, and a badge saying "checked" over a partial result is worse than no
	 * badge at all.
	 *
	 * @return void
	 */
	public function test_an_unfinished_scan_earns_nothing(): void {
		foreach ( array( ScanStatus::Queued, ScanStatus::Running, ScanStatus::Failed, ScanStatus::Cancelled ) as $status ) {
			$this->assertSame(
				ScanCoverage::Never,
				ScanCoverage::of( $this->scan( $status, BrowserPassStatus::Ran ) ),
				$status->value . ' should not earn a coverage badge.'
			);
		}
	}

	/**
	 * No scan at all reads as never checked.
	 *
	 * @return void
	 */
	public function test_no_scan_reads_as_never_checked(): void {
		$this->assertSame( ScanCoverage::Never, ScanCoverage::of( null ) );
	}

	/**
	 * The two positive badges do not read as the same claim.
	 *
	 * The whole point of having two. If they were worded alike, the distinction
	 * would exist in the code and not in anybody's head.
	 *
	 * @return void
	 */
	public function test_the_two_positive_badges_say_different_things(): void {
		$this->assertNotSame( ScanCoverage::Content->label(), ScanCoverage::Full->label() );
		$this->assertStringContainsString( 'Content', ScanCoverage::Content->label() );
		$this->assertStringContainsString( 'Fully', ScanCoverage::Full->label() );

		// And the smaller one has to say what it did not look at.
		$this->assertMatchesRegularExpression(
			'/colour|color/i',
			ScanCoverage::Content->blurb(),
			'The partial badge must name what it did not check.'
		);
	}
}
