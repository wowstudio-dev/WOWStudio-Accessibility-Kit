<?php
/**
 * Issue repository tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Mockery;
use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Tests\Doubles\FakeDecisionStore;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\ScanPass;
use WOWStudio\AccessibilityKit\Scanner\Severity;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests issue storage, including the SQL safety boundary.
 *
 * @covers \WOWStudio\AccessibilityKit\Db\IssueRepository
 */
final class IssueRepositoryTest extends TestCase {

	/**
	 * Mock database handle.
	 *
	 * @var Mockery\MockInterface
	 */
	private $wpdb;

	/**
	 * SQL passed to prepare(), captured for assertions.
	 *
	 * @var string
	 */
	private string $sql = '';

	/**
	 * Values passed to prepare(), captured for assertions.
	 *
	 * @var array<int, mixed>
	 */
	private array $values = array();

	/**
	 * Installs a mock $wpdb that records what it was asked to prepare.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';
		$this->wpdb->posts  = 'wp_posts';

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( string $sql, $values = array() ) {
					$this->sql    = $sql;
					$this->values = is_array( $values ) ? $values : array_slice( func_get_args(), 1 );

					return $sql;
				}
			);

		$GLOBALS['wpdb'] = $this->wpdb;
	}

	/**
	 * An empty batch writes nothing and touches no database.
	 *
	 * @return void
	 */
	public function test_empty_batch_does_no_work(): void {
		$this->wpdb->shouldNotReceive( 'query' );

		$this->assertSame( 0, ( new IssueRepository() )->add_many( 1, array() ) );
	}

	/**
	 * A batch is written as a single multi-row INSERT.
	 *
	 * @return void
	 */
	public function test_batch_is_written_as_one_insert(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 2 );

		$written = ( new IssueRepository( new FakeDecisionStore() ) )->add_many(
			7,
			array(
				array(
					'post_id'   => 3,
					'rule_id'   => 'img-alt-missing',
					'wcag_sc'   => '1.1.1',
					'severity'  => Severity::Critical,
					'detection' => Detection::Auto,
					'message'   => 'Image has no alt attribute.',
				),
				array(
					'post_id'   => 3,
					'rule_id'   => 'link-name-vague',
					'wcag_sc'   => '2.4.4',
					'severity'  => Severity::Moderate,
					'detection' => Detection::Manual,
					'message'   => 'Link text is not descriptive.',
				),
			)
		);

		$this->assertSame( 2, $written );
		$this->assertSame( 1, substr_count( $this->sql, 'INSERT INTO' ), 'Should be a single INSERT.' );
		$this->assertSame( 2, substr_count( $this->sql, '(%d, %d, %s' ), 'Should carry one value group per issue.' );
		$this->assertCount( 33, $this->values, 'Sixteen columns times two rows, plus the table identifier.' );
		$this->assertSame( 'wp_wsak_issues', $this->values[0], 'The table goes through prepare(), not into the SQL.' );

		// A finding that does not say which pass produced it is recorded as a
		// server finding, never left empty: found_by drives what the coverage
		// surfaces claim was checked, and an empty value there would quietly
		// misreport what the scan actually looked at.
		$this->assertContains( ScanPass::Server->value, $this->values, 'An unattributed finding defaults to the server pass.' );
	}

	/**
	 * A new scan retires the findings of the last one.
	 *
	 * Without this the issues table is an append-only log that every count then
	 * reads as the present: a page scanned sixteen times contributes sixteen
	 * copies of each fault, and the site-wide numbers drift further from the
	 * truth the more somebody uses the plugin.
	 *
	 * @return void
	 */
	public function test_superseded_findings_are_removed(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 4 );

		$removed = ( new IssueRepository( new FakeDecisionStore() ) )->prune_superseded( 88 );

		$this->assertSame( 4, $removed );
		$this->assertStringContainsString( 'DELETE i FROM %i', $this->sql );
		$this->assertStringContainsString( 'old.scope = keep.scope', $this->sql );
		$this->assertStringContainsString( 'old.target_id = keep.target_id', $this->sql );
		$this->assertStringContainsString( 'old.id <> keep.id', $this->sql, 'The scan being kept must survive its own pruning.' );
		$this->assertContains( 88, $this->values, 'The scan to keep goes through prepare(), not into the SQL.' );
		$this->assertStringNotContainsString( 'wp_wsak_issues', $this->sql );
	}

	/**
	 * A site-wide list defaults to what is still open.
	 *
	 * @return void
	 */
	public function test_current_findings_default_to_open(): void {
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

		( new IssueRepository( new FakeDecisionStore() ) )->find_current();

		$this->assertStringContainsString( 'i.status = %s', $this->sql );
		$this->assertContains( 'open', $this->values );
	}

	/**
	 * Findings against deleted content are left out.
	 *
	 * Their rows outlive the post, and sending somebody to an edit screen that
	 * 404s is a worse answer than not listing the finding.
	 *
	 * @return void
	 */
	public function test_findings_on_deleted_content_are_excluded(): void {
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

		( new IssueRepository( new FakeDecisionStore() ) )->find_current();

		$this->assertStringContainsString( 'p.ID IS NOT NULL', $this->sql );
		// A template finding is bound to no post and must survive the same test.
		$this->assertStringContainsString( 'i.post_id = 0 OR', $this->sql );
	}

	/**
	 * A rule filter narrows the list.
	 *
	 * @return void
	 */
	public function test_a_rule_filter_is_a_prepared_value(): void {
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

		( new IssueRepository( new FakeDecisionStore() ) )
			->find_current( array( 'rule_id' => 'link-name-vague' ) );

		$this->assertStringContainsString( 'i.rule_id = %s', $this->sql );
		$this->assertContains( 'link-name-vague', $this->values );
		$this->assertStringNotContainsString( 'link-name-vague', $this->sql );
	}

	/**
	 * The count and the list ask the same question.
	 *
	 * They sit on the same screen, one as the heading and one as the body. A
	 * filter that reached only one of them would put a page in front of
	 * somebody that disagreed with itself about how much work there was.
	 *
	 * @return void
	 */
	public function test_the_count_and_the_list_share_their_conditions(): void {
		$repository = new IssueRepository( new FakeDecisionStore() );
		$filters    = array(
			'rule_id' => 'link-name-vague',
			'post_id' => 42,
		);

		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );
		$repository->find_current( $filters );
		$list_where  = $this->where_of( $this->sql );
		$list_values = $this->values;

		$this->wpdb->shouldReceive( 'get_var' )->once()->andReturn( 0 );
		$repository->count_current( $filters );
		$count_where  = $this->where_of( $this->sql );
		$count_values = $this->values;

		$this->assertSame( $list_where, $count_where );

		// The list carries two extra values, its limit and its offset.
		$this->assertSame( $count_values, array_slice( $list_values, 0, count( $count_values ) ) );
	}

	/**
	 * Returns everything from WHERE onwards, without the paging tail.
	 *
	 * @param string $sql The query.
	 * @return string
	 */
	private function where_of( string $sql ): string {
		$where = substr( $sql, (int) strpos( $sql, 'WHERE' ) );
		$order = strpos( $where, ' ORDER BY ' );

		return false === $order ? $where : substr( $where, 0, $order );
	}

	/**
	 * The table name is a prepared identifier, never interpolated.
	 *
	 * @return void
	 */
	public function test_table_name_is_a_prepared_identifier(): void {
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

		( new IssueRepository() )->find_by_scan( 1 );

		$this->assertStringContainsString( 'FROM %i', $this->sql );
		$this->assertStringNotContainsString( 'wp_wsak_issues', $this->sql );
		$this->assertSame( 'wp_wsak_issues', $this->values[0] );
	}

	/**
	 * Every value in the batch goes through prepare, never into the SQL.
	 *
	 * @return void
	 */
	public function test_batch_values_are_never_interpolated(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 1 );

		( new IssueRepository( new FakeDecisionStore() ) )->add_many(
			7,
			array(
				array(
					'rule_id' => "evil'); DROP TABLE wp_wsak_issues; --",
					'message' => 'Injected.',
				),
			)
		);

		$this->assertStringNotContainsString( 'DROP TABLE', $this->sql );
		$this->assertContains( "evil'); DROP TABLE wp_wsak_issues; --", $this->values );
	}

	/**
	 * Grouping is restricted to an allow-list.
	 *
	 * The group-by column cannot be parameterised, so anything outside the list
	 * must be refused before it reaches the query.
	 *
	 * @return void
	 */
	public function test_grouping_by_an_unlisted_column_is_refused(): void {
		$this->wpdb->shouldNotReceive( 'get_results' );

		$repository = new IssueRepository();

		$this->assertSame( array(), $repository->count_by( 1, 'id; DROP TABLE wp_wsak_issues' ) );
		$this->assertSame( array(), $repository->count_by( 1, 'note' ) );
		$this->assertSame( array(), $repository->count_by( 1, '' ) );
	}

	/**
	 * Grouping by an allowed column returns counts keyed by value.
	 *
	 * @return void
	 */
	public function test_grouping_by_detection_returns_counts(): void {
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			array(
				(object) array(
					'bucket' => 'auto',
					'total'  => '4',
				),
				(object) array(
					'bucket' => 'manual',
					'total'  => '2',
				),
			)
		);

		$counts = ( new IssueRepository() )->count_by( 1, 'detection' );

		$this->assertSame(
			array(
				'auto'   => 4,
				'manual' => 2,
			),
			$counts
		);
	}

	/**
	 * Results are ordered by real severity, not alphabetically.
	 *
	 * @return void
	 */
	public function test_results_are_ordered_by_severity_weight(): void {
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

		( new IssueRepository() )->find_by_scan( 1 );

		$this->assertStringContainsString(
			"FIELD(severity, 'critical', 'serious', 'moderate', 'minor')",
			$this->sql
		);
	}

	/**
	 * An unbounded page size is clamped.
	 *
	 * @return void
	 */
	public function test_page_size_is_clamped(): void {
		$this->wpdb->shouldReceive( 'get_results' )->twice()->andReturn( array() );

		$repository = new IssueRepository();

		$repository->find_by_scan( 1, array( 'limit' => 100000 ) );
		$this->assertContains( 500, $this->values, 'Should clamp to the maximum.' );

		$repository->find_by_scan( 1, array( 'limit' => -5 ) );
		$this->assertContains( 1, $this->values, 'Should clamp to at least one row.' );
	}
}
