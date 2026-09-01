<?php
/**
 * What to do about a finding, grouped by what it asks of you.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

defined( 'ABSPATH' ) || exit;

/**
 * The four things a finding can ask of the person reading it.
 *
 * Findings have always been grouped by severity, which answers "how bad is
 * this" and never answers "what do I do now". Somebody looking at a list of
 * ninety problems does not need them ranked by how serious they are; they need
 * to know which ones they can clear in a minute, which ones need them to read
 * something first, which ones need a decision only they can make, and which
 * ones are not theirs to fix at all.
 *
 * Those are four different kinds of work, and they get four groups. Severity
 * still sorts within a group, where it is the right question.
 *
 * @since 0.13.0
 */
enum ActionBand: string {

	/**
	 * Press the button. Nothing to read, nothing to decide.
	 *
	 * @since 0.13.0
	 */
	case Now = 'now';

	/**
	 * Something has been drafted for you. Read it, then apply it.
	 *
	 * @since 0.13.0
	 */
	case Review = 'review';

	/**
	 * Only you know the answer.
	 *
	 * @since 0.13.0
	 */
	case Decide = 'decide';

	/**
	 * Not in your content. Somebody who edits the theme has to do it.
	 *
	 * @since 0.13.0
	 */
	case Delegate = 'delegate';

	/**
	 * Works out which band a rule's findings belong in.
	 *
	 * Derived from the fix plan rather than stored separately, so a rule can
	 * never claim one thing about its fix and be filed under another.
	 *
	 * @since 0.13.0
	 *
	 * @param FixPlan $plan What the rule says can be done.
	 * @return self
	 */
	public static function from_plan( FixPlan $plan ): self {
		if ( $plan->is_one_click() ) {
			return self::Now;
		}

		if ( $plan->is_reviewable() ) {
			return self::Review;
		}

		return $plan->is_handoff() ? self::Delegate : self::Decide;
	}

	/**
	 * Returns the order this band appears in.
	 *
	 * Cheapest real improvement first. Not because those problems matter most —
	 * they often matter least — but because a list that opens with work you can
	 * finish is a list people finish, and one that opens with "ask your
	 * developer" is a list people close.
	 *
	 * @since 0.13.0
	 *
	 * @return int
	 */
	public function rank(): int {
		return match ( $this ) {
			self::Now      => 0,
			self::Review   => 1,
			self::Decide   => 2,
			self::Delegate => 3,
		};
	}

	/**
	 * Returns the heading shown above the group.
	 *
	 * @since 0.13.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Now      => __( 'Fix these now', 'wowstudio-accessibility-kit' ),
			self::Review   => __( 'Read, then apply', 'wowstudio-accessibility-kit' ),
			self::Decide   => __( 'Needs a decision from you', 'wowstudio-accessibility-kit' ),
			self::Delegate => __( 'For whoever looks after your theme', 'wowstudio-accessibility-kit' ),
		};
	}

	/**
	 * Returns the sentence under the heading.
	 *
	 * @since 0.13.0
	 *
	 * @return string
	 */
	public function blurb(): string {
		return match ( $this ) {
			self::Now      => __( 'There is one correct answer and we already know it. Nothing here is a guess, so nothing here needs checking first.', 'wowstudio-accessibility-kit' ),
			self::Review   => __( 'We can draft these, but a draft is not an answer. Read what it says before you apply it — it was written by a model that cannot see why the page exists.', 'wowstudio-accessibility-kit' ),
			self::Decide   => __( 'These depend on what your content means, which is not something any tool can work out for you. Each one explains what to weigh up.', 'wowstudio-accessibility-kit' ),
			self::Delegate => __( 'These are in your theme rather than your content, so nothing here can reach them. Each one comes with what to change and where.', 'wowstudio-accessibility-kit' ),
		};
	}
}
