<?php
/**
 * WP_Error stub.
 *
 * Unit tests do not load WordPress. Brain Monkey covers functions; these are the
 * few classes the plugin constructs directly, kept to the smallest shape the
 * code under test relies on so a test cannot pass by leaning on behaviour the
 * real class does not have.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Stand-in for WordPress's error object.
	 */
	class WP_Error {

		/**
		 * Error code.
		 *
		 * @var string
		 */
		public string $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		public string $message;

		/**
		 * Error data.
		 *
		 * @var mixed
		 */
		public $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( string $code = '', string $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/**
		 * Returns the error code.
		 *
		 * @return string
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Returns the error message.
		 *
		 * @return string
		 */
		public function get_error_message(): string {
			return $this->message;
		}
	}
}
