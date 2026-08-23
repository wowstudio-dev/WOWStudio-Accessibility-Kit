<?php
/**
 * The non-destructive override layer.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Db\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Stores fixes as overrides, and applies them as a page renders.
 *
 * Nothing here edits post content. A fix is a stored pair of "this markup" and
 * "this markup instead", substituted on the way out. That is what makes undo
 * genuinely free: reverting sets a flag, and the next render is the original
 * again. The user's content is never rewritten, so a bad suggestion cannot
 * damage anything, and uninstalling the plugin restores every page by simply
 * ceasing to filter.
 *
 * @since 0.6.0
 */
final class OverrideStore implements Registrable {

	/**
	 * Overrides already loaded this request, keyed by post ID.
	 *
	 * A page can run the_content more than once. Reading the table each time
	 * would put a query per call into every page view.
	 *
	 * @since 0.6.0
	 * @var array<int, Fix[]>
	 */
	private array $cache = array();

	/**
	 * Hooks override application into rendering.
	 *
	 * @since 0.6.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Priority 5 puts this before wp_filter_content_tags, so WordPress's own
		// content filters run over the corrected markup rather than the original.
		add_filter( 'the_content', array( $this, 'apply_to_content' ), 5 );
	}

	/**
	 * Substitutes any applied overrides into rendered content.
	 *
	 * @since 0.6.0
	 *
	 * @param string $content Rendered post content.
	 * @return string
	 */
	public function apply_to_content( string $content ): string {
		$post_id = get_the_ID();

		if ( ! is_int( $post_id ) || $post_id <= 0 || '' === $content ) {
			return $content;
		}

		$fixes = $this->applied_for_post( $post_id );

		if ( array() === $fixes ) {
			// The common case. Never pay for a DOM parse on a page with no fixes.
			return $content;
		}

		foreach ( $fixes as $fix ) {
			$content = $this->substitute( $content, $fix->before_markup, $fix->after_markup );
		}

		return $content;
	}

	/**
	 * Replaces the first element matching the recorded markup.
	 *
	 * Matching happens in the DOM rather than on the string: see Substitution
	 * for why a byte-for-byte match cannot work here.
	 *
	 * @since 0.6.0
	 *
	 * @param string $haystack Content to search.
	 * @param string $needle   Markup recorded when the issue was found.
	 * @param string $with     Markup to put in its place.
	 * @return string
	 */
	public function substitute( string $haystack, string $needle, string $with ): string {
		return Substitution::replace( $haystack, $needle, $with );
	}

	/**
	 * Returns the overrides in force for a post.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post_id Post to read.
	 * @return Fix[]
	 */
	public function applied_for_post( int $post_id ): array {
		if ( isset( $this->cache[ $post_id ] ) ) {
			return $this->cache[ $post_id ];
		}

		$this->cache[ $post_id ] = $this->for_post( $post_id, FixStatus::Applied );

		return $this->cache[ $post_id ];
	}

	/**
	 * Records a new override.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string, mixed> $data Override fields.
	 * @return int New row ID, or 0 on failure.
	 */
	public function add( array $data ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$inserted = $wpdb->insert(
			Schema::fixes_table(),
			array(
				'issue_id'      => (int) ( $data['issue_id'] ?? 0 ),
				'post_id'       => (int) ( $data['post_id'] ?? 0 ),
				'rule_id'       => (string) ( $data['rule_id'] ?? '' ),
				'engine'        => (string) ( $data['engine'] ?? '' ),
				'provider'      => (string) ( $data['provider'] ?? '' ),
				'model'         => (string) ( $data['model'] ?? '' ),
				'before_markup' => (string) ( $data['before'] ?? '' ),
				'after_markup'  => (string) ( $data['after'] ?? '' ),
				'status'        => FixStatus::Applied->value,
				'applied_at'    => gmdate( 'Y-m-d H:i:s' ),
				'applied_by'    => (int) ( $data['applied_by'] ?? 0 ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		$this->cache = array();

		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Finds one override.
	 *
	 * @since 0.6.0
	 *
	 * @param int $id Row ID.
	 * @return Fix|null
	 */
	public function find( int $id ): ?Fix {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Schema::fixes_table(), $id ) );

		return $row instanceof \stdClass ? Fix::from_row( $row ) : null;
	}

	/**
	 * Returns the override recorded against an issue, if any.
	 *
	 * @since 0.6.0
	 *
	 * @param int $issue_id Issue to look up.
	 * @return Fix|null
	 */
	public function for_issue( int $issue_id ): ?Fix {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE issue_id = %d ORDER BY id DESC LIMIT 1',
				Schema::fixes_table(),
				$issue_id
			)
		);

		return $row instanceof \stdClass ? Fix::from_row( $row ) : null;
	}

	/**
	 * Returns a post's overrides.
	 *
	 * @since 0.6.0
	 *
	 * @param int            $post_id Post to read.
	 * @param FixStatus|null $status  Restrict to one status, or null for all.
	 * @return Fix[]
	 */
	public function for_post( int $post_id, ?FixStatus $status = null ): array {
		global $wpdb;

		if ( null === $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE post_id = %d ORDER BY id DESC', Schema::fixes_table(), $post_id )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE post_id = %d AND status = %s ORDER BY id ASC',
					Schema::fixes_table(),
					$post_id,
					$status->value
				)
			);
		}

		return array_map(
			static fn( object $row ): Fix => Fix::from_row( $row ),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Takes an override out of force.
	 *
	 * The row is kept rather than deleted: what was changed, and what was
	 * changed back, is exactly the record a conformance log needs.
	 *
	 * @since 0.6.0
	 *
	 * @param int $id      Row ID.
	 * @param int $user_id Who reverted it.
	 * @return bool
	 */
	public function revert( int $id, int $user_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; no core API covers it.
		$updated = $wpdb->update(
			Schema::fixes_table(),
			array(
				'status'      => FixStatus::Reverted->value,
				'reverted_at' => gmdate( 'Y-m-d H:i:s' ),
				'reverted_by' => $user_id,
			),
			array(
				'id'     => $id,
				'status' => FixStatus::Applied->value,
			),
			array( '%s', '%s', '%d' ),
			array( '%d', '%s' )
		);

		$this->cache = array();

		return is_int( $updated ) && $updated > 0;
	}
}
