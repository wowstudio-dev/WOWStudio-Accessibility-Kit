<?php
/**
 * The command line.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Cli;

use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Db\ScanRepository;
use WOWStudio\AccessibilityKit\Scanner\PageScanner;
use WOWStudio\AccessibilityKit\Scanner\RuleRegistry;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\SiteFixes\SiteFixManager;
use WOWStudio\AccessibilityKit\Support\ScannableTypes;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Checks accessibility from the command line.
 *
 * The reason this exists, and the reason bulk is not held back from it: the
 * point of a command line is continuous integration and staging audits, and a
 * command that can only do one page at a time is useless for both. Somebody who
 * reaches for WP-CLI is going to put it in a pipeline, and a tool that cannot go
 * in a pipeline is not a command-line tool, it is a demonstration.
 *
 * Runs synchronously rather than through Action Scheduler. A queue exists so
 * that a browser request does not time out; a terminal has no such problem, and
 * a command that returned immediately and told you to wait for a background job
 * would be worse at the one thing it is for — telling you the answer.
 *
 * @since 0.24.0
 */
final class Command {

	/**
	 * Checks one or more pages.
	 *
	 * ## OPTIONS
	 *
	 * [<post-id>...]
	 * : Posts to check. Omit when using --all.
	 *
	 * [--all]
	 * : Check every published post of the given types.
	 *
	 * [--post-type=<types>]
	 * : Comma-separated post types to include with --all. Only types this
	 * plugin checks are accepted; by default that is page and post.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, json, csv, yaml, count. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp wsak scan 12 44
	 *     wp wsak scan --all --post-type=page
	 *     wp wsak scan --all --format=json > accessibility.json
	 *
	 * @since 0.24.0
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function scan( array $args, array $assoc_args ): void {
		$post_ids = array_map( 'intval', $args );

		if ( isset( $assoc_args['all'] ) ) {
			$scannable = ScannableTypes::names();

			$types = isset( $assoc_args['post-type'] )
				? array_map( 'trim', explode( ',', (string) $assoc_args['post-type'] ) )
				: $scannable;

			/*
			 * Refused rather than quietly dropped. WP_Query matches nothing for
			 * a post type it does not recognise, so silently filtering a typo
			 * out of this list would report "nothing to check" on a site full
			 * of content and leave the operator to work out why.
			 */
			$unsupported = array_values( array_diff( $types, $scannable ) );

			if ( array() !== $unsupported ) {
				WP_CLI::error(
					sprintf(
						'This plugin checks %1$s. It cannot check: %2$s.',
						implode( ', ', $scannable ),
						implode( ', ', $unsupported )
					)
				);
			}

			$post_ids = get_posts(
				array(
					'post_type'   => $types,
					'post_status' => 'publish',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			);
			$post_ids = array_map( 'intval', (array) $post_ids );
		}

		if ( array() === $post_ids ) {
			WP_CLI::error( 'Nothing to check. Give one or more post ids, or use --all.' );
		}

		// Ids given on the command line go through the same gate the REST
		// routes use, so `wp wsak scan 12` is not a way around it.
		$refused  = array_values( array_filter( $post_ids, static fn( int $id ): bool => ! ScannableTypes::covers( $id ) ) );
		$post_ids = array_values( array_filter( $post_ids, static fn( int $id ): bool => ScannableTypes::covers( $id ) ) );

		foreach ( $refused as $id ) {
			WP_CLI::warning(
				sprintf( '%d is not a post or page, so it was skipped.', $id )
			);
		}

		if ( array() === $post_ids ) {
			WP_CLI::error( 'None of that content is a post or page.' );
		}

		$scans    = new ScanRepository();
		$issues   = new IssueRepository();
		$scanner  = new PageScanner( $scans, $issues );
		$rows     = array();
		$failed   = 0;
		$progress = WP_CLI\Utils\make_progress_bar( 'Checking', count( $post_ids ) );

		foreach ( $post_ids as $post_id ) {
			$scan_id = $scans->start( ScanScope::Page, $post_id, 0 );

			if ( 0 === $scan_id ) {
				++$failed;
				$progress->tick();

				continue;
			}

			$outcome = $scanner->run( $scan_id, $post_id );

			if ( is_wp_error( $outcome ) ) {
				++$failed;
				$rows[] = array(
					'id'       => $post_id,
					'title'    => (string) get_the_title( $post_id ),
					'score'    => '-',
					'findings' => '-',
					'coverage' => $outcome->get_error_message(),
				);
				$progress->tick();

				continue;
			}

			$rows[] = array(
				'id'       => $post_id,
				'title'    => (string) get_the_title( $post_id ),
				'score'    => $outcome['score'],
				'findings' => (int) ( $outcome['summary']['total'] ?? 0 ),
				// Said on every row rather than once at the end, because a
				// content-only scan covers less than a full-page one and a
				// score that does not say so invites being compared with one
				// that means something different.
				'coverage' => $outcome['full_page'] ? 'full page' : 'content only',
			);

			$progress->tick();
		}

		$progress->finish();

		WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$rows,
			array( 'id', 'title', 'score', 'findings', 'coverage' )
		);

		if ( $failed > 0 ) {
			WP_CLI::warning( sprintf( '%d page(s) could not be checked.', $failed ) );
		}
	}

	/**
	 * Lists findings from the most recent check of a page.
	 *
	 * ## OPTIONS
	 *
	 * [--post=<id>]
	 * : Only findings on this page.
	 *
	 * [--severity=<severity>]
	 * : critical, serious, moderate or minor.
	 *
	 * [--status=<status>]
	 * : open, fixed or ignored. Default: open
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, json, csv, yaml, count. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp wsak issues --post=12
	 *     wp wsak issues --severity=critical --format=csv
	 *
	 * @since 0.24.0
	 *
	 * @subcommand issues
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function issues( array $args, array $assoc_args ): void {
		unset( $args );

		$scans  = new ScanRepository();
		$issues = new IssueRepository();
		$rows   = array();

		$post_ids = isset( $assoc_args['post'] )
			? array( (int) $assoc_args['post'] )
			: array_map(
				static fn( $scan ): int => $scan->target_id,
				$scans->recent( 100 )
			);

		foreach ( array_unique( array_filter( $post_ids ) ) as $post_id ) {
			$scan = $scans->latest_for_post( $post_id );

			if ( null === $scan ) {
				continue;
			}

			$found = $issues->find_by_scan(
				$scan->id,
				array(
					'limit' => 500,
				)
			);

			foreach ( $found as $issue ) {
				if ( isset( $assoc_args['severity'] ) && $issue->severity->value !== $assoc_args['severity'] ) {
					continue;
				}

				$status = (string) ( $assoc_args['status'] ?? 'open' );

				if ( $issue->status->value !== $status ) {
					continue;
				}

				$rows[] = array(
					'post'     => $post_id,
					'rule'     => $issue->rule_id,
					'severity' => $issue->severity->value,
					'wcag'     => $issue->wcag_sc,
					'detected' => $issue->detection->value,
					'message'  => $issue->message,
				);
			}
		}

		if ( array() === $rows ) {
			WP_CLI::success( 'No findings match. That is not the same as the pages being accessible: automated checks cover part of WCAG.' );

			return;
		}

		WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$rows,
			array( 'post', 'rule', 'severity', 'wcag', 'detected', 'message' )
		);
	}

	/**
	 * Lists every check this plugin runs.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, json, csv, yaml, count. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp wsak checks
	 *     wp wsak checks --format=json
	 *
	 * @since 0.24.0
	 *
	 * @subcommand checks
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function checks( array $args, array $assoc_args ): void {
		unset( $args );

		$rows = array();

		foreach ( RuleRegistry::with_defaults()->coverage() as $rule ) {
			$rows[] = array(
				'id'       => $rule['id'],
				'wcag'     => $rule['wcag_sc'],
				'severity' => $rule['severity'],
				'detected' => $rule['detection'],
				'pass'     => $rule['pass'],
				'title'    => $rule['title'],
			);
		}

		WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$rows,
			array( 'id', 'wcag', 'severity', 'detected', 'pass', 'title' )
		);
	}

	/**
	 * Lists the site-wide fixes, or switches one on or off.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : The fix to change. Omit to list them all.
	 *
	 * [--on]
	 * : Switch it on.
	 *
	 * [--off]
	 * : Switch it off.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, json, csv, yaml, count. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp wsak fixes
	 *     wp wsak fixes skip-link --on
	 *
	 * @since 0.24.0
	 *
	 * @subcommand fixes
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function fixes( array $args, array $assoc_args ): void {
		$manager = new SiteFixManager();

		if ( array() !== $args ) {
			$id = (string) $args[0];

			if ( ! isset( $assoc_args['on'] ) && ! isset( $assoc_args['off'] ) ) {
				WP_CLI::error( 'Say which way: --on or --off.' );
			}

			if ( ! $manager->set( $id, isset( $assoc_args['on'] ) ) ) {
				WP_CLI::error( sprintf( 'There is no fix called "%s".', $id ) );
			}

			WP_CLI::success( sprintf( '%s is now %s.', $id, isset( $assoc_args['on'] ) ? 'on' : 'off' ) );

			return;
		}

		$rows = array();

		foreach ( $manager->to_array() as $fix ) {
			$rows[] = array(
				'id'      => $fix['id'],
				'on'      => $fix['enabled'] ? 'yes' : 'no',
				'browser' => $fix['in_browser'] ? 'yes' : 'no',
				'title'   => $fix['title'],
			);
		}

		WP_CLI\Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$rows,
			array( 'id', 'on', 'browser', 'title' )
		);
	}
}
