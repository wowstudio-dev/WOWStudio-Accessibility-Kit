<?php
/**
 * Scan repository tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use WOWStudio\AccessibilityKit\Db\ScanRepository;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests scan run storage.
 *
 * @covers \WOWStudio\AccessibilityKit\Db\ScanRepository
 */
final class ScanRepositoryTest extends TestCase {

	/**
	 * Mock database handle.
	 *
	 * @var Mockery\MockInterface
	 */
	private $wpdb;

	/**
	 * Installs a mock $wpdb.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->wpdb            = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix    = 'wp_';
		$this->wpdb->insert_id = 0;

		$GLOBALS['wpdb'] = $this->wpdb;

		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	/**
	 * A new scan is recorded as running, with no score yet.
	 *
	 * @return void
	 */
	public function test_start_records_a_running_scan(): void {
		$captured = array();

		$this->wpdb->insert_id = 42;
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturnUsing(
				function ( string $table, array $data ) use ( &$captured ): int {
					$captured = $data;

					return 1;
				}
			);

		$id = ( new ScanRepository() )->start( ScanScope::Page, 7, 3 );

		$this->assertSame( 42, $id );
		$this->assertSame( 'running', $captured['status'] );
		$this->assertSame( 'page', $captured['scope'] );
		$this->assertSame( 7, $captured['target_id'] );
		$this->assertSame( 3, $captured['created_by'] );
		$this->assertArrayNotHasKey( 'score', $captured, 'A scan has no score until it finishes.' );
	}

	/**
	 * A failed insert returns 0 rather than a bogus ID.
	 *
	 * @return void
	 */
	public function test_failed_insert_returns_zero(): void {
		$this->wpdb->shouldReceive( 'insert' )->once()->andReturn( false );

		$this->assertSame( 0, ( new ScanRepository() )->start( ScanScope::Page ) );
	}

	/**
	 * Completing a scan stores the score, summary, and finish time.
	 *
	 * @return void
	 */
	public function test_complete_stores_score_and_summary(): void {
		$captured = array();

		$this->wpdb->shouldReceive( 'update' )
			->once()
			->andReturnUsing(
				function ( string $table, array $data ) use ( &$captured ): int {
					$captured = $data;

					return 1;
				}
			);

		$done = ( new ScanRepository() )->complete( 42, 87, array( 'open' => 3 ) );

		$this->assertTrue( $done );
		$this->assertSame( 'complete', $captured['status'] );
		$this->assertSame( 87, $captured['score'] );
		$this->assertSame( '{"open":3}', $captured['summary'] );
		$this->assertNotEmpty( $captured['finished_at'] );
	}

	/**
	 * An interrupted run is recorded as failed, not left looking clean.
	 *
	 * A scan that died half way through has found nothing useful. Leaving it
	 * marked running, or silently complete, would misrepresent the site.
	 *
	 * @return void
	 */
	public function test_fail_marks_the_run_failed(): void {
		$captured = array();

		$this->wpdb->shouldReceive( 'update' )
			->once()
			->andReturnUsing(
				function ( string $table, array $data ) use ( &$captured ): int {
					$captured = $data;

					return 1;
				}
			);

		( new ScanRepository() )->fail( 42 );

		$this->assertSame( 'failed', $captured['status'] );
		$this->assertArrayNotHasKey( 'score', $captured );
	}

	/**
	 * Deleting a scan removes its issues first.
	 *
	 * There are no foreign keys, so the order matters: issues orphaned by a
	 * deleted scan would still be read by the issue list.
	 *
	 * @return void
	 */
	public function test_delete_removes_issues_before_the_scan(): void {
		$order = array();

		$this->wpdb->shouldReceive( 'delete' )
			->twice()
			->andReturnUsing(
				function ( string $table ) use ( &$order ): int {
					$order[] = $table;

					return 1;
				}
			);

		( new ScanRepository() )->delete( 42 );

		$this->assertSame( array( 'wp_wsak_issues', 'wp_wsak_scans' ), $order );
	}

	/**
	 * A missing row returns null rather than a half-built record.
	 *
	 * @return void
	 */
	public function test_find_returns_null_when_absent(): void {
		$this->wpdb->shouldReceive( 'prepare' )->andReturn( 'SQL' );
		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );

		$this->assertNull( ( new ScanRepository() )->find( 999 ) );
	}

	/**
	 * The recent list is clamped so a caller cannot ask for everything.
	 *
	 * @return void
	 */
	public function test_recent_clamps_the_limit(): void {
		$captured = array();

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( string $sql, string $table, int $limit ) use ( &$captured ): string {
					$captured[] = $limit;

					return $sql;
				}
			);
		$this->wpdb->shouldReceive( 'get_results' )->twice()->andReturn( array() );

		$repository = new ScanRepository();
		$repository->recent( 100000 );
		$repository->recent( 0 );

		$this->assertSame( array( 100, 1 ), $captured );
	}
}
