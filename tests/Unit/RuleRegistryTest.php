<?php
/**
 * Rule registry tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Filters;
use WOWStudio\AccessibilityKit\Scanner\BrowserRules;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\ScanPass;
use WOWStudio\AccessibilityKit\Scanner\Rule;
use WOWStudio\AccessibilityKit\Scanner\RuleRegistry;
use WOWStudio\AccessibilityKit\Scanner\Rules\ImageAltMissing;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests rule registration and the coverage report.
 *
 * @covers \WOWStudio\AccessibilityKit\Scanner\RuleRegistry
 */
final class RuleRegistryTest extends TestCase {

	/**
	 * The default set is registered and keyed by rule ID.
	 *
	 * @return void
	 */
	public function test_defaults_are_registered_by_id(): void {
		$registry = RuleRegistry::with_defaults();

		$this->assertCount( 24, $registry->all() );
		$this->assertInstanceOf( ImageAltMissing::class, $registry->get( 'img-alt-missing' ) );
		$this->assertNull( $registry->get( 'not-a-rule' ) );
	}

	/**
	 * Rule IDs are unique, so no rule silently replaces another.
	 *
	 * @return void
	 */
	public function test_rule_ids_are_unique(): void {
		$registry = RuleRegistry::with_defaults();

		$ids = array_map( static fn( Rule $rule ): string => $rule->id(), array_values( $registry->all() ) );

		$this->assertSame( count( $ids ), count( array_unique( $ids ) ) );
	}

	/**
	 * Anything in the filtered list that is not a rule is ignored.
	 *
	 * A filter returning junk should shrink the rule set, never fatal mid-scan.
	 *
	 * @return void
	 */
	public function test_non_rules_from_the_filter_are_ignored(): void {
		Filters\expectApplied( 'wsak_rules' )->andReturn( array( new ImageAltMissing(), 'nonsense', 42, null ) );

		$registry = RuleRegistry::with_defaults();

		$this->assertCount( 1, $registry->all() );
	}

	/**
	 * A site can remove a rule it disagrees with.
	 *
	 * @return void
	 */
	public function test_a_rule_can_be_removed(): void {
		$registry = RuleRegistry::with_defaults();
		$registry->remove( 'img-alt-missing' );

		$this->assertNull( $registry->get( 'img-alt-missing' ) );
	}

	/**
	 * Coverage describes every rule, including whether it needs a human.
	 *
	 * This is the data behind the honesty panel, so every row must carry a
	 * detection value.
	 *
	 * @return void
	 */
	public function test_coverage_reports_detection_for_every_rule(): void {
		$coverage = RuleRegistry::with_defaults()->coverage();

		$this->assertCount( 29, $coverage, '24 server checks plus 5 browser checks.' );

		$valid  = array( Detection::Auto->value, Detection::Manual->value );
		$passes = array( ScanPass::Server->value, ScanPass::Browser->value );

		foreach ( $coverage as $row ) {
			$this->assertNotSame( '', $row['id'] );
			$this->assertNotSame( '', $row['title'] );
			$this->assertNotSame( '', $row['description'] );
			$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $row['wcag_sc'] );
			$this->assertContains( $row['detection'], $valid );
			$this->assertContains( $row['pass'], $passes, $row['id'] . ' does not say which pass performs it.' );
		}
	}

	/**
	 * The coverage list describes the browser checks even though PHP cannot run them.
	 *
	 * The panel answers "what can this plugin look at", not "what did it manage
	 * to look at this time". Listing only the checks that happened to be
	 * available would quietly shrink the stated coverage on exactly the scans
	 * where the user most needs to know something was missed.
	 *
	 * @return void
	 */
	public function test_coverage_includes_browser_checks_that_php_cannot_run(): void {
		$coverage = RuleRegistry::with_defaults()->coverage();

		$browser = array_values(
			array_filter(
				$coverage,
				static fn( array $row ): bool => ScanPass::Browser->value === $row['pass']
			)
		);

		$this->assertCount( 5, $browser );

		$ids = array_column( $browser, 'id' );
		$this->assertContains( 'colour-contrast', $ids, 'Contrast is the whole point of the browser pass.' );

		// Executable rules are still only the server ones — the engine must not
		// try to evaluate a check that has no PHP implementation.
		$this->assertCount( 24, RuleRegistry::with_defaults()->all() );
	}

	/**
	 * No check is claimed by both passes.
	 *
	 * Identity is the rule ID, not the success criterion. Two engines reporting
	 * the same *check* twice would make the findings list untrustworthy and let
	 * the score punish one fault repeatedly — that is what this guards.
	 *
	 * Sharing a success criterion is expected and fine. 4.1.2 covers name, role
	 * and value for every control on a page; "this button has no accessible
	 * name" and "this aria-hidden element can still take focus" both live there
	 * and are entirely different faults with entirely different fixes.
	 *
	 * @return void
	 */
	public function test_no_check_is_claimed_by_both_passes(): void {
		$coverage = RuleRegistry::with_defaults()->coverage();

		$ids = array_column( $coverage, 'id' );

		$this->assertSame(
			array_unique( $ids ),
			$ids,
			'A rule ID is claimed by more than one check, so one fault would be reported and scored twice.'
		);

		// The allowlist the browser pass is validated against carries every
		// browser check and nothing else. A finding naming anything outside it
		// is dropped, so a short list here would silently discard real findings.
		$this->assertSame(
			array_column( array_filter( $coverage, static fn( $r ) => 'browser' === $r['pass'] ), 'id' ),
			BrowserRules::ids(),
			'The browser allowlist and the browser coverage rows have drifted apart.'
		);
	}

	/**
	 * The rule set genuinely contains both kinds of check.
	 *
	 * If every rule claimed to be automatic, the honesty tag would be
	 * decoration rather than information.
	 *
	 * @return void
	 */
	public function test_the_rule_set_contains_both_auto_and_manual_checks(): void {
		$detections = array_column( RuleRegistry::with_defaults()->coverage(), 'detection' );

		$this->assertContains( Detection::Auto->value, $detections );
		$this->assertContains( Detection::Manual->value, $detections );
	}
}
