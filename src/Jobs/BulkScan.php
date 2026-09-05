<?php
/**
 * Starting, running, and stopping a bulk scan.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Jobs;

use WOWStudio\AccessibilityKit\Db\ScanRepository;
use WOWStudio\AccessibilityKit\Scanner\FetchStrategy;
use WOWStudio\AccessibilityKit\Scanner\PageScanner;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\Scanner\ScanStatus;
use Throwable;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A bulk scan, modelled as a parent scan row with one child per page.
 *
 * The child rows are written up front, before any work happens. That is the
 * whole design: progress is a `COUNT` rather than a number somebody remembered
 * to increment, and resuming after a timeout needs no cursor, because the rows
 * still marked queued *are* the cursor. A run interrupted by a fatal error on
 * page forty picks up at page forty-one with no extra bookkeeping.
 *
 * Nothing here trusts the scheduler to be exactly-once. A worker claims its row
 * with a conditional update and stops if the claim fails, so a duplicated job is
 * a no-op rather than a second scan writing a second set of findings.
 *
 * @since 0.12.0
 */
final class BulkScan {

	/**
	 * How many pages one run may cover.
	 *
	 * Not a licensing limit — there is no licence. This is the point past which
	 * a single run stops being something a person is watching, and
	 * queueing tens of thousands of rows from one button press is a mistake we
	 * should make hard rather than possible.
	 *
	 * @since 0.12.0
	 * @var int
	 */
	public const MAX_PAGES = 500;

	/**
	 * Scan storage.
	 *
	 * @since 0.12.0
	 * @var ScanRepository
	 */
	private ScanRepository $scans;

	/**
	 * The scheduler.
	 *
	 * @since 0.12.0
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * The thing that actually scans a page.
	 *
	 * @since 0.12.0
	 * @var PageScanner
	 */
	private PageScanner $scanner;

	/**
	 * Constructor.
	 *
	 * @since 0.12.0
	 *
	 * @param ScanRepository|null $scans   Scan storage.
	 * @param Queue|null          $queue   Scheduler boundary.
	 * @param PageScanner|null    $scanner Page scanner.
	 */
	public function __construct(
		?ScanRepository $scans = null,
		?Queue $queue = null,
		?PageScanner $scanner = null
	) {
		$this->scans   = $scans ?? new ScanRepository();
		$this->queue   = $queue ?? new Queue();
		$this->scanner = $scanner ?? new PageScanner();
	}

	/**
	 * Opens a run over the given posts and queues every one of them.
	 *
	 * @since 0.12.0
	 *
	 * @param int[] $post_ids Posts to scan.
	 * @param int   $user_id  Who asked.
	 * @return int|WP_Error Run identifier, or why it could not start.
	 */
	public function start( array $post_ids, int $user_id ) {
		if ( ! $this->queue->is_available() ) {
			return new WP_Error(
				'wsak_no_scheduler',
				__( 'The background scheduler is not running, so a bulk scan cannot be started. Scanning one page at a time still works.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 503 )
			);
		}

		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );

		if ( array() === $post_ids ) {
			return new WP_Error(
				'wsak_nothing_selected',
				__( 'No content was selected, so there is nothing to scan.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 400 )
			);
		}

		if ( count( $post_ids ) > self::MAX_PAGES ) {
			return new WP_Error(
				'wsak_too_many',
				sprintf(
					/* translators: %d: maximum number of pages in one run. */
					__( 'A single run covers at most %d items. Select fewer, and run again when it finishes.', 'wowstudio-accessibility-kit' ),
					self::MAX_PAGES
				),
				array( 'status' => 400 )
			);
		}

		$run_id = $this->scans->start_run( $user_id );

		if ( 0 === $run_id ) {
			return new WP_Error(
				'wsak_run_not_started',
				__( 'The run could not be recorded. Check that the plugin tables exist.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 500 )
			);
		}

		$queued = 0;

		foreach ( $post_ids as $post_id ) {
			$scan_id = $this->scans->queue( ScanScope::Page, $post_id, $user_id, $run_id );

			if ( 0 === $scan_id ) {
				continue;
			}

			// A row that cannot be scheduled is cancelled rather than left
			// queued for ever, so progress can still reach the end.
			if ( $this->queue->enqueue( $run_id, $scan_id ) ) {
				++$queued;
			} else {
				$this->scans->fail( $scan_id, __( 'This page could not be added to the queue.', 'wowstudio-accessibility-kit' ) );
			}
		}

		if ( 0 === $queued ) {
			$this->scans->fail( $run_id, __( 'Nothing could be added to the queue.', 'wowstudio-accessibility-kit' ) );

			return new WP_Error(
				'wsak_nothing_queued',
				__( 'None of the selected content could be added to the queue.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires when a bulk scan has been queued.
		 *
		 * @since 0.12.0
		 *
		 * @param int   $run_id   Run identifier.
		 * @param int[] $post_ids Posts queued.
		 */
		do_action( 'wsak_bulk_scan_started', $run_id, $post_ids );

		return $run_id;
	}

	/**
	 * Scans one queued page. This is what the scheduler calls.
	 *
	 * Everything is caught. An action that throws is recorded as failed by
	 * Action Scheduler and, depending on version and configuration, may be
	 * retried — which for a page that will never parse means burning the queue
	 * on it repeatedly. A page that cannot be scanned is a finding about that
	 * page, not an exception for the scheduler to manage.
	 *
	 * @since 0.12.0
	 *
	 * @param int $scan_id Scan row to fill in.
	 * @return void
	 */
	public function step( int $scan_id ): void {
		$scan = $this->scans->find( $scan_id );

		if ( null === $scan ) {
			return;
		}

		// Somebody cancelled the run between this action being queued and being
		// picked up. Checked here as well as unscheduling, because unscheduling
		// races with a runner that has already claimed a batch.
		$run = $scan->parent_id > 0 ? $this->scans->find( $scan->parent_id ) : null;

		if ( null !== $run && ScanStatus::Cancelled === $run->status ) {
			return;
		}

		if ( ! $this->scans->claim( $scan_id ) ) {
			return;
		}

		try {
			/*
			 * Content, not the whole page. A hundred loopback fetches is a
			 * different proposition from one: it fails on hosts that block
			 * loopback, gets a 404 for every draft because a queued job has no
			 * user, and makes the site render itself a second time for every
			 * page. What the theme contributes is checked once, separately.
			 * Decision F9.
			 */
			$this->scanner->run( $scan_id, $scan->target_id, FetchStrategy::Content );
		} catch ( Throwable $error ) {
			$this->scans->fail( $scan_id, $error->getMessage() );
		}

		if ( $scan->parent_id > 0 ) {
			$this->settle( $scan->parent_id );
		}
	}

	/**
	 * Closes a run once nothing is owed.
	 *
	 * @since 0.12.0
	 *
	 * @param int $run_id Run to check.
	 * @return void
	 */
	private function settle( int $run_id ): void {
		$progress = $this->progress( $run_id );

		if ( ! $progress->is_finished() ) {
			return;
		}

		$run = $this->scans->find( $run_id );

		if ( null === $run || ScanStatus::Running !== $run->status ) {
			return;
		}

		$this->scans->complete( $run_id, null, $progress->to_array() );

		/**
		 * Fires when every page in a run has settled.
		 *
		 * @since 0.12.0
		 *
		 * @param int      $run_id   Run identifier.
		 * @param Progress $progress Final counts.
		 */
		do_action( 'wsak_bulk_scan_finished', $run_id, $progress );
	}

	/**
	 * Stops a run, leaving whatever it already found in place.
	 *
	 * @since 0.12.0
	 *
	 * @param int $run_id Run to stop.
	 * @return Progress|WP_Error
	 */
	public function cancel( int $run_id ) {
		$run = $this->scans->find( $run_id );

		if ( null === $run || ScanScope::Site !== $run->scope ) {
			return new WP_Error(
				'wsak_unknown_run',
				__( 'That run could not be found.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		$this->queue->cancel( $run_id );
		$this->scans->cancel_queued( $run_id );

		// The run row itself is marked cancelled rather than failed. Nothing
		// went wrong, and a run somebody stopped must not read later as a run
		// that broke.
		$this->scans->set_status( $run_id, ScanStatus::Cancelled );

		/**
		 * Fires when a run is stopped by hand.
		 *
		 * @since 0.12.0
		 *
		 * @param int $run_id Run identifier.
		 */
		do_action( 'wsak_bulk_scan_cancelled', $run_id );

		return $this->progress( $run_id );
	}

	/**
	 * Reads how far a run has got.
	 *
	 * @since 0.12.0
	 *
	 * @param int $run_id Run to read.
	 * @return Progress
	 */
	public function progress( int $run_id ): Progress {
		return Progress::from_counts( $this->scans->count_by_status( $run_id ) );
	}
}
