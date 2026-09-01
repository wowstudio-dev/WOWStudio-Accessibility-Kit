<?php
/**
 * Finding the images that have no alt text.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AltText;

use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * The media library, filtered to what is actually missing a description.
 *
 * Worth being precise about what "missing" means here, because two different
 * things look the same in the database. An attachment with no
 * `_wp_attachment_image_alt` row has never been described. An attachment with an
 * empty one has been described *as decorative* — somebody looked at it and
 * decided it carries no information, which is a real and correct answer that a
 * screen reader relies on.
 *
 * Treating those alike would mean nagging people about images they have already
 * dealt with, and worse, offering to overwrite a deliberate decision with a
 * generated sentence. So only the first counts as missing.
 *
 * @since 0.13.0
 */
final class MediaIndex {

	/**
	 * Mime types the generator can actually read.
	 *
	 * @since 0.13.0
	 * @var string[]
	 */
	private const SUPPORTED = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

	/**
	 * Counts images with no description at all.
	 *
	 * @since 0.13.0
	 *
	 * @return int
	 */
	public function undescribed_count(): int {
		return (int) $this->query( 1, 1 )->found_posts;
	}

	/**
	 * Returns one page of images that have never been described.
	 *
	 * @since 0.13.0
	 *
	 * @param int $page     One-based page number.
	 * @param int $per_page How many per page.
	 * @return array<string, mixed>
	 */
	public function undescribed( int $page = 1, int $per_page = 40 ): array {
		$query = $this->query( $page, $per_page );
		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
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
	 * Describes one attachment, including any suggestion waiting on it.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_Post $post Attachment.
	 * @return array<string, mixed>
	 */
	public function describe( WP_Post $post ): array {
		$state = (string) get_post_meta( $post->ID, AltTextRun::STATE_META, true );

		return array(
			'id'         => $post->ID,
			'title'      => get_the_title( $post ),
			'filename'   => wp_basename( (string) get_attached_file( $post->ID ) ),
			'thumbnail'  => (string) wp_get_attachment_image_url( $post->ID, 'thumbnail' ),
			'edit_url'   => (string) get_edit_post_link( $post->ID, 'raw' ),
			'alt'        => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			'state'      => '' === $state ? AltTextRun::STATE_NONE : $state,
			'suggestion' => (string) get_post_meta( $post->ID, AltTextRun::TEXT_META, true ),
			'decorative' => (bool) get_post_meta( $post->ID, AltTextRun::DECORATIVE_META, true ),
			'error'      => (string) get_post_meta( $post->ID, AltTextRun::ERROR_META, true ),
		);
	}

	/**
	 * Builds the query for images with no alt row at all.
	 *
	 * @since 0.13.0
	 *
	 * @param int $page     One-based page number.
	 * @param int $per_page How many per page.
	 * @return WP_Query
	 */
	private function query( int $page, int $per_page ): WP_Query {
		return new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => self::SUPPORTED,
				'posts_per_page' => max( 1, min( 100, $per_page ) ),
				'paged'          => max( 1, $page ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				// NOT EXISTS only. An empty string is a decision — somebody
				// marked this decorative — and offering to overwrite it would
				// undo a correct answer.
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The whole feature is "which images lack this meta"; there is no other way to ask.
				'meta_query'     => array(
					array(
						'key'     => '_wp_attachment_image_alt',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
	}
}
