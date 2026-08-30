<?php
/**
 * Scan storage.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Db;

use WOWStudio\AccessibilityKit\Scanner\BrowserPassStatus;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\Scanner\ScanStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes scan runs.
 *
 * Table names go through prepare()'s %i identifier placeholder rather than
 * string interpolation. WordPress 6.2 added it, our floor is 6.6, and it means
 * no query in this class assembles SQL by concatenation.
 *
 * Timestamps are stored in UTC. Formatting for the reader's locale and timezone
 * is the presentation layer's job, and storing local time would make any later
 * timezone change silently rewrite history.
 *
 * @since 0.2.0
 */
final class ScanRepository {

	/**
	 * Records the start of a scan.
	 *
	 * @since 0.2.0
	 *
	 * @param ScanScope $scope     What the scan will cover.
	 * @param int       $target_id Post ID for a page scan, 0 for a site scan.
	 * @param int       $user_id   User starting the scan.
	 * @return int New scan ID, or 0 on failure.
	 */
	public function start( ScanScope $scope, int $target_id = 0, int $user_id = 0 ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it, and a write must not be cached.
		$inserted = $wpdb->insert(
			Schema::scans_table(),
			array(
				'scope'      => $scope->value,
				'target_id'  => $target_id,
				'status'     => ScanStatus::Running->value,
				'started_at' => gmdate( 'Y-m-d H:i:s' ),
				'created_by' => $user_id,
			),
			array( '%s', '%d', '%s', '%s', '%d' )
		);

		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Marks a scan finished and stores its score and summary.
	 *
	 * @since 0.2.0
	 *
	 * @param int                  $scan_id Scan to update.
	 * @param int|null             $score   Score out of 100.
	 * @param array<string, mixed> $summary Summary payload, stored as JSON.
	 * @param BrowserPassStatus    $browser_pass Whether the render-dependent checks ran.
	 *                                           Defaults to Skipped, so a caller that
	 *                                           forgets to say records the honest answer
	 *                                           rather than implying coverage it never had.
	 * @return bool
	 */
	public function complete( int $scan_id, ?int $score, array $summary = array(), BrowserPassStatus $browser_pass = BrowserPassStatus::Skipped ): bool {
		global $wpdb;

		$encoded = wp_json_encode( $summary );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$updated = $wpdb->update(
			Schema::scans_table(),
			array(
				'status'       => ScanStatus::Complete->value,
				'score'        => $score,
				'browser_pass' => $browser_pass->value,
				'summary'      => false === $encoded ? '' : $encoded,
				'finished_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $scan_id ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Records what happened to the browser pass, and the score that follows.
	 *
	 * Separate from complete() because the browser pass finishes after the
	 * server pass has already been recorded — the scan exists, has findings and
	 * a score, and is then told that a second engine has been over the same
	 * page. Folding this into complete() would mean a scan could not be
	 * considered finished until a browser had reported, which is exactly the
	 * coupling the two-pass design is trying to avoid: a scheduled scan with no
	 * browser attached must still be a complete, honest scan.
	 *
	 * @since 0.10.0
	 *
	 * @param int               $scan_id Scan to update.
	 * @param BrowserPassStatus $status  What happened to the pass.
	 * @param int|null          $score   Recalculated score, or null to leave it.
	 * @return bool
	 */
	public function record_browser_pass( int $scan_id, BrowserPassStatus $status, ?int $score = null ): bool {
		global $wpdb;

		$data   = array( 'browser_pass' => $status->value );
		$format = array( '%s' );

		if ( null !== $score ) {
			$data['score'] = $score;
			$format[]      = '%d';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$updated = $wpdb->update(
			Schema::scans_table(),
			$data,
			array( 'id' => $scan_id ),
			$format,
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Marks a scan as failed.
	 *
	 * A run interrupted part-way is recorded rather than left looking like a
	 * scan that found nothing.
	 *
	 * @since 0.2.0
	 *
	 * @param int $scan_id Scan to update.
	 * @return bool
	 */
	public function fail( int $scan_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$updated = $wpdb->update(
			Schema::scans_table(),
			array(
				'status'      => ScanStatus::Failed->value,
				'finished_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $scan_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Finds one scan by ID.
	 *
	 * @since 0.2.0
	 *
	 * @param int $id Scan ID.
	 * @return Scan|null
	 */
	public function find( int $id ): ?Scan {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Schema::scans_table(), $id ) );

		return $row instanceof \stdClass ? Scan::from_row( $row ) : null;
	}

	/**
	 * Finds the most recent completed scan for a post.
	 *
	 * @since 0.2.0
	 *
	 * @param int $post_id Post ID.
	 * @return Scan|null
	 */
	public function latest_for_post( int $post_id ): ?Scan {
		global $wpdb;

		// The statement is written inline rather than into a variable: static
		// analysers cannot tell a fixed literal from a built string once it has
		// been through a variable, and report every such call as unescaped.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE scope = %s AND target_id = %d AND status = %s ORDER BY started_at DESC, id DESC LIMIT 1',
				Schema::scans_table(),
				ScanScope::Page->value,
				$post_id,
				ScanStatus::Complete->value
			)
		);

		return $row instanceof \stdClass ? Scan::from_row( $row ) : null;
	}

	/**
	 * Returns the most recent scans, newest first.
	 *
	 * @since 0.2.0
	 *
	 * @param int $limit Maximum rows to return, capped at 100.
	 * @return Scan[]
	 */
	public function recent( int $limit = 20 ): array {
		global $wpdb;

		$limit = max( 1, min( 100, $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY started_at DESC, id DESC LIMIT %d',
				Schema::scans_table(),
				$limit
			)
		);

		return array_map(
			static fn( object $row ): Scan => Scan::from_row( $row ),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Deletes a scan and the issues belonging to it.
	 *
	 * WordPress tables carry no foreign keys, so the cascade is explicit.
	 * Issues are removed first: an orphaned issue row is worse than an orphaned
	 * scan, because the UI reads issues by scan and would show findings that
	 * belong to nothing.
	 *
	 * @since 0.2.0
	 *
	 * @param int $id Scan ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$wpdb->delete( Schema::issues_table(), array( 'scan_id' => $id ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$deleted = $wpdb->delete( Schema::scans_table(), array( 'id' => $id ), array( '%d' ) );

		return false !== $deleted && $deleted > 0;
	}
}
