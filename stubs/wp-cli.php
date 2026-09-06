<?php
/**
 * Minimal WP-CLI declarations, for static analysis only.
 *
 * WP-CLI is not a dependency of this plugin and is never installed alongside it
 * — it is the environment the plugin may find itself running inside. These
 * declarations exist so PHPStan can check src/Cli/ against something; they are
 * never loaded at runtime and are excluded from the shipped build.
 *
 * @package WOWStudio\AccessibilityKit
 */

// phpcs:disable

namespace {
	class WP_CLI {
		/**
		 * @param string $name
		 * @param mixed  $callable
		 * @param array  $args
		 * @return bool
		 */
		public static function add_command( $name, $callable, $args = array() ) { return true; }

		/**
		 * @param string $message
		 * @return void
		 */
		public static function success( $message ) {}

		/**
		 * @param string $message
		 * @return void
		 */
		public static function warning( $message ) {}

		/**
		 * @param string $message
		 * @return never
		 */
		public static function error( $message ) { exit( 1 ); }

		/**
		 * @param string $message
		 * @return void
		 */
		public static function log( $message ) {}
	}
}

namespace WP_CLI\Utils {
	/**
	 * @param string $message
	 * @param int    $count
	 * @return \cli\progress\Bar
	 */
	function make_progress_bar( $message, $count ) {}

	/**
	 * @param string $format
	 * @param array  $items
	 * @param array  $fields
	 * @return void
	 */
	function format_items( $format, $items, $fields ) {}
}

namespace cli\progress {
	class Bar {
		/**
		 * @return void
		 */
		public function tick() {}

		/**
		 * @return void
		 */
		public function finish() {}
	}
}
