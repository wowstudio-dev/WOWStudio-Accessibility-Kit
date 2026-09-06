<?php
/**
 * Site-wide fix contract.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes;

defined( 'ABSPATH' ) || exit;

/**
 * One repair that applies to the whole site rather than to one finding.
 *
 * The distinction from the override layer is worth stating, because they solve
 * different problems and the difference decides which one a fix belongs in.
 *
 * An override answers *one finding on one page*: this image, this heading, this
 * paragraph. It is stored against an issue, it is previewed and diffed, and it
 * is undone individually.
 *
 * A site fix answers *a class of finding everywhere at once*: no skip link, no
 * visible focus, no `lang` attribute. There is nothing to diff because there is
 * no "before" on any particular page — the problem is an absence, and the fix is
 * a hook that supplies what is missing on every request. It is switched on and
 * off, not applied and reverted.
 *
 * Three rules every implementation must keep:
 *
 * 1. **Nothing is written to anybody's content.** Fixes work through filters and
 *    actions, and switching one off must leave the site exactly as it was. A
 *    fix that edits posts is an override and belongs in the other layer.
 * 2. **No buffering the page and rewriting it.** SPEC decision F6 rules that out
 *    and the reason is not performance: rewriting whatever HTML happens to come
 *    past is how an overlay works, and it "fixes" far more than it was asked to.
 *    Reach the specific thing through the specific filter, or do not reach it.
 * 3. **Say plainly when it takes effect.** Most of these are hooks declared
 *    early in the request, so switching one on cannot change the request that
 *    did the switching. A button that worked must not look like a button that
 *    did nothing.
 *
 * @since 0.19.0
 */
interface SiteFix {

	/**
	 * Returns the stable identifier this fix is stored and addressed by.
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Returns the short name shown as the control's label.
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function title(): string;

	/**
	 * Returns what this changes, in the reader's terms.
	 *
	 * Says what the site will do differently, not which hook is used. Somebody
	 * deciding whether to switch this on is asking what their visitors will
	 * experience.
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function description(): string;

	/**
	 * Returns the rule identifiers whose findings this fix answers.
	 *
	 * Lets a finding point at the switch that would settle it, and lets the
	 * settings screen say how many open findings a fix would clear. An empty
	 * array means the fix is good practice that no current check reports.
	 *
	 * @since 0.19.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array;

	/**
	 * Registers the hooks that carry out the fix.
	 *
	 * Called only when the fix is switched on, so an implementation never has
	 * to check its own state. Anything registered here runs on every request,
	 * including the front end, so it has to be cheap.
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function hooks(): void;

	/**
	 * Returns what the site owner should know before switching this on.
	 *
	 * Two kinds of thing belong here, and both are the kind that gets left out:
	 * when the change actually becomes visible, and anything it may collide
	 * with — a theme that already does this, a plugin that will fight it.
	 * Return an empty string when there is genuinely nothing to warn about.
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function caveat(): string;
}
