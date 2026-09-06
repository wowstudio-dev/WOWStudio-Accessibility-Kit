<?php
/**
 * Findings keep their identity, and decisions about them survive.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Mockery;
use WOWStudio\AccessibilityKit\Db\Decision;
use WOWStudio\AccessibilityKit\Db\DecisionRepository;
use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Scanner\Fingerprint;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WOWStudio\AccessibilityKit\Scanner\Severity;
use WOWStudio\AccessibilityKit\Tests\Doubles\FakeDecisionStore;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the identity a finding carries and the decisions attached to it.
 *
 * @covers \WOWStudio\AccessibilityKit\Scanner\Fingerprint
 * @covers \WOWStudio\AccessibilityKit\Db\DecisionRepository
 */
final class DecisionTest extends TestCase {

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
	 * Two different images are two different findings.
	 *
	 * The temptation when writing the fingerprint is to strip attributes so
	 * that "similar" markup groups together. This is the test that says no: if
	 * these two collided, one decision would retire a finding about a
	 * photograph nobody had looked at, and the page would report itself
	 * reviewed when it was not.
	 *
	 * @return void
	 */
	public function test_two_different_images_do_not_share_an_identity(): void {
		$this->assertNotSame(
			Fingerprint::of( 'img-alt-missing', '<img src="a.jpg" alt="">' ),
			Fingerprint::of( 'img-alt-missing', '<img src="b.jpg" alt="">' )
		);
	}

	/**
	 * The same markup on two pages is the same finding.
	 *
	 * What lets one decision cover the social icons a theme prints into every
	 * footer, rather than asking somebody to judge them once per page.
	 *
	 * @return void
	 */
	public function test_the_same_markup_shares_an_identity(): void {
		$footer = '<svg aria-hidden="true" focusable="false"><path d="M0 0"/></svg>';

		$this->assertSame(
			Fingerprint::of( 'aria-hidden-focusable', $footer ),
			Fingerprint::of( 'aria-hidden-focusable', $footer )
		);
	}

	/**
	 * Reindenting a template does not create a new finding.
	 *
	 * @return void
	 */
	public function test_whitespace_does_not_change_identity(): void {
		$this->assertSame(
			Fingerprint::of( 'link-name-vague', '<a href="#">read   more</a>' ),
			Fingerprint::of( 'link-name-vague', "<a href=\"#\">read\n\tmore</a>" )
		);
	}

	/**
	 * One element failing two rules is two findings, decided separately.
	 *
	 * @return void
	 */
	public function test_the_rule_is_part_of_the_identity(): void {
		$markup = '<a href="#"><img src="a.jpg"></a>';

		$this->assertNotSame(
			Fingerprint::of( 'img-alt-missing', $markup ),
			Fingerprint::of( 'link-name-vague', $markup )
		);
	}

	/**
	 * A dismissal survives the scan that found it.
	 *
	 * The regression test for the bug this whole change exists to close. Before
	 * it, every scan inserted rows with status open and consulted nothing, so
	 * rescanning a page silently threw away the reasoning somebody had written
	 * down — and the only sign of it was a finding quietly reappearing.
	 *
	 * @return void
	 */
	public function test_a_dismissal_survives_a_rescan(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 1 );

		$markup      = '<svg aria-hidden="true"><path d="M0 0"/></svg>';
		$fingerprint = Fingerprint::of( 'aria-hidden-focusable', $markup );

		$decisions = new FakeDecisionStore();
		$decisions->record(
			$fingerprint,
			42,
			'aria-hidden-focusable',
			IssueStatus::Ignored,
			'Screen reader text is present alongside the icon.',
			7
		);

		// The next scan of the same page, reporting the same fault afresh.
		( new IssueRepository( $decisions ) )->add_many(
			99,
			array(
				array(
					'post_id'  => 42,
					'rule_id'  => 'aria-hidden-focusable',
					'severity' => Severity::Moderate,
					'context'  => $markup,
					'message'  => 'Hidden from screen readers.',
				),
			)
		);

		$this->assertContains( IssueStatus::Ignored->value, $this->values, 'The new row should arrive already dismissed.' );
		$this->assertContains( 'Screen reader text is present alongside the icon.', $this->values, 'The stated reason should come with it.' );
		$this->assertContains( 7, $this->values, 'And so should the person who decided.' );
	}

	/**
	 * A finding nobody has judged arrives open.
	 *
	 * @return void
	 */
	public function test_an_undecided_finding_arrives_open(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 1 );

		( new IssueRepository( new FakeDecisionStore() ) )->add_many(
			99,
			array(
				array(
					'post_id' => 42,
					'rule_id' => 'img-alt-missing',
					'context' => '<img src="a.jpg">',
					'message' => 'Image has no alt attribute.',
				),
			)
		);

		$this->assertContains( IssueStatus::Open->value, $this->values );
		$this->assertNotContains( IssueStatus::Ignored->value, $this->values );
	}

	/**
	 * A page's own decision outranks the one covering the whole site.
	 *
	 * So that putting a single page's instance back on the list does not unpick
	 * a judgement somebody took about two hundred others.
	 *
	 * @return void
	 */
	public function test_a_page_decision_outranks_a_site_wide_one(): void {
		$fingerprint = Fingerprint::of( 'aria-hidden-focusable', '<svg aria-hidden="true"></svg>' );

		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			array(
				(object) array(
					'id'          => 1,
					'fingerprint' => $fingerprint,
					'post_id'     => 0,
					'rule_id'     => 'aria-hidden-focusable',
					'status'      => IssueStatus::Ignored->value,
					'note'        => 'Correctly hidden everywhere.',
					'decided_by'  => 7,
					'created_at'  => '2026-09-01 00:00:00',
					'updated_at'  => '2026-09-01 00:00:00',
				),
				(object) array(
					'id'          => 2,
					'fingerprint' => $fingerprint,
					'post_id'     => 42,
					'rule_id'     => 'aria-hidden-focusable',
					'status'      => IssueStatus::Open->value,
					'note'        => 'On this page the icon is the only label.',
					'decided_by'  => 9,
					'created_at'  => '2026-09-02 00:00:00',
					'updated_at'  => '2026-09-02 00:00:00',
				),
			)
		);

		$found = ( new DecisionRepository() )->for_fingerprints( array( $fingerprint ), 42 );

		$this->assertArrayHasKey( $fingerprint, $found );
		$this->assertInstanceOf( Decision::class, $found[ $fingerprint ] );
		$this->assertSame( IssueStatus::Open, $found[ $fingerprint ]->status, 'The page-specific decision should win.' );
		$this->assertSame( 42, $found[ $fingerprint ]->post_id );
	}

	/**
	 * A site-wide decision applies where the page has said nothing.
	 *
	 * @return void
	 */
	public function test_a_site_wide_decision_applies_by_default(): void {
		$fingerprint = Fingerprint::of( 'aria-hidden-focusable', '<svg aria-hidden="true"></svg>' );

		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			array(
				(object) array(
					'id'          => 1,
					'fingerprint' => $fingerprint,
					'post_id'     => 0,
					'rule_id'     => 'aria-hidden-focusable',
					'status'      => IssueStatus::Ignored->value,
					'note'        => 'Correctly hidden everywhere.',
					'decided_by'  => 7,
					'created_at'  => '2026-09-01 00:00:00',
					'updated_at'  => '2026-09-01 00:00:00',
				),
			)
		);

		$found = ( new DecisionRepository() )->for_fingerprints( array( $fingerprint ), 42 );

		$this->assertSame( IssueStatus::Ignored, $found[ $fingerprint ]->status );
		$this->assertTrue( $found[ $fingerprint ]->is_site_wide() );
	}

	/**
	 * The lookup asks only about the page in front of it.
	 *
	 * A decision made on one page must not leak onto another that happens to
	 * carry the same markup unless it was deliberately made site-wide.
	 *
	 * @return void
	 */
	public function test_the_lookup_is_scoped_to_the_page_and_site_wide_only(): void {
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );

		( new DecisionRepository() )->for_fingerprints( array( 'abc123' ), 42 );

		$this->assertStringContainsString( 'post_id IN ( 0, %d )', $this->sql );
		$this->assertContains( 42, $this->values );
	}
}
