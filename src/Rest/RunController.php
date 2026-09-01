<?php
/**
 * Bulk scanning REST routes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Db\ContentIndex;
use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Db\ScanRepository;
use WOWStudio\AccessibilityKit\Jobs\BulkScan;
use WOWStudio\AccessibilityKit\Remediation\ThemeTriage;
use WOWStudio\AccessibilityKit\Remediation\WorkList;
use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Scanner\LoopbackProbe;
use WOWStudio\AccessibilityKit\Scanner\Preview;
use WOWStudio\AccessibilityKit\Scanner\ScanCoverage;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\Scanner\ScanStatus;
use WOWStudio\AccessibilityKit\Scanner\TemplateScan;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WOWStudio\AccessibilityKit\Support\Plan;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Listing content, starting runs over it, and reading how they went.
 *
 * @since 0.13.0
 */
final class RunController implements Registrable {

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
			'/content/types',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'types' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/content',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'content' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'type'     => array(
							'type'              => 'string',
							'default'           => 'page',
							'sanitize_callback' => 'sanitize_key',
						),
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 50,
							'sanitize_callback' => 'absint',
						),
						'search'   => array(
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
			'/runs',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start' ),
					'permission_callback' => array( $this, 'can_scan' ),
					'args'                => array(
						'post_ids' => array(
							'required' => true,
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/runs/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'cancel' ),
					'permission_callback' => array( $this, 'can_scan' ),
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
			'/theme',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'theme' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'check_theme' ),
					'permission_callback' => array( $this, 'can_scan' ),
				),
			)
		);
	}

	/**
	 * Checks the scan capability.
	 *
	 * @since 0.13.0
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
	 * Checks the reports capability.
	 *
	 * @since 0.13.0
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
	 * Lists post types with something in them.
	 *
	 * @since 0.13.0
	 *
	 * @return WP_REST_Response
	 */
	public function types(): WP_REST_Response {
		$probe = new LoopbackProbe();

		return new WP_REST_Response(
			array(
				'types'    => ( new ContentIndex() )->types(),
				// Sent with the list rather than discovered when a run stalls,
				// so somebody is told what coverage they will get before they
				// press the button rather than after. Never triggers a request
				// of its own: an unknown answer stays unknown here.
				'loopback' => $probe->known(),
			)
		);
	}

	/**
	 * Lists one page of content.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function content( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			( new ContentIndex() )->items(
				(string) $request->get_param( 'type' ),
				absint( $request->get_param( 'page' ) ),
				absint( $request->get_param( 'per_page' ) ),
				(string) $request->get_param( 'search' )
			)
		);
	}

	/**
	 * Starts a run over the selected content.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function start( WP_REST_Request $request ) {
		// The line from F1: one page is free, many pages is paid. Enforced here
		// rather than in the interface, because a limit that only exists in the
		// interface is not a limit.
		if ( ! ( new Plan() )->is_pro() ) {
			return ( new Plan() )->refuse( __( 'Checking many pages at once', 'wowstudio-accessibility-kit' ) );
		}

		$requested = (array) $request->get_param( 'post_ids' );
		$allowed   = array();

		// Filtered against what this user may actually read, one at a time. A
		// run is started by somebody who can scan, which is not the same as
		// somebody who can see every post on the site.
		foreach ( $requested as $post_id ) {
			$post_id = absint( $post_id );

			if ( $post_id > 0 && current_user_can( 'read_post', $post_id ) ) {
				$allowed[] = $post_id;
			}
		}

		if ( array() === $allowed ) {
			return new WP_Error(
				'wsak_nothing_selected',
				__( 'None of the selected content could be read with your account, so there is nothing to scan.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 400 )
			);
		}

		$run = ( new BulkScan() )->start( $allowed, get_current_user_id() );

		if ( is_wp_error( $run ) ) {
			return $run;
		}

		return new WP_REST_Response( $this->describe_run( $run ), 201 );
	}

	/**
	 * Returns a run's progress and results.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$run_id = absint( $request->get_param( 'id' ) );
		$run    = ( new ScanRepository() )->find( $run_id );

		if ( null === $run || ScanScope::Site !== $run->scope ) {
			return new WP_Error(
				'wsak_unknown_run',
				__( 'That run could not be found.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $this->describe_run( $run_id ) );
	}

	/**
	 * Stops a run.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel( WP_REST_Request $request ) {
		$run_id   = absint( $request->get_param( 'id' ) );
		$progress = ( new BulkScan() )->cancel( $run_id );

		if ( is_wp_error( $progress ) ) {
			return $progress;
		}

		return new WP_REST_Response( $this->describe_run( $run_id ) );
	}

	/**
	 * Returns what is known about the theme.
	 *
	 * @since 0.13.0
	 *
	 * @return WP_REST_Response
	 */
	public function theme(): WP_REST_Response {
		$templates = new TemplateScan();
		$profile   = $templates->profile();
		$scans     = new ScanRepository();
		$issues    = new IssueRepository();
		$latest    = $scans->latest_of_scope( ScanScope::Template );

		$findings = null === $latest ? array() : $this->triaged( $latest->id, $issues );

		return new WP_REST_Response(
			array(
				'profile'  => $profile->to_array(),
				'checked'  => $profile->known,
				'scan_id'  => null === $latest ? 0 : $latest->id,
				'theme'    => (string) wp_get_theme()->get( 'Name' ),
				'findings' => $findings,
				// Written here rather than assembled in the interface, so what
				// gets handed to a developer is the same text every time and
				// carries the same caveats.
				'handover' => $this->handover( $findings ),
			)
		);
	}

	/**
	 * Checks the theme.
	 *
	 * @since 0.13.0
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function check_theme() {
		$outcome = ( new TemplateScan() )->run( get_current_user_id() );

		if ( is_wp_error( $outcome ) ) {
			return $outcome;
		}

		return $this->theme();
	}

	/**
	 * Assembles a run's progress and its per-page results.
	 *
	 * @since 0.13.0
	 *
	 * @param int $run_id Run to describe.
	 * @return array<string, mixed>
	 */
	private function describe_run( int $run_id ): array {
		$bulk   = new BulkScan();
		$scans  = new ScanRepository();
		$issues = new IssueRepository();
		$run    = $scans->find( $run_id );

		$pages = array();

		foreach ( $scans->children( $run_id ) as $child ) {
			$coverage = ScanCoverage::of( $child );

			$pages[] = array(
				'scan_id'        => $child->id,
				'post_id'        => $child->target_id,
				'title'          => get_the_title( $child->target_id ),
				'edit_url'       => (string) get_edit_post_link( $child->target_id, 'raw' ),
				// Needed by the rendered queue, which frames each page in the
				// administrator's own browser to run the checks a background
				// job cannot. Empty when the content has no public address, and
				// the queue skips those rather than framing nothing.
				'preview_url'    => Preview::url_for( $child->target_id ),
				'status'         => $child->status->value,
				'status_label'   => $child->status->label(),
				'score'          => $child->score,
				'coverage'       => $coverage->value,
				'coverage_label' => $coverage->label(),
				// Why a page was skipped, kept with the page rather than in a
				// log nobody had switched on at the time.
				'error'          => (string) ( $child->summary['error'] ?? '' ),
				'findings'       => ScanStatus::Complete === $child->status
					? $this->present( $child->id, $issues )
					: array(),
			);
		}

		return array(
			'run_id'   => $run_id,
			'status'   => null === $run ? ScanStatus::Failed->value : $run->status->value,
			'progress' => $bulk->progress( $run_id )->to_array(),
			'pages'    => $pages,
		);
	}

	/**
	 * Shapes theme findings, each sorted by what would actually fix it.
	 *
	 * @since 0.14.0
	 *
	 * @param int             $scan_id Scan to read.
	 * @param IssueRepository $issues  Issue storage.
	 * @return array<int, array<string, mixed>>
	 */
	private function triaged( int $scan_id, IssueRepository $issues ): array {
		$registry = ( new Engine() )->registry();
		$triage   = new ThemeTriage();
		$rows     = array();

		// Indexed once. Looking each row's issue up by walking the whole set
		// would be quadratic, on a list whose whole point is that a theme fault
		// appears once rather than four hundred times — but a page builder site
		// can still produce plenty.
		$by_id = array();

		foreach ( $issues->find_by_scan( $scan_id ) as $issue ) {
			$by_id[ $issue->id ] = $issue;
		}

		foreach ( $this->present( $scan_id, $issues ) as $row ) {
			$issue = $by_id[ $row['id'] ] ?? null;

			$row['triage'] = null === $issue
				? array()
				: $triage->triage( $issue, $registry->descriptor( $issue->rule_id ) );

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Writes the document somebody hands to whoever maintains the theme.
	 *
	 * Plain text on purpose. It gets pasted into an email, a ticket, or a chat
	 * window, and every one of those would mangle anything cleverer.
	 *
	 * @since 0.14.0
	 *
	 * @param array<int, array<string, mixed>> $findings Triaged findings.
	 * @return string
	 */
	private function handover( array $findings ): string {
		$theme = (string) wp_get_theme()->get( 'Name' );
		$lines = array(
			sprintf(
				/* translators: %s: theme name. */
				__( 'Accessibility changes needed in the %s theme', 'wowstudio-accessibility-kit' ),
				$theme
			),
			'',
			__( 'These are in theme files rather than in page content, so they cannot be changed from the WordPress admin. Each one lists what a person loses because of it, and the smallest change that would fix it.', 'wowstudio-accessibility-kit' ),
			'',
			__( 'Found by WOWStudio Accessibility Kit. Automated checks cover part of WCAG, not all of it — this list is a starting point rather than a complete audit.', 'wowstudio-accessibility-kit' ),
			'',
			str_repeat( '-', 60 ),
			'',
		);

		$handoffs = 0;

		foreach ( $findings as $finding ) {
			if ( ( $finding['triage']['tier'] ?? '' ) !== ThemeTriage::TIER_HANDOFF ) {
				continue;
			}

			++$handoffs;

			$lines[] = sprintf( '%d. %s', $handoffs, $finding['rule_title'] );
			$lines[] = '   ' . __( 'Why it matters:', 'wowstudio-accessibility-kit' ) . ' ' . $finding['consequence'];
			$lines[] = '   ' . __( 'Where:', 'wowstudio-accessibility-kit' ) . ' ' . $finding['selector'];

			if ( '' !== trim( (string) $finding['context'] ) ) {
				$lines[] = '   ' . __( 'Current markup:', 'wowstudio-accessibility-kit' ) . ' ' . trim( $finding['context'] );
			}

			if ( '' !== trim( (string) ( $finding['triage']['snippet'] ?? '' ) ) ) {
				$lines[] = '   ' . __( 'Change to:', 'wowstudio-accessibility-kit' ) . ' ' . $finding['triage']['snippet'];
			}

			$lines[] = '   ' . __( 'WCAG:', 'wowstudio-accessibility-kit' ) . ' ' . $finding['wcag_sc'];
			$lines[] = '';
		}

		if ( 0 === $handoffs ) {
			return '';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Shapes one scan's findings for the interface.
	 *
	 * @since 0.13.0
	 *
	 * @param int             $scan_id Scan to read.
	 * @param IssueRepository $issues  Issue storage.
	 * @return array<int, array<string, mixed>>
	 */
	private function present( int $scan_id, IssueRepository $issues ): array {
		$registry = ( new Engine() )->registry();
		$work     = new WorkList( $registry->descriptors() );
		$rows     = array();

		foreach ( $issues->find_by_scan( $scan_id ) as $issue ) {
			$rule = $registry->descriptor( $issue->rule_id );

			$rows[] = array(
				'id'              => $issue->id,
				'rule_id'         => $issue->rule_id,
				'rule_title'      => null === $rule ? $issue->rule_id : $rule->title(),
				'consequence'     => null === $rule ? '' : $rule->consequence(),
				'band'            => $work->band_for( $issue->rule_id )->value,
				'fix'             => null === $rule ? array() : $rule->fix_plan()->to_array(),
				'wcag_sc'         => $issue->wcag_sc,
				'severity'        => $issue->severity->value,
				'severity_label'  => $issue->severity->label(),
				'detection'       => $issue->detection->value,
				'detection_label' => $issue->detection->label(),
				'status'          => $issue->status->value,
				'note'            => $issue->note,
				'message'         => $issue->message,
				'selector'        => $issue->selector,
				'context'         => $issue->context,
			);
		}

		return $work->order( $rows );
	}
}
