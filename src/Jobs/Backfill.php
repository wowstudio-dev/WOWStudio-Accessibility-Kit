<?php
/**
 * Giving older findings the identity they were stored without.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Jobs;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Db\IssueRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Fills in the fingerprints of findings stored before 0.29.0.
 *
 * A row without a fingerprint cannot carry a decision across a rescan and
 * cannot be grouped with the identical markup elsewhere, so the two things
 * added in 0.29.0 do nothing for it. Rescanning every page would fix it, and on
 * a large site that is hours of work nobody asked for.
 *
 * In batches, through the scheduler, because the alternative is holding an
 * admin request open while a hundred thousand rows are rewritten. Each batch
 * queues the next while work remains, so the size of the site decides how long
 * it takes rather than whether it finishes.
 *
 * @since 0.29.0
 */
final class Backfill implements Registrable {

	/**
	 * How many rows one batch rewrites.
	 *
	 * Small enough that a batch is comfortably inside any host's time limit,
	 * large enough that a typical site finishes in one or two.
	 *
	 * @since 0.29.0
	 * @var int
	 */
	public const BATCH = 200;

	/**
	 * Work queue.
	 *
	 * @since 0.29.0
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * Findings.
	 *
	 * @since 0.29.0
	 * @var IssueRepository
	 */
	private IssueRepository $issues;

	/**
	 * Constructor.
	 *
	 * @since 0.29.0
	 *
	 * @param Queue|null           $queue  Work queue.
	 * @param IssueRepository|null $issues Findings.
	 */
	public function __construct( ?Queue $queue = null, ?IssueRepository $issues = null ) {
		$this->queue  = $queue ?? new Queue();
		$this->issues = $issues ?? new IssueRepository();
	}

	/**
	 * Registers the scheduler hook.
	 *
	 * @since 0.29.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( Queue::HOOK_BACKFILL, array( $this, 'step' ) );
	}

	/**
	 * Does one batch, and queues the next if there is more.
	 *
	 * @since 0.29.0
	 *
	 * @return void
	 */
	public function step(): void {
		$done = $this->issues->backfill_fingerprints( self::BATCH );

		if ( 0 === $done ) {
			return;
		}

		if ( $this->issues->fingerprints_pending() > 0 ) {
			$this->queue->enqueue_backfill();
		}
	}
}
