<?php
/**
 * Running one page scan and recording what it found.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Db\ScanRepository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Fetches a page, checks it, and writes the outcome against an open scan row.
 *
 * Extracted from the REST controller when the queue arrived, because a bulk run
 * and a button in the admin have to do exactly the same thing. Two copies of
 * "scan a page" would drift, and the way they would drift is that one of them
 * would forget to record the coverage caveat — which is the field the whole
 * honesty story hangs on.
 *
 * The caller owns the scan row. This fills it in, marking it failed with a
 * reason when the page cannot be read, so a page that could not be scanned is
 * visible as a problem rather than silently absent from the history.
 *
 * @since 0.12.0
 *
 * Not final, unlike most classes here. It is handed to another object through
 * that object's constructor so the object can be tested without it, and sealing
 * it would make the injection decorative — a parameter nobody could ever pass
 * anything but the default to. The rule here is final by default, open where
 * something is meant to be substituted.
 */
class PageScanner {

	/**
	 * Scan storage.
	 *
	 * @since 0.12.0
	 * @var ScanRepository
	 */
	private ScanRepository $scans;

	/**
	 * Issue storage.
	 *
	 * @since 0.12.0
	 * @var IssueRepository
	 */
	private IssueRepository $issues;

	/**
	 * Where page markup comes from.
	 *
	 * @since 0.12.0
	 * @var PageSource
	 */
	private PageSource $source;

	/**
	 * The rule engine.
	 *
	 * @since 0.12.0
	 * @var Engine
	 */
	private Engine $engine;

	/**
	 * What the theme contributes, for content-only scans.
	 *
	 * @since 0.12.0
	 * @var TemplateScan
	 */
	private TemplateScan $templates;

	/**
	 * Constructor.
	 *
	 * @since 0.12.0
	 *
	 * @param ScanRepository|null  $scans  Scan storage.
	 * @param IssueRepository|null $issues Issue storage.
	 * @param PageSource|null      $source    Markup source.
	 * @param Engine|null          $engine    Rule engine.
	 * @param TemplateScan|null    $templates Theme profile source.
	 */
	public function __construct(
		?ScanRepository $scans = null,
		?IssueRepository $issues = null,
		?PageSource $source = null,
		?Engine $engine = null,
		?TemplateScan $templates = null
	) {
		$this->scans     = $scans ?? new ScanRepository();
		$this->issues    = $issues ?? new IssueRepository();
		$this->source    = $source ?? new PageSource();
		$this->engine    = $engine ?? new Engine();
		$this->templates = $templates ?? new TemplateScan();
	}

	/**
	 * Scans one post into an already-open scan row.
	 *
	 * @since 0.12.0
	 *
	 * @param int                $scan_id  Scan row to fill in.
	 * @param int                $post_id  Post to scan.
	 * @param FetchStrategy|null $strategy How to get the markup, or null for the whole page.
	 * @return array<string, mixed>|WP_Error What was found, or why nothing was.
	 */
	public function run( int $scan_id, int $post_id, ?FetchStrategy $strategy = null ) {
		$markup = $this->source->for_post( $post_id, $strategy );

		if ( is_wp_error( $markup ) ) {
			$this->scans->fail( $scan_id, $markup->get_error_message() );

			return $markup;
		}

		/*
		 * A whole page carries its own theme, so it needs no profile. A
		 * content-only scan does: without it the heading rules treat the first
		 * heading in the content as the first on the page, and miss the two
		 * commonest real faults. See decision F9.
		 */
		$profile = $markup->from_loopback ? null : $this->templates->profile();

		$result = $this->engine->scan( $markup->html, $profile );

		if ( null === $result ) {
			$error = new WP_Error(
				'wsak_unparseable',
				__( 'The page could not be parsed as HTML, so it could not be scanned.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 422 )
			);

			$this->scans->fail( $scan_id, $error->get_error_message() );

			return $error;
		}

		$rows = array();

		foreach ( $result->findings as $finding ) {
			$rows[] = $finding->to_row( $post_id );
		}

		$summary                    = $result->summary();
		$summary['from_loopback']   = $markup->from_loopback;
		$summary['coverage_notice'] = $markup->notice;
		$summary['strategy']        = $markup->from_loopback ? FetchStrategy::Loopback->value : FetchStrategy::Content->value;

		$this->issues->add_many( $scan_id, $rows );
		$this->issues->prune_superseded( $scan_id );
		$this->scans->complete( $scan_id, $result->score(), $summary );

		return array(
			'strategy'      => $markup->from_loopback ? FetchStrategy::Loopback->value : FetchStrategy::Content->value,
			'score'         => $result->score(),
			'summary'       => $summary,
			'full_page'     => $result->full_page,
			'from_loopback' => $markup->from_loopback,
			'notice'        => $markup->notice,
		);
	}
}
