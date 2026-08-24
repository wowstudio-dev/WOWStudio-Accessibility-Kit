<?php
/**
 * Accessibility statement REST routes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Conformance\ConformanceStatus;
use WOWStudio\AccessibilityKit\Conformance\StatementGenerator;
use WOWStudio\AccessibilityKit\Conformance\StatementSettings;
use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Reading, writing, and signing off the accessibility statement.
 *
 * @since 0.7.0
 */
final class StatementController implements Registrable {

	/**
	 * Registers the routes.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the routes.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/statement',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_statement' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_statement' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/statement/attest',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'attest' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'name' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'role' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/statement/withdraw',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'withdraw' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Checks the reports capability.
	 *
	 * @since 0.7.0
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
	 * Checks the settings capability.
	 *
	 * @since 0.7.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_manage() {
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
	 * Returns the statement, its settings, and what is still missing.
	 *
	 * @since 0.7.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_statement(): WP_REST_Response {
		$settings  = new StatementSettings();
		$generator = new StatementGenerator( $settings );

		$statuses = array();

		foreach ( ConformanceStatus::cases() as $status ) {
			$statuses[] = array(
				'value' => $status->value,
				'label' => $status->label(),
			);
		}

		return new WP_REST_Response(
			array(
				'settings'  => $settings->all(),
				'statuses'  => $statuses,
				'standards' => array(
					array(
						'value' => 'wcag22aa',
						'label' => StatementGenerator::standard_label( 'wcag22aa' ),
					),
					array(
						'value' => 'wcag21aa',
						'label' => StatementGenerator::standard_label( 'wcag21aa' ),
					),
				),
				'missing'   => $generator->missing(),
				'attested'  => $generator->is_attested(),
				'preview'   => $generator->render(),
				'shortcode' => '[' . \WOWStudio\AccessibilityKit\Conformance\StatementBlock::SHORTCODE . ']',
			)
		);
	}

	/**
	 * Saves statement settings.
	 *
	 * @since 0.7.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function update_statement( WP_REST_Request $request ): WP_REST_Response {
		$fields  = array(
			'organisation',
			'standard',
			'status',
			'known_limitations',
			'feedback_email',
			'feedback_phone',
			'feedback_url',
			'response_days',
			'enforcement_body',
			'enforcement_url',
			'assessment',
			'reviewed_on',
		);
		$changes = array();

		foreach ( $fields as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$changes[ $field ] = $request->get_param( $field );
			}
		}

		( new StatementSettings() )->update( $changes );

		return $this->get_statement();
	}

	/**
	 * Records that a named person stands behind the statement.
	 *
	 * Refused while anything essential is still missing. Signing off a statement
	 * with no contact route, or one that says parts of the site fall short
	 * without saying which, would put a name to a document that does not yet
	 * say anything useful.
	 *
	 * @since 0.7.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function attest( WP_REST_Request $request ) {
		$settings  = new StatementSettings();
		$generator = new StatementGenerator( $settings );
		$missing   = $generator->missing();

		if ( array() !== $missing ) {
			return new WP_Error(
				'wsak_statement_incomplete',
				__( 'The statement is not finished yet, so it cannot be signed off.', 'wowstudio-accessibility-kit' ),
				array(
					'status'  => 422,
					'missing' => $missing,
				)
			);
		}

		$name = trim( (string) $request->get_param( 'name' ) );

		if ( '' === $name ) {
			return new WP_Error(
				'wsak_attestation_needs_a_name',
				__( 'Sign-off needs the name of the person taking responsibility for the statement.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 400 )
			);
		}

		$settings->attest( $name, (string) $request->get_param( 'role' ) );

		/**
		 * Fires when an accessibility statement is signed off.
		 *
		 * @since 0.7.0
		 *
		 * @param string $name Person attesting.
		 * @param int    $user WordPress user who recorded it.
		 */
		do_action( 'wsak_statement_attested', $name, get_current_user_id() );

		return $this->get_statement();
	}

	/**
	 * Withdraws sign-off, returning the statement to a draft.
	 *
	 * @since 0.7.0
	 *
	 * @return WP_REST_Response
	 */
	public function withdraw(): WP_REST_Response {
		( new StatementSettings() )->withdraw();

		return $this->get_statement();
	}
}
