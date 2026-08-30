<?php
/**
 * Issue storage.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Db;

use WOWStudio\AccessibilityKit\Scanner\Detection;
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
 */
final class IssueRepository {

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
	 * Stores a batch of findings for a scan.
	 *
	 * Written as one multi-row INSERT: a scan can produce hundreds of findings,
	 * and a query per row is the difference between a fast scan and a timeout.
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

		foreach ( $issues as $issue ) {
			$severity  = $issue['severity'] ?? Severity::Moderate;
			$detection = $issue['detection'] ?? Detection::Manual;
			$found_by  = $issue['found_by'] ?? ScanPass::Server;

			$placeholders[] = '(%d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)';

			array_push(
				$values,
				$scan_id,
				(int) ( $issue['post_id'] ?? 0 ),
				(string) ( $issue['rule_id'] ?? '' ),
				(string) ( $issue['wcag_sc'] ?? '' ),
				$severity instanceof Severity ? $severity->value : (string) $severity,
				$detection instanceof Detection ? $detection->value : (string) $detection,
				$found_by instanceof ScanPass ? $found_by->value : (string) $found_by,
				IssueStatus::Open->value,
				(string) ( $issue['selector'] ?? '' ),
				(string) ( $issue['context'] ?? '' ),
				(string) ( $issue['message'] ?? '' ),
				(string) ( $issue['note'] ?? '' ),
				$now,
				$now
			);
		}

		array_unshift( $values, Schema::issues_table() );

		$sql = 'INSERT INTO %i'
			. ' (scan_id, post_id, rule_id, wcag_sc, severity, detection, found_by, status, selector, context, message, note, created_at, updated_at)'
			. ' VALUES ' . implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Assembled from placeholders only; the table and every value go through prepare().
		$written = $wpdb->query( $wpdb->prepare( $sql, $values ) );

		return false === $written ? 0 : (int) $written;
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
	 * @param IssueStatus $status New status.
	 * @param string      $note   Reviewer note, expected when ignoring.
	 * @return bool
	 */
	public function set_status( int $id, IssueStatus $status, string $note = '' ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$updated = $wpdb->update(
			Schema::issues_table(),
			array(
				'status'     => $status->value,
				'note'       => $note,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
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
