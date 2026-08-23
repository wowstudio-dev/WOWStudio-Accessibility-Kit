<?php
/**
 * Deactivation routine.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Stands the plugin down without destroying anything.
 *
 * Deactivation must be reversible: no user data, settings, or capabilities are
 * removed here. Destructive cleanup belongs in uninstall.php, and only when the
 * site owner has opted in.
 *
 * @since 0.1.0
 */
final class Deactivator {

	/**
	 * Stops scheduled work and clears caches.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'wsak_scheduled_scan' );

		/**
		 * Fires when the plugin is deactivated, before caches are cleared.
		 *
		 * Queued background work should be cancelled on this hook.
		 *
		 * @since 0.1.0
		 */
		do_action( 'wsak_deactivated' );
	}
}
