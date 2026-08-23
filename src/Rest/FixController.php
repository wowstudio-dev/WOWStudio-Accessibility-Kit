<?php
/**
 * Fix REST routes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Remediation\Fix;
use WOWStudio\AccessibilityKit\Remediation\FixManager;
use WOWStudio\AccessibilityKit\Remediation\OverrideStore;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Preview, apply, and undo, one issue at a time.
 *
 * @since 0.6.0
 */
final class FixController implements Registrable {

	/**
	 * Registers the routes.
	 *
	 * @since 0.6.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the routes.
	 *
	 * @since 0.6.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/fixes/preview',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'preview' ),
					'permission_callback' => array( $this, 'can_fix' ),
					'args'                => array(
						'issue_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/fixes/apply',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'apply' ),
					'permission_callback' => array( $this, 'can_fix' ),
					'args'                => array(
						'issue_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'after'    => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The reviewed markup. Sanitised again before it is stored.', 'wowstudio-accessibility-kit' ),
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/fixes/(?P<id>\d+)/revert',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'revert' ),
					'permission_callback' => array( $this, 'can_fix' ),
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

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/fixes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'post_id' => array(
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
	 * @since 0.6.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_fix() {
		if ( current_user_can( Capabilities::APPLY_FIX ) ) {
			return true;
		}

		return new WP_Error(
			'wsak_forbidden',
			__( 'You do not have permission to apply accessibility fixes.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Checks the reports capability.
	 *
	 * @since 0.6.0
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
	 * Proposes a correction without changing anything.
	 *
	 * @since 0.6.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview( WP_REST_Request $request ) {
		$preview = ( new FixManager() )->preview( absint( $request->get_param( 'issue_id' ) ) );

		if ( is_wp_error( $preview ) ) {
			return $preview;
		}

		return new WP_REST_Response( $preview );
	}

	/**
	 * Applies a reviewed correction.
	 *
	 * @since 0.6.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply( WP_REST_Request $request ) {
		$fix = ( new FixManager() )->apply(
			absint( $request->get_param( 'issue_id' ) ),
			(string) $request->get_param( 'after' ),
			get_current_user_id()
		);

		if ( is_wp_error( $fix ) ) {
			return $fix;
		}

		return new WP_REST_Response(
			array( 'fix' => $fix instanceof Fix ? $fix->to_array() : null ),
			201
		);
	}

	/**
	 * Undoes an applied correction.
	 *
	 * @since 0.6.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function revert( WP_REST_Request $request ) {
		$fix = ( new FixManager() )->revert(
			absint( $request->get_param( 'id' ) ),
			get_current_user_id()
		);

		if ( is_wp_error( $fix ) ) {
			return $fix;
		}

		return new WP_REST_Response( array( 'fix' => $fix instanceof Fix ? $fix->to_array() : null ) );
	}

	/**
	 * Lists the overrides recorded against a post.
	 *
	 * @since 0.6.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function index( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );

		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return new WP_Error(
				'wsak_forbidden_post',
				__( 'You do not have permission to read that content.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$fixes = array_map(
			static fn( Fix $fix ): array => $fix->to_array(),
			( new OverrideStore() )->for_post( $post_id )
		);

		return new WP_REST_Response( array( 'fixes' => $fixes ) );
	}
}
