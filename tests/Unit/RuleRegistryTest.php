<?php
/**
 * Rule registry tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Filters;
use WOWStudio\AccessibilityKit\Scanner\Detection;
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

		$this->assertCount( 12, $registry->all() );
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

		$this->assertCount( 12, $coverage );

		$valid = array( Detection::Auto->value, Detection::Manual->value );

		foreach ( $coverage as $row ) {
			$this->assertNotSame( '', $row['id'] );
			$this->assertNotSame( '', $row['title'] );
			$this->assertNotSame( '', $row['description'] );
			$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $row['wcag_sc'] );
			$this->assertContains( $row['detection'], $valid );
		}
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
