<?php
/**
 * Monitoring tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Db\Scan;
use WOWStudio\AccessibilityKit\Db\ScanRepository;
use WOWStudio\AccessibilityKit\Monitoring\Comparison;
use WOWStudio\AccessibilityKit\Monitoring\Schedule;
use WOWStudio\AccessibilityKit\Scanner\BrowserPassStatus;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\Scanner\ScanStatus;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the schedule and the comparison it exists to feed.
 *
 * @covers \WOWStudio\AccessibilityKit\Monitoring\Schedule
 * @covers \WOWStudio\AccessibilityKit\Monitoring\Comparison
 * @covers \WOWStudio\AccessibilityKit\Monitoring\Change
 */
final class MonitoringTest extends TestCase {

	/**
	 * Options, held in memory for the duration of one test.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * Stubs the handful of WordPress functions monitoring reaches for.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->options = array();

		Functions\when( 'get_option' )->alias(
			function ( $name, $fallback = false ) {
				return $this->options[ $name ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->options[ $name ] = $value;

				return true;
			}
		);
		Functions\when( 'get_the_title' )->justReturn( 'A page' );
	}

	/**
	 * Builds a completed page scan.
	 *
	 * @param int      $id      Scan id.
	 * @param int      $post_id Page it scanned.
	 * @param int|null $score   Score recorded.
	 * @return Scan
	 */
	private function scan( int $id, int $post_id, ?int $score ): Scan {
		return new Scan(
			$id,
			ScanScope::Page,
			$post_id,
			0,
			ScanStatus::Complete,
			$score,
			BrowserPassStatus::Skipped,
			array(),
			'2026-09-06 10:00:00',
			'2026-09-06 10:00:05',
			0
		);
	}

	/**
	 * Builds a Comparison whose stores answer from arrays.
	 *
	 * @param array<int, array<string, int>> $counts  Rule counts, keyed by scan id.
	 * @param Scan[]                         $history Scans, newest first.
	 * @return Comparison
	 */
	private function comparison( array $counts, array $history = array() ): Comparison {
		$issues = new class( $counts ) extends IssueRepository {
			/**
			 * Holds the counts this double answers with.
			 *
			 * @param array<int, array<string, int>> $counts Rule counts by scan id.
			 */
			public function __construct( private array $counts ) {}

			/**
			 * {@inheritDoc}
			 *
			 * @param int    $scan_id Scan.
			 * @param string $column  Column to group by.
			 * @return array<string, int>
			 */
			public function count_by( int $scan_id, string $column = 'severity' ): array {
				return $this->counts[ $scan_id ] ?? array();
			}
		};

		$scans = new class( $history ) extends ScanRepository {
			/**
			 * Holds the scans this double answers with.
			 *
			 * @param Scan[] $history Scans, newest first.
			 */
			public function __construct( private array $history ) {}

			/**
			 * {@inheritDoc}
			 *
			 * @param int $post_id Post.
			 * @param int $limit   How many.
			 * @return Scan[]
			 */
			public function history_for_post( int $post_id, int $limit = 2 ): array {
				return array_slice( $this->history, 0, $limit );
			}
		};

		return new Comparison( $scans, $issues );
	}

	/**
	 * Monitoring is off until somebody asks for it.
	 *
	 * Not caution for its own sake: switching a background job on for somebody
	 * who did not ask means their host starts doing work they did not budget
	 * for, possibly on a plan billed by the CPU-second.
	 *
	 * @return void
	 */
	public function test_scheduled_scanning_is_off_by_default(): void {
		$schedule = new Schedule();

		$this->assertSame( 'off', $schedule->frequency() );
		$this->assertFalse( $schedule->is_on() );
		$this->assertSame( 0, $schedule->interval() );
	}

	/**
	 * A frequency round-trips, and anything not on offer is refused.
	 *
	 * @return void
	 */
	public function test_a_frequency_can_be_set_and_only_a_real_one(): void {
		$schedule = new Schedule();

		$this->assertTrue( $schedule->set( 'weekly' ) );
		$this->assertSame( 'weekly', $schedule->frequency() );
		$this->assertTrue( $schedule->is_on() );
		$this->assertSame( 7 * DAY_IN_SECONDS, $schedule->interval() );

		// Nothing more frequent than weekly is offered, deliberately: a check
		// running more often than the content changes produces a stream of
		// "nothing happened" that people stop reading.
		$this->assertFalse( $schedule->set( 'hourly' ) );
		$this->assertFalse( $schedule->set( 'daily' ) );
		$this->assertSame( 'weekly', $schedule->frequency(), 'A refused frequency must not overwrite the stored one.' );

		$this->assertTrue( $schedule->set( 'off' ) );
		$this->assertFalse( $schedule->is_on() );
	}

	/**
	 * A page scanned once has nothing to compare against.
	 *
	 * Null rather than an empty change, because "no change" and "nothing to
	 * compare" are different statements and reporting the first for the second
	 * would be a claim about a page nobody has looked at twice.
	 *
	 * @return void
	 */
	public function test_one_scan_is_not_a_comparison(): void {
		$comparison = $this->comparison(
			array( 1 => array( 'img-alt-missing' => 2 ) ),
			array( $this->scan( 1, 10, 80 ) )
		);

		$this->assertNull( $comparison->for_post( 10 ) );
	}

	/**
	 * Findings that appeared and findings that went are both reported.
	 *
	 * @return void
	 */
	public function test_what_appeared_and_what_was_resolved(): void {
		$comparison = $this->comparison(
			array(
				1 => array(
					'img-alt-missing'       => 3,
					'heading-level-skipped' => 1,
				),
				2 => array(
					'img-alt-missing' => 1,
					'link-to-file'    => 2,
				),
			),
			array( $this->scan( 2, 10, 88 ), $this->scan( 1, 10, 72 ) )
		);

		$change = $comparison->for_post( 10 );

		$this->assertNotNull( $change );
		$this->assertSame( array( 'link-to-file' => 2 ), $change->appeared );
		$this->assertSame(
			array(
				'heading-level-skipped' => 1,
				'img-alt-missing'       => 2,
			),
			$change->resolved
		);
		$this->assertSame( 16, $change->delta() );
		$this->assertTrue( $change->regressed(), 'A rule that was not failing and now is counts as a regression.' );
		$this->assertFalse( $change->is_empty() );
	}

	/**
	 * A page that only improved has not regressed.
	 *
	 * The distinction the alerting seam depends on: a score can move for
	 * reasons that are nobody's fault, so "regressed" is the narrow, factual
	 * claim that findings appeared which were not there before.
	 *
	 * @return void
	 */
	public function test_improvement_alone_is_not_a_regression(): void {
		$comparison = $this->comparison(
			array(
				1 => array( 'img-alt-missing' => 4 ),
				2 => array( 'img-alt-missing' => 1 ),
			),
			array( $this->scan( 2, 10, 95 ), $this->scan( 1, 10, 60 ) )
		);

		$change = $comparison->for_post( 10 );

		$this->assertNotNull( $change );
		$this->assertSame( array(), $change->appeared );
		$this->assertFalse( $change->regressed() );
		$this->assertSame( array( 'img-alt-missing' => 3 ), $change->resolved );
	}

	/**
	 * Two identical scans report nothing at all.
	 *
	 * @return void
	 */
	public function test_nothing_changing_reports_nothing(): void {
		$comparison = $this->comparison(
			array(
				1 => array( 'img-alt-missing' => 2 ),
				2 => array( 'img-alt-missing' => 2 ),
			),
			array( $this->scan( 2, 10, 80 ), $this->scan( 1, 10, 80 ) )
		);

		$change = $comparison->for_post( 10 );

		$this->assertNotNull( $change );
		$this->assertTrue( $change->is_empty() );
		$this->assertFalse( $change->regressed() );
	}

	/**
	 * A missing score is reported as unknown rather than as zero.
	 *
	 * @return void
	 */
	public function test_an_unknown_score_does_not_become_a_delta(): void {
		$comparison = $this->comparison(
			array(
				1 => array(),
				2 => array( 'img-alt-missing' => 1 ),
			),
			array( $this->scan( 2, 10, 90 ), $this->scan( 1, 10, null ) )
		);

		$change = $comparison->for_post( 10 );

		$this->assertNotNull( $change );
		$this->assertNull( $change->delta(), 'A scan with no score cannot produce a movement.' );
	}
}
