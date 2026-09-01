<?php
/**
 * Issue record.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Db;

use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WOWStudio\AccessibilityKit\Scanner\ScanPass;
use WOWStudio\AccessibilityKit\Scanner\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * One row of the issues table.
 *
 * @since 0.2.0
 */
final class Issue {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param int         $id         Row ID.
	 * @param int         $scan_id    Scan this issue belongs to.
	 * @param int         $post_id    Post the issue was found on, 0 if not post-bound.
	 * @param string      $rule_id    Rule that produced the issue.
	 * @param string      $wcag_sc    WCAG success criterion, e.g. "1.1.1".
	 * @param Severity    $severity   How much it hurts.
	 * @param Detection   $detection  Whether a machine or a person decides this.
	 * @param ScanPass    $found_by   Which engine produced this finding.
	 * @param IssueStatus $status     Where it sits in the workflow.
	 * @param string      $selector   XPath or CSS selector for the element.
	 * @param string      $context    The offending markup, for preview and diff.
	 * @param string      $message    Plain-language description.
	 * @param string      $note        Reviewer note, required when ignoring.
	 * @param int         $resolved_by Who ignored or reopened it, 0 when nobody has.
	 * @param string      $created_at MySQL datetime.
	 * @param string      $updated_at MySQL datetime.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $scan_id,
		public readonly int $post_id,
		public readonly string $rule_id,
		public readonly string $wcag_sc,
		public readonly Severity $severity,
		public readonly Detection $detection,
		public readonly ScanPass $found_by,
		public readonly IssueStatus $status,
		public readonly string $selector,
		public readonly string $context,
		public readonly string $message,
		public readonly string $note,
		public readonly int $resolved_by,
		public readonly string $created_at,
		public readonly string $updated_at
	) {}

	/**
	 * Builds an issue from a database row.
	 *
	 * @since 0.2.0
	 *
	 * @param object $row Row from $wpdb.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->scan_id,
			(int) $row->post_id,
			(string) $row->rule_id,
			(string) $row->wcag_sc,
			Severity::tryFrom( (string) $row->severity ) ?? Severity::Moderate,
			Detection::tryFrom( (string) $row->detection ) ?? Detection::Manual,
			ScanPass::tryFrom( (string) ( $row->found_by ?? '' ) ) ?? ScanPass::Server,
			IssueStatus::tryFrom( (string) $row->status ) ?? IssueStatus::Open,
			(string) ( $row->selector ?? '' ),
			(string) ( $row->context ?? '' ),
			(string) $row->message,
			(string) ( $row->note ?? '' ),
			(int) ( $row->resolved_by ?? 0 ),
			(string) $row->created_at,
			(string) $row->updated_at
		);
	}
}
