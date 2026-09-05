<?php
/**
 * Whether a fix is known or guessed.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

defined( 'ABSPATH' ) || exit;

/**
 * How much judgement a fix needs before it can be applied.
 *
 * This is the distinction that lets "auto fix" exist at all without breaking
 * the rule that every automated change is reviewable. One phrase was covering
 * two different things: a rule that computes the one right answer, and a model
 * that produces a plausible one. Only the first can be applied unreviewed, and
 * separating them is what makes a one-click button honest rather than a
 * shortcut around the promise.
 *
 * @since 0.13.0
 */
enum FixKind: string {

	/**
	 * A rule computes the answer. There is one, and it is not a matter of taste.
	 *
	 * `lang="en"` is present or it is not. A 24-pixel minimum is 24 pixels. A
	 * colour that clears 4.5:1 clears it. Nothing here needs a person to agree
	 * with it, which is why these can be applied in one click and in bulk.
	 *
	 * @since 0.13.0
	 */
	case Deterministic = 'deterministic';

	/**
	 * A model writes the answer. It is a suggestion, and it may be wrong.
	 *
	 * Alt text, a button's name, better link wording. These can be produced in
	 * bulk, but never applied unreviewed: what a model writes about an image it
	 * cannot see the purpose of is a guess with good grammar.
	 *
	 * **Nothing in this plugin produces one.** Generation was removed in 0.16.0
	 * along with the whole AI layer, and the six rules that declared this kind
	 * now declare Manual, which is what they always were underneath. The case
	 * is kept because the distinction it draws is the reason one-click fixes
	 * can exist here at all — a fix that is computed and a fix that is guessed
	 * must never share a button — and because the planned paid add-on
	 * reintroduces generation against this same contract.
	 *
	 * @since 0.13.0
	 */
	case Generative = 'generative';

	/**
	 * Nobody can produce the answer but the person who owns the content.
	 *
	 * Which cells in a table are headers, which of two top-level headings
	 * should be demoted, whether a hidden control should be revealed or taken
	 * out of the tab order. Offering a button here would mean guessing at
	 * meaning, and being confidently wrong about meaning is the failure this
	 * product exists to avoid.
	 *
	 * @since 0.13.0
	 */
	case Manual = 'manual';

	/**
	 * Returns the translated label.
	 *
	 * @since 0.13.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Deterministic => __( 'Fix automatically', 'wowstudio-accessibility-kit' ),
			self::Generative    => __( 'Suggest a fix to review', 'wowstudio-accessibility-kit' ),
			self::Manual        => __( 'Needs a person', 'wowstudio-accessibility-kit' ),
		};
	}

	/**
	 * Whether a fix of this kind may be applied without being read first.
	 *
	 * @since 0.13.0
	 *
	 * @return bool
	 */
	public function may_apply_unreviewed(): bool {
		return self::Deterministic === $this;
	}
}
