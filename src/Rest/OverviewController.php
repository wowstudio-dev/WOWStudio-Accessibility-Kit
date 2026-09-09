<?php
/**
 * The site-wide picture, in one request.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Scanner\RuleRegistry;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WOWStudio\AccessibilityKit\Support\ScannableTypes;
use WP_REST_Request;
use WOWStudio\AccessibilityKit\Guidance\NextStep;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates what has already been scanned into one summary.
 *
 * The screens before this one each answered a question about a single page.
 * That is the right unit for fixing something, and the wrong unit for deciding
 * what to fix first, which needs the shape of the whole site: where the issues
 * are, what kind they are, and whether the last month of work moved anything.
 *
 * Everything here is counted from scans that have already run. Nothing is
 * projected, estimated, or filled in — a site with two scanned pages gets a
 * summary of two pages and says so. That matters more than usual on this
 * screen: a dashboard is exactly where a number starts to look like a verdict,
 * and the one number people will read as a verdict is the score.
 *
 * @since 0.16.0
 */
final class OverviewController implements Registrable {

	/**
	 * How many history points the trend returns at most.
	 *
	 * @since 0.16.0
	 * @var int
	 */
	private const HISTORY_LIMIT = 30;

	/**
	 * How many pages the "worst first" list returns.
	 *
	 * @since 0.16.0
	 * @var int
	 */
	private const WORST_LIMIT = 8;

	/**
	 * Registers the route.
	 *
	 * @since 0.16.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the route.
	 *
	 * @since 0.16.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/overview',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_overview' ),
					'permission_callback' => array( $this, 'can_read' ),
				),
			)
		);
	}

	/**
	 * Whether the caller may read the summary.
	 *
	 * @since 0.16.0
	 *
	 * @return bool
	 */
	public function can_read(): bool {
		return current_user_can( Capabilities::RUN_SCAN );
	}

	/**
	 * Returns the summary.
	 *
	 * @since 0.16.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function get_overview( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$data = array(
			'scanned'   => $this->scanned(),
			'score'     => $this->score(),
			'issues'    => $this->issue_counts(),
			'by_band'   => $this->count_by( 'severity' ),
			'by_rule'   => $this->by_rule(),
			'by_pass'   => $this->count_by( 'found_by' ),
			'detection' => $this->count_by( 'detection' ),
			'history'   => $this->history(),
			'worst'     => $this->worst_pages(),
			'coverage'  => $this->coverage(),
		);

		/*
		 * Worked out from the report rather than alongside it, so the suggestion
		 * can never disagree with the numbers shown next to it.
		 */
		$data['next_step'] = ( new NextStep() )->for_overview( $data );

		/**
		 * Filters the whole-site report before it reaches the interface.
		 *
		 * The seam an exporter attaches to. Everything the report screen shows
		 * passes through here, so a CSV or PDF writer can take exactly what is
		 * on screen rather than reassembling it from the tables and drifting
		 * out of step with what somebody is looking at.
		 *
		 * @since 0.26.0
		 *
		 * @param array<string, mixed> $data The report.
		 */
		$data = (array) apply_filters( 'wsak_report_data', $data );

		/**
		 * Filters the export formats offered for the report.
		 *
		 * Each entry is keyed by an identifier and carries at least a `label`.
		 * The free plugin registers none, because it offers no export, and the
		 * interface shows the control only when something has registered one —
		 * so nothing ever advertises a format that cannot be produced, and
		 * there is no locked button.
		 *
		 * @since 0.26.0
		 *
		 * @param array<string, array<string, string>> $formats Export formats.
		 * @param array<string, mixed>                 $data    The report.
		 */
		$data['exports'] = (array) apply_filters( 'wsak_report_formats', array(), $data );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * How many pages have ever been scanned, and how many exist.
	 *
	 * Both numbers, because "62 issues" means something different across four
	 * scanned pages than across four hundred.
	 *
	 * @since 0.16.0
	 *
	 * @return array<string, int>
	 */
	private function scanned(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate over the plugin's own tables; no core API covers it.
		$scanned = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT s.target_id)
			 FROM {$wpdb->prefix}wsak_scans s
			 INNER JOIN {$wpdb->posts} p ON p.ID = s.target_id AND p.post_status = 'publish'
			 WHERE s.scope = 'page' AND s.status = 'complete'"
		);

		$total = 0;

		// Only what the plugin would ever check. Counting everything published
		// made the denominator include content no scan will ever reach, so
		// "8 of 40 scanned" was measuring the site against a target that
		// cannot be met.
		foreach ( ScannableTypes::names() as $type ) {
			$counts = wp_count_posts( $type );
			$total += isset( $counts->publish ) ? (int) $counts->publish : 0;
		}

		return array(
			'pages'     => $scanned,
			'published' => $total,
		);
	}

	/**
	 * The mean of the most recent score for each scanned page.
	 *
	 * A mean rather than a total: a site is not more accessible for having
	 * fewer pages. Pages never scanned are absent rather than counted as
	 * perfect, which is why `scanned` travels alongside it.
	 *
	 * @since 0.16.0
	 *
	 * @return int|null Null when nothing has been scanned yet.
	 */
	private function score(): ?int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- As above.
		$mean = $wpdb->get_var(
			"SELECT AVG(latest.score) FROM (
				SELECT s.score
				FROM {$wpdb->prefix}wsak_scans s
				INNER JOIN {$wpdb->posts} p ON p.ID = s.target_id AND p.post_status = 'publish'
				INNER JOIN (
					SELECT target_id, MAX(id) AS id
					FROM {$wpdb->prefix}wsak_scans
					WHERE scope = 'page' AND status = 'complete'
					GROUP BY target_id
				) newest ON newest.id = s.id
			) AS latest"
		);

		return null === $mean ? null : (int) round( (float) $mean );
	}

	/**
	 * Open, fixed and set-aside counts.
	 *
	 * @since 0.16.0
	 *
	 * @return array<string, int>
	 */
	private function issue_counts(): array {
		$counts = $this->count_by( 'status' );
		$out    = array(
			'open'    => 0,
			'fixed'   => 0,
			'ignored' => 0,
		);

		foreach ( $counts as $row ) {
			if ( isset( $out[ $row['key'] ] ) ) {
				$out[ $row['key'] ] = $row['count'];
			}
		}

		$out['total'] = array_sum( $out );

		/*
		 * The open count, split by whether anything was actually settled.
		 *
		 * Rule 4 says every finding is tagged auto-detected or needs-manual-
		 * review, and every finding is — but the number people read was the sum
		 * of both, which quietly undoes the tagging. On the development site
		 * that headline said 387 where 153 were barriers we had found and 234
		 * were questions we could not answer. Reporting "I could not check this"
		 * in the same figure as "this is broken" overstates the second and
		 * devalues the first, and it is the number somebody screenshots.
		 */
		$open = $this->open_by_detection();

		$out['found']        = $open['auto'] ?? 0;
		$out['needs_a_look'] = $open['manual'] ?? 0;

		return $out;
	}

	/**
	 * Counts open findings by whether automation settled them.
	 *
	 * @since 0.29.0
	 *
	 * @return array<string, int>
	 */
	private function open_by_detection(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate over the plugin's own table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT detection, COUNT(*) AS n
				 FROM {$wpdb->prefix}wsak_issues
				 WHERE status = %s
				 GROUP BY detection",
				'open'
			)
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ (string) $row->detection ] = (int) $row->n;
		}

		return $out;
	}

	/**
	 * Counts open issues grouped by one column.
	 *
	 * The column is never caller-supplied — it is chosen from a fixed set at
	 * each call site, so it cannot carry anything into the query.
	 *
	 * @since 0.16.0
	 *
	 * @param string $column One of: severity, found_by, detection, status.
	 * @return array<int, array<string, mixed>>
	 */
	private function count_by( string $column ): array {
		global $wpdb;

		$allowed = array( 'severity', 'found_by', 'detection', 'status' );

		if ( ! in_array( $column, $allowed, true ) ) {
			return array();
		}

		$where = 'status' === $column ? '' : "WHERE status = 'open'";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is matched against a fixed allowlist directly above.
		$rows = $wpdb->get_results( "SELECT {$column} AS k, COUNT(*) AS n FROM {$wpdb->prefix}wsak_issues {$where} GROUP BY {$column} ORDER BY n DESC" );

		return array_map(
			static fn( $row ): array => array(
				'key'   => (string) $row->k,
				'count' => (int) $row->n,
			),
			(array) $rows
		);
	}

	/**
	 * The rules producing the most open findings.
	 *
	 * @since 0.16.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function by_rule(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate over the plugin's own tables.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rule_id, severity, COUNT(*) AS n
				 FROM {$wpdb->prefix}wsak_issues
				 WHERE status = 'open'
				 GROUP BY rule_id, severity
				 ORDER BY n DESC
				 LIMIT %d",
				self::WORST_LIMIT
			)
		);

		$titles = array();

		foreach ( RuleRegistry::with_defaults()->descriptors() as $rule ) {
			$titles[ $rule->id() ] = $rule->title();
		}

		return array_map(
			static fn( $row ): array => array(
				'rule'     => (string) $row->rule_id,
				'title'    => $titles[ $row->rule_id ] ?? (string) $row->rule_id,
				'severity' => (string) $row->severity,
				'count'    => (int) $row->n,
			),
			(array) $rows
		);
	}

	/**
	 * Score for each completed page scan, oldest first.
	 *
	 * This is a record of scans that happened, not a schedule — the plugin does
	 * not re-scan on its own, so the spacing between points is however often
	 * somebody pressed the button.
	 *
	 * @since 0.16.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function history(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- As above.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, score, finished_at
				 FROM {$wpdb->prefix}wsak_scans
				 WHERE scope = 'page' AND status = 'complete' AND finished_at IS NOT NULL
				 ORDER BY finished_at DESC
				 LIMIT %d",
				self::HISTORY_LIMIT
			)
		);

		$points = array_map(
			static fn( $row ): array => array(
				'id'    => (int) $row->id,
				'score' => (int) $row->score,
				'at'    => (string) $row->finished_at,
			),
			(array) $rows
		);

		return array_reverse( $points );
	}

	/**
	 * Scanned pages with the most open findings.
	 *
	 * @since 0.16.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function worst_pages(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- As above.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.post_id, COUNT(*) AS n
				 FROM {$wpdb->prefix}wsak_issues i
				 INNER JOIN {$wpdb->posts} p
				     ON p.ID = i.post_id AND p.post_status = 'publish'
				 WHERE i.status = 'open'
				 GROUP BY i.post_id
				 ORDER BY n DESC
				 LIMIT %d",
				self::WORST_LIMIT
			)
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$title = get_the_title( (int) $row->post_id );

			$out[] = array(
				'post_id' => (int) $row->post_id,
				'title'   => '' !== $title ? $title : __( '(no title)', 'wowstudio-accessibility-kit' ),
				'count'   => (int) $row->n,
			);
		}

		return $out;
	}

	/**
	 * How much of the rule set the last scans actually exercised.
	 *
	 * The browser rules only run when somebody opens the page view, so a site
	 * can be scanned thoroughly and still have five rules that never ran. Saying
	 * so is the difference between a score and a claim.
	 *
	 * @since 0.16.0
	 *
	 * @return array<string, int>
	 */
	private function coverage(): array {
		global $wpdb;

		$descriptors = RuleRegistry::with_defaults()->descriptors();
		$total       = count( $descriptors );
		$browser     = 0;

		foreach ( $descriptors as $rule ) {
			if ( 'browser' === $rule->pass()->value ) {
				++$browser;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- As above.
		$with_browser = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT s.target_id)
			 FROM {$wpdb->prefix}wsak_scans s
			 INNER JOIN {$wpdb->posts} p ON p.ID = s.target_id AND p.post_status = 'publish'
			 WHERE s.scope = 'page' AND s.status = 'complete' AND s.browser_pass = 'ran'"
		);

		return array(
			'rules_total'   => $total,
			'rules_browser' => $browser,
			'rules_server'  => $total - $browser,
			'pages_browser' => $with_browser,
		);
	}
}
