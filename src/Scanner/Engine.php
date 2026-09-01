<?php
/**
 * Scan engine.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the registered rules over a page.
 *
 * @since 0.3.0
 */
final class Engine {

	/**
	 * Rules to run.
	 *
	 * @since 0.3.0
	 * @var RuleRegistry
	 */
	private RuleRegistry $registry;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param RuleRegistry|null $registry Rules to run, or null for the defaults.
	 */
	public function __construct( ?RuleRegistry $registry = null ) {
		$this->registry = $registry ?? RuleRegistry::with_defaults();
	}

	/**
	 * Returns the registry in use.
	 *
	 * @since 0.3.0
	 *
	 * @return RuleRegistry
	 */
	public function registry(): RuleRegistry {
		return $this->registry;
	}

	/**
	 * Scans an HTML string.
	 *
	 * Returns null only when the markup cannot be parsed at all.
	 *
	 * @since 0.3.0
	 *
	 * @param string               $html    Page or fragment markup.
	 * @param TemplateProfile|null $profile What the theme puts around it, when known.
	 * @return Result|null
	 */
	public function scan( string $html, ?TemplateProfile $profile = null ): ?Result {
		$document = Document::from_html( $html, $profile );

		if ( null === $document ) {
			return null;
		}

		$findings = array();
		$ran      = 0;

		foreach ( $this->registry->all() as $rule ) {
			try {
				$produced = $rule->evaluate( $document );
				++$ran;
			} catch ( Throwable $error ) {
				/*
				 * One rule failing must not lose the findings of the other
				 * eleven. A site with unusual markup should get a partial scan
				 * and a log line, not an error page.
				 */
				$this->log_rule_failure( $rule, $error );

				continue;
			}

			foreach ( $produced as $finding ) {
				if ( $finding instanceof Finding ) {
					$findings[] = $finding;
				}
			}
		}

		return new Result( $findings, $document->is_full_page(), $ran );
	}

	/**
	 * Records a rule that threw.
	 *
	 * @since 0.3.0
	 *
	 * @param Rule      $rule  Rule that failed.
	 * @param Throwable $error What it threw.
	 * @return void
	 */
	private function log_rule_failure( Rule $rule, Throwable $error ): void {
		/**
		 * Fires when a rule throws during a scan.
		 *
		 * @since 0.3.0
		 *
		 * @param Rule      $rule  Rule that failed.
		 * @param Throwable $error What it threw.
		 */
		do_action( 'wsak_rule_failed', $rule, $error );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Diagnostics for a failing rule, only when debugging is on.
			error_log( sprintf( 'WSAK rule "%s" failed: %s', $rule->id(), $error->getMessage() ) );
		}
	}
}
