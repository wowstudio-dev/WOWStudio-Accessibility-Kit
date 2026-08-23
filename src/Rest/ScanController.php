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
			'/scans/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_scan' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'The scan to read.', 'wowstudio-accessibility-kit' ),
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/scannable',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'scannable' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'search'   => array(
							'type'              => 'string',
							'description'       => __( 'Filter the list by title.', 'wowstudio-accessibility-kit' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
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
				'post_title'      => get_the_title( $post_id ),
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
	 * Returns a stored scan and its findings.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_scan( WP_REST_Request $request ) {
		$scan_id = absint( $request->get_param( 'id' ) );
		$scan    = ( new ScanRepository() )->find( $scan_id );

		if ( null === $scan ) {
			return new WP_Error(
				'wsak_unknown_scan',
				__( 'That scan could not be found.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		if ( $scan->target_id > 0 && ! current_user_can( 'read_post', $scan->target_id ) ) {
			return new WP_Error(
				'wsak_forbidden_post',
				__( 'You do not have permission to read that content.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$issues = new IssueRepository();

		return new WP_REST_Response(
			array(
				'scan_id'         => $scan->id,
				'post_id'         => $scan->target_id,
				'post_title'      => $scan->target_id > 0 ? get_the_title( $scan->target_id ) : '',
				'status'          => $scan->status->value,
				'score'           => $scan->score,
				'summary'         => $scan->summary,
				'started_at'      => $scan->started_at,
				'finished_at'     => $scan->finished_at,
				'full_page'       => (bool) ( $scan->summary['full_page'] ?? true ),
				'coverage_notice' => (string) ( $scan->summary['coverage_notice'] ?? '' ),
				'issues'          => $this->present_issues( $scan->id, $issues ),
			)
		);
	}

	/**
	 * Lists content that can be scanned, newest first.
	 *
	 * Each entry carries its most recent completed scan so the dashboard can
	 * show what is already known without a request per row.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function scannable( WP_REST_Request $request ): WP_REST_Response {
		$per_page = max( 1, min( 50, absint( $request->get_param( 'per_page' ) ) ) );
		$search   = (string) $request->get_param( 'search' );

		$query = array(
			'post_type'        => array( 'post', 'page' ),
			'post_status'      => 'publish',
			'posts_per_page'   => $per_page,
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'suppress_filters' => false,
			'no_found_rows'    => true,
		);

		if ( '' !== $search ) {
			$query['s'] = $search;
		}

		$scans = new ScanRepository();
		$items = array();

		foreach ( get_posts( $query ) as $post ) {
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}

			$latest = $scans->latest_for_post( $post->ID );

			$items[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'type'      => $post->post_type,
				'url'       => (string) get_permalink( $post ),
				'edit_url'  => (string) get_edit_post_link( $post->ID, 'raw' ),
				'last_scan' => null === $latest ? null : array(
					'scan_id'     => $latest->id,
					'score'       => $latest->score,
					'finished_at' => $latest->finished_at,
					'summary'     => $latest->summary,
				),
			);
		}

		return new WP_REST_Response( array( 'items' => $items ) );
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
		$registry  = ( new Engine() )->registry();

		foreach ( $issues->find_by_scan( $scan_id ) as $issue ) {
			$rule = $registry->get( $issue->rule_id );

			$presented[] = array(
				'rule_title'      => null === $rule ? $issue->rule_id : $rule->title(),
				'how_to_fix'      => null === $rule ? '' : $rule->description(),
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
