<?php
/**
 * What a rule says can be done about its findings.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

defined( 'ABSPATH' ) || exit;

/**
 * A rule's own account of how — and whether — its findings can be fixed.
 *
 * Declared per rule rather than inferred, because inference is how a coverage
 * claim goes quietly wrong. A rule that gains an automated fix has to say so in
 * its own file, next to the rest of what it claims about itself, and a rule
 * that has none has to say that too.
 *
 * The interface never decides on its own whether to show a fix button. It asks.
 *
 * @since 0.13.0
 */
final class FixPlan {

	/**
	 * Constructor.
	 *
	 * @since 0.13.0
	 *
	 * @param FixKind   $kind    How much judgement the fix needs.
	 * @param FixTarget $target  Where the fix would be written.
	 * @param string    $summary One sentence on what happens, or why nothing can.
	 */
	public function __construct(
		public readonly FixKind $kind,
		public readonly FixTarget $target,
		public readonly string $summary = ''
	) {
	}

	/**
	 * A plan for a finding only a person can settle.
	 *
	 * @since 0.13.0
	 *
	 * @param FixTarget $target  Where the change belongs.
	 * @param string    $summary What the person has to decide.
	 * @return self
	 */
	public static function manual( FixTarget $target, string $summary = '' ): self {
		return new self( FixKind::Manual, $target, $summary );
	}

	/**
	 * Whether the interface may offer a single button that just does it.
	 *
	 * Both halves have to hold. Knowing the right answer is not enough if it
	 * has to be written somewhere we cannot reach, and being able to write
	 * somewhere is not enough if we would be guessing about what to put there.
	 *
	 * @since 0.13.0
	 *
	 * @return bool
	 */
	public function is_one_click(): bool {
		return $this->kind->may_apply_unreviewed() && $this->target->is_reachable();
	}

	/**
	 * Whether a suggestion can be produced for a person to review.
	 *
	 * @since 0.13.0
	 *
	 * @return bool
	 */
	public function is_reviewable(): bool {
		return FixKind::Generative === $this->kind && $this->target->is_reachable();
	}

	/**
	 * Whether this belongs to whoever maintains the theme.
	 *
	 * @since 0.13.0
	 *
	 * @return bool
	 */
	public function is_handoff(): bool {
		return FixTarget::Theme === $this->target;
	}

	/**
	 * The shape the REST layer sends.
	 *
	 * @since 0.13.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'kind'         => $this->kind->value,
			'kind_label'   => $this->kind->label(),
			'target'       => $this->target->value,
			'target_label' => $this->target->label(),
			'summary'      => $this->summary,
			'one_click'    => $this->is_one_click(),
			'reviewable'   => $this->is_reviewable(),
			'handoff'      => $this->is_handoff(),
		);
	}
}
