<?php
/**
 * Deciding about one piece of markup everywhere it appears.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Remediation\SiteWideReview;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WOWStudio\AccessibilityKit\Tests\Doubles\FakeDecisionStore;
use WOWStudio\AccessibilityKit\Tests\Doubles\RecordingIssueStore;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the decision that covers every page at once.
 *
 * @covers \WOWStudio\AccessibilityKit\Remediation\SiteWideReview
 */
final class SiteWideReviewTest extends TestCase {

	/**
	 * A well-formed fingerprint.
	 *
	 * @var string
	 */
	private const FINGERPRINT = '97dbc2f4ced320ec9628af59265430fb0793f5fc';

	/**
	 * Lets everything through unless a test says otherwise.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'rest_authorization_required_code' )->justReturn( 403 );
	}

	/**
	 * A reason is required, with no path around it.
	 *
	 * At page scope an unexplained dismissal is one person's shortcut. At this
	 * scope it is a claim about the whole site that somebody else inherits, so
	 * there is no version of this that skips the note.
	 *
	 * @return void
	 */
	public function test_a_decision_for_the_whole_site_needs_a_reason(): void {
		$review = new SiteWideReview( new RecordingIssueStore(), new FakeDecisionStore() );

		$error = $this->assertWPError( $review->ignore( self::FINGERPRINT, 'target-too-small', 'ok', 7 ) );

		$this->assertSame( 'wsak_note_required', $error->get_error_code() );
	}

	/**
	 * Nothing is written when the reason is refused.
	 *
	 * @return void
	 */
	public function test_a_refused_decision_changes_nothing(): void {
		$issues    = new RecordingIssueStore();
		$decisions = new FakeDecisionStore();

		( new SiteWideReview( $issues, $decisions ) )->ignore( self::FINGERPRINT, 'target-too-small', '.', 7 );

		$this->assertSame( array(), $issues->applied );
		$this->assertSame( array(), $decisions->records );
	}

	/**
	 * Deciding this widely needs the right to edit other people's content.
	 *
	 * @return void
	 */
	public function test_it_needs_the_right_to_edit_other_peoples_content(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$review = new SiteWideReview( new RecordingIssueStore(), new FakeDecisionStore() );

		$error = $this->assertWPError(
			$review->ignore( self::FINGERPRINT, 'target-too-small', 'A perfectly good reason here.', 7 )
		);

		$this->assertSame( 'wsak_forbidden_site_wide', $error->get_error_code() );
	}

	/**
	 * A made-up fingerprint is refused rather than recorded.
	 *
	 * @return void
	 */
	public function test_an_unrecognisable_finding_is_refused(): void {
		$review = new SiteWideReview( new RecordingIssueStore(), new FakeDecisionStore() );

		$error = $this->assertWPError(
			$review->ignore( 'not-a-fingerprint', 'target-too-small', 'A perfectly good reason here.', 7 )
		);

		$this->assertSame( 'wsak_unknown_issue', $error->get_error_code() );
	}

	/**
	 * The judgement is recorded at site scope and applied to the rows.
	 *
	 * @return void
	 */
	public function test_a_decision_is_recorded_for_every_page(): void {
		$issues    = new RecordingIssueStore();
		$decisions = new FakeDecisionStore();

		$result = ( new SiteWideReview( $issues, $decisions ) )->ignore(
			self::FINGERPRINT,
			'target-too-small',
			'These render 40px tall; the scanner measured the inline anchor.',
			7
		);

		$this->assertIsArray( $result );
		$this->assertSame( 8, $result['affected'] );

		// post_id 0 is what makes it site-wide.
		$stored = $decisions->find( self::FINGERPRINT, 0 );
		$this->assertNotNull( $stored );
		$this->assertSame( IssueStatus::Ignored, $stored->status );
		$this->assertSame( 7, $stored->decided_by );

		$this->assertCount( 1, $issues->applied );
		$this->assertSame( IssueStatus::Ignored->value, $issues->applied[0]['status'] );
	}

	/**
	 * Withdrawing puts every finding it covered back.
	 *
	 * @return void
	 */
	public function test_withdrawing_puts_the_findings_back(): void {
		$issues    = new RecordingIssueStore();
		$decisions = new FakeDecisionStore();

		$review = new SiteWideReview( $issues, $decisions );

		$review->ignore( self::FINGERPRINT, 'target-too-small', 'A perfectly good reason here.', 7 );
		$result = $review->reopen( self::FINGERPRINT, 9 );

		$this->assertIsArray( $result );
		$this->assertSame( IssueStatus::Open->value, $result['status'] );
		$this->assertNull( $decisions->find( self::FINGERPRINT, 0 ), 'The record should be gone, not merely flipped.' );
		$this->assertSame( IssueStatus::Open->value, $issues->applied[1]['status'] );
	}

	/**
	 * Withdrawing something nobody decided is refused.
	 *
	 * @return void
	 */
	public function test_withdrawing_a_decision_that_was_never_taken_is_refused(): void {
		$review = new SiteWideReview( new RecordingIssueStore(), new FakeDecisionStore() );

		$error = $this->assertWPError( $review->reopen( self::FINGERPRINT, 9 ) );

		$this->assertSame( 'wsak_unknown_issue', $error->get_error_code() );
	}
}
