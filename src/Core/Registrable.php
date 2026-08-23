<?php
/**
 * Contract for services that hook themselves into WordPress.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Core;

defined( 'ABSPATH' ) || exit;

/**
 * A service that registers its own hooks.
 *
 * Keeping registration inside each service is what stops Plugin from turning
 * into a god object that knows every hook in the plugin.
 *
 * @since 0.1.0
 */
interface Registrable {

	/**
	 * Adds this service's actions and filters.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register(): void;
}
