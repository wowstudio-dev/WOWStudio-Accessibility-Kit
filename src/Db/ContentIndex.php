<?php
/**
 * Listing a site's content with what is known about each piece.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Db;

use WOWStudio\AccessibilityKit\Scanner\ScanCoverage;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * The list somebody picks from before starting a run.
 *
 * Grouped by post type rather than presented as one pile, because that is how
 * people think about their own sites: a shop owner has products, and a
 * publication has articles, and neither of them has "content". A site with four
 * hundred posts and nine pages needs those separated before any of it is
 * selectable.
 *
 * @since 0.13.0
 */
final class ContentIndex {

	/**
	 * Scan storage.
	 *
	 * @since 0.13.0
	 * @var ScanRepository
	 */
	private ScanRepository $scans;

	/**
	 * Constructor.
	 *
	 * @since 0.13.0
	 *
	 * @param ScanRepository|null $scans Scan storage.
	 */
	public function __construct( ?ScanRepository $scans = null ) {
		$this->scans = $scans ?? new ScanRepository();
	}

	/**
	 * Returns the post types worth offering, with how much is in each.
	 *
	 * Attachments are excluded: an image has no page of its own to check in any
	 * meaningful sense, and its alt text is handled by a different screen.
	 *
	 * @since 0.13.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function types(): array {
		$types = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}

			$counts = wp_count_posts( $type->name );
			$total  = (int) ( $counts->publish ?? 0 );

			if ( 0 === $total ) {
				continue;
			}

			$types[] = array(
				'name'  => $type->name,
				'label' => $type->labels->name ?? $type->name,
				'count' => $total,
			);
		}

		/**
		 * Filters the post types offered for scanning.
		 *
		 * @since 0.13.0
		 *
		 * @param array<int, array<string, mixed>> $types Types with counts.
		 */
		return (array) apply_filters( 'wsak_scannable_post_types', $types );
	}

	/**
	 * Returns one page of content, with what is known about each item.
	 *
	 * @since 0.13.0
	 *
	 * @param string $type     Post type.
	 * @param int    $page     One-based page number.
	 * @param int    $per_page How many per page.
	 * @param string $search   Title filter.
	 * @return array<string, mixed>
	 */
	public function items( string $type, int $page = 1, int $per_page = 50, string $search = '' ): array {
		$query = new \WP_Query(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'posts_per_page'   => max( 1, min( 100, $per_page ) ),
				'paged'            => max( 1, $page ),
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
				's'                => $search,
			)
		);

		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post || ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}

			$items[] = $this->describe( $post );
		}

		return array(
			'items'       => $items,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => max( 1, $page ),
		);
	}

	/**
	 * Describes one post and the state of the last scan on it.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_Post $post Post to describe.
	 * @return array<string, mixed>
	 */
	public function describe( WP_Post $post ): array {
		$latest   = $this->scans->latest_for_post( $post->ID );
		$coverage = ScanCoverage::of( $latest );

		return array(
			'id'             => $post->ID,
			'title'          => get_the_title( $post ),
			'type'           => $post->post_type,
			'url'            => (string) get_permalink( $post ),
			'edit_url'       => (string) get_edit_post_link( $post->ID, 'raw' ),
			'modified'       => $post->post_modified_gmt,
			'coverage'       => $coverage->value,
			'coverage_label' => $coverage->label(),
			'coverage_blurb' => $coverage->blurb(),
			'scanned_at'     => null === $latest ? null : $latest->finished_at,
			'scan_id'        => null === $latest ? 0 : $latest->id,
			'score'          => null === $latest ? null : $latest->score,
			'stale'          => $this->is_stale( $post, $latest ),
		);
	}

	/**
	 * Reports whether the content changed after it was last checked.
	 *
	 * Worked out on read rather than recorded on save. A stored flag would need
	 * a hook on every write path — the editor, the REST API, WP-CLI, an import,
	 * a scheduled publish — and the one that gets missed leaves a page claiming
	 * a coverage it no longer has. Two timestamps cannot drift apart.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_Post   $post   Post in question.
	 * @param Scan|null $latest Most recent finished scan.
	 * @return bool
	 */
	private function is_stale( WP_Post $post, ?Scan $latest ): bool {
		if ( null === $latest || null === $latest->finished_at ) {
			return false;
		}

		return strtotime( $post->post_modified_gmt ) > strtotime( $latest->finished_at );
	}
}
