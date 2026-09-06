<?php
/**
 * Monitoring REST routes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Monitoring\Change;
use WOWStudio\AccessibilityKit\Monitoring\Comparison;
use WOWStudio\AccessibilityKit\Monitoring\Monitor;
use WOWStudio\AccessibilityKit\Monitoring\Schedule;
use WOWStudio\AccessibilityKit\Scanner\RuleRegistry;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * The schedule, and the record of what has changed since last time.
 *
 * @since 0.21.0
 */
final class MonitoringController implements Registrable {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.21.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the routes.
	 *
	 * @since 0.21.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/monitoring',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'can_change' ),
					'args'                => array(
						'frequency' => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => array_keys( Schedule::choices() ),
						),
					),
				),
			)
		);
	}

	/**
	 * Reading the record needs only the reports capability.
	 *
	 * @since 0.21.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_view() {
		if ( current_user_can( Capabilities::VIEW_REPORTS ) ) {
			return true;
		}

		return new WP_Error(
			'wsak_forbidden',
			__( 'You do not have permission to view accessibility reports.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Changing the schedule starts recurring work on somebody's server.
	 *
	 * @since 0.21.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_change() {
		if ( current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			return true;
		}

		return new WP_Error(
			'wsak_forbidden',
			__( 'Scheduling a repeating scan runs work on your server, so it needs the accessibility settings capability.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Returns the schedule and what has changed.
	 *
	 * @since 0.21.0
	 *
	 * @return WP_REST_Response
	 */
	public function index(): WP_REST_Response {
		return new WP_REST_Response( $this->state() );
	}

	/**
	 * Saves the frequency.
	 *
	 * @since 0.21.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$schedule = new Schedule();

		if ( ! $schedule->set( (string) $request->get_param( 'frequency' ) ) ) {
			return new WP_Error(
				'wsak_bad_frequency',
				__( 'That is not a frequency this plugin offers.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response( $this->state() );
	}

	/**
	 * Composes everything the monitoring screen shows.
	 *
	 * @since 0.21.0
	 *
	 * @return array<string, mixed>
	 */
	private function state(): array {
		$schedule = new Schedule();
		$monitor  = new Monitor( $schedule );
		$next     = $monitor->next_run();

		$titles  = $this->rule_titles();
		$changes = array_map(
			static function ( Change $change ) use ( $titles ): array {
				$row = $change->to_array();

				// The interface should say "Image has no alt attribute", not
				// "img-alt-missing". Named here rather than in the client so
				// the wording stays in one place and stays translated.
				foreach ( array( 'appeared', 'resolved' ) as $key ) {
					$named = array();

					foreach ( $row[ $key ] as $rule => $count ) {
						$named[] = array(
							'rule'  => $rule,
							'title' => $titles[ $rule ] ?? $rule,
							'count' => $count,
						);
					}

					$row[ $key ] = $named;
				}

				return $row;
			},
			( new Comparison() )->recent( 25 )
		);

		return array(
			'available'  => $monitor->is_available(),
			'frequency'  => $schedule->frequency(),
			'choices'    => Schedule::choices(),
			'page_limit' => $schedule->page_limit(),
			'next_run'   => $next,
			'next_run_h' => null === $next ? '' : date_i18n( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $next ),
			'changes'    => $changes,
		);
	}

	/**
	 * Returns every rule's title, keyed by id.
	 *
	 * @since 0.21.0
	 *
	 * @return array<string, string>
	 */
	private function rule_titles(): array {
		$titles = array();

		foreach ( RuleRegistry::with_defaults()->coverage() as $row ) {
			$titles[ (string) $row['id'] ] = (string) $row['title'];
		}

		return $titles;
	}
}
