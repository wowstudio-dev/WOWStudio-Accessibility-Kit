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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Assembled from placeholders only; the table and every value go through prepare().
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Conditions are fixed placeholder fragments; every value goes through prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		return array_map(
			static fn( object $row ): Issue => Issue::from_row( $row ),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Finds current findings from anywhere on the site.
	 *
	 * The site-wide counterpart of find_by_scan(). It needs no join to work out
	 * which scan is current, because since 0.29.0 nothing else is left: a scan
	 * retires the findings of the previous scan of the same thing, so every row
	 * in this table belongs to the newest scan of whatever produced it.
	 *
	 * Findings against deleted content are excluded. Their rows survive until
	 * something prunes them, and listing a finding whose page no longer exists
	 * would send somebody to an edit screen that 404s.
	 *
	 * @since 0.29.0
	 *
	 * @param array<string, mixed> $args Optional rule_id, post_id, severity,
	 *                                   detection, status, limit, offset.
	 * @return Issue[]
	 */
	public function find_current( array $args = array() ): array {
		global $wpdb;

		list( $where, $values ) = $this->current_conditions( $args );

		$values[] = max( 1, min( 500, (int) ( $args['limit'] ?? 100 ) ) );
		$values[] = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$sql = 'SELECT i.* FROM %i AS i
			LEFT JOIN %i AS p ON p.ID = i.post_id
			WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY ' . self::severity_order() . ', i.post_id ASC, i.id ASC LIMIT %d OFFSET %d';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Conditions are fixed placeholder fragments; every value goes through prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		return array_map(
			static fn( object $row ): Issue => Issue::from_row( $row ),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Counts current findings matching the same filters.
	 *
	 * Separate from find_current() so a list can say how many there are in
	 * total rather than how many fitted on this page of it. A drill-down that
	 * says "39" and then shows a hundred rows without saying so is a list
	 * somebody stops trusting.
	 *
	 * @since 0.29.0
	 *
	 * @param array<string, mixed> $args Same filters as find_current().
	 * @return int
	 */
	public function count_current( array $args = array() ): int {
		global $wpdb;

		list( $where, $values ) = $this->current_conditions( $args );

		$sql = 'SELECT COUNT(*) FROM %i AS i
			LEFT JOIN %i AS p ON p.ID = i.post_id
			WHERE ' . implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- As above.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Groups current findings by the markup that produced them.
	 *
	 * A theme prints the same social icons into every footer, so the same fault
	 * arrives once per page and asks to be judged once per page. After the
	 * fortieth identical decision people stop reading the findings and start
	 * clearing them, which is the point at which the list has taught somebody
	 * to ignore it. Grouping turns forty judgements back into one.
	 *
	 * Ordered by how many pages a group touches rather than by how many
	 * findings it holds: the ones worth deciding once are the ones that reach
	 * furthest.
	 *
	 * @since 0.29.0
	 *
	 * @param array<string, mixed> $args Same filters as find_current().
	 * @return array<int, array{fingerprint: string, rule_id: string, sample_id: int, instances: int, pages: int}>
	 */
	public function group_current( array $args = array() ): array {
		global $wpdb;

		list( $where, $values ) = $this->current_conditions( $args );

		/*
		 * A row with no fingerprint has no identity, and grouping on the empty
		 * string would collect every one of them into a single group that means
		 * nothing — a group whose "set aside everywhere" would retire dozens of
		 * unrelated findings in one click. Excluded rather than guessed at. The
		 * backfill gives older rows their identity; until it reaches them they
		 * are counted honestly as ungrouped instead of grouped wrongly.
		 */
		$where[]  = 'i.fingerprint <> %s';
		$values[] = '';

		$values[] = max( 1, min( 200, (int) ( $args['limit'] ?? 50 ) ) );
		$values[] = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$sql = 'SELECT i.fingerprint AS fingerprint,
				MIN(i.rule_id) AS rule_id,
				MIN(i.id) AS sample_id,
				COUNT(*) AS instances,
				COUNT(DISTINCT i.post_id) AS pages
			FROM %i AS i
			LEFT JOIN %i AS p ON p.ID = i.post_id
			WHERE ' . implode( ' AND ', $where )
			. ' GROUP BY i.fingerprint
			ORDER BY pages DESC, instances DESC, sample_id ASC
			LIMIT %d OFFSET %d';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Conditions are fixed placeholder fragments; every value goes through prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		return array_map(
			static fn( object $row ): array => array(
				'fingerprint' => (string) $row->fingerprint,
				'rule_id'     => (string) $row->rule_id,
				'sample_id'   => (int) $row->sample_id,
				'instances'   => (int) $row->instances,
				'pages'       => (int) $row->pages,
			),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Counts findings still carrying no identity.
	 *
	 * Rows written before 0.29.0 have an empty fingerprint until their page is
	 * scanned again or the backfill reaches them.
	 *
	 * @since 0.29.0
	 *
	 * @return int
	 */
	public function fingerprints_pending(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE fingerprint = %s',
				Schema::issues_table(),
				''
			)
		);
	}

	/**
	 * Gives a batch of older findings the identity they were stored without.
	 *
	 * Computed in PHP rather than in SQL, deliberately. The hash is over
	 * whitespace-normalised markup, and MySQL's regular-expression support for
	 * that is neither available on every version WordPress runs on nor certain
	 * to normalise identically. A fingerprint that disagreed with the one
	 * Fingerprint::of() produces would be worse than no fingerprint at all: it
	 * would look like an identity and never match one, so decisions would go on
	 * silently failing to carry with nothing to show for it.
	 *
	 * @since 0.29.0
	 *
	 * @param int $limit How many rows to do in this batch.
	 * @return int Rows given an identity.
	 */
	public function backfill_fingerprints( int $limit = 200 ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, rule_id, context FROM %i WHERE fingerprint = %s LIMIT %d',
				Schema::issues_table(),
				'',
				max( 1, min( 1000, $limit ) )
			)
		);

		$done = 0;

		foreach ( (array) $rows as $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
			$updated = $wpdb->update(
				Schema::issues_table(),
				array( 'fingerprint' => Fingerprint::of( (string) $row->rule_id, (string) $row->context ) ),
				array( 'id' => (int) $row->id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( false !== $updated ) {
				++$done;
			}
		}

		return $done;
	}

	/**
	 * Counts how many distinct pieces of markup match the filters.
	 *
	 * @since 0.29.0
	 *
	 * @param array<string, mixed> $args Same filters as find_current().
	 * @return int
	 */
	public function count_groups( array $args = array() ): int {
		global $wpdb;

		list( $where, $values ) = $this->current_conditions( $args );

		// As in group_current(): rows without an identity are not a group.
		$where[]  = 'i.fingerprint <> %s';
		$values[] = '';

		$sql = 'SELECT COUNT(DISTINCT i.fingerprint) FROM %i AS i
			LEFT JOIN %i AS p ON p.ID = i.post_id
			WHERE ' . implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- As above.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Lists which pages each of these groups appears on.
	 *
	 * Named, not counted. A decision covering thirty-seven pages is one
	 * somebody should be able to see the extent of before taking it, and "37"
	 * is not something anybody can check.
	 *
	 * @since 0.29.0
	 *
	 * @param string[] $fingerprints Groups to look up.
	 * @return array<string, int[]> Post IDs keyed by fingerprint.
	 */
	public function pages_for( array $fingerprints ): array {
		global $wpdb;

		$fingerprints = array_values( array_unique( array_filter( $fingerprints ) ) );

		if ( array() === $fingerprints ) {
			return array();
		}

		$slots  = implode( ', ', array_fill( 0, count( $fingerprints ), '%s' ) );
		$values = array_merge( array( Schema::issues_table() ), $fingerprints, array( IssueStatus::Open->value ) );

		$sql = "SELECT DISTINCT fingerprint, post_id FROM %i
			WHERE fingerprint IN ( {$slots} ) AND status = %s AND post_id > 0
			ORDER BY post_id ASC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The IN list is placeholders only; every value goes through prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		$pages = array();

		foreach ( (array) $rows as $row ) {
			$pages[ (string) $row->fingerprint ][] = (int) $row->post_id;
		}

		return $pages;
	}

	/**
	 * Applies a decision to every current finding with this markup.
	 *
	 * The decisions table is the record; this brings the rows the interface
	 * reads into line with it, so a site-wide judgement takes effect on pages
	 * that were scanned before it was made rather than only on ones scanned
	 * after.
	 *
	 * Pages that have already made their own call about this finding are left
	 * alone. Somebody who looked at one page and decided the opposite has said
	 * something more specific than the site-wide sweep knows, and overwriting
	 * it here would quietly undo a judgement made with the page in front of
	 * them — the same precedence DecisionRepository::for_fingerprints() applies
	 * on the way out, enforced on the way in.
	 *
	 * @since 0.29.0
	 *
	 * @param string      $fingerprint Which finding.
	 * @param IssueStatus $status      What was decided.
	 * @param string      $note        Why.
	 * @param int         $user_id     Who decided.
	 * @return int Rows brought into line.
	 */
	public function apply_everywhere( string $fingerprint, IssueStatus $status, string $note, int $user_id ): int {
		global $wpdb;

		if ( '' === $fingerprint ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables; no core API covers them.
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i AS i
				SET i.status = %s, i.note = %s, i.resolved_by = %d, i.updated_at = %s
				WHERE i.fingerprint = %s
				  AND NOT EXISTS (
					SELECT 1 FROM %i AS d
					WHERE d.fingerprint = i.fingerprint AND d.post_id = i.post_id
				  )',
				Schema::issues_table(),
				$status->value,
				$note,
				$user_id,
				gmdate( 'Y-m-d H:i:s' ),
				$fingerprint,
				Schema::decisions_table()
			)
		);

		return false === $updated ? 0 : (int) $updated;
	}

	/**
	 * Builds the shared WHERE for the two site-wide queries.
	 *
	 * One place, so a filter cannot apply to the list and not to the count that
	 * sits above it — which would show a heading and a body disagreeing about
	 * the same question.
	 *
	 * @since 0.29.0
	 *
	 * @param array<string, mixed> $args Filters.
	 * @return array{0: string[], 1: array<int, mixed>}
	 */
	private function current_conditions( array $args ): array {
		global $wpdb;

		// A finding not bound to a post is a template finding and has no page to
		// have been deleted, so it is kept regardless.
		$where  = array( '( i.post_id = 0 OR p.ID IS NOT NULL )' );
		$values = array( Schema::issues_table(), $wpdb->posts );

		$status = $args['status'] ?? IssueStatus::Open;

		if ( $status instanceof IssueStatus ) {
			$where[]  = 'i.status = %s';
			$values[] = $status->value;
		}

		if ( ! empty( $args['rule_id'] ) ) {
			$where[]  = 'i.rule_id = %s';
			$values[] = (string) $args['rule_id'];
		}

		if ( isset( $args['post_id'] ) ) {
			$where[]  = 'i.post_id = %d';
			$values[] = (int) $args['post_id'];
		}

		if ( ( $args['severity'] ?? null ) instanceof Severity ) {
			$where[]  = 'i.severity = %s';
			$values[] = $args['severity']->value;
		}

		if ( ( $args['detection'] ?? null ) instanceof Detection ) {
			$where[]  = 'i.detection = %s';
			$values[] = $args['detection']->value;
		}

		return array( $where, $values );
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
