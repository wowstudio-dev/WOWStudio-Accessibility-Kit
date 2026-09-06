<?php
/**
 * Issue storage.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Db;

use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\Fingerprint;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WOWStudio\AccessibilityKit\Scanner\ScanPass;
use WOWStudio\AccessibilityKit\Scanner\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes scan findings.
 *
 * Table and column names go through prepare()'s %i identifier placeholder, so
 * no query here is assembled by concatenation. The allow-list on the groupable
 * columns stays regardless: %i escapes an identifier, it does not check that
 * the identifier is one a caller should be allowed to ask for.
 *
 * @since 0.2.0
 *
 * Not final, unlike most classes here. It is handed to another object through
 * that object's constructor so the object can be tested without it, and sealing
 * it would make the injection decorative — a parameter nobody could ever pass
 * anything but the default to. The rule here is final by default, open where
 * something is meant to be substituted.
 */
class IssueRepository {

	/**
	 * Columns that may be grouped on.
	 *
	 * Grouping takes a column name, which cannot be parameterised. Restricting
	 * it to this list is what keeps the query safe.
	 *
	 * @since 0.2.0
	 * @var string[]
	 */
	private const GROUPABLE = array( 'severity', 'detection', 'status', 'rule_id', 'wcag_sc' );

	/**
	 * Durable record of what people have already decided.
	 *
	 * @since 0.29.0
	 * @var DecisionRepository|null
	 */
	private ?DecisionRepository $decisions;

	/**
	 * Constructor.
	 *
	 * @since 0.29.0
	 *
	 * @param DecisionRepository|null $decisions Decision storage.
	 */
	public function __construct( ?DecisionRepository $decisions = null ) {
		$this->decisions = $decisions;
	}

	/**
	 * Returns decision storage, built on first use.
	 *
	 * @since 0.29.0
	 *
	 * @return DecisionRepository
	 */
	private function decisions(): DecisionRepository {
		if ( ! $this->decisions instanceof DecisionRepository ) {
			$this->decisions = new DecisionRepository();
		}

		return $this->decisions;
	}

	/**
	 * Stores a batch of findings for a scan.
	 *
	 * Written as one multi-row INSERT: a scan can produce hundreds of findings,
	 * and a query per row is the difference between a fast scan and a timeout.
	 *
	 * Each row is stamped with its fingerprint and with whatever has already
	 * been decided about it. Carrying the decision onto the row here, rather
	 * than joining to it on the way out, is what lets every existing query keep
	 * working unchanged — the counts, the severity breakdown, the dismissal log
	 * and the per-scan lists all read `status` off the row and all stay correct
	 * without knowing decisions exist. The decisions table is the record; this
	 * column is a copy of it that the last scan happens to be holding.
	 *
	 * @since 0.2.0
	 *
	 * @param int                             $scan_id Scan the findings belong to.
	 * @param array<int, array<string,mixed>> $issues  Findings, each with rule_id and message at minimum.
	 * @return int Number of rows written.
	 */
	public function add_many( int $scan_id, array $issues ): int {
		global $wpdb;

		if ( array() === $issues ) {
			return 0;
		}

		$now          = gmdate( 'Y-m-d H:i:s' );
		$placeholders = array();
		$values       = array();
		$decided      = $this->decisions_for( $issues );

		foreach ( $issues as $issue ) {
			$severity  = $issue['severity'] ?? Severity::Moderate;
			$detection = $issue['detection'] ?? Detection::Manual;
			$found_by  = $issue['found_by'] ?? ScanPass::Server;

			$post_id     = (int) ( $issue['post_id'] ?? 0 );
			$rule_id     = (string) ( $issue['rule_id'] ?? '' );
			$context     = (string) ( $issue['context'] ?? '' );
			$fingerprint = Fingerprint::of( $rule_id, $context );

			$decision = $decided[ $post_id ][ $fingerprint ] ?? null;

			$placeholders[] = '(%d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s)';

			array_push(
				$values,
				$scan_id,
				$post_id,
				$rule_id,
				(string) ( $issue['wcag_sc'] ?? '' ),
				$severity instanceof Severity ? $severity->value : (string) $severity,
				$detection instanceof Detection ? $detection->value : (string) $detection,
				$found_by instanceof ScanPass ? $found_by->value : (string) $found_by,
				$decision instanceof Decision ? $decision->status->value : IssueStatus::Open->value,
				$fingerprint,
				(string) ( $issue['selector'] ?? '' ),
				$context,
				(string) ( $issue['message'] ?? '' ),
				$decision instanceof Decision ? $decision->note : (string) ( $issue['note'] ?? '' ),
				$decision instanceof Decision ? $decision->decided_by : 0,
				$now,
				$now
			);
		}

		array_unshift( $values, Schema::issues_table() );

		$sql = 'INSERT INTO %i'
			. ' (scan_id, post_id, rule_id, wcag_sc, severity, detection, found_by, status, fingerprint, selector, context, message, note, resolved_by, created_at, updated_at)'
			. ' VALUES ' . implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Assembled from placeholders only; the table and every value go through prepare().
		$written = $wpdb->query( $wpdb->prepare( $sql, $values ) );

		return false === $written ? 0 : (int) $written;
	}

	/**
	 * Looks up what has already been decided about a batch of findings.
	 *
	 * Grouped by post because a decision is scoped to a page, and a batch can
	 * span more than one — a theme scan reports against post 0 while a page
	 * scan reports against its own. One query per distinct page, which in
	 * practice is one.
	 *
	 * @since 0.29.0
	 *
	 * @param array<int, array<string,mixed>> $issues Findings about to be stored.
	 * @return array<int, array<string, Decision>> Decisions keyed by post, then fingerprint.
	 */
	private function decisions_for( array $issues ): array {
		$wanted = array();

		foreach ( $issues as $issue ) {
			$post_id = (int) ( $issue['post_id'] ?? 0 );

			$wanted[ $post_id ][] = Fingerprint::of(
				(string) ( $issue['rule_id'] ?? '' ),
				(string) ( $issue['context'] ?? '' )
			);
		}

		$found = array();

		foreach ( $wanted as $post_id => $fingerprints ) {
			$found[ $post_id ] = $this->decisions()->for_fingerprints( $fingerprints, $post_id );
		}

		return $found;
	}

	/**
	 * Removes the findings of every earlier scan of the same thing.
	 *
	 * Without this the issues table is an append-only log that every count then
	 * reads as though it were the present: a page scanned sixteen times
	 * contributes sixteen copies of each of its faults, and the site-wide
	 * numbers drift further from the truth the more diligently somebody uses
	 * the plugin. The score never had this problem because it joins to the
	 * newest scan per page; the counts beside it did, and disagreed with it.
	 *
	 * Scoped by the scan's own scope and target, read from the scans table
	 * rather than passed in, so a caller cannot prune the wrong thing by
	 * getting an argument wrong. Idempotent: running it twice for the same scan
	 * removes nothing the second time.
	 *
	 * @since 0.29.0
	 *
	 * @param int $scan_id The scan whose findings are the current ones.
	 * @return int Rows removed.
	 */
	public function prune_superseded( int $scan_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables; no core API covers them.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE i FROM %i AS i
				INNER JOIN %i AS keep ON keep.id = %d
				INNER JOIN %i AS old ON old.id = i.scan_id
				WHERE old.id <> keep.id
				  AND old.scope = keep.scope
				  AND old.target_id = keep.target_id',
				Schema::issues_table(),
				Schema::scans_table(),
				$scan_id,
				Schema::scans_table()
			)
		);

		return false === $deleted ? 0 : (int) $deleted;
	}

	/**
	 * Clears out every superseded finding on the site at once.
	 *
	 * The upgrade counterpart of prune_superseded(). Sites that ran earlier
	 * versions have an issues table holding the findings of every scan they
	 * ever ran, so this sweeps the backlog in a single statement rather than
	 * waiting for each page to be scanned again.
	 *
	 * Dismissals are copied into the decisions table before this runs. See
	 * Installer::migrate_decisions(), which owns that ordering.
	 *
	 * @since 0.29.0
	 *
	 * @return int Rows removed.
	 */
	public function prune_all_superseded(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade over the plugin's own tables.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE i FROM %i AS i
				INNER JOIN %i AS s ON s.id = i.scan_id
				INNER JOIN (
					SELECT scope, target_id, MAX(id) AS newest
					FROM %i
					GROUP BY scope, target_id
				) AS newest
					ON newest.scope = s.scope AND newest.target_id = s.target_id
				WHERE i.scan_id <> newest.newest',
				Schema::issues_table(),
				Schema::scans_table(),
				Schema::scans_table()
			)
		);

		return false === $deleted ? 0 : (int) $deleted;
	}

	/**
	 * Finds one issue by ID.
	 *
	 * @since 0.2.0
	 *
	 * @param int $id Issue ID.
	 * @return Issue|null
	 */
	public function find( int $id ): ?Issue {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Schema::issues_table(), $id ) );

		return $row instanceof \stdClass ? Issue::from_row( $row ) : null;
	}

	/**
	 * Finds the issues of a scan, most severe first.
	 *
	 * @since 0.2.0
	 *
	 * @param int                  $scan_id Scan ID.
	 * @param array<string, mixed> $args    Optional status, severity, detection, post_id, limit, offset.
	 * @return Issue[]
	 */
	public function find_by_scan( int $scan_id, array $args = array() ): array {
		global $wpdb;

		$where  = array( 'scan_id = %d' );
		$values = array( Schema::issues_table(), $scan_id );

		if ( ( $args['status'] ?? null ) instanceof IssueStatus ) {
			$where[]  = 'status = %s';
			$values[] = $args['status']->value;
		}

		if ( ( $args['severity'] ?? null ) instanceof Severity ) {
			$where[]  = 'severity = %s';
			$values[] = $args['severity']->value;
		}

		if ( ( $args['detection'] ?? null ) instanceof Detection ) {
			$where[]  = 'detection = %s';
			$values[] = $args['detection']->value;
		}

		if ( isset( $args['post_id'] ) ) {
			$where[]  = 'post_id = %d';
			$values[] = (int) $args['post_id'];
		}

		$values[] = max( 1, min( 500, (int) ( $args['limit'] ?? 100 ) ) );
		$values[] = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$sql = 'SELECT * FROM %i WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY ' . self::severity_order() . ', id ASC LIMIT %d OFFSET %d';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Conditions are fixed placeholder fragments; every value goes through prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		return array_map(
			static fn( object $row ): Issue => Issue::from_row( $row ),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Returns findings somebody has set aside, most recent first.
	 *
	 * The record of what was dismissed, who dismissed it and why. Everything it
	 * needs is already on the row — the note, `resolved_by`, `updated_at` — so
	 * this is a query rather than a second store, and it cannot drift out of
	 * step with the findings it describes.
	 *
	 * Scoped to the latest scan of each page. A finding set aside three scans
	 * ago is recorded against a scan nobody is looking at any more, and listing
	 * every historical copy would show the same decision five times over.
	 *
	 * @since 0.22.0
	 *
	 * @param int $limit  How many to return, capped at 200.
	 * @param int $offset Where to start.
	 * @return Issue[]
	 */
	public function dismissed( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables; no core API covers them.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT i.* FROM %i AS i
				INNER JOIN (
					SELECT target_id, MAX(id) AS newest
					FROM %i
					WHERE scope = %s AND status = %s
					GROUP BY target_id
				) AS latest ON latest.newest = i.scan_id
				WHERE i.status = %s
				ORDER BY i.updated_at DESC, i.id DESC
				LIMIT %d OFFSET %d',
				Schema::issues_table(),
				Schema::scans_table(),
				'page',
				'complete',
				IssueStatus::Ignored->value,
				max( 1, min( 200, $limit ) ),
				max( 0, $offset )
			)
		);

		return array_map(
			static fn( object $row ): Issue => Issue::from_row( $row ),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Counts how many findings are currently set aside.
	 *
	 * @since 0.22.0
	 *
	 * @return int
	 */
	public function dismissed_count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables; no core API covers them.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i AS i
				INNER JOIN (
					SELECT target_id, MAX(id) AS newest
					FROM %i
					WHERE scope = %s AND status = %s
					GROUP BY target_id
				) AS latest ON latest.newest = i.scan_id
				WHERE i.status = %s',
				Schema::issues_table(),
				Schema::scans_table(),
				'page',
				'complete',
				IssueStatus::Ignored->value
			)
		);
	}

	/**
	 * Counts a scan's issues grouped by one column.
	 *
	 * Grouping by detection is how the UI reports what automation could and
	 * could not decide, so this is the honesty panel's data source.
	 *
	 * @since 0.2.0
	 *
	 * @param int    $scan_id Scan ID.
	 * @param string $column  One of self::GROUPABLE.
	 * @return array<string, int> Counts keyed by column value.
	 */
	public function count_by( int $scan_id, string $column = 'severity' ): array {
		global $wpdb;

		if ( ! in_array( $column, self::GROUPABLE, true ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT %i AS bucket, COUNT(*) AS total FROM %i WHERE scan_id = %d GROUP BY %i',
				$column,
				Schema::issues_table(),
				$scan_id,
				$column
			)
		);

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row->bucket ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Updates an issue's workflow status.
	 *
	 * @since 0.2.0
	 *
	 * @param int         $id     Issue ID.
	 * @param IssueStatus $status  New status.
	 * @param string      $note    Reviewer note, required when ignoring.
	 * @param int         $user_id Who decided, so the record has an author.
	 * @return bool
	 */
	public function set_status( int $id, IssueStatus $status, string $note = '', int $user_id = 0 ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$updated = $wpdb->update(
			Schema::issues_table(),
			array(
				'status'      => $status->value,
				'note'        => $note,
				'resolved_by' => $user_id,
				'updated_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Deletes every issue belonging to a scan.
	 *
	 * @since 0.2.0
	 *
	 * @param int $scan_id Scan ID.
	 * @return int Rows removed.
	 */
	public function delete_by_scan( int $scan_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$deleted = $wpdb->delete( Schema::issues_table(), array( 'scan_id' => $scan_id ), array( '%d' ) );

		return false === $deleted ? 0 : (int) $deleted;
	}

	/**
	 * Builds the ORDER BY fragment that sorts by severity weight.
	 *
	 * Severity is stored as a word, so alphabetical ordering would put "critical"
	 * after "serious". FIELD() imposes the real order and is supported by both
	 * MySQL and MariaDB. The list is generated from the enum so the two cannot
	 * drift apart.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	private static function severity_order(): string {
		$cases = Severity::cases();

		usort(
			$cases,
			static fn( Severity $a, Severity $b ): int => $b->weight() <=> $a->weight()
		);

		$quoted = array_map(
			static fn( Severity $severity ): string => "'" . $severity->value . "'",
			$cases
		);

		return 'FIELD(severity, ' . implode( ', ', $quoted ) . ')';
	}
}
