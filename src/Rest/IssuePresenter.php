<?php
/**
 * Turning stored findings into what the interface reads.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Db\Issue;
use WOWStudio\AccessibilityKit\Remediation\WorkList;
use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Scanner\RuleRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * One shape for a finding, wherever it is being listed.
 *
 * Extracted from the scan endpoint when a second surface needed the same thing.
 * Two copies of this would not have failed loudly: they would have drifted a
 * field at a time until the same finding read differently depending on which
 * screen somebody reached it from, and the screen showing less would be the one
 * nobody noticed was wrong.
 *
 * @since 0.29.0
 */
final class IssuePresenter {

	/**
	 * The rules, for titles, consequences and fix plans.
	 *
	 * @since 0.29.0
	 * @var RuleRegistry
	 */
	private RuleRegistry $registry;

	/**
	 * What to do about each rule, and in what order.
	 *
	 * @since 0.29.0
	 * @var WorkList
	 */
	private WorkList $work;

	/**
	 * Constructor.
	 *
	 * @since 0.29.0
	 *
	 * @param RuleRegistry|null $registry The rules.
	 */
	public function __construct( ?RuleRegistry $registry = null ) {
		$this->registry = $registry ?? ( new Engine() )->registry();
		$this->work     = new WorkList( $this->registry->descriptors() );
	}

	/**
	 * Presents a set of findings, ordered.
	 *
	 * Ordered here rather than in the interface, so every surface that renders
	 * findings gets the same order without having to agree on one.
	 *
	 * @since 0.29.0
	 *
	 * @param Issue[] $issues Stored findings.
	 * @return array<int, array<string, mixed>>
	 */
	public function present( array $issues ): array {
		$presented = array();

		foreach ( $issues as $issue ) {
			$presented[] = $this->one( $issue );
		}

		return $this->work->order( $presented );
	}

	/**
	 * Presents a single finding.
	 *
	 * @since 0.29.0
	 *
	 * @param Issue $issue Stored finding.
	 * @return array<string, mixed>
	 */
	private function one( Issue $issue ): array {
		$rule = $this->registry->descriptor( $issue->rule_id );

		return array(
			'rule_title'      => null === $rule ? $issue->rule_id : $rule->title(),
			// What this costs a person, in one line. Sent alongside the
			// technical description rather than instead of it: the reader
			// who wants the success criterion still gets it, one level down.
			'consequence'     => null === $rule ? '' : $rule->consequence(),
			'band'            => $this->work->band_for( $issue->rule_id )->value,
			'fix'             => null === $rule ? array() : $rule->fix_plan()->to_array(),
			'how_to_fix'      => null === $rule ? '' : $rule->description(),
			// Only findings about a specific image can be handed to the
			// alt-text generator, so resolve the media item here rather than
			// making the interface guess.
			'attachment_id'   => $this->attachment_for( $issue->rule_id, $issue->context ),
			'id'              => $issue->id,
			'rule_id'         => $issue->rule_id,
			'wcag_sc'         => $issue->wcag_sc,
			'severity'        => $issue->severity->value,
			'severity_label'  => $issue->severity->label(),
			'detection'       => $issue->detection->value,
			'detection_label' => $issue->detection->label(),
			'found_by'        => $issue->found_by->value,
			'status'          => $issue->status->value,
			'note'            => $issue->note,
			'message'         => $issue->message,
			'selector'        => $issue->selector,
			'context'         => $issue->context,
		);
	}

	/**
	 * Finds the media item a finding is about, when it is about one.
	 *
	 * @since 0.29.0
	 *
	 * @param string $rule_id Rule that produced the finding.
	 * @param string $context The offending markup.
	 * @return int Attachment ID, or 0.
	 */
	private function attachment_for( string $rule_id, string $context ): int {
		if ( 'img-alt-missing' !== $rule_id || '' === $context ) {
			return 0;
		}

		if ( 1 !== preg_match( '/\ssrc=["\']([^"\']+)["\']/i', $context, $matches ) ) {
			return 0;
		}

		$url = $matches[1];

		// Relative sources are common in rendered markup; make them absolute so
		// the lookup can match what WordPress stored.
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			$url = home_url( $url );
		}

		return (int) attachment_url_to_postid( $url );
	}
}
