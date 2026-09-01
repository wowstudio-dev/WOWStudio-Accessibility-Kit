<?php
/**
 * Tests for the order findings are worked through in.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Remediation\ActionBand;
use WOWStudio\AccessibilityKit\Remediation\WorkList;
use WOWStudio\AccessibilityKit\Scanner\BrowserRules;
use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests that the list opens with work somebody can finish.
 *
 * Sorted by severity, a real page opens with the most serious problem on it —
 * which is very often in the theme, and not the reader's to fix. A list that
 * opens with "ask your developer" is a list people close. These tests pin the
 * ordering that replaced it.
 *
 * @covers \WOWStudio\AccessibilityKit\Remediation\WorkList
 * @covers \WOWStudio\AccessibilityKit\Remediation\ActionBand
 */
final class WorkListTest extends TestCase {

	/**
	 * A work list over the real rule set.
	 *
	 * @return WorkList
	 */
	private function list(): WorkList {
		return new WorkList( array_merge( ( new Engine() )->registry()->all(), BrowserRules::all() ) );
	}

	/**
	 * Builds a finding row.
	 *
	 * @param string $rule_id  Rule.
	 * @param string $severity Severity value.
	 * @return array<string, mixed>
	 */
	private function issue( string $rule_id, string $severity = 'moderate' ): array {
		return array(
			'rule_id'  => $rule_id,
			'severity' => $severity,
		);
	}

	/**
	 * Findings are banded by what they ask of the reader.
	 *
	 * @return void
	 */
	public function test_findings_are_banded_by_what_they_ask_of_you(): void {
		$work = $this->list();

		$this->assertSame( ActionBand::Now, $work->band_for( 'colour-contrast' ) );
		$this->assertSame( ActionBand::Review, $work->band_for( 'img-alt-missing' ) );
		$this->assertSame( ActionBand::Decide, $work->band_for( 'table-headers-missing' ) );
		$this->assertSame( ActionBand::Delegate, $work->band_for( 'landmark-main-missing' ) );
	}

	/**
	 * A one-click fix comes before a critical problem in the theme.
	 *
	 * The whole point, and the thing that looks wrong until you say it out
	 * loud: the critical finding matters more, and putting it first serves
	 * nobody, because the reader cannot act on it.
	 *
	 * @return void
	 */
	public function test_cheap_wins_come_before_serious_work_somebody_else_must_do(): void {
		$ordered = $this->list()->order(
			array(
				$this->issue( 'landmark-main-missing', 'critical' ),
				$this->issue( 'colour-contrast', 'minor' ),
			)
		);

		$this->assertSame( 'colour-contrast', $ordered[0]['rule_id'] );
		$this->assertSame( 'landmark-main-missing', $ordered[1]['rule_id'] );
	}

	/**
	 * Severity still decides, inside a band.
	 *
	 * @return void
	 */
	public function test_severity_sorts_within_a_band(): void {
		$ordered = $this->list()->order(
			array(
				$this->issue( 'img-alt-missing', 'minor' ),
				$this->issue( 'button-name-missing', 'critical' ),
			)
		);

		$this->assertSame( 'button-name-missing', $ordered[0]['rule_id'] );
	}

	/**
	 * Findings that tie keep the order they arrived in.
	 *
	 * A list that reshuffles between two readings of the same scan is a list
	 * nobody can work through, because their place in it keeps moving.
	 *
	 * @return void
	 */
	public function test_the_order_is_stable(): void {
		$issues = array();

		for ( $i = 0; $i < 12; $i++ ) {
			$issues[] = array(
				'rule_id'  => 'img-alt-missing',
				'severity' => 'critical',
				'marker'   => $i,
			);
		}

		$markers = array_column( $this->list()->order( $issues ), 'marker' );

		$this->assertSame( range( 0, 11 ), $markers );
	}

	/**
	 * An unrecognised rule needs a person; it is never filed as harmless.
	 *
	 * @return void
	 */
	public function test_an_unknown_rule_needs_a_person(): void {
		$this->assertSame( ActionBand::Decide, $this->list()->band_for( 'something-we-never-shipped' ) );
	}

	/**
	 * Grouping drops empty bands and keeps the reading order.
	 *
	 * @return void
	 */
	public function test_grouping_keeps_the_order_and_drops_empty_bands(): void {
		$groups = $this->list()->grouped(
			array(
				$this->issue( 'landmark-main-missing' ),
				$this->issue( 'colour-contrast' ),
			)
		);

		$this->assertCount( 2, $groups );
		$this->assertSame( 'now', $groups[0]['band'] );
		$this->assertSame( 'delegate', $groups[1]['band'] );
		$this->assertSame( 1, $groups[0]['count'] );
		$this->assertNotSame( '', $groups[0]['label'] );
		$this->assertNotSame( '', $groups[0]['blurb'] );
	}

	/**
	 * The tally keeps empty bands, unlike the grouping.
	 *
	 * "Nothing needs a decision from you" is worth reading; an absent line says
	 * nothing at all.
	 *
	 * @return void
	 */
	public function test_the_tally_reports_zeroes(): void {
		$tally = $this->list()->tally( array( $this->issue( 'colour-contrast' ) ) );

		$this->assertSame( 1, $tally['now'] );
		$this->assertSame( 0, $tally['review'] );
		$this->assertSame( 0, $tally['decide'] );
		$this->assertSame( 0, $tally['delegate'] );
	}

	/**
	 * Every rule says what its findings cost a person, without jargon.
	 *
	 * The list of banned words is the point. "Accessible name" and "landmark"
	 * are precise, and they are precise in a vocabulary the person reading this
	 * has no reason to have learned.
	 *
	 * @return void
	 */
	public function test_every_consequence_is_written_for_a_reader(): void {
		$jargon = array( 'accessible name', 'landmark', 'alt attribute', 'aria-', 'DOM', 'tabindex', 'WCAG' );

		foreach ( array_merge( ( new Engine() )->registry()->all(), BrowserRules::all() ) as $rule ) {
			$sentence = $rule->consequence();

			$this->assertNotSame( '', trim( $sentence ), $rule->id() . ' does not say who it shuts out.' );
			$this->assertLessThan( 200, strlen( $sentence ), $rule->id() . ' is too long to sit in a list.' );

			foreach ( $jargon as $term ) {
				$this->assertStringNotContainsStringIgnoringCase(
					$term,
					$sentence,
					$rule->id() . ' explains itself in specification vocabulary.'
				);
			}
		}
	}
}
