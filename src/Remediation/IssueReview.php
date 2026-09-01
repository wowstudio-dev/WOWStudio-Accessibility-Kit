<?php
/**
 * Setting a finding aside, and the record that says who did.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Dismissing a finding as wrong, or as a decision already taken.
 *
 * Every scanner produces some findings that are not problems: a table used for
 * layout that a rule reads as data, a link whose surrounding sentence supplies
 * the meaning the rule cannot see. A tool with no way to say "this one is fine"
 * forces people to choose between a permanently dirty list and pretending to
 * fix things that were never broken, and both of those end with the list being
 * ignored entirely.
 *
 * So findings can be set aside — and the reason is required, not optional.
 * These records are what a conformance document is eventually built from, and
 * "somebody decided this was fine at some point" is not something anybody can
 * stand behind a year later. If it is worth dismissing, it is worth a sentence.
 *
 * @since 0.13.0
 */
final class IssueReview {

	/**
	 * The shortest note that could carry a reason.
	 *
	 * Not a formatting rule. It exists so "n/a", "ok" and "." do not pass for
	 * an explanation, because the whole value of the record is that somebody
	 * had to articulate the thought.
	 *
	 * @since 0.13.0
	 * @var int
	 */
	public const MIN_NOTE = 10;

	/**
	 * Issue storage.
	 *
	 * @since 0.13.0
	 * @var IssueRepository
	 */
	private IssueRepository $issues;

	/**
	 * Constructor.
	 *
	 * @since 0.13.0
	 *
	 * @param IssueRepository|null $issues Issue storage.
	 */
	public function __construct( ?IssueRepository $issues = null ) {
		$this->issues = $issues ?? new IssueRepository();
	}

	/**
	 * Sets a finding aside, with the reason attached.
	 *
	 * @since 0.13.0
	 *
	 * @param int    $issue_id Finding to dismiss.
	 * @param string $note     Why it is not a problem.
	 * @param int    $user_id  Who decided.
	 * @return array<string, mixed>|WP_Error
	 */
	public function ignore( int $issue_id, string $note, int $user_id ) {
		$issue = $this->issues->find( $issue_id );

		if ( null === $issue ) {
			return new WP_Error(
				'wsak_unknown_issue',
				__( 'That finding could not be found.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		$note = trim( wp_strip_all_tags( $note ) );

		if ( mb_strlen( $note ) < self::MIN_NOTE ) {
			return new WP_Error(
				'wsak_note_required',
				__( 'Say why this is not a problem. The reason is kept with the finding, and it is what makes this a record somebody can rely on later rather than an unexplained dismissal.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 400 )
			);
		}

		if ( $issue->post_id > 0 && ! current_user_can( 'edit_post', $issue->post_id ) ) {
			return new WP_Error(
				'wsak_forbidden_post',
				__( 'You do not have permission to make decisions about that content.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( ! $this->issues->set_status( $issue_id, IssueStatus::Ignored, $note, $user_id ) ) {
			return new WP_Error(
				'wsak_not_stored',
				__( 'That decision could not be saved.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires when a finding is set aside.
		 *
		 * @since 0.13.0
		 *
		 * @param int    $issue_id Finding dismissed.
		 * @param string $note     The stated reason.
		 * @param int    $user_id  Who decided.
		 */
		do_action( 'wsak_issue_ignored', $issue_id, $note, $user_id );

		return $this->describe( $issue_id );
	}

	/**
	 * Puts a dismissed finding back on the list.
	 *
	 * The note is deliberately kept. A finding that was set aside and then
	 * reopened has a history, and erasing the first half of it would leave the
	 * record saying less than it knew.
	 *
	 * @since 0.13.0
	 *
	 * @param int $issue_id Finding to restore.
	 * @param int $user_id  Who decided.
	 * @return array<string, mixed>|WP_Error
	 */
	public function reopen( int $issue_id, int $user_id ) {
		$issue = $this->issues->find( $issue_id );

		if ( null === $issue ) {
			return new WP_Error(
				'wsak_unknown_issue',
				__( 'That finding could not be found.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		if ( $issue->post_id > 0 && ! current_user_can( 'edit_post', $issue->post_id ) ) {
			return new WP_Error(
				'wsak_forbidden_post',
				__( 'You do not have permission to make decisions about that content.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$this->issues->set_status( $issue_id, IssueStatus::Open, $issue->note, $user_id );

		/**
		 * Fires when a dismissed finding is put back.
		 *
		 * @since 0.13.0
		 *
		 * @param int $issue_id Finding restored.
		 * @param int $user_id  Who decided.
		 */
		do_action( 'wsak_issue_reopened', $issue_id, $user_id );

		return $this->describe( $issue_id );
	}

	/**
	 * Describes a finding's review state for the interface.
	 *
	 * @since 0.13.0
	 *
	 * @param int $issue_id Finding to describe.
	 * @return array<string, mixed>
	 */
	private function describe( int $issue_id ): array {
		$issue = $this->issues->find( $issue_id );

		if ( null === $issue ) {
			return array( 'id' => $issue_id );
		}

		$who = $issue->resolved_by > 0 ? get_userdata( $issue->resolved_by ) : false;

		return array(
			'id'               => $issue->id,
			'status'           => $issue->status->value,
			'note'             => $issue->note,
			'resolved_by'      => $issue->resolved_by,
			'resolved_by_name' => false === $who ? '' : $who->display_name,
			'updated_at'       => $issue->updated_at,
		);
	}
}
