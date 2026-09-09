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
use WOWStudio\AccessibilityKit\Scanner\BrowserPassStatus;
use WOWStudio\AccessibilityKit\Scanner\BrowserRules;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\ScanPass;
use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Scanner\PageScanner;
use WOWStudio\AccessibilityKit\Scanner\Preview;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WOWStudio\AccessibilityKit\Support\ScannableTypes;
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
							'minimum'           => 1,
							'description'       => __( 'The post or page to scan.', 'wowstudio-accessibility-kit' ),
							'sanitize_callback' => 'absint',
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
			'/scans/(?P<id>\d+)/browser',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'record_browser_pass' ),
					'permission_callback' => array( $this, 'can_scan' ),
					'args'                => array(
						'id'       => array(
							'required'          => true,
							'type'              => 'integer',
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'status'   => array(
							'required'    => true,
							'type'        => 'string',
							'enum'        => array( 'ran', 'blocked' ),
							'description' => __( 'Whether the browser pass completed.', 'wowstudio-accessibility-kit' ),
						),
						'findings' => array(
							'type'        => 'array',
							'default'     => array(),
							'description' => __( 'Findings the browser pass produced.', 'wowstudio-accessibility-kit' ),
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
	 * Checks that the caller may read the post they asked to scan.
	 *
	 * Checks readability as well as existence: the capability to scan does not
	 * imply the capability to read every post on the site, and a scan response
	 * echoes back the markup of the page it scanned.
	 *
	 * Called from the handler rather than wired up as a validate_callback, and
	 * that placement is the whole point. WordPress validates arguments *before*
	 * it runs permission_callback, so this check sitting in a validate_callback
	 * answered anonymous callers too — and answering "that content could not be
	 * found" for one id while answering "you do not have permission" for another
	 * turned the route into an oracle for which post ids exist, drafts and
	 * private posts included. Running it after can_scan() means only a caller
	 * already entitled to scan can tell the two apart.
	 *
	 * @since 0.3.0
	 * @since 0.9.0 Moved out of the argument validator, which runs too early.
	 *
	 * @param int $post_id Post the caller asked for.
	 * @return WP_Error|null Error to return, or null when access is fine.
	 */
	private function post_access_error( int $post_id ): ?WP_Error {
		if ( null === get_post( $post_id ) ) {
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

		/*
		 * Last, and after both checks above, for the same reason they are
		 * ordered the way they are: answering "that type is not scanned" to a
		 * caller who may not read the post would tell them the post exists.
		 */
		if ( ! ScannableTypes::covers( $post_id ) ) {
			return new WP_Error(
				'wsak_unsupported_type',
				__( 'This plugin checks posts and pages. Templates, patterns and other content types are not pages a visitor can open, so a scan of one says very little about what anybody would meet.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 400 )
			);
		}

		return null;
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

		$denied = $this->post_access_error( $post_id );

		if ( $denied instanceof WP_Error ) {
			return $denied;
		}

		$scans  = new ScanRepository();
		$issues = new IssueRepository();

		$scan_id = $scans->start( ScanScope::Page, $post_id, get_current_user_id() );

		if ( 0 === $scan_id ) {
			return new WP_Error(
				'wsak_scan_not_started',
				__( 'The scan could not be recorded. Check that the plugin tables exist.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 500 )
			);
		}

		// The scan itself lives in PageScanner, because a queued bulk run has to
		// do exactly this and two copies would drift.
		$outcome = ( new PageScanner( $scans, $issues ) )->run( $scan_id, $post_id );

		if ( is_wp_error( $outcome ) ) {
			return $outcome;
		}

		return new WP_REST_Response(
			array(
				'scan_id'         => $scan_id,
				'post_id'         => $post_id,
				'post_title'      => get_the_title( $post_id ),
				'preview_url'     => Preview::url_for( $post_id ),
				// Findings can only be placed on the live page when the scan
				// read that same page. A content-only fallback produces
				// selectors relative to a fragment, which resolve to nothing.
				'placeable'       => $outcome['from_loopback'] && '' !== Preview::url_for( $post_id ),
				'score'           => $outcome['score'],
				'summary'         => $outcome['summary'],
				'full_page'       => $outcome['full_page'],
				// Surfaced separately so the UI cannot present a reduced scan as
				// a complete one without deliberately ignoring this field.
				'coverage_notice' => $outcome['notice'],
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
				'preview_url'     => $scan->target_id > 0 ? Preview::url_for( $scan->target_id ) : '',
				'browser_pass'    => $scan->browser_pass->value,
				'placeable'       => ! empty( $scan->summary['from_loopback'] ) && $scan->target_id > 0,
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
			'post_type'        => ScannableTypes::names(),
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
	 * Records what the browser pass found.
	 *
	 * The browser is an untrusted caller. It ran our code, but it ran it on the
	 * user's machine, on a page we do not control, and what arrives here is just
	 * a request. So it may report two things and nothing else: which of our
	 * checks failed, and where. Everything that gives a finding weight — its
	 * severity, its success criterion, whether it counts as settled — is decided
	 * here, from the rule declaration.
	 *
	 * A finding naming a rule we did not publish is dropped rather than stored,
	 * so the pass cannot introduce checks that never appeared in the coverage
	 * panel. And `certain` may only move a finding towards needing a person: the
	 * browser can say "I could not tell", never "trust me, this is a failure".
	 *
	 * @since 0.10.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function record_browser_pass( WP_REST_Request $request ) {
		$scan_id = absint( $request->get_param( 'id' ) );
		$scans   = new ScanRepository();
		$scan    = $scans->find( $scan_id );

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

		$status = BrowserPassStatus::tryFrom( (string) $request->get_param( 'status' ) )
			?? BrowserPassStatus::Blocked;

		$issues = new IssueRepository();

		if ( BrowserPassStatus::Ran !== $status ) {
			// Nothing was checked, so nothing is stored and the scan records
			// that its coverage fell short.
			$scans->record_browser_pass( $scan_id, $status );

			return new WP_REST_Response(
				array(
					'browser_pass' => $status->value,
					'stored'       => 0,
					'dropped'      => 0,
					'issues'       => $this->present_issues( $scan_id, $issues ),
				),
				200
			);
		}

		$declared = array();

		foreach ( BrowserRules::all() as $rule ) {
			$declared[ $rule->id() ] = $rule;
		}

		$rows    = array();
		$dropped = 0;

		foreach ( (array) $request->get_param( 'findings' ) as $reported ) {
			$rule = is_array( $reported )
				? ( $declared[ (string) ( $reported['rule_id'] ?? '' ) ] ?? null )
				: null;

			if ( null === $rule ) {
				++$dropped;

				continue;
			}

			$rows[] = array(
				'post_id'   => $scan->target_id,
				'rule_id'   => $rule->id(),
				'wcag_sc'   => $rule->wcag_sc(),
				'severity'  => $rule->severity(),
				'detection' => empty( $reported['certain'] ) ? Detection::Manual : $rule->detection(),
				'found_by'  => ScanPass::Browser,
				'selector'  => sanitize_text_field( (string) ( $reported['selector'] ?? '' ) ),
				'context'   => wp_kses_post( (string) ( $reported['context'] ?? '' ) ),
				'message'   => sanitize_text_field( (string) ( $reported['message'] ?? '' ) ),
			);
		}

		$issues->add_many( $scan_id, $rows );

		$score = $this->rescore( $scan_id, $issues );

		$scans->record_browser_pass( $scan_id, $status, $score );

		/*
		 * The score and the counts go back with the findings, not just the
		 * findings.
		 *
		 * Without them the screen showed the server pass's verdict above the
		 * browser pass's results: a page whose content-only scan found nothing
		 * read "100 / 100 — 0 issues detected automatically" directly above a
		 * list of contrast failures. Both halves were honestly computed and the
		 * two were about different passes, which is not a distinction anybody
		 * should have to infer from a screen contradicting itself.
		 */
		return new WP_REST_Response(
			array(
				'browser_pass' => $status->value,
				'stored'       => count( $rows ),
				'dropped'      => $dropped,
				'score'        => $score,
				'summary'      => $this->summary_of( $scan_id, $issues ),
				'issues'       => $this->present_issues( $scan_id, $issues ),
			),
			200
		);
	}

	/**
	 * Counts a scan's findings the way the score card reads them.
	 *
	 * @since 0.29.0
	 *
	 * @param int             $scan_id Scan to count.
	 * @param IssueRepository $issues  Repository to read from.
	 * @return array<string, array<string, int>>
	 */
	private function summary_of( int $scan_id, IssueRepository $issues ): array {
		return array(
			'by_detection' => $issues->count_by( $scan_id, 'detection' ),
			'by_severity'  => $issues->count_by( $scan_id, 'severity' ),
		);
	}

	/**
	 * Recalculates a scan's score across both passes.
	 *
	 * The server pass scored the scan before the browser pass had reported, so
	 * the number is revised once a second engine has been over the same page.
	 * Only settled findings count, exactly as before: something flagged for a
	 * person to look at is not yet known to be a fault, and scoring it as one
	 * would report a page as worse than we actually know it to be.
	 *
	 * @since 0.10.0
	 *
	 * @param int             $scan_id Scan to rescore.
	 * @param IssueRepository $issues  Issue store.
	 * @return int
	 */
	private function rescore( int $scan_id, IssueRepository $issues ): int {
		$penalty = array(
			'critical' => 10,
			'serious'  => 6,
			'moderate' => 3,
			'minor'    => 1,
		);

		$total = 0;

		foreach ( $issues->find_by_scan( $scan_id ) as $issue ) {
			if ( Detection::Auto !== $issue->detection ) {
				continue;
			}

			$total += $penalty[ $issue->severity->value ] ?? 1;
		}

		return max( 0, 100 - $total );
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
		return ( new IssuePresenter() )->present( $issues->find_by_scan( $scan_id ) );
	}
}
