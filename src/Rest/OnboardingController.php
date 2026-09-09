<?php
/**
 * Setup-state REST route.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Admin\Onboarding;
use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Remembering whether somebody has been through the setup.
 *
 * One flag, and it is deliberately writable in both directions. "Done" is a
 * claim about a person rather than about the site, and the only cost of letting
 * them take it back is that they see the setup again.
 *
 * @since 0.29.0
 */
final class OnboardingController implements Registrable {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.29.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the route.
	 *
	 * @since 0.29.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/onboarding',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_change' ),
					'args'                => array(
						'done' => array(
							'required' => true,
							'type'     => 'boolean',
						),
					),
				),
			)
		);
	}

	/**
	 * The setup writes settings, so finishing it needs the settings capability.
	 *
	 * @since 0.29.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_change() {
		if ( current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			return true;
		}

		return new WP_Error(
			'wsak_forbidden',
			__( 'You do not have permission to change accessibility settings.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Marks the setup finished, or reopens it.
	 *
	 * @since 0.29.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$done = (bool) $request->get_param( 'done' );

		Onboarding::set_done( $done );

		return new WP_REST_Response( array( 'done' => $done ) );
	}
}
