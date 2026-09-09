<?php
/**
 * Admin list-table column tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Admin\ContentColumns;
use WOWStudio\AccessibilityKit\Db\Scan;
use WOWStudio\AccessibilityKit\Db\ScanRepository;
use WOWStudio\AccessibilityKit\Scanner\BrowserPassStatus;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\Scanner\ScanStatus;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the accessibility column on the Posts and Pages screens.
 *
 * @covers \WOWStudio\AccessibilityKit\Admin\ContentColumns
 */
final class ContentColumnsTest extends TestCase {

	/**
	 * Every post type named is registered, as far as these tests care.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'post_type_exists' )->justReturn( true );
	}

	/**
	 * A row as a list table hands it over: an id and a type.
	 *
	 * The type is not decoration. Priming ignores posts of types this plugin
	 * does not scan, which is what stops a query for something else consuming
	 * the lookup, so a fixture without one is not testing the real path.
	 *
	 * @param int    $id   Post id.
	 * @param string $type Post type.
	 * @return object
	 */
	private function page( int $id, string $type = 'page' ): object {
		return (object) array(
			'ID'        => $id,
			'post_type' => $type,
		);
	}

	/**
	 * Builds a column reading from a fixed set of scans.
	 *
	 * @param array<int, Scan> $scans Scans keyed by post id.
	 * @return ContentColumns
	 */
	private function columns( array $scans ): ContentColumns {
		$repository = new class( $scans ) extends ScanRepository {
			/**
			 * Holds the scans this double answers with.
			 *
			 * @param array<int, Scan> $scans Scans keyed by post id.
			 */
			public function __construct( private array $scans ) {}

			/**
			 * {@inheritDoc}
			 *
			 * @param int[] $post_ids Posts.
			 * @return array<int, Scan>
			 */
			public function latest_for_posts( array $post_ids ): array {
				return array_intersect_key( $this->scans, array_flip( $post_ids ) );
			}
		};

		return new ContentColumns( $repository );
	}

	/**
	 * Builds a completed scan.
	 *
	 * @param int      $post_id Page.
	 * @param int|null $score   Score.
	 * @param int      $total   Findings.
	 * @return Scan
	 */
	private function scan( int $post_id, ?int $score, int $total ): Scan {
		return new Scan(
			1,
			ScanScope::Page,
			$post_id,
			0,
			ScanStatus::Complete,
			$score,
			BrowserPassStatus::Skipped,
			array( 'total' => $total ),
			'2026-09-06 10:00:00',
			'2026-09-06 10:00:05',
			0
		);
	}

	/**
	 * Renders one cell and returns it.
	 *
	 * @param ContentColumns $columns The column.
	 * @param int            $post_id The row.
	 * @return string
	 */
	private function cell( ContentColumns $columns, int $post_id ): string {
		ob_start();
		$columns->render( 'wsak_accessibility', $post_id );

		return (string) ob_get_clean();
	}

	/**
	 * The column goes before the date, not after it.
	 *
	 * Date is conventionally last, and appending after it would push the thing
	 * people actually scan the row for off the end.
	 *
	 * @return void
	 */
	public function test_the_column_sits_before_the_date(): void {
		$columns = $this->columns( array() )->add_column(
			array(
				'cb'    => '',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		$this->assertSame( array( 'cb', 'title', 'wsak_accessibility', 'date' ), array_keys( $columns ) );
	}

	/**
	 * A screen with no date column still gets the column.
	 *
	 * @return void
	 */
	public function test_a_screen_without_a_date_column_still_works(): void {
		$columns = $this->columns( array() )->add_column( array( 'title' => 'Title' ) );

		$this->assertArrayHasKey( 'wsak_accessibility', $columns );
	}

	/**
	 * A page nobody has scanned says so, rather than showing a zero.
	 *
	 * The distinction this plugin exists to keep: "nobody looked" and "nothing
	 * found" are different statements, and rendering them alike would be our
	 * own version of the fault we report on other people's sites.
	 *
	 * @return void
	 */
	public function test_an_unscanned_page_is_not_a_zero(): void {
		$columns = $this->columns( array() );
		$columns->prime( array( $this->page( 7 ) ) );

		$cell = $this->cell( $columns, 7 );

		$this->assertStringContainsString( 'Not checked', $cell );
		$this->assertStringNotContainsString( '0/100', $cell );
		$this->assertStringNotContainsString( '0 findings', $cell );
	}

	/**
	 * Another query on the same screen cannot spend the lookup.
	 *
	 * This is the bug, and it was reported as "every page says Not checked".
	 * Priming used to happen once, from whichever query fired `the_posts`
	 * first, on the assumption that was the list table's. On a block theme it
	 * is not: the Pages screen runs a `wp_global_styles` query before the list.
	 * The one lookup this class allowed itself went on a single post nobody was
	 * going to render, came back empty, and empty was stored — which the guard
	 * then read as "already primed". Every row after that said "Not checked"
	 * over pages with nineteen completed scans behind them.
	 *
	 * @return void
	 */
	public function test_a_query_for_something_else_does_not_consume_the_lookup(): void {
		$columns = $this->columns( array( 7 => $this->scan( 7, 82, 4 ) ) );

		// What a block theme runs before the list table's own query.
		$columns->prime( array( $this->page( 39, 'wp_global_styles' ) ) );

		// The list table's query, arriving second.
		$columns->prime( array( $this->page( 7 ) ) );

		$this->assertStringContainsString( '82/100', $this->cell( $columns, 7 ) );
	}

	/**
	 * A second query primes the rows the first one did not cover.
	 *
	 * The same screen can run more than one query for the same post type, and
	 * a class that primes once has nothing to say about the rows in the second.
	 *
	 * @return void
	 */
	public function test_a_later_query_primes_the_rows_the_first_one_missed(): void {
		$columns = $this->columns(
			array(
				7 => $this->scan( 7, 82, 4 ),
				9 => $this->scan( 9, 51, 6 ),
			)
		);

		$columns->prime( array( $this->page( 7 ) ) );
		$columns->prime( array( $this->page( 9 ) ) );

		$this->assertStringContainsString( '82/100', $this->cell( $columns, 7 ) );
		$this->assertStringContainsString( '51/100', $this->cell( $columns, 9 ) );
	}

	/**
	 * A row the batch never saw is still reported honestly.
	 *
	 * One indexed query for one row, and worth paying: "Not checked" has to be
	 * a fact about the page rather than about our own bookkeeping.
	 *
	 * @return void
	 */
	public function test_a_row_that_was_never_primed_is_looked_up(): void {
		$columns = $this->columns( array( 7 => $this->scan( 7, 82, 4 ) ) );

		$this->assertStringContainsString( '82/100', $this->cell( $columns, 7 ) );
	}

	/**
	 * A scanned page shows its score and how many findings are open.
	 *
	 * @return void
	 */
	public function test_a_scanned_page_shows_its_score_and_count(): void {
		$columns = $this->columns( array( 7 => $this->scan( 7, 82, 4 ) ) );
		$columns->prime( array( $this->page( 7 ) ) );

		$cell = $this->cell( $columns, 7 );

		$this->assertStringContainsString( '82/100', $cell );
		$this->assertStringContainsString( '4 findings', $cell );
	}

	/**
	 * A single finding is not called "1 findings".
	 *
	 * @return void
	 */
	public function test_one_finding_reads_as_one(): void {
		$columns = $this->columns( array( 7 => $this->scan( 7, 95, 1 ) ) );
		$columns->prime( array( $this->page( 7 ) ) );

		$this->assertStringContainsString( '1 finding', $this->cell( $columns, 7 ) );
		$this->assertStringNotContainsString( '1 findings', $this->cell( $columns, 7 ) );
	}

	/**
	 * The band is colour only, and carries no verdict in words.
	 *
	 * Calling a page "good" on the strength of automated checks that cover part
	 * of WCAG is the judgement this product does not issue, so the class name
	 * is a position and the cell says nothing beyond the number.
	 *
	 * @return void
	 */
	public function test_the_band_is_a_class_and_not_a_verdict(): void {
		foreach ( array(
			95 => 'high',
			70 => 'mid',
			20 => 'low',
		) as $score => $band ) {
			$columns = $this->columns( array( 7 => $this->scan( 7, $score, 1 ) ) );
			$columns->prime( array( $this->page( 7 ) ) );

			$cell = $this->cell( $columns, 7 );

			$this->assertStringContainsString( 'wsak-col--' . $band, $cell );

			foreach ( array( 'good', 'bad', 'pass', 'fail', 'compliant' ) as $verdict ) {
				$this->assertStringNotContainsString(
					'>' . $verdict,
					strtolower( $cell ),
					'The cell must not put a verdict in words.'
				);
			}
		}
	}

	/**
	 * Another column's cell is left alone.
	 *
	 * @return void
	 */
	public function test_other_columns_are_not_touched(): void {
		$columns = $this->columns( array( 7 => $this->scan( 7, 82, 4 ) ) );
		$columns->prime( array( $this->page( 7 ) ) );

		ob_start();
		$columns->render( 'author', 7 );

		$this->assertSame( '', (string) ob_get_clean() );
	}
}
