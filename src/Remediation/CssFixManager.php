<?php
/**
 * Validating and storing CSS fixes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WOWStudio\AccessibilityKit\Scanner\ScanPass;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a reviewed proposal into a rule in the site's Additional CSS.
 *
 * The interface composes a proposal by measuring the live page, and a person
 * approves it. Nothing about that survives the trip: the selector, the
 * properties and the values are all re-checked here against what the named rule
 * is permitted to do, because the request arrives from a browser and a browser
 * is not a trusted narrator of its own intentions.
 *
 * @since 0.11.0
 */
final class CssFixManager {

	/**
	 * Issue storage.
	 *
	 * @since 0.11.0
	 * @var IssueRepository
	 */
	private IssueRepository $issues;

	/**
	 * The managed block of Additional CSS.
	 *
	 * @since 0.11.0
	 * @var CustomCss
	 */
	private CustomCss $css;

	/**
	 * Constructor.
	 *
	 * @since 0.11.0
	 *
	 * @param IssueRepository|null $issues Issue storage.
	 * @param CustomCss|null       $css    Managed stylesheet block.
	 */
	public function __construct( ?IssueRepository $issues = null, ?CustomCss $css = null ) {
		$this->issues = $issues ?? new IssueRepository();
		$this->css    = $css ?? new CustomCss();
	}

	/**
	 * Returns the rules currently in force, keyed by the issue each answers.
	 *
	 * @since 0.11.0
	 *
	 * @return array<int, string>
	 */
	public function rules(): array {
		return $this->css->rules();
	}

	/**
	 * Writes a reviewed rule into the site's stylesheet.
	 *
	 * @since 0.11.0
	 *
	 * @param int                                                $issue_id     Finding being answered.
	 * @param string                                             $selector     Selector to apply it to.
	 * @param array<int, array{property: string, value: string}> $declarations Properties and values.
	 * @return array<string, mixed>|WP_Error
	 */
	public function apply( int $issue_id, string $selector, array $declarations ) {
		$issue = $this->issues->find( $issue_id );

		if ( null === $issue ) {
			return new WP_Error(
				'wsak_unknown_issue',
				__( 'That issue could not be found.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		// A CSS fix answers something seen on the rendered page. A finding from
		// the markup pass has an override waiting for it, and routing it here
		// instead would paper over a markup problem with a style rule — which
		// changes what the page looks like without changing what it says.
		if ( ScanPass::Browser !== $issue->found_by ) {
			return new WP_Error(
				'wsak_not_a_css_issue',
				__( 'This finding came from reading your markup, not from the page, so it is fixed by correcting the markup rather than by adding a style rule.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 422 )
			);
		}

		if ( ! CssRules::fixable( $issue->rule_id ) ) {
			return new WP_Error(
				'wsak_no_css_fix',
				__( 'There is no style rule that would answer this finding.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 422 )
			);
		}

		$selector = trim( $selector );

		if ( ! CssRules::selector_is_safe( $selector ) ) {
			return new WP_Error(
				'wsak_unsafe_selector',
				__( 'That selector cannot be written into a stylesheet safely. Use element, class, id and attribute selectors only.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 400 )
			);
		}

		$body = $this->declarations( $issue->rule_id, $declarations );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$rule = $selector . " {\n" . $body . "\n}";

		if ( ! $this->css->put( $issue->id, $rule ) ) {
			return new WP_Error(
				'wsak_css_not_saved',
				__( 'The rule could not be saved to your Additional CSS.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 500 )
			);
		}

		$this->issues->set_status( $issue->id, IssueStatus::Fixed, '' );

		/**
		 * Fires when a CSS rule is written for a finding.
		 *
		 * @since 0.11.0
		 *
		 * @param int    $issue_id Finding answered.
		 * @param string $rule     The rule written.
		 */
		do_action( 'wsak_css_fix_applied', $issue->id, $rule );

		return array(
			'issue_id' => $issue->id,
			'css'      => $rule,
			'theme'    => $this->theme_name(),
		);
	}

	/**
	 * Removes the rule answering one finding and reopens it.
	 *
	 * @since 0.11.0
	 *
	 * @param int $issue_id Finding whose rule should go.
	 * @return array<string, mixed>|WP_Error
	 */
	public function revert( int $issue_id ) {
		$rules = $this->css->rules();

		if ( ! isset( $rules[ $issue_id ] ) ) {
			return new WP_Error(
				'wsak_no_such_rule',
				__( 'There is no rule stored for that finding. It may already have been removed, or edited by hand in the Customiser.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->css->remove( $issue_id ) ) {
			return new WP_Error(
				'wsak_css_not_saved',
				__( 'The rule could not be removed from your Additional CSS.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 500 )
			);
		}

		$this->issues->set_status( $issue_id, IssueStatus::Open, '' );

		/**
		 * Fires when a CSS rule is removed.
		 *
		 * @since 0.11.0
		 *
		 * @param int $issue_id Finding reopened.
		 */
		do_action( 'wsak_css_fix_reverted', $issue_id );

		return array( 'issue_id' => $issue_id );
	}

	/**
	 * Checks and formats the declarations one rule is allowed to set.
	 *
	 * Two gates, and both have to pass. Ours restricts the properties to the
	 * ones this particular finding is about, so a contrast fix cannot quietly
	 * set `position` or `content`. Core's `safecss_filter_attr` then applies the
	 * same filtering WordPress uses on inline styles, which is the check that
	 * has already seen every trick anybody has tried.
	 *
	 * @since 0.11.0
	 *
	 * @param string                                             $rule_id      Rule being answered.
	 * @param array<int, array{property: string, value: string}> $declarations Proposed declarations.
	 * @return string|WP_Error The formatted block, or a refusal.
	 */
	private function declarations( string $rule_id, array $declarations ) {
		$allowed = CssRules::properties( $rule_id );
		$lines   = array();

		foreach ( $declarations as $declaration ) {
			$property = strtolower( trim( (string) ( $declaration['property'] ?? '' ) ) );
			$value    = trim( (string) ( $declaration['value'] ?? '' ) );

			if ( ! in_array( $property, $allowed, true ) ) {
				return new WP_Error(
					'wsak_property_not_allowed',
					sprintf(
						/* translators: 1: CSS property name, 2: accessibility rule name. */
						__( 'A fix for %2$s is not allowed to set %1$s.', 'wowstudio-accessibility-kit' ),
						$property,
						$rule_id
					),
					array( 'status' => 400 )
				);
			}

			$safe = safecss_filter_attr( $property . ': ' . $value );

			if ( '' === trim( $safe ) ) {
				return new WP_Error(
					'wsak_value_not_allowed',
					sprintf(
						/* translators: %s: CSS property name. */
						__( 'The value given for %s is not one WordPress will store in a stylesheet.', 'wowstudio-accessibility-kit' ),
						$property
					),
					array( 'status' => 400 )
				);
			}

			$lines[] = "\t" . rtrim( trim( $safe ), ';' ) . ';';
		}

		if ( array() === $lines ) {
			return new WP_Error(
				'wsak_empty_fix',
				__( 'The fix contained no declarations, so there was nothing to write.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 400 )
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Returns the name of the theme these rules belong to.
	 *
	 * WordPress stores Additional CSS per theme, so the fixes stop applying when
	 * the theme changes. That is core's behaviour rather than ours, and the
	 * interface says so rather than letting somebody discover it later.
	 *
	 * @since 0.11.0
	 *
	 * @return string
	 */
	public function theme_name(): string {
		return (string) wp_get_theme()->get( 'Name' );
	}
}
