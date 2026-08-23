<?php
/**
 * WP_REST_Server stub.
 *
 * Unit tests do not load WordPress. Brain Monkey covers functions; these are the
 * few classes the plugin constructs directly, kept to the smallest shape the
 * code under test relies on so a test cannot pass by leaning on behaviour the
 * real class does not have.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

if ( ! class_exists( 'WP_REST_Server' ) ) {
	/**
	 * Stand-in for the REST server, for its method constants.
	 */
	class WP_REST_Server {

		/**
		 * GET.
		 *
		 * @var string
		 */
		const READABLE = 'GET';

		/**
		 * POST, PUT, PATCH.
		 *
		 * @var string
		 */
		const CREATABLE = 'POST';

		/**
		 * DELETE.
		 *
		 * @var string
		 */
		const DELETABLE = 'DELETE';
	}
}
