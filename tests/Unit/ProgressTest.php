<?php
/**
 * Tests for a run's progress counts.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Jobs\Progress;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Counts, and the questions asked of them.
 *
 * @covers \WOWStudio\AccessibilityKit\Jobs\Progress
 */
final class ProgressTest extends TestCase {

	/**
	 * A run with nothing in it is not reported as complete.
	 *
	 * @return void
	 */
	public function test_an_empty_run_is_zero_per_cent(): void {
		$this->assertSame( 0, ( new Progress() )->percent() );
	}

	/**
	 * Skipped pages count towards the end, but never towards success.
	 *
	 * A bar that fills up after failing nine pages has told the reader
	 * something false, so the two numbers stay apart.
	 *
	 * @return void
	 */
	public function test_failures_finish_the_run_without_counting_as_done(): void {
		$progress = new Progress( 0, 0, 1, 9, 0 );

		$this->assertTrue( $progress->is_finished() );
		$this->assertSame( 100, $progress->percent() );
		$this->assertSame( 1, $progress->complete );
		$this->assertSame( 9, $progress->failed );
	}

	/**
	 * Anything still owed keeps the run open.
	 *
	 * @return void
	 */
	public function test_work_in_flight_keeps_the_run_open(): void {
		$this->assertFalse( ( new Progress( 0, 1, 5, 0, 0 ) )->is_finished() );
		$this->assertFalse( ( new Progress( 1, 0, 5, 0, 0 ) )->is_finished() );
	}

	/**
	 * Percentages round down, so nothing reads as finished early.
	 *
	 * @return void
	 */
	public function test_progress_rounds_down(): void {
		$this->assertSame( 99, ( new Progress( 1, 0, 199, 0, 0 ) )->percent() );
	}
}
