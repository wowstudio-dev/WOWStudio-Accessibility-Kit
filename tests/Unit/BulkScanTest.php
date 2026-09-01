<?php
/**
 * Tests for the bulk-scan run model.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Jobs\BulkScan;
use WOWStudio\AccessibilityKit\Jobs\Progress;
use WOWStudio\AccessibilityKit\Jobs\Queue;
use WOWStudio\AccessibilityKit\Scanner\ScanStatus;
use WOWStudio\AccessibilityKit\Tests\Doubles\FakePage;
use WOWStudio\AccessibilityKit\Tests\Doubles\FakeQueue;
use WOWStudio\AccessibilityKit\Tests\Doubles\FakeScanStore;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests what a run does when the world does not cooperate.
 *
 * A bulk run is the first thing in this plugin that happens while nobody is
 * watching, in a request with no user, possibly hours after somebody pressed a
 * button. So the interesting cases are all failures: the scheduler missing, the
 * run cancelled between queueing and running, the same job delivered twice, a
 * page that throws. The happy path gets one test; the rest get the others.
 *
 * @covers \WOWStudio\AccessibilityKit\Jobs\BulkScan
 * @covers \WOWStudio\AccessibilityKit\Jobs\Progress
 */
final class BulkScanTest extends TestCase {

	/**
	 * Wires the WordPress helpers this class reaches for.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'is_wp_error' )->alias(
			static fn( $thing ): bool => $thing instanceof \WP_Error
		);
		Functions\when( 'absint' )->alias( static fn( $value ): int => (int) abs( (int) $value ) );
	}

	/**
	 * Builds a manager wired to the doubles.
	 *
	 * @param FakeScanStore $scans   Scan storage.
	 * @param FakeQueue     $queue   Scheduler.
	 * @param FakePage      $scanner Page scanner.
	 * @return BulkScan
	 */
	private function manager( FakeScanStore $scans, FakeQueue $queue, FakePage $scanner ): BulkScan {
		return new BulkScan( $scans, $queue, $scanner );
	}

	/**
	 * A run queues one scan per page and reports itself unfinished.
	 *
	 * @return void
	 */
	public function test_a_run_queues_every_page(): void {
		$scans = new FakeScanStore();
		$queue = new FakeQueue();

		$run = $this->manager( $scans, $queue, new FakePage( $scans ) )->start( array( 11, 12, 13 ), 1 );

		$this->assertIsInt( $run );
		$this->assertCount( 3, $queue->enqueued );

		$progress = $this->manager( $scans, $queue, new FakePage( $scans ) )->progress( $run );
		$this->assertSame( 3, $progress->queued );
		$this->assertSame( 3, $progress->total() );
		$this->assertFalse( $progress->is_finished() );
	}

	/**
	 * The same page listed twice is scanned once.
	 *
	 * @return void
	 */
	public function test_duplicates_in_the_selection_are_collapsed(): void {
		$scans = new FakeScanStore();
		$queue = new FakeQueue();

		$this->manager( $scans, $queue, new FakePage( $scans ) )->start( array( 11, 11, 12, 0, 11 ), 1 );

		$this->assertCount( 2, $queue->enqueued );
	}

	/**
	 * With no scheduler, a run refuses rather than silently doing nothing.
	 *
	 * The failure mode this prevents is the worst kind: a progress bar that
	 * never moves, on a site where Action Scheduler failed to load, with no
	 * indication that nothing was ever going to happen.
	 *
	 * @return void
	 */
	public function test_a_run_refuses_when_there_is_no_scheduler(): void {
		$queue            = new FakeQueue();
		$queue->available = false;

		$scans  = new FakeScanStore();
		$result = $this->manager( $scans, $queue, new FakePage( $scans ) )->start( array( 11 ), 1 );

		$error = $this->assertWPError( $result );
		$this->assertSame( 'wsak_no_scheduler', $error->get_error_code() );
	}

	/**
	 * An empty selection is refused.
	 *
	 * @return void
	 */
	public function test_an_empty_selection_is_refused(): void {
		$scans  = new FakeScanStore();
		$result = $this->manager( $scans, new FakeQueue(), new FakePage( $scans ) )->start( array( 0, 0 ), 1 );

		$this->assertSame( 'wsak_nothing_selected', $this->assertWPError( $result )->get_error_code() );
	}

	/**
	 * A selection larger than one run allows is refused before anything is written.
	 *
	 * @return void
	 */
	public function test_an_oversized_selection_is_refused(): void {
		$scans  = new FakeScanStore();
		$result = $this->manager( $scans, new FakeQueue(), new FakePage( $scans ) )
			->start( range( 1, BulkScan::MAX_PAGES + 1 ), 1 );

		$this->assertSame( 'wsak_too_many', $this->assertWPError( $result )->get_error_code() );
		$this->assertSame( array(), $scans->rows, 'Nothing may be recorded for a run that was refused.' );
	}

	/**
	 * Running a step scans the page and completes the run.
	 *
	 * @return void
	 */
	public function test_a_step_scans_its_page_and_settles_the_run(): void {
		$scans   = new FakeScanStore();
		$queue   = new FakeQueue();
		$scanner = new FakePage( $scans );

		$run     = $this->manager( $scans, $queue, $scanner )->start( array( 11 ), 1 );
		$scan_id = $queue->enqueued[0]['scan_id'];

		$this->manager( $scans, $queue, $scanner )->step( $scan_id );

		$this->assertSame( array( 11 ), $scanner->scanned );
		$this->assertSame( ScanStatus::Complete->value, $scans->rows[ $scan_id ]['status'] );
		$this->assertSame( ScanStatus::Complete->value, $scans->rows[ $run ]['status'] );
	}

	/**
	 * The same job delivered twice scans once.
	 *
	 * Action Scheduler is at-least-once, not exactly-once. Without the claim
	 * this would write a second set of findings for the same page and the
	 * counts would exceed the run.
	 *
	 * @return void
	 */
	public function test_a_duplicated_job_does_not_scan_twice(): void {
		$scans   = new FakeScanStore();
		$queue   = new FakeQueue();
		$scanner = new FakePage( $scans );

		$this->manager( $scans, $queue, $scanner )->start( array( 11 ), 1 );
		$scan_id = $queue->enqueued[0]['scan_id'];

		$this->manager( $scans, $queue, $scanner )->step( $scan_id );
		$this->manager( $scans, $queue, $scanner )->step( $scan_id );

		$this->assertSame( array( 11 ), $scanner->scanned );
	}

	/**
	 * A cancelled run does no further work, even for jobs already queued.
	 *
	 * Unscheduling races with a runner that has already claimed a batch, so the
	 * status check is what actually stops it.
	 *
	 * @return void
	 */
	public function test_a_cancelled_run_does_not_keep_scanning(): void {
		$scans   = new FakeScanStore();
		$queue   = new FakeQueue();
		$scanner = new FakePage( $scans );

		$run = $this->manager( $scans, $queue, $scanner )->start( array( 11, 12 ), 1 );
		$this->manager( $scans, $queue, $scanner )->cancel( $run );

		foreach ( $queue->enqueued as $action ) {
			$this->manager( $scans, $queue, $scanner )->step( $action['scan_id'] );
		}

		$this->assertSame( array(), $scanner->scanned );
		$this->assertSame( array( $run ), $queue->cancelled );
		$this->assertSame( ScanStatus::Cancelled->value, $scans->rows[ $run ]['status'] );
	}

	/**
	 * Cancelling leaves finished work alone.
	 *
	 * @return void
	 */
	public function test_cancelling_keeps_what_was_already_found(): void {
		$scans   = new FakeScanStore();
		$queue   = new FakeQueue();
		$scanner = new FakePage( $scans );

		$run = $this->manager( $scans, $queue, $scanner )->start( array( 11, 12 ), 1 );
		$this->manager( $scans, $queue, $scanner )->step( $queue->enqueued[0]['scan_id'] );
		$this->manager( $scans, $queue, $scanner )->cancel( $run );

		$progress = $this->manager( $scans, $queue, $scanner )->progress( $run );

		$this->assertSame( 1, $progress->complete );
		$this->assertSame( 1, $progress->cancelled );
		$this->assertTrue( $progress->is_finished() );
	}

	/**
	 * A page that throws fails that page and lets the run carry on.
	 *
	 * One unparseable page must not take the queue down with it, and it must
	 * not be re-attempted for ever by the scheduler's own retry.
	 *
	 * @return void
	 */
	public function test_a_page_that_throws_fails_only_itself(): void {
		$scans             = new FakeScanStore();
		$queue             = new FakeQueue();
		$scanner           = new FakePage( $scans );
		$scanner->throw_on = 12;

		$run = $this->manager( $scans, $queue, $scanner )->start( array( 11, 12, 13 ), 1 );

		foreach ( $queue->enqueued as $action ) {
			$this->manager( $scans, $queue, $scanner )->step( $action['scan_id'] );
		}

		$progress = $this->manager( $scans, $queue, $scanner )->progress( $run );

		$this->assertSame( 2, $progress->complete );
		$this->assertSame( 1, $progress->failed );
		$this->assertTrue( $progress->is_finished() );
		$this->assertSame( ScanStatus::Complete->value, $scans->rows[ $run ]['status'] );
	}

	/**
	 * The reason a page failed is kept with the row.
	 *
	 * @return void
	 */
	public function test_a_failure_records_why(): void {
		$scans             = new FakeScanStore();
		$queue             = new FakeQueue();
		$scanner           = new FakePage( $scans );
		$scanner->throw_on = 11;

		$this->manager( $scans, $queue, $scanner )->start( array( 11 ), 1 );
		$scan_id = $queue->enqueued[0]['scan_id'];
		$this->manager( $scans, $queue, $scanner )->step( $scan_id );

		$this->assertStringContainsString( 'no good', $scans->rows[ $scan_id ]['reason'] );
	}

	/**
	 * Cancelling something that is not a run is refused.
	 *
	 * @return void
	 */
	public function test_cancelling_an_unknown_run_is_refused(): void {
		$scans  = new FakeScanStore();
		$result = $this->manager( $scans, new FakeQueue(), new FakePage( $scans ) )->cancel( 999 );

		$this->assertSame( 'wsak_unknown_run', $this->assertWPError( $result )->get_error_code() );
	}
}
