<?php
/**
 * Describing many images in the background, then reviewing what came back.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AltText;

use WOWStudio\AccessibilityKit\Jobs\Queue;
use Throwable;
use WP_Error;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * A bulk alt-text run: generate in the background, approve in one screen.
 *
 * The highest-leverage thing this plugin does, and the only bulk write it makes
 * that carries no risk of landing in the wrong place. `_wp_attachment_image_alt`
 * is not an override and not an interception: it describes the image itself, so
 * it applies wherever that image appears, under any theme, and it survives this
 * plugin being deleted. See decision F7.
 *
 * What it is not is unreviewed. What a model writes about an image whose purpose
 * it cannot see is a guess with good grammar, so every suggestion lands in a
 * queue for a person to read. Bulk here means one screen instead of forty
 * modals, not nobody looking.
 *
 * State lives in postmeta on each attachment rather than a table. Progress is
 * then a count, resuming needs no cursor — the rows still marked queued are the
 * cursor — and an attachment somebody deletes takes its own bookkeeping with it.
 *
 * @since 0.13.0
 */
final class AltTextRun {

	/**
	 * Which run an attachment belongs to.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const RUN_META = '_wsak_alt_run';

	/**
	 * Where an attachment has got to.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const STATE_META = '_wsak_alt_state';

	/**
	 * The suggestion waiting to be read.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const TEXT_META = '_wsak_alt_suggestion';

	/**
	 * Whether the model judged the image decorative.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const DECORATIVE_META = '_wsak_alt_decorative';

	/**
	 * Why an image could not be described.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const ERROR_META = '_wsak_alt_error';

	/**
	 * The run counter.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const SEQUENCE_OPTION = 'wsak_alt_run_seq';

	/**
	 * Never asked about.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const STATE_NONE = 'none';

	/**
	 * In the queue.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const STATE_QUEUED = 'queued';

	/**
	 * Described, and waiting for somebody to read it.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const STATE_READY = 'ready';

	/**
	 * Could not be described.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const STATE_FAILED = 'failed';

	/**
	 * Read, approved, and written to the media library.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const STATE_APPLIED = 'applied';

	/**
	 * How many images one run may cover.
	 *
	 * @since 0.13.0
	 * @var int
	 */
	public const MAX_IMAGES = 200;

	/**
	 * The scheduler.
	 *
	 * @since 0.13.0
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * The thing that describes an image.
	 *
	 * @since 0.13.0
	 * @var Generator
	 */
	private Generator $generator;

	/**
	 * Constructor.
	 *
	 * @since 0.13.0
	 *
	 * @param Queue|null     $queue     Scheduler boundary.
	 * @param Generator|null $generator Alt-text generator.
	 */
	public function __construct( ?Queue $queue = null, ?Generator $generator = null ) {
		$this->queue     = $queue ?? new Queue();
		$this->generator = $generator ?? new Generator();
	}

	/**
	 * Queues a set of images for description.
	 *
	 * @since 0.13.0
	 *
	 * @param int[] $attachment_ids Images to describe.
	 * @return int|WP_Error Run identifier, or why it could not start.
	 */
	public function start( array $attachment_ids ) {
		if ( ! $this->queue->is_available() ) {
			return new WP_Error(
				'wsak_no_scheduler',
				__( 'The background scheduler is not running, so images cannot be described in bulk. Describing them one at a time still works.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 503 )
			);
		}

		$ids = array();

		foreach ( array_unique( array_map( 'absint', $attachment_ids ) ) as $id ) {
			if ( $id > 0 && 'attachment' === get_post_type( $id ) && current_user_can( 'edit_post', $id ) ) {
				$ids[] = $id;
			}
		}

		if ( array() === $ids ) {
			return new WP_Error(
				'wsak_nothing_selected',
				__( 'None of the selected images could be edited with your account, so there is nothing to describe.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 400 )
			);
		}

		if ( count( $ids ) > self::MAX_IMAGES ) {
			return new WP_Error(
				'wsak_too_many',
				sprintf(
					/* translators: %d: maximum images in one run. */
					__( 'One run covers at most %d images. Select fewer, and run again when it finishes.', 'wowstudio-accessibility-kit' ),
					self::MAX_IMAGES
				),
				array( 'status' => 400 )
			);
		}

		$run_id = (int) get_option( self::SEQUENCE_OPTION, 0 ) + 1;
		update_option( self::SEQUENCE_OPTION, $run_id, true );

		foreach ( $ids as $id ) {
			update_post_meta( $id, self::RUN_META, $run_id );
			update_post_meta( $id, self::STATE_META, self::STATE_QUEUED );
			delete_post_meta( $id, self::ERROR_META );

			if ( ! $this->queue->enqueue_alt_text( $run_id, $id ) ) {
				$this->fail( $id, __( 'This image could not be added to the queue.', 'wowstudio-accessibility-kit' ) );
			}
		}

		/**
		 * Fires when a bulk alt-text run is queued.
		 *
		 * @since 0.13.0
		 *
		 * @param int   $run_id Run identifier.
		 * @param int[] $ids    Images queued.
		 */
		do_action( 'wsak_alt_run_started', $run_id, $ids );

		return $run_id;
	}

	/**
	 * Describes one queued image. This is what the scheduler calls.
	 *
	 * @since 0.13.0
	 *
	 * @param int $attachment_id Image to describe.
	 * @return void
	 */
	public function step( int $attachment_id ): void {
		if ( self::STATE_QUEUED !== (string) get_post_meta( $attachment_id, self::STATE_META, true ) ) {
			// Already done, already failed, or cancelled between queueing and
			// running. A second delivery of the same job must not spend another
			// allowance describing the same image twice.
			return;
		}

		// Claimed before the work, so a duplicated job finds it no longer queued.
		update_post_meta( $attachment_id, self::STATE_META, self::STATE_READY );

		try {
			$parent     = (int) ( get_post( $attachment_id )->post_parent ?? 0 );
			$suggestion = $this->generator->for_attachment( $attachment_id, $parent );
		} catch ( Throwable $error ) {
			$this->fail( $attachment_id, $error->getMessage() );

			return;
		}

		if ( is_wp_error( $suggestion ) ) {
			$this->fail( $attachment_id, $suggestion->get_error_message() );

			return;
		}

		update_post_meta( $attachment_id, self::TEXT_META, $suggestion->text );
		update_post_meta( $attachment_id, self::DECORATIVE_META, $suggestion->is_decorative ? 1 : 0 );
		delete_post_meta( $attachment_id, self::ERROR_META );
	}

	/**
	 * Records that an image could not be described, and why.
	 *
	 * @since 0.13.0
	 *
	 * @param int    $attachment_id Image.
	 * @param string $reason        What went wrong.
	 * @return void
	 */
	private function fail( int $attachment_id, string $reason ): void {
		update_post_meta( $attachment_id, self::STATE_META, self::STATE_FAILED );
		update_post_meta( $attachment_id, self::ERROR_META, $reason );
	}

	/**
	 * Writes an approved suggestion onto the image itself.
	 *
	 * @since 0.13.0
	 *
	 * @param int    $attachment_id Image to describe.
	 * @param string $text          The reviewed description.
	 * @return array<string, mixed>|WP_Error
	 */
	public function approve( int $attachment_id, string $text ) {
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error(
				'wsak_not_attachment',
				__( 'That is not an image in the media library.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error(
				'wsak_forbidden_attachment',
				__( 'You do not have permission to edit that image.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$previous = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$clean    = sanitize_text_field( $text );

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $clean );
		update_post_meta( $attachment_id, self::STATE_META, self::STATE_APPLIED );

		/**
		 * Fires after alt text is saved onto an attachment.
		 *
		 * @since 0.5.0
		 *
		 * @param int    $attachment_id Attachment updated.
		 * @param string $text          New alt text.
		 * @param string $previous      Alt text that was replaced.
		 */
		do_action( 'wsak_alt_text_applied', $attachment_id, $clean, $previous );

		return array(
			'id'    => $attachment_id,
			'alt'   => $clean,
			'state' => self::STATE_APPLIED,
		);
	}

	/**
	 * Discards a suggestion without writing it.
	 *
	 * @since 0.13.0
	 *
	 * @param int $attachment_id Image whose suggestion is rejected.
	 * @return array<string, mixed>
	 */
	public function reject( int $attachment_id ): array {
		delete_post_meta( $attachment_id, self::TEXT_META );
		delete_post_meta( $attachment_id, self::DECORATIVE_META );
		delete_post_meta( $attachment_id, self::STATE_META );
		delete_post_meta( $attachment_id, self::RUN_META );

		return array(
			'id'    => $attachment_id,
			'state' => self::STATE_NONE,
		);
	}

	/**
	 * Counts a run's images by state.
	 *
	 * @since 0.13.0
	 *
	 * @param int $run_id Run to count.
	 * @return array<string, int|bool>
	 */
	public function progress( int $run_id ): array {
		$counts = array(
			self::STATE_QUEUED  => 0,
			self::STATE_READY   => 0,
			self::STATE_FAILED  => 0,
			self::STATE_APPLIED => 0,
		);

		foreach ( $this->ids_in( $run_id ) as $id ) {
			$state = (string) get_post_meta( $id, self::STATE_META, true );

			if ( isset( $counts[ $state ] ) ) {
				++$counts[ $state ];
			}
		}

		$total = array_sum( $counts );

		return array_merge(
			$counts,
			array(
				'total'    => $total,
				'settled'  => $total - $counts[ self::STATE_QUEUED ],
				'finished' => 0 === $counts[ self::STATE_QUEUED ],
				'percent'  => 0 === $total
					? 0
					: (int) floor( ( $total - $counts[ self::STATE_QUEUED ] ) / $total * 100 ),
			)
		);
	}

	/**
	 * Returns the images in a run.
	 *
	 * @since 0.13.0
	 *
	 * @param int $run_id Run to read.
	 * @return int[]
	 */
	public function ids_in( int $run_id ): array {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => self::MAX_IMAGES,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Reading one run's members; there is no other index for it.
				'meta_query'     => array(
					array(
						'key'   => self::RUN_META,
						'value' => $run_id,
					),
				),
			)
		);

		return array_map( 'absint', $query->posts );
	}

	/**
	 * Stops a run, leaving anything already described in place to review.
	 *
	 * @since 0.13.0
	 *
	 * @param int $run_id Run to stop.
	 * @return array<string, int>
	 */
	public function cancel( int $run_id ): array {
		$this->queue->cancel_alt_text( $run_id );

		foreach ( $this->ids_in( $run_id ) as $id ) {
			if ( self::STATE_QUEUED === (string) get_post_meta( $id, self::STATE_META, true ) ) {
				delete_post_meta( $id, self::STATE_META );
				delete_post_meta( $id, self::RUN_META );
			}
		}

		return $this->progress( $run_id );
	}
}
