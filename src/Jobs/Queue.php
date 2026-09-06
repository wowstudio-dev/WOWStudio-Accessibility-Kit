<?php
/**
 * The boundary between this plugin and Action Scheduler.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Jobs;

defined( 'ABSPATH' ) || exit;

/**
 * Every call into Action Scheduler happens here and nowhere else.
 *
 * Two reasons for the wrapper. Action Scheduler is a bundled library whose
 * functions are global and only exist once it has loaded, so every call site
 * would otherwise need its own `function_exists()` guard — and the one that
 * forgets is a fatal error on a site where something has gone wrong with the
 * include. And the rest of the plugin should be testable without a scheduler:
 * this class is small enough to stub, which the classes that use it are not.
 *
 * @since 0.12.0
 *
 * Not final, unlike most classes here. It is handed to another object through
 * that object's constructor so the object can be tested without it, and sealing
 * it would make the injection decorative — a parameter nobody could ever pass
 * anything but the default to. The rule here is final by default, open where
 * something is meant to be substituted.
 */
class Queue {

	/**
	 * The action fired for each page in a run.
	 *
	 * @since 0.12.0
	 * @var string
	 */
	public const HOOK = 'wsak_scan_page';

	/**
	 * The action fired to give older findings their identity.
	 *
	 * @since 0.29.0
	 * @var string
	 */
	public const HOOK_BACKFILL = 'wsak_backfill_fingerprints';

	/**
	 * The group the backfill runs in.
	 *
	 * Its own group, not a run's, so cancelling a scan cannot cancel a
	 * migration that happens to be in flight beside it.
	 *
	 * @since 0.29.0
	 * @var string
	 */
	public const GROUP_BACKFILL = 'wsak-backfill';

	/**
	 * Returns the Action Scheduler group for one run.
	 *
	 * A group per run rather than one group for the plugin, so cancelling a run
	 * unschedules exactly its own work and cannot reach into another run that
	 * happens to be in flight.
	 *
	 * @since 0.12.0
	 *
	 * @param int $run_id Run identifier.
	 * @return string
	 */
	public static function group_for( int $run_id ): string {
		return 'wsak-run-' . $run_id;
	}

	/**
	 * Reports whether a scheduler is available at all.
	 *
	 * @since 0.12.0
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return function_exists( 'as_enqueue_async_action' )
			&& function_exists( 'as_unschedule_all_actions' );
	}

	/**
	 * Queues one page for scanning.
	 *
	 * @since 0.12.0
	 *
	 * @param int $run_id  Run the page belongs to.
	 * @param int $scan_id Scan row to fill in.
	 * @return bool Whether the action was accepted.
	 */
	public function enqueue( int $run_id, int $scan_id ): bool {
		if ( ! $this->is_available() ) {
			return false;
		}

		$action_id = as_enqueue_async_action(
			self::HOOK,
			array( 'scan_id' => $scan_id ),
			self::group_for( $run_id )
		);

		return $action_id > 0;
	}

	/**
	 * Drops everything still scheduled for a run.
	 *
	 * @since 0.12.0
	 *
	 * @param int $run_id Run to stop.
	 * @return void
	 */
	/**
	 * Queues the fingerprint backfill.
	 *
	 * No check for one already queued, deliberately. The obvious guard —
	 * as_has_scheduled_action() — counts the action currently running, so a
	 * batch asking for its own successor would be told one already exists and
	 * the backfill would stop after two hundred rows. That failure is invisible
	 * on a small site, which finishes in one batch, and silent on a large one.
	 *
	 * It needs no guard anyway: a batch only ever rewrites rows that still have
	 * no fingerprint, so two workers racing do the same work twice at worst and
	 * never the wrong work.
	 *
	 * @since 0.29.0
	 *
	 * @return bool Whether the work is scheduled.
	 */
	public function enqueue_backfill(): bool {
		if ( ! $this->is_available() ) {
			return false;
		}

		return as_enqueue_async_action( self::HOOK_BACKFILL, array(), self::GROUP_BACKFILL ) > 0;
	}

	/**
	 * Drops everything still scheduled for a run.
	 *
	 * @since 0.12.0
	 *
	 * @param int $run_id Run to stop.
	 * @return void
	 */
	public function cancel( int $run_id ): void {
		if ( ! $this->is_available() ) {
			return;
		}

		as_unschedule_all_actions( '', array(), self::group_for( $run_id ) );
	}
}
