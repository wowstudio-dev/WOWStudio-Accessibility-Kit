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
 *
 * Not final, unlike most classes here. It is handed to another object through
 * that object's constructor so the object can be tested without it, and sealing
 * it would make the injection decorative — a parameter nobody could ever pass
 * anything but the default to. The rule here is final by default, open where
 * something is meant to be substituted.
 */
class ScanRepository {

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
	 * Sets a scan's status and stamps it finished.
	 *
	 * @since 0.12.0
	 *
	 * @param int        $scan_id Scan to update.
	 * @param ScanStatus $status  New status.
	 * @return bool
	 */
	public function set_status( int $scan_id, ScanStatus $status ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$updated = $wpdb->update(
			Schema::scans_table(),
			array(
				'status'      => $status->value,
				'finished_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $scan_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Records a scan that is waiting for the queue.
	 *
	 * Separate from start() because the two mean different things. start() says
	 * "this is happening now"; this says "this is owed". A run that is
	 * interrupted has to be able to tell work it never began from work it began
	 * and lost, and one status cannot carry both.
	 *
	 * @since 0.12.0
	 *
	 * @param ScanScope $scope     What the scan will cover.
	 * @param int       $target_id Post to scan.
	 * @param int       $user_id   Who asked for it.
	 * @param int       $parent_id Run it belongs to.
	 * @return int Row ID, or 0 when the insert failed.
	 */
	public function queue( ScanScope $scope, int $target_id, int $user_id, int $parent_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it, and a write must not be cached.
		$inserted = $wpdb->insert(
			Schema::scans_table(),
			array(
				'scope'      => $scope->value,
				'target_id'  => $target_id,
				'parent_id'  => $parent_id,
				'status'     => ScanStatus::Queued->value,
				'started_at' => gmdate( 'Y-m-d H:i:s' ),
				'created_by' => $user_id,
			),
			array( '%s', '%d', '%d', '%s', '%s', '%d' )
		);

		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Opens a run and returns its ID.
	 *
	 * @since 0.12.0
	 *
	 * @param int $user_id Who started it.
	 * @return int
	 */
	public function start_run( int $user_id ): int {
		return $this->start( ScanScope::Site, 0, $user_id );
	}

	/**
	 * Moves a queued scan to running.
	 *
	 * Returns false when the row was not in the queue any more — cancelled,
	 * or already claimed by another worker. Callers treat that as "somebody
	 * else owns this now" and stop, which is what makes a duplicated job
	 * harmless rather than a double scan.
	 *
	 * @since 0.12.0
	 *
	 * @param int $scan_id Scan to claim.
	 * @return bool
	 */
	public function claim( int $scan_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET status = %s, started_at = %s WHERE id = %d AND status = %s',
				Schema::scans_table(),
				ScanStatus::Running->value,
				gmdate( 'Y-m-d H:i:s' ),
				$scan_id,
				ScanStatus::Queued->value
			)
		);

		return is_int( $updated ) && $updated > 0;
	}

	/**
	 * Counts a run's scans by status.
	 *
	 * @since 0.12.0
	 *
	 * @param int $parent_id Run to count.
	 * @return array<string, int> Counts keyed by status value.
	 */
	public function count_by_status( int $parent_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS total FROM %i WHERE parent_id = %d GROUP BY status',
				Schema::scans_table(),
				$parent_id
			)
		);

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row->status ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Returns a run's scans, oldest first.
	 *
	 * @since 0.12.0
	 *
	 * @param int $parent_id Run to read.
	 * @param int $limit     Maximum rows.
	 * @return Scan[]
	 */
	public function children( int $parent_id, int $limit = 500 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE parent_id = %d ORDER BY id ASC LIMIT %d',
				Schema::scans_table(),
				$parent_id,
				max( 1, $limit )
			)
		);

		return array_map(
			static fn( object $row ): Scan => Scan::from_row( $row ),
			(array) $rows
		);
	}

	/**
	 * Cancels everything in a run that has not started.
	 *
	 * Work already running is left alone. Interrupting a scan mid-flight would
	 * leave findings half-written, and the step it is in will finish in seconds
	 * anyway.
	 *
	 * @since 0.12.0
	 *
	 * @param int $parent_id Run to stop.
	 * @return int How many scans were cancelled.
	 */
	public function cancel_queued( int $parent_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET status = %s, finished_at = %s WHERE parent_id = %d AND status = %s',
				Schema::scans_table(),
				ScanStatus::Cancelled->value,
				gmdate( 'Y-m-d H:i:s' ),
				$parent_id,
				ScanStatus::Queued->value
			)
		);

		return is_int( $updated ) ? $updated : 0;
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
	 * @param int    $scan_id Scan to update.
	 * @param string $reason  What went wrong, kept with the row.
	 * @return bool
	 */
	public function fail( int $scan_id, string $reason = '' ): bool {
		global $wpdb;

		$data    = array(
			'status'      => ScanStatus::Failed->value,
			'finished_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		$formats = array( '%s', '%s' );

		// The reason is stored rather than logged. A bulk run that skipped nine
		// pages has to be able to say which nine and why, months later, without
		// anybody having had debug logging switched on at the time.
		if ( '' !== $reason ) {
			$encoded         = wp_json_encode( array( 'error' => $reason ) );
			$data['summary'] = false === $encoded ? '' : $encoded;
			$formats[]       = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$updated = $wpdb->update(
			Schema::scans_table(),
			$data,
			array( 'id' => $scan_id ),
			$formats,
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
	 * Finds the most recent completed scan of a given scope.
	 *
	 * @since 0.13.0
	 *
	 * @param ScanScope $scope Scope to look for.
	 * @return Scan|null
	 */
	public function latest_of_scope( ScanScope $scope ): ?Scan {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE scope = %s AND status = %s ORDER BY id DESC LIMIT 1',
				Schema::scans_table(),
				$scope->value,
				ScanStatus::Complete->value
			)
		);

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
	 * Returns a post's most recent completed scans, newest first.
	 *
	 * Monitoring is comparison, and comparison needs two of something. This is
	 * the "something": the last few scans of one page, so a change can be
	 * worked out from what is already stored rather than from a separate log
	 * that could disagree with it.
	 *
	 * Only completed scans. A run that failed or was cancelled recorded nothing
	 * to compare against, and treating it as the previous state would report
	 * every finding on the page as newly appeared.
	 *
	 * @since 0.21.0
	 *
	 * @param int $post_id Post ID.
	 * @param int $limit   How many to return, capped at 50.
	 * @return Scan[]
	 */
	public function history_for_post( int $post_id, int $limit = 2 ): array {
		global $wpdb;

		$limit = max( 1, min( 50, $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE scope = %s AND target_id = %d AND status = %s ORDER BY started_at DESC, id DESC LIMIT %d',
				Schema::scans_table(),
				ScanScope::Page->value,
				$post_id,
				ScanStatus::Complete->value,
				$limit
			)
		);

		return array_map(
			static fn( $row ): Scan => Scan::from_row( $row ),
			is_array( $rows ) ? $rows : array()
		);
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
