<?php
/**
 * A single scan finding.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * What a rule reports about one element.
 *
 * Separate from Db\Issue on purpose: a finding is what a rule produced, an
 * issue is what was stored and has since acquired a status and a history.
 *
 * @since 0.3.0
 */
final class Finding {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param string    $rule_id   Rule that produced this.
	 * @param string    $wcag_sc   WCAG success criterion, e.g. "1.1.1".
	 * @param Severity  $severity  How much it hurts.
	 * @param Detection $detection Whether a machine settled this or a person must.
	 * @param string    $message   Plain-language description of this occurrence.
	 * @param string    $selector  XPath locating the element.
	 * @param string    $context   The offending markup.
	 * @param ScanPass  $pass      Which engine produced this. Defaults to the
	 *                             server pass, because every rule that existed
	 *                             before the browser pass was a server rule.
	 */
	public function __construct(
		public readonly string $rule_id,
		public readonly string $wcag_sc,
		public readonly Severity $severity,
		public readonly Detection $detection,
		public readonly string $message,
		public readonly string $selector = '',
		public readonly string $context = '',
		public readonly ScanPass $pass = ScanPass::Server
	) {}

	/**
	 * Converts to the array shape IssueRepository::add_many() expects.
	 *
	 * @since 0.3.0
	 *
	 * @param int $post_id Post the finding belongs to.
	 * @return array<string, mixed>
	 */
	public function to_row( int $post_id = 0 ): array {
		return array(
			'post_id'   => $post_id,
			'rule_id'   => $this->rule_id,
			'wcag_sc'   => $this->wcag_sc,
			'severity'  => $this->severity,
			'detection' => $this->detection,
			'message'   => $this->message,
			'selector'  => $this->selector,
			'context'   => $this->context,
			'found_by'  => $this->pass,
		);
	}
}
