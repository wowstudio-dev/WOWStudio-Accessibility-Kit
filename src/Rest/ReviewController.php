<?php
/**
 * Reviewing findings: setting them aside, and the deterministic fixes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Db\DecisionRepository;
use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Remediation\IssueReview;
use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Remediation\SiteWideReview;
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

		/*
		 * Keyed on the fingerprint rather than a row id, because the decision
		 * outlives every row it currently applies to. The next scan replaces
		 * them all, and a route that took an id would be recording a judgement
		 * against something about to be deleted.
		 */
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/markup/(?P<fingerprint>[0-9a-f]{40})/ignore',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'ignore_site_wide' ),
					'permission_callback' => array( $this, 'can_read' ),
					'args'                => array(
						'fingerprint' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'rule'        => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'note'        => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
							'description'       => __( 'Why this is not a problem anywhere it appears.', 'wowstudio-accessibility-kit' ),
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/markup/(?P<fingerprint>[0-9a-f]{40})/reopen',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reopen_site_wide' ),
					'permission_callback' => array( $this, 'can_read' ),
					'args'                => array(
						'fingerprint' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/dismissed',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'dismissed' ),
					'permission_callback' => array( $this, 'can_read' ),
					'args'                => array(
						'limit'  => array(
							'type'              => 'integer',
							'default'           => 50,
							'sanitize_callback' => 'absint',
						),
						'offset' => array(
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
	 * Reading the log needs only the reports capability.
	 *
	 * Deliberately wider than the capability needed to dismiss something. A
	 * record of decisions that only the decision-makers can read is not much of
	 * a record — the point of it is that somebody else can check the reasoning.
	 *
	 * @since 0.22.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_read() {
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
	 * Returns what has been set aside, and who set it aside.
	 *
	 * @since 0.22.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function dismissed( WP_REST_Request $request ): WP_REST_Response {
		$issues = new IssueRepository();

		$rows = array();

		foreach ( $issues->dismissed( (int) $request->get_param( 'limit' ), (int) $request->get_param( 'offset' ) ) as $issue ) {
			$user = $issue->resolved_by > 0 ? get_userdata( $issue->resolved_by ) : false;

			$rows[] = array(
				'id'         => $issue->id,
				'post_id'    => $issue->post_id,
				'post_title' => (string) get_the_title( $issue->post_id ),
				'edit_url'   => (string) get_edit_post_link( $issue->post_id, 'raw' ),
				'rule_id'    => $issue->rule_id,
				'wcag_sc'    => $issue->wcag_sc,
				'severity'   => $issue->severity->value,
				'message'    => $issue->message,
				'note'       => $issue->note,
				// Named, not just numbered. A log that says "user 4" is a log
				// nobody can act on without another lookup.
				'by'         => false !== $user ? $user->display_name : __( 'Somebody no longer on this site', 'wowstudio-accessibility-kit' ),
				'at'         => $issue->updated_at,
			);
		}

		return new WP_REST_Response(
			array(
				'total'      => $issues->dismissed_count(),
				'dismissed'  => $rows,

				/*
				 * Site-wide decisions are listed here and nowhere else. Once
				 * taken, the findings they cover are set aside, so they are
				 * absent from every list of open findings — which would leave a
				 * judgement covering thirty-seven pages with no screen showing
				 * it and no way to withdraw it.
				 */
				'site_wide'  => $this->site_wide_decisions(),
				'may_decide' => current_user_can( 'edit_others_posts' ),
			)
		);
	}

	/**
	 * Describes the decisions taken for the whole site.
	 *
	 * @since 0.29.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function site_wide_decisions(): array {
		$registry = ( new Engine() )->registry();
		$rows     = array();

		foreach ( ( new DecisionRepository() )->site_wide() as $decision ) {
			$user = $decision->decided_by > 0 ? get_userdata( $decision->decided_by ) : false;
			$rule = $registry->descriptor( $decision->rule_id );

			$rows[] = array(
				'fingerprint' => $decision->fingerprint,
				'rule_id'     => $decision->rule_id,
				'rule_title'  => null === $rule ? $decision->rule_id : $rule->title(),
				'note'        => $decision->note,
				'by'          => false !== $user ? $user->display_name : __( 'Somebody no longer on this site', 'wowstudio-accessibility-kit' ),
				'at'          => $decision->updated_at,
			);
		}

		return $rows;
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
	 * Sets a finding aside wherever the same markup appears.
	 *
	 * The permission check is deliberately the reading one. What may actually be
	 * done here is settled inside SiteWideReview, which knows that this needs
	 * the right to edit other people's content — putting that in the route's
	 * permission callback would answer "may you reach this endpoint" with the
	 * answer to a different and larger question, and the refusal somebody sees
	 * would say 403 rather than what they can do instead.
	 *
	 * @since 0.29.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ignore_site_wide( WP_REST_Request $request ) {
		$result = ( new SiteWideReview() )->ignore(
			(string) $request->get_param( 'fingerprint' ),
			(string) $request->get_param( 'rule' ),
			(string) $request->get_param( 'note' ),
			get_current_user_id()
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Withdraws a site-wide judgement.
	 *
	 * @since 0.29.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reopen_site_wide( WP_REST_Request $request ) {
		$result = ( new SiteWideReview() )->reopen(
			(string) $request->get_param( 'fingerprint' ),
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
