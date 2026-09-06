<?php
/**
 * Decision record.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Db;

use WOWStudio\AccessibilityKit\Scanner\IssueStatus;

defined( 'ABSPATH' ) || exit;

/**
 * One judgement somebody made about a finding.
 *
 * @since 0.29.0
 */
final class Decision {

	/**
	 * Constructor.
	 *
	 * @since 0.29.0
	 *
	 * @param int         $id          Row ID.
	 * @param string      $fingerprint Which finding this is about.
	 * @param int         $post_id     Page it applies to, 0 for site-wide.
	 * @param string      $rule_id     Rule that produced the finding, kept so the
	 *                                 log can be read without a scan to join to.
	 * @param IssueStatus $status      What was decided.
	 * @param string      $note        Why.
	 * @param int         $decided_by  Who decided.
	 * @param string      $created_at  MySQL datetime.
	 * @param string      $updated_at  MySQL datetime.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $fingerprint,
		public readonly int $post_id,
		public readonly string $rule_id,
		public readonly IssueStatus $status,
		public readonly string $note,
		public readonly int $decided_by,
		public readonly string $created_at,
		public readonly string $updated_at
	) {}

	/**
	 * Reports whether this decision covers every page.
	 *
	 * @since 0.29.0
	 *
	 * @return bool
	 */
	public function is_site_wide(): bool {
		return 0 === $this->post_id;
	}

	/**
	 * Builds a decision from a database row.
	 *
	 * @since 0.29.0
	 *
	 * @param object $row Row from $wpdb.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(string) $row->fingerprint,
			(int) $row->post_id,
			(string) ( $row->rule_id ?? '' ),
			IssueStatus::tryFrom( (string) $row->status ) ?? IssueStatus::Ignored,
			(string) ( $row->note ?? '' ),
			(int) ( $row->decided_by ?? 0 ),
			(string) $row->created_at,
			(string) $row->updated_at
		);
	}
}
