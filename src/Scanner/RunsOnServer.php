<?php
/**
 * Marks a check as belonging to the server pass.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies pass() for every rule that runs against parsed HTML.
 *
 * A trait rather than a default on the interface because PHP has no interface
 * defaults, and rather than twelve identical methods because they would drift.
 * Anything implementing Rule runs in PHP by definition; if that ever stops
 * being true, this is the one place that has to change.
 *
 * @since 0.10.0
 */
trait RunsOnServer {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.10.0
	 *
	 * @return ScanPass
	 */
	public function pass(): ScanPass {
		return ScanPass::Server;
	}
}
