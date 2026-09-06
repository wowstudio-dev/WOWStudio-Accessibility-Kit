<?php
/**
 * Reviewing findings: setting them aside, and the deterministic fixes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Remediation\IssueReview;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Dismissing findings, restoring them, and the fixes that need no review.
 *
 * @since 0.13.0
 */
final class ReviewController implements Registrable {

	/**
	 * Registers the routes.
	 *
	 * @since 0.13.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the routes.
	 *
	 * @since 0.13.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/issues/(?P<id>\d+)/ignore',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'ignore' ),
					'permission_callback' => array( $this, 'can_decide' ),
					'args'                => array(
						'id'   => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'note' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
							'description'       => __( 'Why this is not a problem. Kept with the finding.', 'wowstudio-accessibility-kit' ),
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/issues/(?P<id>\d+)/reopen',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reopen' ),
					'permission_callback' => array( $this, 'can_decide' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Checks the fix capability.
	 *
	 * @since 0.13.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_decide() {
		if ( current_user_can( Capabilities::APPLY_FIX ) ) {
			return true;
		}

		return new WP_Error(
			'wsak_forbidden',
			__( 'You do not have permission to make decisions about findings.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Requires our capability and core's theme capability.
	 *
	 * This one changes how every page on the site renders, so it takes the same
	 * capability WordPress asks for before letting somebody change the theme's
	 * behaviour. Ours alone would make this a way around that.
	 *
	 * @since 0.13.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_change_theme_support() {
		$allowed = $this->can_decide();

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'wsak_forbidden_theme',
				__( 'This fix changes how your theme renders every page, and your account is not allowed to change theme settings. An administrator can apply it.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Sets a finding aside.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ignore( WP_REST_Request $request ) {
		$result = ( new IssueReview() )->ignore(
			absint( $request->get_param( 'id' ) ),
			(string) $request->get_param( 'note' ),
			get_current_user_id()
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Puts a dismissed finding back.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reopen( WP_REST_Request $request ) {
		$result = ( new IssueReview() )->reopen(
			absint( $request->get_param( 'id' ) ),
			get_current_user_id()
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result );
	}
}
