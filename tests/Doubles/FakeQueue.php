<?php
/**
 * A scheduler that records instead of scheduling.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Doubles;

use WOWStudio\AccessibilityKit\Jobs\Queue;

/**
 * Captures what would have been queued, and can pretend to be absent.
 */
final class FakeQueue extends Queue {

	/**
	 * Whether a scheduler exists at all.
	 *
	 * @var bool
	 */
	public bool $available = true;

	/**
	 * Actions that were queued.
	 *
	 * @var array<int, array{run_id: int, scan_id: int}>
	 */
	public array $enqueued = array();

	/**
	 * Runs that were cancelled.
	 *
	 * @var int[]
	 */
	public array $cancelled = array();

	/**
	 * {@inheritDoc}
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return $this->available;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $run_id  Run.
	 * @param int $scan_id Scan.
	 * @return bool
	 */
	public function enqueue( int $run_id, int $scan_id ): bool {
		$this->enqueued[] = array(
			'run_id'  => $run_id,
			'scan_id' => $scan_id,
		);

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $run_id Run.
	 * @return void
	 */
	public function cancel( int $run_id ): void {
		$this->cancelled[] = $run_id;
	}
}
