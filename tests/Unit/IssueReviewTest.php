<?php
/**
 * Tests for setting a finding aside.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Remediation\IssueReview;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WOWStudio\AccessibilityKit\Tests\Doubles\FakeDecisionStore;
use WOWStudio\AccessibilityKit\Tests\Doubles\FakeIssueStore;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests dismissal, and the reason it has to come with one.
 *
 * Every scanner produces findings that are not problems, and a tool with no way
 * to say so forces people to choose between a permanently dirty list and
 * pretending to fix things that were never broken. Both end with the list being
 * ignored.
 *
 * But these records are what a conformance document is eventually built from,
 * and "somebody decided this was fine" is not something anybody can stand behind
 * a year later. So the reason is required, and these tests are mostly about the
 * ways somebody might get around writing one.
 *
 * @covers \WOWStudio\AccessibilityKit\Remediation\IssueReview
 */
final class IssueReviewTest extends TestCase {

	/**
	 * Rows the fake store holds.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $written = array();

	/**
	 * Wires the helpers these classes reach for.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->written = array();

		Functions\when( 'is_wp_error' )->alias( static fn( $thing ): bool => $thing instanceof \WP_Error );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'rest_authorization_required_code' )->justReturn( 403 );
		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn( string $text ): string => trim( (string) preg_replace( '/<[^>]*>/', '', $text ) )
		);
		Functions\when( 'get_userdata' )->justReturn( (object) array( 'display_name' => 'Ada' ) );
	}

	/**
	 * A note that is no reason at all is refused.
	 *
	 * @dataProvider empty_reasons
	 *
	 * @param string $note What somebody typed.
	 * @return void
	 */
	public function test_a_dismissal_without_a_reason_is_refused( string $note ): void {
		$review = new IssueReview( new FakeIssueStore() );

		$error = $this->assertWPError( $review->ignore( 1, $note, 7 ) );

		$this->assertSame( 'wsak_note_required', $error->get_error_code() );
	}

	/**
	 * The things people type instead of a reason.
	 *
	 * @return array<string, array{string}>
	 */
	public static function empty_reasons(): array {
		return array(
			'nothing'      => array( '' ),
			'whitespace'   => array( "   \n\t " ),
			'a full stop'  => array( '.' ),
			'n slash a'    => array( 'n/a' ),
			'ok'           => array( 'ok' ),
			'not an issue' => array( 'no' ),
		);
	}

	/**
	 * A real reason is accepted and kept with the finding.
	 *
	 * @return void
	 */
	public function test_a_dismissal_with_a_reason_is_recorded_against_its_author(): void {
		$store  = new FakeIssueStore();
		$review = new IssueReview( $store, new FakeDecisionStore() );

		$result = $review->ignore( 1, 'This table lays out a form, it is not tabular data.', 7 );

		$this->assertIsArray( $result );
		$this->assertSame( IssueStatus::Ignored->value, $result['status'] );
		$this->assertSame( 7, $result['resolved_by'] );
		$this->assertSame( 'Ada', $result['resolved_by_name'] );
		$this->assertStringContainsString( 'lays out a form', $result['note'] );
	}

	/**
	 * Reopening keeps the reason it was dismissed for.
	 *
	 * A finding set aside and then restored has a history, and erasing half of
	 * it would leave the record saying less than it knew.
	 *
	 * @return void
	 */
	public function test_reopening_keeps_the_original_reason(): void {
		$store  = new FakeIssueStore();
		$review = new IssueReview( $store, new FakeDecisionStore() );

		$review->ignore( 1, 'Decorative image, the caption already says what it shows.', 7 );
		$result = $review->reopen( 1, 9 );

		$this->assertSame( IssueStatus::Open->value, $result['status'] );
		$this->assertStringContainsString( 'Decorative image', $result['note'] );
	}

	/**
	 * Putting one back takes the durable record out with it.
	 *
	 * This is the half that decides whether "not a false positive after all"
	 * means anything. A dismissal is stored in its own table and applied to
	 * every future scan, so reopening that only flipped the row would hold
	 * until the next scan and then quietly put the finding back in the log —
	 * the same fault the decisions table was added to fix, running the other
	 * way.
	 *
	 * @return void
	 */
	public function test_reopening_withdraws_the_decision_that_would_reapply_it(): void {
		$store     = new FakeIssueStore();
		$decisions = new FakeDecisionStore();
		$review    = new IssueReview( $store, $decisions );

		$review->ignore( 1, 'Decorative image, the caption already says what it shows.', 7 );

		$this->assertNotSame( array(), $decisions->records, 'A dismissal should be recorded to survive a rescan.' );

		$review->reopen( 1, 9 );

		$this->assertSame(
			array(),
			$decisions->records,
			'The record that would re-dismiss this on the next scan is still there.'
		);
	}

	/**
	 * The log offers the way back, and the payload says who may take it.
	 *
	 * Source-reading, because both halves fail silently. Once a finding is set
	 * aside it leaves every list of open findings, so this log is the only
	 * screen it appears on — the card that offers "not a false positive after
	 * all" while you are dismissing it is on a list the finding is about to
	 * leave, which made the undo available for exactly as long as it took to
	 * change your mind immediately. If the flag stops being sent or the control
	 * stops being rendered, the button simply is not there and nothing errors.
	 *
	 * @return void
	 */
	public function test_the_log_offers_a_way_back(): void {
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local source files in a unit test; WordPress is not loaded.
		$controller = (string) file_get_contents( __DIR__ . '/../../src/Rest/ReviewController.php' );
		$screen     = (string) file_get_contents( __DIR__ . '/../../assets/src/components/dismissed.js' );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertStringContainsString(
			"'may_reopen'",
			$controller,
			'The dismissed log no longer says whether each row can be put back.'
		);

		$this->assertStringContainsString(
			'reopenIssue',
			$screen,
			'The false-positives screen no longer offers a way back.'
		);

		$this->assertStringContainsString(
			'row.may_reopen',
			$screen,
			'The control must be gated on the flag the route sends, not on a guess.'
		);
	}

	/**
	 * A finding that does not exist cannot be dismissed.
	 *
	 * @return void
	 */
	public function test_an_unknown_finding_is_refused(): void {
		$store          = new FakeIssueStore();
		$store->missing = true;

		$error = $this->assertWPError( ( new IssueReview( $store ) )->ignore( 99, 'A perfectly good reason here.', 7 ) );

		$this->assertSame( 'wsak_unknown_issue', $error->get_error_code() );
	}

	/**
	 * Somebody who cannot edit the content cannot decide about it.
	 *
	 * @return void
	 */
	public function test_someone_without_edit_rights_cannot_dismiss(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$error = $this->assertWPError(
			( new IssueReview( new FakeIssueStore() ) )->ignore( 1, 'A perfectly good reason here.', 7 )
		);

		$this->assertSame( 'wsak_forbidden_post', $error->get_error_code() );
	}
}
