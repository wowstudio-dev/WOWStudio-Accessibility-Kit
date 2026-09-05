<?php
/**
 * Rule registry.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

use WOWStudio\AccessibilityKit\Scanner\Rules\ButtonNameMissing;
use WOWStudio\AccessibilityKit\Scanner\Rules\DocumentTitleMissing;
use WOWStudio\AccessibilityKit\Scanner\Rules\FormControlLabelMissing;
use WOWStudio\AccessibilityKit\Scanner\Rules\HeadingLevelSkipped;
use WOWStudio\AccessibilityKit\Scanner\Rules\HtmlLangMissing;
use WOWStudio\AccessibilityKit\Scanner\Rules\IframeTitleMissing;
use WOWStudio\AccessibilityKit\Scanner\Rules\AriaReferenceBroken;
use WOWStudio\AccessibilityKit\Scanner\Rules\FormLabelOrphaned;
use WOWStudio\AccessibilityKit\Scanner\Rules\HeadingEmpty;
use WOWStudio\AccessibilityKit\Scanner\Rules\ImageAltIsFilename;
use WOWStudio\AccessibilityKit\Scanner\Rules\ImageAltRedundant;
use WOWStudio\AccessibilityKit\Scanner\Rules\ImageAltTooLong;
use WOWStudio\AccessibilityKit\Scanner\Rules\ImageMapAreaAltMissing;
use WOWStudio\AccessibilityKit\Scanner\Rules\LinkAnchorBroken;
use WOWStudio\AccessibilityKit\Scanner\Rules\LinkNotKeyboardReachable;
use WOWStudio\AccessibilityKit\Scanner\Rules\LinkOpensNewWindow;
use WOWStudio\AccessibilityKit\Scanner\Rules\LinkToFile;
use WOWStudio\AccessibilityKit\Scanner\Rules\PageHasNoHeadings;
use WOWStudio\AccessibilityKit\Scanner\Rules\ImageAltMissing;
use WOWStudio\AccessibilityKit\Scanner\Rules\LinkNameMissing;
use WOWStudio\AccessibilityKit\Scanner\Rules\LinkTextNotDescriptive;
use WOWStudio\AccessibilityKit\Scanner\Rules\MainLandmarkMissing;
use WOWStudio\AccessibilityKit\Scanner\Rules\MultipleTopHeadings;
use WOWStudio\AccessibilityKit\Scanner\Rules\TableHeadersMissing;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the rules the engine runs.
 *
 * Keeping the set in one filterable place is what makes adding a rule, or
 * turning one off for a site, a change of a few lines rather than a change to
 * the engine.
 *
 * @since 0.3.0
 */
final class RuleRegistry {

	/**
	 * Rules keyed by identifier.
	 *
	 * @since 0.3.0
	 * @var array<string, Rule>
	 */
	private array $rules = array();

	/**
	 * Builds a registry holding the MVP rule set.
	 *
	 * @since 0.3.0
	 *
	 * @return self
	 */
	public static function with_defaults(): self {
		$registry = new self();

		$defaults = array(
			new ImageAltMissing(),
			new FormControlLabelMissing(),
			new LinkNameMissing(),
			new ButtonNameMissing(),
			new IframeTitleMissing(),
			new HtmlLangMissing(),
			new DocumentTitleMissing(),
			new HeadingLevelSkipped(),
			new LinkTextNotDescriptive(),
			new TableHeadersMissing(),
			new MainLandmarkMissing(),
			new MultipleTopHeadings(),

			// 0.17.0. Ordered by what they are about rather than by when they
			// were added, so the list reads as a description of the coverage.
			new ImageAltIsFilename(),
			new ImageAltRedundant(),
			new ImageAltTooLong(),
			new ImageMapAreaAltMissing(),
			new LinkAnchorBroken(),
			new LinkNotKeyboardReachable(),
			new LinkOpensNewWindow(),
			new LinkToFile(),
			new HeadingEmpty(),
			new PageHasNoHeadings(),
			new FormLabelOrphaned(),
			new AriaReferenceBroken(),
		);

		/**
		 * Filters the rules the scanner runs.
		 *
		 * Anything in the list that is not a Rule is ignored, so a filter that
		 * returns something unexpected degrades to a smaller rule set rather
		 * than a fatal error mid-scan.
		 *
		 * @since 0.3.0
		 *
		 * @param Rule[] $defaults The built-in rules.
		 */
		$rules = (array) apply_filters( 'wsak_rules', $defaults );

		foreach ( $rules as $rule ) {
			if ( $rule instanceof Rule ) {
				$registry->add( $rule );
			}
		}

		return $registry;
	}

	/**
	 * Adds or replaces a rule.
	 *
	 * @since 0.3.0
	 *
	 * @param Rule $rule Rule to register.
	 * @return void
	 */
	public function add( Rule $rule ): void {
		$this->rules[ $rule->id() ] = $rule;
	}

	/**
	 * Removes a rule.
	 *
	 * @since 0.3.0
	 *
	 * @param string $id Rule identifier.
	 * @return void
	 */
	public function remove( string $id ): void {
		unset( $this->rules[ $id ] );
	}

	/**
	 * Returns one rule.
	 *
	 * @since 0.3.0
	 *
	 * @param string $id Rule identifier.
	 * @return Rule|null
	 */
	public function get( string $id ): ?Rule {
		return $this->rules[ $id ] ?? null;
	}

	/**
	 * Returns any check by ID, of either pass.
	 *
	 * The get() method deliberately returns only executable rules, so the engine
	 * cannot be handed a check it has no way to run. Presentation needs the
	 * other kind too — a browser finding still has a title and remediation
	 * advice to show.
	 *
	 * @since 0.10.0
	 *
	 * @param string $id Rule identifier.
	 * @return RuleDescriptor|null
	 */
	public function descriptor( string $id ): ?RuleDescriptor {
		foreach ( $this->descriptors() as $rule ) {
			if ( $rule->id() === $id ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Returns every registered rule.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string, Rule>
	 */
	public function all(): array {
		return $this->rules;
	}

	/**
	 * Returns every check this plugin performs, of either pass.
	 *
	 * @since 0.10.0
	 *
	 * @return RuleDescriptor[]
	 */
	public function descriptors(): array {
		return array_merge( array_values( $this->rules ), BrowserRules::all() );
	}

	/**
	 * Describes the rule set for the coverage panel.
	 *
	 * The UI is required to tell people what automation can and cannot settle,
	 * and this is the data behind that promise. It therefore lists the browser
	 * pass's checks alongside the server pass's, whether or not a browser pass
	 * has ever run: a coverage list that only showed the checks that happened
	 * to be available would answer "what did you look at" when the question
	 * being asked is "what can you look at".
	 *
	 * @since 0.3.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function coverage(): array {
		$coverage = array();

		foreach ( $this->descriptors() as $rule ) {
			$coverage[] = array(
				'id'          => $rule->id(),
				'title'       => $rule->title(),
				'description' => $rule->description(),
				'wcag_sc'     => $rule->wcag_sc(),
				'severity'    => $rule->severity()->value,
				'detection'   => $rule->detection()->value,
				'pass'        => $rule->pass()->value,
				// Sent so the interface asks rather than infers. Working out
				// which findings can be fixed by matching rule IDs in
				// JavaScript is how the two lists quietly stop agreeing.
				'fix'         => $rule->fix_plan()->to_array(),
			);
		}

		return $coverage;
	}
}
