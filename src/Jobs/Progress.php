<?php
/**
 * Where a run has got to.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Jobs;

use WOWStudio\AccessibilityKit\Scanner\ScanStatus;

defined( 'ABSPATH' ) || exit;

/**
 * A run's counts, and the questions the interface actually asks of them.
 *
 * Deliberately carries failures and cancellations as first-class numbers rather
 * than folding them into "done". A progress bar that reaches the end after
 * skipping nine pages has told the user something false, and the interface can
 * only avoid that if the shape of the data makes it awkward to.
 *
 * @since 0.12.0
 */
final class Progress {

	/**
	 * Constructor.
	 *
	 * @since 0.12.0
	 *
	 * @param int $queued    Not started.
	 * @param int $running   In flight.
	 * @param int $complete  Finished, findings recorded.
	 * @param int $failed    Attempted and not readable.
	 * @param int $cancelled Never attempted, because somebody stopped the run.
	 */
	public function __construct(
		public readonly int $queued = 0,
		public readonly int $running = 0,
		public readonly int $complete = 0,
		public readonly int $failed = 0,
		public readonly int $cancelled = 0
	) {
	}

	/**
	 * Builds progress from a status count.
	 *
	 * @since 0.12.0
	 *
	 * @param array<string, int> $counts Counts keyed by status value.
	 * @return self
	 */
	public static function from_counts( array $counts ): self {
		return new self(
			(int) ( $counts[ ScanStatus::Queued->value ] ?? 0 ),
			(int) ( $counts[ ScanStatus::Running->value ] ?? 0 ),
			(int) ( $counts[ ScanStatus::Complete->value ] ?? 0 ),
			(int) ( $counts[ ScanStatus::Failed->value ] ?? 0 ),
			(int) ( $counts[ ScanStatus::Cancelled->value ] ?? 0 )
		);
	}

	/**
	 * Every page in the run.
	 *
	 * @since 0.12.0
	 *
	 * @return int
	 */
	public function total(): int {
		return $this->queued + $this->running + $this->complete + $this->failed + $this->cancelled;
	}

	/**
	 * Pages the run will not come back to.
	 *
	 * @since 0.12.0
	 *
	 * @return int
	 */
	public function settled(): int {
		return $this->complete + $this->failed + $this->cancelled;
	}

	/**
	 * Whether anything is still owed.
	 *
	 * @since 0.12.0
	 *
	 * @return bool
	 */
	public function is_finished(): bool {
		return 0 === $this->queued && 0 === $this->running;
	}

	/**
	 * How far along, as a whole percentage.
	 *
	 * @since 0.12.0
	 *
	 * @return int
	 */
	public function percent(): int {
		$total = $this->total();

		if ( 0 === $total ) {
			return 0;
		}

		return (int) floor( $this->settled() / $total * 100 );
	}

	/**
	 * The shape the REST layer sends.
	 *
	 * @since 0.12.0
	 *
	 * @return array<string, int|bool>
	 */
	public function to_array(): array {
		return array(
			'queued'    => $this->queued,
			'running'   => $this->running,
			'complete'  => $this->complete,
			'failed'    => $this->failed,
			'cancelled' => $this->cancelled,
			'total'     => $this->total(),
			'settled'   => $this->settled(),
			'percent'   => $this->percent(),
			'finished'  => $this->is_finished(),
		);
	}
}
