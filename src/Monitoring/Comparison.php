<?php
/**
 * Working out what moved between two scans.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Monitoring;

use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Db\Scan;
use WOWStudio\AccessibilityKit\Db\ScanRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Compares a page's two most recent scans.
 *
 * Computed from what is already stored rather than written into a log of its
 * own. A separate change table would be faster to read and would eventually
 * disagree with the scans it claims to describe — a scan deleted, a run
 * cancelled halfway, a restore from backup — and a monitoring feature whose
 * history contradicts its own data is worse than no history.
 *
 * The unit of comparison is the rule, not the individual finding. Findings do
 * not have stable identities across scans: the same missing alt attribute on
 * the same image is a new row every time the page is scanned, because a finding
 * records where something was in one parse of one document. Counting per rule
 * asks the question that can actually be answered — "did this page start
 * failing something it used to pass" — and answers it correctly even when the
 * content around the fault has moved.
 *
 * @since 0.21.0
 */
final class Comparison {

	/**
	 * Scans.
	 *
	 * @since 0.21.0
	 * @var ScanRepository
	 */
	private ScanRepository $scans;

	/**
	 * Findings.
	 *
	 * @since 0.21.0
	 * @var IssueRepository
	 */
	private IssueRepository $issues;

	/**
	 * Constructor.
	 *
	 * @since 0.21.0
	 *
	 * @param ScanRepository|null  $scans  Scans.
	 * @param IssueRepository|null $issues Findings.
	 */
	public function __construct( ?ScanRepository $scans = null, ?IssueRepository $issues = null ) {
		$this->scans  = $scans ?? new ScanRepository();
		$this->issues = $issues ?? new IssueRepository();
	}

	/**
	 * Returns what changed on one page, or null when there is nothing to say.
	 *
	 * Null rather than an empty change when the page has been scanned only
	 * once. "No change" and "nothing to compare against" are different
	 * statements, and a first scan reported as "no change" would be a lie about
	 * a page nobody has ever looked at twice.
	 *
	 * @since 0.21.0
	 *
	 * @param int $post_id The page.
	 * @return Change|null
	 */
	public function for_post( int $post_id ): ?Change {
		$history = $this->scans->history_for_post( $post_id, 2 );

		if ( count( $history ) < 2 ) {
			return null;
		}

		return $this->between( $history[1], $history[0] );
	}

	/**
	 * Builds the change record between two scans of the same page.
	 *
	 * @since 0.21.0
	 *
	 * @param Scan $before The earlier scan.
	 * @param Scan $after  The later scan.
	 * @return Change
	 */
	public function between( Scan $before, Scan $after ): Change {
		$was = $this->rule_counts( $before->id );
		$now = $this->rule_counts( $after->id );

		$appeared = array();
		$resolved = array();

		foreach ( $now as $rule => $count ) {
			$previous = $was[ $rule ] ?? 0;

			if ( $count > $previous ) {
				$appeared[ $rule ] = $count - $previous;
			}
		}

		foreach ( $was as $rule => $count ) {
			$current = $now[ $rule ] ?? 0;

			if ( $count > $current ) {
				$resolved[ $rule ] = $count - $current;
			}
		}

		ksort( $appeared );
		ksort( $resolved );

		return new Change(
			$after->target_id,
			(string) get_the_title( $after->target_id ),
			$before->id,
			$after->id,
			$before->score,
			$after->score,
			$appeared,
			$resolved,
			(string) ( $after->finished_at ?? $after->started_at )
		);
	}

	/**
	 * Returns changes across every page scanned more than once, newest first.
	 *
	 * @since 0.21.0
	 *
	 * @param int  $limit          Maximum rows to return.
	 * @param bool $only_movements Whether to drop pages where nothing moved.
	 * @return Change[]
	 */
	public function recent( int $limit = 20, bool $only_movements = true ): array {
		$limit = max( 1, min( 100, $limit ) );

		$seen    = array();
		$changes = array();

		// Walks recent scans rather than posts, so a site with ten thousand
		// pages does not pay for nine thousand of them that have never been
		// scanned at all.
		foreach ( $this->scans->recent( 100 ) as $scan ) {
			if ( count( $changes ) >= $limit ) {
				break;
			}

			$post_id = $scan->target_id;

			if ( $post_id <= 0 || isset( $seen[ $post_id ] ) ) {
				continue;
			}

			$seen[ $post_id ] = true;

			$change = $this->for_post( $post_id );

			if ( null === $change ) {
				continue;
			}

			if ( $only_movements && $change->is_empty() ) {
				continue;
			}

			$changes[] = $change;
		}

		return $changes;
	}

	/**
	 * Returns how many open findings a scan has, per rule.
	 *
	 * @since 0.21.0
	 *
	 * @param int $scan_id The scan.
	 * @return array<string, int>
	 */
	private function rule_counts( int $scan_id ): array {
		return array_map( 'intval', $this->issues->count_by( $scan_id, 'rule_id' ) );
	}
}
