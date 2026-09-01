<?php
/**
 * Scan run status.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Where a scan run got to.
 *
 * Recorded because scans are queued through Action Scheduler and a run can be
 * interrupted. A row with no status would be indistinguishable from a scan that
 * genuinely found nothing.
 *
 * @since 0.2.0
 */
enum ScanStatus: string {

	/**
	 * Started and not yet finished.
	 */
	/**
	 * Accepted into the queue and not started.
	 *
	 * Distinct from Running on purpose. A queued scan has a row, a place in a
	 * run, and a position in the progress count, but nothing has happened to it
	 * yet — and a run that is interrupted has to be able to tell the difference
	 * between work it never began and work it began and lost.
	 *
	 * @since 0.12.0
	 */
	case Queued = 'queued';

	case Running = 'running';

	/**
	 * Finished normally.
	 */
	case Complete = 'complete';

	/**
	 * Stopped before finishing.
	 */
	case Failed = 'failed';

	/**
	 * Stopped because somebody asked it to stop.
	 *
	 * Not Failed: nothing went wrong, and a run someone cancelled must not
	 * appear in the history as a run that broke.
	 *
	 * @since 0.12.0
	 */
	case Cancelled = 'cancelled';

	/**
	 * Returns the translated label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Queued    => __( 'Waiting', 'wowstudio-accessibility-kit' ),
			self::Running   => __( 'Running', 'wowstudio-accessibility-kit' ),
			self::Complete  => __( 'Complete', 'wowstudio-accessibility-kit' ),
			self::Failed    => __( 'Failed', 'wowstudio-accessibility-kit' ),
			self::Cancelled => __( 'Stopped', 'wowstudio-accessibility-kit' ),
		};
	}
}
