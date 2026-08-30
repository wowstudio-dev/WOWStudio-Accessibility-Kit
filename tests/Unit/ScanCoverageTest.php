<?php
/**
 * Coverage-honesty tests for the two-pass scanner.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use WOWStudio\AccessibilityKit\Db\ScanRepository;
use WOWStudio\AccessibilityKit\Scanner\BrowserPassStatus;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\Finding;
use WOWStudio\AccessibilityKit\Scanner\ScanPass;
use WOWStudio\AccessibilityKit\Scanner\Severity;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * The scanner has two passes, and only one of them can see a rendered page.
 *
 * These tests guard a single product rule: a scan must never present as more
 * thorough than it was. The server pass always runs; the browser pass runs only
 * where a browser exists, and it can be blocked by an ordinary, blameless site
 * configuration. Whenever it does not run, the result has to say so — because
 * "we found nothing" and "we did not look" are different sentences, and telling
 * a user the first when the second is true is the failure this product exists
 * to prevent.
 *
 * @covers \WOWStudio\AccessibilityKit\Scanner\BrowserPassStatus
 * @covers \WOWStudio\AccessibilityKit\Scanner\ScanPass
 */
final class ScanCoverageTest extends TestCase {

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

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';

		$GLOBALS['wpdb'] = $this->wpdb;

		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	/**
	 * Only a pass that actually ran counts as coverage.
	 *
	 * The whole honesty model reduces to this one predicate, so it is asserted
	 * over every case rather than only the interesting one. A future case added
	 * without thought fails here.
	 *
	 * @return void
	 */
	public function test_only_a_pass_that_ran_counts_as_covered(): void {
		$this->assertTrue( BrowserPassStatus::Ran->covered() );

		foreach ( BrowserPassStatus::cases() as $status ) {
			if ( BrowserPassStatus::Ran === $status ) {
				continue;
			}

			$this->assertFalse(
				$status->covered(),
				sprintf( '"%s" must not count as coverage — the checks did not run.', $status->value )
			);
		}
	}

	/**
	 * Every outcome can explain itself to a user.
	 *
	 * A status with no consequence text would reach the screen as an unexplained
	 * state, which is how a partial scan quietly starts reading like a full one.
	 *
	 * @return void
	 */
	public function test_every_outcome_explains_what_it_means(): void {
		foreach ( BrowserPassStatus::cases() as $status ) {
			$this->assertNotSame( '', trim( $status->label() ), $status->value . ' has no label.' );
			$this->assertNotSame( '', trim( $status->consequence() ), $status->value . ' has no consequence text.' );
		}

		// The two failure states must say plainly that something was not checked,
		// rather than describing the failure and leaving the user to infer it.
		$this->assertStringContainsString( 'not', strtolower( BrowserPassStatus::Blocked->consequence() ) );
		$this->assertStringContainsString( 'not', strtolower( BrowserPassStatus::Skipped->consequence() ) );
	}

	/**
	 * A caller that says nothing gets the honest answer, not the flattering one.
	 *
	 * Every scan path that existed before the browser pass calls complete()
	 * without mentioning it. Defaulting to Skipped means those paths keep
	 * telling the truth for free; defaulting to Ran would have silently
	 * relabelled every server-only scan as fully checked.
	 *
	 * @return void
	 */
	public function test_completing_a_scan_without_saying_defaults_to_not_covered(): void {
		$captured = array();

		$this->wpdb->shouldReceive( 'update' )
			->once()
			->andReturnUsing(
				function ( $table, $data ) use ( &$captured ) {
					$captured = $data;

					return 1;
				}
			);

		( new ScanRepository() )->complete( 5, 100, array() );

		$this->assertSame( BrowserPassStatus::Skipped->value, $captured['browser_pass'] );
		$this->assertFalse( BrowserPassStatus::from( $captured['browser_pass'] )->covered() );
	}

	/**
	 * A scan that ran the browser pass records it.
	 *
	 * @return void
	 */
	public function test_a_browser_scan_records_that_it_ran(): void {
		$captured = array();

		$this->wpdb->shouldReceive( 'update' )
			->once()
			->andReturnUsing(
				function ( $table, $data ) use ( &$captured ) {
					$captured = $data;

					return 1;
				}
			);

		( new ScanRepository() )->complete( 5, 92, array(), BrowserPassStatus::Ran );

		$this->assertSame( BrowserPassStatus::Ran->value, $captured['browser_pass'] );
	}

	/**
	 * A finding is attributed to the server pass unless it says otherwise.
	 *
	 * Every rule written before the browser pass existed constructs a Finding
	 * without naming one, and all of them are server rules, so the default is
	 * both convenient and true.
	 *
	 * @return void
	 */
	public function test_findings_default_to_the_server_pass(): void {
		$finding = new Finding( 'img-alt-missing', '1.1.1', Severity::Critical, Detection::Auto, 'No alt attribute.' );

		$this->assertSame( ScanPass::Server, $finding->pass );
		$this->assertSame( ScanPass::Server, $finding->to_row( 3 )['found_by'] );
	}

	/**
	 * A browser finding survives the trip into a storable row.
	 *
	 * @return void
	 */
	public function test_a_browser_finding_keeps_its_attribution(): void {
		$finding = new Finding(
			'colour-contrast',
			'1.4.3',
			Severity::Serious,
			Detection::Auto,
			'Text contrast is 2.1:1.',
			'/html/body/p[1]',
			'<p>Hello</p>',
			ScanPass::Browser
		);

		$this->assertSame( ScanPass::Browser, $finding->to_row( 3 )['found_by'] );
	}

	/**
	 * Both passes describe themselves in terms a non-expert can act on.
	 *
	 * @return void
	 */
	public function test_both_passes_describe_what_they_can_see(): void {
		foreach ( ScanPass::cases() as $pass ) {
			$this->assertNotSame( '', trim( $pass->label() ), $pass->value . ' has no label.' );
			$this->assertNotSame( '', trim( $pass->description() ), $pass->value . ' has no description.' );
		}
	}
}
