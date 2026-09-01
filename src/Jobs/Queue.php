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
	 * The action fired for each image in an alt-text run.
	 *
	 * A separate hook rather than a payload flag on the first, so cancelling a
	 * scan cannot reach an alt-text run that happens to share a number, and so
	 * the two show up distinctly in Action Scheduler's own admin screen when
	 * somebody is working out why their site is busy.
	 *
	 * @since 0.13.0
	 * @var string
	 */
	public const ALT_HOOK = 'wsak_describe_image';

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
	 * Returns the Action Scheduler group for one alt-text run.
	 *
	 * @since 0.13.0
	 *
	 * @param int $run_id Run identifier.
	 * @return string
	 */
	public static function alt_group_for( int $run_id ): string {
		return 'wsak-alt-' . $run_id;
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
	public function cancel( int $run_id ): void {
		if ( ! $this->is_available() ) {
			return;
		}

		as_unschedule_all_actions( '', array(), self::group_for( $run_id ) );
	}

	/**
	 * Queues one image for description.
	 *
	 * @since 0.13.0
	 *
	 * @param int $run_id        Run the image belongs to.
	 * @param int $attachment_id Image to describe.
	 * @return bool Whether the action was accepted.
	 */
	public function enqueue_alt_text( int $run_id, int $attachment_id ): bool {
		if ( ! $this->is_available() ) {
			return false;
		}

		$action_id = as_enqueue_async_action(
			self::ALT_HOOK,
			array( 'attachment_id' => $attachment_id ),
			self::alt_group_for( $run_id )
		);

		return $action_id > 0;
	}

	/**
	 * Drops everything still scheduled for an alt-text run.
	 *
	 * @since 0.13.0
	 *
	 * @param int $run_id Run to stop.
	 * @return void
	 */
	public function cancel_alt_text( int $run_id ): void {
		if ( ! $this->is_available() ) {
			return;
		}

		as_unschedule_all_actions( '', array(), self::alt_group_for( $run_id ) );
	}
}
