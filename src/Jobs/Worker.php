<?php
/**
 * Wiring the scheduler's action to the code that answers it.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Jobs;

use WOWStudio\AccessibilityKit\AltText\AltTextRun;
use WOWStudio\AccessibilityKit\Core\Registrable;

defined( 'ABSPATH' ) || exit;

/**
 * Listens for the scheduler and hands each page to BulkScan.
 *
 * Thin on purpose. The hook has to be registered on every request — the queue
 * runs in requests nobody is watching, including cron and Action Scheduler's
 * own async runner — so this must not be conditional on being in the admin, on
 * a capability, or on a nonce. Whatever it triggers therefore has to be safe to
 * run with no user, which is why the work is a page scan and nothing else.
 *
 * @since 0.12.0
 */
final class Worker implements Registrable {

	/**
	 * Registers the scheduler hook.
	 *
	 * @since 0.12.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Both handlers belong to paid features whose implementations Freemius
		// strips from the free build. Registering a hook whose handler would
		// fatal is worse than not registering it: the action would be claimed,
		// run, and take the queue down with it.
		if ( class_exists( BulkScan::class ) ) {
			add_action( Queue::HOOK, array( $this, 'scan_page' ), 10, 1 );
		}

		if ( class_exists( AltTextRun::class ) ) {
			add_action( Queue::ALT_HOOK, array( $this, 'describe_image' ), 10, 1 );
		}
	}

	/**
	 * Scans one queued page.
	 *
	 * @since 0.12.0
	 *
	 * @param mixed $scan_id Scan row the action was queued with.
	 * @return void
	 */
	public function scan_page( $scan_id ): void {
		$scan_id = absint( $scan_id );

		if ( 0 === $scan_id ) {
			return;
		}

		( new BulkScan() )->step( $scan_id );
	}

	/**
	 * Describes one queued image.
	 *
	 * @since 0.13.0
	 *
	 * @param mixed $attachment_id Image the action was queued with.
	 * @return void
	 */
	public function describe_image( $attachment_id ): void {
		$attachment_id = absint( $attachment_id );

		if ( 0 === $attachment_id ) {
			return;
		}

		( new AltTextRun() )->step( $attachment_id );
	}
}
