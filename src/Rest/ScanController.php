<?php
/**
 * Scan REST route.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Db\ScanRepository;
use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Scanner\PageSource;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes scanning over the REST API.
 *
 * @since 0.3.0
 */
final class ScanController implements Registrable {

	/**
	 * REST namespace.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	public const REST_NAMESPACE = 'wsak/v1';

	/**
	 * Registers the routes.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the routes.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/scan',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'scan' ),
					'permission_callback' => array( $this, 'can_scan' ),
					'args'                => array(
						'post_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'The post or page to scan.', 'wowstudio-accessibility-kit' ),
							'sanitize_callback' => 'absint',
							'validate_callback' => array( $this, 'validate_post_id' ),
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/coverage',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'coverage' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
			)
		);
	}

	/**
	 * Checks whether the current user may run a scan.
	 *
	 * @since 0.3.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_scan() {
		if ( current_user_can( Capabilities::RUN_SCAN ) ) {
			return true;
		}

		return new WP_Error(
			'wsak_forbidden',
			__( 'You do not have permission to run accessibility scans.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Checks whether the current user may read scan information.
	 *
	 * @since 0.3.0
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
	 * Validates the requested post.
	 *
	 * Checks readability as well as existence: the capability to scan does not
	 * imply the capability to read every post on the site, and a scan response
	 * echoes back the markup of the page it scanned.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $value Submitted value.
	 * @return true|WP_Error
	 */
	public function validate_post_id( $value ) {
		$post_id = absint( $value );
		$post    = get_post( $post_id );

		if ( null === $post ) {
			return new WP_Error(
				'wsak_unknown_post',
				__( 'That content could not be found.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return new WP_Error(
				'wsak_forbidden_post',
				__( 'You do not have permission to read that content.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Scans one page and stores the findings.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function scan( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$scans   = new ScanRepository();
		$issues  = new IssueRepository();

		$scan_id = $scans->start( ScanScope::Page, $post_id, get_current_user_id() );

		if ( 0 === $scan_id ) {
			return new WP_Error(
				'wsak_scan_not_started',
				__( 'The scan could not be recorded. Check that the plugin tables exist.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 500 )
			);
		}

		$markup = ( new PageSource() )->for_post( $post_id );

		if ( is_wp_error( $markup ) ) {
			// The run is recorded as failed rather than deleted, so a page that
			// cannot be fetched is visible as a problem instead of silently
			// never appearing in the history.
			$scans->fail( $scan_id );

			return $markup;
		}

		$result = ( new Engine() )->scan( $markup->html );

		if ( null === $result ) {
			$scans->fail( $scan_id );

			return new WP_Error(
				'wsak_unparseable',
				__( 'The page could not be parsed as HTML, so it could not be scanned.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 422 )
			);
		}

		$rows = array();

		foreach ( $result->findings as $finding ) {
			$rows[] = $finding->to_row( $post_id );
		}

		$summary                    = $result->summary();
		$summary['from_loopback']   = $markup->from_loopback;
		$summary['coverage_notice'] = $markup->notice;

		$issues->add_many( $scan_id, $rows );
		$scans->complete( $scan_id, $result->score(), $summary );

		return new WP_REST_Response(
			array(
				'scan_id'         => $scan_id,
				'post_id'         => $post_id,
				'score'           => $result->score(),
				'summary'         => $summary,
				'full_page'       => $result->full_page,
				// Surfaced separately so the UI cannot present a reduced scan as
				// a complete one without deliberately ignoring this field.
				'coverage_notice' => $markup->notice,
				'issues'          => $this->present_issues( $scan_id, $issues ),
			),
			201
		);
	}

	/**
	 * Returns what the rule set can and cannot settle automatically.
	 *
	 * @since 0.3.0
	 *
	 * @return WP_REST_Response
	 */
	public function coverage(): WP_REST_Response {
		$registry = ( new Engine() )->registry();

		return new WP_REST_Response(
			array(
				'rules'       => $registry->coverage(),
				'total_rules' => count( $registry->all() ),
			)
		);
	}

	/**
	 * Shapes stored issues for the response.
	 *
	 * @since 0.3.0
	 *
	 * @param int             $scan_id Scan to read.
	 * @param IssueRepository $issues  Repository to read from.
	 * @return array<int, array<string, mixed>>
	 */
	private function present_issues( int $scan_id, IssueRepository $issues ): array {
		$presented = array();

		foreach ( $issues->find_by_scan( $scan_id ) as $issue ) {
			$presented[] = array(
				'id'              => $issue->id,
				'rule_id'         => $issue->rule_id,
				'wcag_sc'         => $issue->wcag_sc,
				'severity'        => $issue->severity->value,
				'severity_label'  => $issue->severity->label(),
				'detection'       => $issue->detection->value,
				'detection_label' => $issue->detection->label(),
				'status'          => $issue->status->value,
				'message'         => $issue->message,
				'selector'        => $issue->selector,
				'context'         => $issue->context,
			);
		}

		return $presented;
	}
}
