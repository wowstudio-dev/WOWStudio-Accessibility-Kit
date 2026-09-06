<?php
/**
 * Deciding about one piece of markup everywhere it appears.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

use WOWStudio\AccessibilityKit\Db\Decision;
use WOWStudio\AccessibilityKit\Db\DecisionRepository;
use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * One judgement, applied wherever the same markup appears.
 *
 * A theme prints its social icons into every footer, so the same finding
 * arrives once per page. Asking somebody to judge it thirty-seven times does
 * not make the site more accessible; it teaches them to clear findings without
 * reading them, and the finding that mattered is the one they clear on autopilot
 * afterwards. So the decision can be taken once.
 *
 * Which is also why this is more careful than the per-page version rather than
 * less. A site-wide dismissal retires a finding on pages nobody has looked at,
 * so:
 *
 * - the reason is required, with no path that skips it — at page scope an
 *   unexplained decision is one person's shortcut, at this scope it is a claim
 *   about the whole site that somebody else will inherit;
 * - it needs the right to edit other people's content, because that is what it
 *   is really doing;
 * - pages that already made their own call about this finding keep it;
 * - and it can be withdrawn in one step, which is what makes it safe to offer
 *   at all.
 *
 * @since 0.29.0
 */
final class SiteWideReview {

	/**
	 * Findings, and the rows the interface reads.
	 *
	 * @since 0.29.0
	 * @var IssueRepository
	 */
	private IssueRepository $issues;

	/**
	 * The durable record of the judgement.
	 *
	 * @since 0.29.0
	 * @var DecisionRepository
	 */
	private DecisionRepository $decisions;

	/**
	 * Constructor.
	 *
	 * @since 0.29.0
	 *
	 * @param IssueRepository|null    $issues    Issue storage.
	 * @param DecisionRepository|null $decisions Decision storage.
	 */
	public function __construct( ?IssueRepository $issues = null, ?DecisionRepository $decisions = null ) {
		$this->issues    = $issues ?? new IssueRepository();
		$this->decisions = $decisions ?? new DecisionRepository();
	}

	/**
	 * Sets a finding aside wherever this markup appears.
	 *
	 * @since 0.29.0
	 *
	 * @param string $fingerprint Which finding.
	 * @param string $rule_id     Rule that produced it.
	 * @param string $note        Why it is not a problem.
	 * @param int    $user_id     Who decided.
	 * @return array<string, mixed>|WP_Error
	 */
	public function ignore( string $fingerprint, string $rule_id, string $note, int $user_id ) {
		$refusal = $this->refuse( $fingerprint, $note, $user_id );

		if ( $refusal instanceof WP_Error ) {
			return $refusal;
		}

		$note = trim( wp_strip_all_tags( $note ) );

		$this->decisions->record(
			$fingerprint,
			0,
			$rule_id,
			IssueStatus::Ignored,
			$note,
			$user_id
		);

		$brought_into_line = $this->issues->apply_everywhere(
			$fingerprint,
			IssueStatus::Ignored,
			$note,
			$user_id
		);

		/**
		 * Fires when a finding is set aside everywhere it appears.
		 *
		 * @since 0.29.0
		 *
		 * @param string $fingerprint Which finding.
		 * @param string $note        The stated reason.
		 * @param int    $user_id     Who decided.
		 */
		do_action( 'wsak_issue_ignored_site_wide', $fingerprint, $note, $user_id );

		return array(
			'fingerprint' => $fingerprint,
			'status'      => IssueStatus::Ignored->value,
			'note'        => $note,
			'affected'    => $brought_into_line,
		);
	}

	/**
	 * Withdraws a site-wide judgement, putting the finding back.
	 *
	 * @since 0.29.0
	 *
	 * @param string $fingerprint Which finding.
	 * @param int    $user_id     Who decided.
	 * @return array<string, mixed>|WP_Error
	 */
	public function reopen( string $fingerprint, int $user_id ) {
		if ( 1 !== preg_match( '/^[0-9a-f]{40}$/', $fingerprint ) ) {
			return $this->unknown();
		}

		if ( ! $this->may_decide( $user_id ) ) {
			return $this->forbidden();
		}

		$existing = $this->decisions->find( $fingerprint, 0 );

		if ( ! $existing instanceof Decision ) {
			return $this->unknown();
		}

		$this->decisions->withdraw( $fingerprint, 0 );

		$brought_into_line = $this->issues->apply_everywhere(
			$fingerprint,
			IssueStatus::Open,
			'',
			$user_id
		);

		/**
		 * Fires when a site-wide judgement is withdrawn.
		 *
		 * @since 0.29.0
		 *
		 * @param string $fingerprint Which finding.
		 * @param int    $user_id     Who decided.
		 */
		do_action( 'wsak_issue_reopened_site_wide', $fingerprint, $user_id );

		return array(
			'fingerprint' => $fingerprint,
			'status'      => IssueStatus::Open->value,
			'affected'    => $brought_into_line,
		);
	}

	/**
	 * Returns why this decision cannot be taken, or null when it can.
	 *
	 * @since 0.29.0
	 *
	 * @param string $fingerprint Which finding.
	 * @param string $note        The stated reason.
	 * @param int    $user_id     Who is asking.
	 * @return WP_Error|null
	 */
	private function refuse( string $fingerprint, string $note, int $user_id ): ?WP_Error {
		if ( 1 !== preg_match( '/^[0-9a-f]{40}$/', $fingerprint ) ) {
			return $this->unknown();
		}

		if ( ! $this->may_decide( $user_id ) ) {
			return $this->forbidden();
		}

		if ( mb_strlen( trim( wp_strip_all_tags( $note ) ) ) < IssueReview::MIN_NOTE ) {
			return new WP_Error(
				'wsak_note_required',
				__( 'Say why this is not a problem. This decision covers every page carrying this markup, including pages you have not opened, so the reason is what lets somebody else check the judgement later.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 400 )
			);
		}

		return null;
	}

	/**
	 * Reports whether somebody may decide for the whole site.
	 *
	 * `edit_others_posts` rather than the plugin's own capability, because that
	 * is the right being exercised: a decision here reaches content belonging to
	 * people who are not asking for it. An author who may edit their own posts
	 * can still set findings aside one page at a time.
	 *
	 * @since 0.29.0
	 *
	 * @param int $user_id Who is asking.
	 * @return bool
	 */
	private function may_decide( int $user_id ): bool {
		$allowed = current_user_can( 'edit_others_posts' );

		/**
		 * Filters whether somebody may set a finding aside site-wide.
		 *
		 * Narrows only, exactly as `wsak_can_dismiss` does. The capability check
		 * above has already run and is combined with `&&`, so no filter can hand
		 * somebody the right to make decisions about content they may not edit.
		 *
		 * @since 0.29.0
		 *
		 * @param bool $allowed Whether the capability check passed.
		 * @param int  $user_id Who is asking.
		 */
		return $allowed && (bool) apply_filters( 'wsak_can_dismiss_site_wide', $allowed, $user_id );
	}

	/**
	 * The error for a finding that is not there.
	 *
	 * @since 0.29.0
	 *
	 * @return WP_Error
	 */
	private function unknown(): WP_Error {
		return new WP_Error(
			'wsak_unknown_issue',
			__( 'That finding could not be found.', 'wowstudio-accessibility-kit' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * The error for somebody without the right to decide this widely.
	 *
	 * @since 0.29.0
	 *
	 * @return WP_Error
	 */
	private function forbidden(): WP_Error {
		return new WP_Error(
			'wsak_forbidden_site_wide',
			__( 'Setting a finding aside everywhere needs permission to edit other people\'s content. You can still set it aside one page at a time.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
