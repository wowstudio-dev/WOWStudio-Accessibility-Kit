<?php
/**
 * CSS fix REST routes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Remediation\CssFixManager;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Writing, listing, and removing the style rules that answer findings.
 *
 * @since 0.11.0
 */
final class CssFixController implements Registrable {

	/**
	 * Registers the routes.
	 *
	 * @since 0.11.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the routes.
	 *
	 * @since 0.11.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/fixes/css',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_edit_css' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'apply' ),
					'permission_callback' => array( $this, 'can_edit_css' ),
					'args'                => array(
						'issue_id'     => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'selector'     => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The selector the rule applies to. Checked again before it is written.', 'wowstudio-accessibility-kit' ),
						),
						'declarations' => array(
							'required'    => true,
							'type'        => 'array',
							'description' => __( 'Property and value pairs, restricted to what the finding is about.', 'wowstudio-accessibility-kit' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'property' => array( 'type' => 'string' ),
									'value'    => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/fixes/css/(?P<issue_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'revert' ),
					'permission_callback' => array( $this, 'can_edit_css' ),
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
	}

	/**
	 * Requires both the plugin's fix capability and core's CSS capability.
	 *
	 * Ours alone is not enough. `edit_css` is what gates the Customiser, and a
	 * rule written here reaches the whole site exactly as one typed there does.
	 * This must never become a way to change site-wide CSS for somebody who
	 * could not already do it by hand.
	 *
	 * @since 0.11.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_edit_css() {
		if ( ! current_user_can( Capabilities::APPLY_FIX ) ) {
			return new WP_Error(
				'wsak_forbidden',
				__( 'You do not have permission to apply accessibility fixes.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( ! current_user_can( 'edit_css' ) ) {
			return new WP_Error(
				'wsak_forbidden_css',
				__( 'Applying this kind of fix edits your site\'s Additional CSS, which your account is not allowed to change. An administrator can apply it, or make the change in Appearance → Customise.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Lists the rules currently in force.
	 *
	 * @since 0.11.0
	 *
	 * @return WP_REST_Response
	 */
	public function index(): WP_REST_Response {
		$manager = new CssFixManager();

		return new WP_REST_Response(
			array(
				'rules' => $manager->rules(),
				'theme' => $manager->theme_name(),
			)
		);
	}

	/**
	 * Writes a reviewed rule.
	 *
	 * @since 0.11.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply( WP_REST_Request $request ) {
		$declarations = $request->get_param( 'declarations' );

		$result = ( new CssFixManager() )->apply(
			absint( $request->get_param( 'issue_id' ) ),
			(string) $request->get_param( 'selector' ),
			is_array( $declarations ) ? $declarations : array()
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * Removes a rule and reopens its finding.
	 *
	 * @since 0.11.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function revert( WP_REST_Request $request ) {
		$result = ( new CssFixManager() )->revert( absint( $request->get_param( 'issue_id' ) ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result );
	}
}
