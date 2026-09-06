<?php
/**
 * Storage for the judgements people make about findings.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Db;

use WOWStudio\AccessibilityKit\Scanner\IssueStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes decisions.
 *
 * A decision is durable in a way an issue row is not. Issues are what the last
 * scan saw and are replaced wholesale by the next one; decisions are what a
 * person concluded, and they have to still be there afterwards. Everything here
 * is keyed on the fingerprint rather than a row id for that reason — the row
 * the judgement was made against will not exist for long.
 *
 * @since 0.29.0
 *
 * Not final. It is injected into IssueRepository and IssueReview so both can be
 * tested without a database, and sealing it would make that injection
 * decorative — a parameter nobody could pass anything but the default to. Final
 * by default, open where something is meant to be substituted.
 */
class DecisionRepository {

	/**
	 * Records a judgement, replacing any earlier one at the same scope.
	 *
	 * @since 0.29.0
	 *
	 * @param string      $fingerprint Which finding.
	 * @param int         $post_id     Page it applies to, 0 for site-wide.
	 * @param string      $rule_id     Rule that produced the finding.
	 * @param IssueStatus $status      What was decided.
	 * @param string      $note        Why.
	 * @param int         $user_id     Who decided.
	 * @return bool
	 */
	public function record( string $fingerprint, int $post_id, string $rule_id, IssueStatus $status, string $note, int $user_id ): bool {
		global $wpdb;

		if ( '' === $fingerprint ) {
			return false;
		}

		$now      = gmdate( 'Y-m-d H:i:s' );
		$existing = $this->find( $fingerprint, $post_id );

		if ( $existing instanceof Decision ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
			$updated = $wpdb->update(
				Schema::decisions_table(),
				array(
					'status'     => $status->value,
					'note'       => $note,
					'decided_by' => $user_id,
					'updated_at' => $now,
				),
				array( 'id' => $existing->id ),
				array( '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);

			return false !== $updated;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$inserted = $wpdb->insert(
			Schema::decisions_table(),
			array(
				'fingerprint' => $fingerprint,
				'post_id'     => $post_id,
				'rule_id'     => $rule_id,
				'status'      => $status->value,
				'note'        => $note,
				'decided_by'  => $user_id,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return false !== $inserted;
	}

	/**
	 * Removes a judgement, putting the finding back on the list.
	 *
	 * @since 0.29.0
	 *
	 * @param string $fingerprint Which finding.
	 * @param int    $post_id     Page it applied to, 0 for site-wide.
	 * @return bool
	 */
	public function withdraw( string $fingerprint, int $post_id ): bool {
		global $wpdb;

		if ( '' === $fingerprint ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$deleted = $wpdb->delete(
			Schema::decisions_table(),
			array(
				'fingerprint' => $fingerprint,
				'post_id'     => $post_id,
			),
			array( '%s', '%d' )
		);

		return false !== $deleted;
	}

	/**
	 * Finds the decision recorded at exactly this scope.
	 *
	 * @since 0.29.0
	 *
	 * @param string $fingerprint Which finding.
	 * @param int    $post_id     Page, 0 for site-wide.
	 * @return Decision|null
	 */
	public function find( string $fingerprint, int $post_id ): ?Decision {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE fingerprint = %s AND post_id = %d LIMIT 1',
				Schema::decisions_table(),
				$fingerprint,
				$post_id
			)
		);

		return is_object( $row ) ? Decision::from_row( $row ) : null;
	}

	/**
	 * Returns the decisions taken for the whole site, most recent first.
	 *
	 * These do not appear in the findings list once taken — the findings they
	 * cover are set aside, and a list of open findings is exactly where they are
	 * not. So the decision log is the only place they can be seen or undone, and
	 * a decision that cannot be undone is not one worth offering.
	 *
	 * @since 0.29.0
	 *
	 * @param int $limit  How many to return, capped at 200.
	 * @param int $offset Where to start.
	 * @return Decision[]
	 */
	public function site_wide( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE post_id = %d AND status = %s
				ORDER BY updated_at DESC, id DESC
				LIMIT %d OFFSET %d',
				Schema::decisions_table(),
				0,
				IssueStatus::Ignored->value,
				max( 1, min( 200, $limit ) ),
				max( 0, $offset )
			)
		);

		return array_map(
			static fn( object $row ): Decision => Decision::from_row( $row ),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Counts the decisions taken for the whole site.
	 *
	 * @since 0.29.0
	 *
	 * @return int
	 */
	public function site_wide_count(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE post_id = %d AND status = %s',
				Schema::decisions_table(),
				0,
				IssueStatus::Ignored->value
			)
		);
	}

	/**
	 * Returns the decisions that apply to a batch of findings on one page.
	 *
	 * A page-specific decision beats a site-wide one, so that somebody can put a
	 * single page's instance back on the list without unpicking the judgement
	 * that covers the other two hundred. The more specific answer wins because
	 * it is the one somebody looked at the page to reach.
	 *
	 * @since 0.29.0
	 *
	 * @param string[] $fingerprints Findings to look up.
	 * @param int      $post_id      Page they were found on.
	 * @return array<string, Decision> Keyed by fingerprint.
	 */
	public function for_fingerprints( array $fingerprints, int $post_id ): array {
		global $wpdb;

		$fingerprints = array_values( array_unique( array_filter( $fingerprints ) ) );

		if ( array() === $fingerprints ) {
			return array();
		}

		$slots  = implode( ', ', array_fill( 0, count( $fingerprints ), '%s' ) );
		$values = array_merge( array( Schema::decisions_table() ), $fingerprints, array( $post_id ) );

		$sql = "SELECT * FROM %i WHERE fingerprint IN ( {$slots} ) AND post_id IN ( 0, %d )";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- The IN list is placeholders only; every value goes through prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		$found = array();

		foreach ( (array) $rows as $row ) {
			$decision = Decision::from_row( $row );
			$existing = $found[ $decision->fingerprint ] ?? null;

			// Site-wide rows are only kept when nothing page-specific is present.
			if ( $existing instanceof Decision && $decision->is_site_wide() ) {
				continue;
			}

			$found[ $decision->fingerprint ] = $decision;
		}

		return $found;
	}
}
