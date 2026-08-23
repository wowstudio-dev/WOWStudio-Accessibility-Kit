<?php
/**
 * Record hydration tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Db\Issue;
use WOWStudio\AccessibilityKit\Db\Scan;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\Scanner\ScanStatus;
use WOWStudio\AccessibilityKit\Scanner\Severity;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests turning database rows into typed records.
 *
 * @covers \WOWStudio\AccessibilityKit\Db\Scan
 * @covers \WOWStudio\AccessibilityKit\Db\Issue
 */
final class RecordTest extends TestCase {

	/**
	 * Builds a scan row.
	 *
	 * @param array<string, mixed> $overrides Values to override.
	 * @return object
	 */
	private function scan_row( array $overrides = array() ): object {
		return (object) array_merge(
			array(
				'id'          => '12',
				'scope'       => 'page',
				'target_id'   => '34',
				'status'      => 'complete',
				'score'       => '87',
				'summary'     => '{"open":3}',
				'started_at'  => '2026-08-23 10:00:00',
				'finished_at' => '2026-08-23 10:00:05',
				'created_by'  => '1',
			),
			$overrides
		);
	}

	/**
	 * Builds an issue row.
	 *
	 * @param array<string, mixed> $overrides Values to override.
	 * @return object
	 */
	private function issue_row( array $overrides = array() ): object {
		return (object) array_merge(
			array(
				'id'         => '5',
				'scan_id'    => '12',
				'post_id'    => '34',
				'rule_id'    => 'img-alt-missing',
				'wcag_sc'    => '1.1.1',
				'severity'   => 'critical',
				'detection'  => 'auto',
				'status'     => 'open',
				'selector'   => '/html/body/img[1]',
				'context'    => '<img src="a.png">',
				'message'    => 'Image has no alt attribute.',
				'note'       => null,
				'created_at' => '2026-08-23 10:00:00',
				'updated_at' => '2026-08-23 10:00:00',
			),
			$overrides
		);
	}

	/**
	 * Strings from the database become typed values.
	 *
	 * @return void
	 */
	public function test_scan_row_is_typed(): void {
		$scan = Scan::from_row( $this->scan_row() );

		$this->assertSame( 12, $scan->id );
		$this->assertSame( ScanScope::Page, $scan->scope );
		$this->assertSame( ScanStatus::Complete, $scan->status );
		$this->assertSame( 87, $scan->score );
		$this->assertSame( array( 'open' => 3 ), $scan->summary );
	}

	/**
	 * A running scan has no score and no finish time.
	 *
	 * @return void
	 */
	public function test_running_scan_has_null_score_and_finish(): void {
		$scan = Scan::from_row(
			$this->scan_row(
				array(
					'status'      => 'running',
					'score'       => null,
					'finished_at' => null,
				)
			)
		);

		$this->assertNull( $scan->score );
		$this->assertNull( $scan->finished_at );
		$this->assertSame( ScanStatus::Running, $scan->status );
	}

	/**
	 * Malformed summary JSON degrades to an empty array rather than throwing.
	 *
	 * @return void
	 */
	public function test_unreadable_summary_becomes_an_empty_array(): void {
		$scan = Scan::from_row( $this->scan_row( array( 'summary' => 'not json' ) ) );

		$this->assertSame( array(), $scan->summary );
	}

	/**
	 * Issue rows become typed values.
	 *
	 * @return void
	 */
	public function test_issue_row_is_typed(): void {
		$issue = Issue::from_row( $this->issue_row() );

		$this->assertSame( Severity::Critical, $issue->severity );
		$this->assertSame( Detection::Auto, $issue->detection );
		$this->assertSame( IssueStatus::Open, $issue->status );
		$this->assertSame( '', $issue->note );
	}

	/**
	 * An unrecognised detection value falls back to "needs manual review".
	 *
	 * A row written by a newer version must not fatal an older one, and when we
	 * cannot tell whether a machine settled something, the honest answer is that
	 * a person still has to look.
	 *
	 * @return void
	 */
	public function test_unknown_detection_falls_back_to_manual(): void {
		$issue = Issue::from_row( $this->issue_row( array( 'detection' => 'quantum' ) ) );

		$this->assertSame( Detection::Manual, $issue->detection );
	}

	/**
	 * An unrecognised severity falls back to moderate rather than throwing.
	 *
	 * @return void
	 */
	public function test_unknown_severity_falls_back_to_moderate(): void {
		$issue = Issue::from_row( $this->issue_row( array( 'severity' => 'apocalyptic' ) ) );

		$this->assertSame( Severity::Moderate, $issue->severity );
	}
}
