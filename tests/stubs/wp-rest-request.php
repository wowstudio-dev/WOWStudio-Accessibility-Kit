<?php
/**
 * WP_REST_Request and WP_REST_Response stubs.
 *
 * Unit tests do not load WordPress. Brain Monkey covers functions; these are the
 * few classes the plugin constructs directly, kept to the smallest shape the
 * code under test relies on so a test cannot pass by leaning on behaviour the
 * real class does not have.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Stand-in for an incoming REST request: a bag of parameters.
	 */
	class WP_REST_Request {

		/**
		 * Parameters set on this request.
		 *
		 * @var array<string, mixed>
		 */
		private array $params = array();

		/**
		 * Constructor.
		 *
		 * @param string $method HTTP method.
		 * @param string $route  Route being called.
		 */
		public function __construct( string $method = 'GET', string $route = '' ) {
		}

		/**
		 * Sets one parameter.
		 *
		 * @param string $key   Parameter name.
		 * @param mixed  $value Parameter value.
		 * @return void
		 */
		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}

		/**
		 * Reads one parameter.
		 *
		 * @param string $key Parameter name.
		 * @return mixed
		 */
		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Stand-in for a REST response: data and a status.
	 */
	class WP_REST_Response {

		/**
		 * Response payload.
		 *
		 * @var mixed
		 */
		private $data;

		/**
		 * HTTP status.
		 *
		 * @var int
		 */
		private int $status;

		/**
		 * Constructor.
		 *
		 * @param mixed $data   Payload.
		 * @param int   $status HTTP status.
		 */
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * Returns the payload.
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * Returns the status.
		 *
		 * @return int
		 */
		public function get_status(): int {
			return $this->status;
		}
	}
}
