<?php
/**
 * Scan result.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Everything one scan produced.
 *
 * @since 0.3.0
 */
final class Result {

	/**
	 * Score penalty per severity.
	 *
	 * @since 0.3.0
	 * @var array<string, int>
	 */
	private const PENALTY = array(
		'critical' => 10,
		'serious'  => 6,
		'moderate' => 3,
		'minor'    => 1,
	);

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Finding[] $findings  What the rules reported.
	 * @param bool      $full_page Whether a whole page was scanned.
	 * @param int       $rules_run How many rules ran.
	 */
	public function __construct(
		public readonly array $findings,
		public readonly bool $full_page = true,
		public readonly int $rules_run = 0
	) {}

	/**
	 * Returns findings counted by severity.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string, int>
	 */
	public function count_by_severity(): array {
		$counts = array();

		foreach ( $this->findings as $finding ) {
			$key            = $finding->severity->value;
			$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
		}

		return $counts;
	}

	/**
	 * Returns findings counted by detection type.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string, int>
	 */
	public function count_by_detection(): array {
		$counts = array(
			Detection::Auto->value   => 0,
			Detection::Manual->value => 0,
		);

		foreach ( $this->findings as $finding ) {
			++$counts[ $finding->detection->value ];
		}

		return $counts;
	}

	/**
	 * Returns findings counted by rule.
	 *
	 * Recorded into the scan summary because the summary is the only part of a
	 * scan that outlives it. Individual findings are pruned once a newer scan
	 * supersedes them, so a week later the rows are gone and the counts here are
	 * all that remains of what the page was failing.
	 *
	 * That matters for anything comparing a page against its own past: without
	 * this, the older of two scans has no findings to count, every rule in the
	 * newer one reads as newly broken, and every page looks like a regression.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, int>
	 */
	public function count_by_rule(): array {
		$counts = array();

		foreach ( $this->findings as $finding ) {
			$key            = $finding->rule_id;
			$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
		}

		ksort( $counts );

		return $counts;
	}

	/**
	 * Returns a score out of 100.
	 *
	 * Only auto-detected findings reduce the score. Items flagged for human
	 * review are unconfirmed by definition, and counting them as failures would
	 * report a page as worse than we actually know it to be. The trade-off is
	 * that the score alone never describes a page, which is why the number is
	 * always shown next to the review count rather than on its own.
	 *
	 * @since 0.3.0
	 *
	 * @return int
	 */
	public function score(): int {
		$penalty = 0;

		foreach ( $this->findings as $finding ) {
			if ( Detection::Auto !== $finding->detection ) {
				continue;
			}

			$penalty += self::PENALTY[ $finding->severity->value ] ?? 1;
		}

		$score = max( 0, 100 - $penalty );

		/**
		 * Filters the score a scan produces.
		 *
		 * @since 0.3.0
		 *
		 * @param int    $score   Score out of 100.
		 * @param Result $result  The scan result.
		 */
		return (int) apply_filters( 'wsak_scan_score', $score, $this );
	}

	/**
	 * Returns the summary payload stored with the scan.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string, mixed>
	 */
	public function summary(): array {
		return array(
			'total'        => count( $this->findings ),
			'by_severity'  => $this->count_by_severity(),
			'by_detection' => $this->count_by_detection(),
			'by_rule'      => $this->count_by_rule(),
			'rules_run'    => $this->rules_run,
			'full_page'    => $this->full_page,
		);
	}
}
