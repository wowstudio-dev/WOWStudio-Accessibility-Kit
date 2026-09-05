<?php
/**
 * Tests for what each rule claims can be done about its findings.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Remediation\CssRules;
use WOWStudio\AccessibilityKit\Remediation\FixKind;
use WOWStudio\AccessibilityKit\Remediation\FixPlan;
use WOWStudio\AccessibilityKit\Remediation\FixTarget;
use WOWStudio\AccessibilityKit\Scanner\BrowserRules;
use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Scanner\RuleDescriptor;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Pins the classification, because it is a claim about the product.
 *
 * "This can be fixed automatically" is the most consequential thing this
 * interface says. It is the difference between a button that just works and a
 * button that quietly guesses at somebody's content, and it is exactly the kind
 * of claim that drifts upward over time — one rule at a time, each looking
 * reasonable on its own.
 *
 * So the whole table is written down here. Changing what a rule offers means
 * changing this file, deliberately, in the same commit.
 *
 * @covers \WOWStudio\AccessibilityKit\Remediation\FixPlan
 * @covers \WOWStudio\AccessibilityKit\Remediation\FixKind
 * @covers \WOWStudio\AccessibilityKit\Remediation\FixTarget
 */
final class FixPlanTest extends TestCase {

	/**
	 * Every rule, both passes.
	 *
	 * @return RuleDescriptor[]
	 */
	private function all_rules(): array {
		return array_merge( ( new Engine() )->registry()->all(), BrowserRules::all() );
	}

	/**
	 * Returns the plans keyed by rule ID.
	 *
	 * @return array<string, FixPlan>
	 */
	private function plans(): array {
		$plans = array();

		foreach ( $this->all_rules() as $rule ) {
			$plans[ $rule->id() ] = $rule->fix_plan();
		}

		return $plans;
	}

	/**
	 * The classification, in full.
	 *
	 * @return void
	 */
	public function test_every_rule_is_classified_as_recorded(): void {
		$expected = array(
			// Deterministic, and somewhere we can write. These get a button.
			'colour-contrast'                => array( FixKind::Deterministic, FixTarget::Css ),
			'link-marked-by-colour-alone'    => array( FixKind::Deterministic, FixTarget::Css ),
			'target-too-small'               => array( FixKind::Deterministic, FixTarget::Css ),
			'document-title-missing'         => array( FixKind::Deterministic, FixTarget::Setting ),

			// Deterministic, and nowhere we can write. Knowing the answer is
			// not the same as being able to apply it.
			'html-lang-missing'              => array( FixKind::Deterministic, FixTarget::Theme ),

			// Wording that depends on what the content means. These were
			// Generative until 0.16.0, when the AI layer was removed — nothing
			// drafts them now, so they are what they always were underneath.
			'img-alt-missing'                => array( FixKind::Manual, FixTarget::Media ),
			'form-control-label-missing'     => array( FixKind::Manual, FixTarget::Content ),
			'link-name-missing'              => array( FixKind::Manual, FixTarget::Content ),
			'button-name-missing'            => array( FixKind::Manual, FixTarget::Content ),
			'iframe-title-missing'           => array( FixKind::Manual, FixTarget::Content ),
			'link-text-not-descriptive'      => array( FixKind::Manual, FixTarget::Content ),

			// Added in 0.17.0. Every one is Manual: each asks what a piece of
			// content is *for*, and none of those answers can be computed.
			'img-alt-is-filename'            => array( FixKind::Manual, FixTarget::Media ),
			'img-alt-too-long'               => array( FixKind::Manual, FixTarget::Media ),
			'img-alt-redundant'              => array( FixKind::Manual, FixTarget::Content ),
			'image-map-area-alt-missing'     => array( FixKind::Manual, FixTarget::Content ),
			'link-opens-new-window'          => array( FixKind::Manual, FixTarget::Content ),
			'link-to-file'                   => array( FixKind::Manual, FixTarget::Content ),
			'link-not-keyboard-reachable'    => array( FixKind::Manual, FixTarget::Content ),
			'heading-empty'                  => array( FixKind::Manual, FixTarget::Content ),
			'page-has-no-headings'           => array( FixKind::Manual, FixTarget::Content ),
			'form-label-orphaned'            => array( FixKind::Manual, FixTarget::Content ),
			// Both of these are usually emitted by a theme or plugin template
			// rather than typed into a post, so they hand off rather than
			// pointing at the editor.
			'link-anchor-broken'             => array( FixKind::Manual, FixTarget::Theme ),
			'aria-reference-broken'          => array( FixKind::Manual, FixTarget::Theme ),

			// Judgements about meaning. No button, ever.
			'heading-level-skipped'          => array( FixKind::Manual, FixTarget::Content ),
			'heading-multiple-h1'            => array( FixKind::Manual, FixTarget::Content ),
			'table-headers-missing'          => array( FixKind::Manual, FixTarget::Content ),
			'landmark-main-missing'          => array( FixKind::Manual, FixTarget::Theme ),
			'scrolling-region-not-reachable' => array( FixKind::Manual, FixTarget::Theme ),
			'hidden-element-still-focusable' => array( FixKind::Manual, FixTarget::Theme ),
		);

		$plans = $this->plans();

		$this->assertSame(
			array(),
			array_diff( array_keys( $plans ), array_keys( $expected ) ),
			'A new rule must be classified here before it ships.'
		);
		$this->assertSame(
			array(),
			array_diff( array_keys( $expected ), array_keys( $plans ) ),
			'A rule listed here no longer exists.'
		);

		foreach ( $expected as $id => list( $kind, $target ) ) {
			$this->assertSame( $kind, $plans[ $id ]->kind, $id . ' changed what kind of fix it claims.' );
			$this->assertSame( $target, $plans[ $id ]->target, $id . ' changed where its fix would be written.' );
		}
	}

	/**
	 * The counts, so a quiet drift upward is loud.
	 *
	 * Four one-click fixes out of twenty-nine checks, three of which already
	 * shipped as CSS. If this number grows, it should be because somebody meant
	 * it — not because a rule was reclassified while its own tests still passed.
	 *
	 * Nothing is reviewable any more. That count is asserted at zero rather
	 * than dropped, because "a draft somebody reads before applying" is a real
	 * category this product intends to have again — and the moment something
	 * claims to be one, it must be a deliberate act with this line changed to
	 * match, not a rule quietly reclassifying itself.
	 *
	 * @return void
	 */
	public function test_the_tally_is_what_the_spec_says(): void {
		$one_click = 0;
		$review    = 0;
		$handoff   = 0;
		$nothing   = 0;

		foreach ( $this->plans() as $plan ) {
			if ( $plan->is_one_click() ) {
				++$one_click;
			} elseif ( $plan->is_reviewable() ) {
				++$review;
			} elseif ( $plan->is_handoff() ) {
				++$handoff;
			} else {
				++$nothing;
			}
		}

		$this->assertSame( 4, $one_click, 'One-click fixes.' );
		$this->assertSame( 0, $review, 'Nothing drafts a fix, so nothing is a draft to review.' );
		$this->assertSame( 6, $handoff, 'Findings that belong to whoever maintains the theme.' );
		$this->assertSame( 19, $nothing, 'Findings only the content owner can settle.' );
		$this->assertCount( 29, $this->plans() );
	}

	/**
	 * Every rule says something about its own fix.
	 *
	 * A blank summary would leave the interface with a button and nothing to
	 * explain it, or a dead end and no reason given.
	 *
	 * @return void
	 */
	public function test_every_rule_explains_itself(): void {
		foreach ( $this->plans() as $id => $plan ) {
			$this->assertNotSame( '', trim( $plan->summary ), $id . ' has no explanation of what can be done.' );
		}
	}

	/**
	 * A generated fix is never offered as one click, whatever it targets.
	 *
	 * The rule the whole taxonomy exists to enforce.
	 *
	 * @return void
	 */
	public function test_a_generated_fix_is_never_one_click(): void {
		foreach ( $this->plans() as $id => $plan ) {
			if ( FixKind::Deterministic !== $plan->kind ) {
				$this->assertFalse( $plan->is_one_click(), $id . ' would apply a guess without review.' );
			}
		}
	}

	/**
	 * Nothing is offered a fix we cannot actually write.
	 *
	 * @return void
	 */
	public function test_an_unreachable_target_is_never_offered(): void {
		foreach ( $this->plans() as $id => $plan ) {
			if ( ! $plan->target->is_reachable() ) {
				$this->assertFalse( $plan->is_one_click(), $id . ' offers a fix with nowhere to put it.' );
				$this->assertFalse( $plan->is_reviewable(), $id . ' offers a suggestion with nowhere to put it.' );
			}
		}
	}

	/**
	 * The rules that target CSS are exactly the ones the CSS route accepts.
	 *
	 * Two lists in two files that have to agree: a rule declaring a CSS fix the
	 * endpoint refuses would be reviewed and then rejected on apply, and a rule
	 * the endpoint accepts without declaring it would write CSS no review screen
	 * ever showed anyone.
	 *
	 * @return void
	 */
	public function test_css_rules_and_css_fix_plans_agree(): void {
		$declared = array();

		foreach ( $this->plans() as $id => $plan ) {
			if ( FixTarget::Css === $plan->target ) {
				$declared[] = $id;
			}
		}

		$allowed = array_keys( CssRules::map() );

		sort( $declared );
		sort( $allowed );

		$this->assertSame( $allowed, $declared );
	}

	/**
	 * A browser rule given no plan is treated as needing a person.
	 *
	 * Silence must never read as "we can fix this".
	 *
	 * @return void
	 */
	public function test_an_unclassified_rule_offers_nothing(): void {
		$plan = FixPlan::manual( FixTarget::Theme );

		$this->assertFalse( $plan->is_one_click() );
		$this->assertFalse( $plan->is_reviewable() );
		$this->assertTrue( $plan->is_handoff() );
	}
}
