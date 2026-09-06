<?php
/**
 * Findings from across the site, filtered.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WOWStudio\AccessibilityKit\Scanner\Severity;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * The endpoint behind a number on the overview.
 *
 * Every figure on the report screen used to be a dead end: it said forty-one
 * links had ambiguous text and offered no way to see one of them. This is what
 * turns those figures into something a person can act on, and it is free for
 * the same reason every check is — being told a barrier exists and not being
 * shown where it is helps nobody, least of all the visitor who meets it.
 *
 * The rule's own explanation travels with the list rather than being repeated
 * against each row. Somebody working through forty-one instances of one fault
 * needs to understand it once; printing it forty-one times is how a list
 * becomes something people scroll past.
 *
 * @since 0.29.0
 */
final class IssueController implements Registrable {

	/**
	 * How many findings one request may return.
	 *
	 * @since 0.29.0
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Registers the routes.
	 *
	 * @since 0.29.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the routes.
	 *
	 * @since 0.29.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/issues',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_issues' ),
					'permission_callback' => array( $this, 'can_read' ),
					'args'                => array(
						'rule'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'description'       => __( 'Limit to one check.', 'wowstudio-accessibility-kit' ),
						),
						'post'      => array(
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'description'       => __( 'Limit to one page.', 'wowstudio-accessibility-kit' ),
						),
						'severity'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'detection' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'status'    => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'limit'     => array(
							'type'              => 'integer',
							'default'           => 50,
							'sanitize_callback' => 'absint',
						),
						'offset'    => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Reports whether the caller may read findings.
	 *
	 * @since 0.29.0
	 *
	 * @return bool
	 */
	public function can_read(): bool {
		return current_user_can( Capabilities::RUN_SCAN );
	}

	/**
	 * Returns the findings matching the filters, and what they mean.
	 *
	 * @since 0.29.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function get_issues( WP_REST_Request $request ): WP_REST_Response {
		$issues = new IssueRepository();

		$filters = array(
			'rule_id' => (string) $request->get_param( 'rule' ),
			'status'  => IssueStatus::tryFrom( (string) $request->get_param( 'status' ) ) ?? IssueStatus::Open,
			'limit'   => min( self::MAX_PER_PAGE, max( 1, (int) $request->get_param( 'limit' ) ) ),
			'offset'  => max( 0, (int) $request->get_param( 'offset' ) ),
		);

		$post_id = (int) $request->get_param( 'post' );

		if ( $post_id > 0 ) {
			$filters['post_id'] = $post_id;
		}

		$severity = Severity::tryFrom( (string) $request->get_param( 'severity' ) );

		if ( $severity instanceof Severity ) {
			$filters['severity'] = $severity;
		}

		$detection = Detection::tryFrom( (string) $request->get_param( 'detection' ) );

		if ( $detection instanceof Detection ) {
			$filters['detection'] = $detection;
		}

		$found = $issues->find_current( $filters );

		$data = array(
			'issues' => ( new IssuePresenter() )->present( $found ),
			'total'  => $issues->count_current( $filters ),
			'limit'  => $filters['limit'],
			'offset' => $filters['offset'],
			'rule'   => $this->describe_rule( $filters['rule_id'] ),
			'page'   => $this->describe_page( $post_id ),
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Describes the check a list has been narrowed to.
	 *
	 * This is the part that makes a filtered list worth opening: what the fault
	 * is, who it shuts out, and what to do about it, stated once at the top.
	 * Everything here is already declared by the rule itself, so it cannot
	 * disagree with what the same rule says anywhere else.
	 *
	 * @since 0.29.0
	 *
	 * @param string $rule_id Rule the list is filtered to, or ''.
	 * @return array<string, mixed>|null
	 */
	private function describe_rule( string $rule_id ): ?array {
		if ( '' === $rule_id ) {
			return null;
		}

		$rule = ( new Engine() )->registry()->descriptor( $rule_id );

		if ( null === $rule ) {
			return null;
		}

		return array(
			'id'              => $rule->id(),
			'title'           => $rule->title(),
			'description'     => $rule->description(),
			'consequence'     => $rule->consequence(),
			'wcag_sc'         => $rule->wcag_sc(),
			'severity'        => $rule->severity()->value,
			'severity_label'  => $rule->severity()->label(),
			'detection'       => $rule->detection()->value,
			'detection_label' => $rule->detection()->label(),
			'fix'             => $rule->fix_plan()->to_array(),
		);
	}

	/**
	 * Describes the page a list has been narrowed to.
	 *
	 * @since 0.29.0
	 *
	 * @param int $post_id Page the list is filtered to, or 0.
	 * @return array<string, mixed>|null
	 */
	private function describe_page( int $post_id ): ?array {
		if ( $post_id <= 0 ) {
			return null;
		}

		$post = get_post( $post_id );

		if ( null === $post ) {
			return null;
		}

		$title = get_the_title( $post );

		return array(
			'id'        => $post_id,
			'title'     => '' !== $title ? $title : __( '(no title)', 'wowstudio-accessibility-kit' ),
			'edit_link' => (string) get_edit_post_link( $post_id, 'raw' ),
			'view_link' => (string) get_permalink( $post_id ),
		);
	}
}
