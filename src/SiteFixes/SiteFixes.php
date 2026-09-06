<?php
/**
 * The registry of site-wide fixes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes;

use WOWStudio\AccessibilityKit\SiteFixes\Fixes\BlockPdfUploads;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\EmptySearchMessage;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\LabelFormFields;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\StripPositiveTabindex;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\StripRedundantTitle;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\ViewportScalable;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\CommentAndSearchLabels;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\DownloadFileInfo;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\FocusOutline;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\HtmlLangAndDir;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\LinkUnderline;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\NewWindowWarning;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\PageTitle;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\SkipLink;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the available site fixes, keyed by id.
 *
 * The filter below is one of the four extension seams the plugin is being built
 * with, so that a separate add-on can register a fix without this file knowing
 * it exists.
 *
 * @since 0.19.0
 */
final class SiteFixes {

	/**
	 * Registered fixes, keyed by id.
	 *
	 * @since 0.19.0
	 * @var array<string, SiteFix>
	 */
	private array $fixes = array();

	/**
	 * Builds the registry with the built-in fixes.
	 *
	 * @since 0.19.0
	 *
	 * @return self
	 */
	public static function with_defaults(): self {
		$registry = new self();

		$defaults = array(
			new SkipLink(),
			new FocusOutline(),
			new LinkUnderline(),
			new HtmlLangAndDir(),
			new PageTitle(),
			new CommentAndSearchLabels(),
			new NewWindowWarning(),
			new DownloadFileInfo(),
			new BlockPdfUploads(),

			// 0.20.0. These five need the rendered page; see RunsInBrowser for
			// why, and for what they are not.
			new ViewportScalable(),
			new StripPositiveTabindex(),
			new StripRedundantTitle(),
			new LabelFormFields(),
			new EmptySearchMessage(),
		);

		/**
		 * Filters the site-wide fixes available to switch on.
		 *
		 * Anything in the list that is not a SiteFix is ignored, so a filter
		 * that returns something unexpected degrades to a shorter list rather
		 * than a fatal error on every request.
		 *
		 * @since 0.19.0
		 *
		 * @param SiteFix[] $defaults The built-in fixes.
		 */
		$fixes = (array) apply_filters( 'wsak_site_fixes', $defaults );

		foreach ( $fixes as $fix ) {
			if ( $fix instanceof SiteFix ) {
				$registry->add( $fix );
			}
		}

		return $registry;
	}

	/**
	 * Adds a fix, replacing any with the same id.
	 *
	 * @since 0.19.0
	 *
	 * @param SiteFix $fix The fix.
	 * @return void
	 */
	public function add( SiteFix $fix ): void {
		$this->fixes[ $fix->id() ] = $fix;
	}

	/**
	 * Returns every registered fix, keyed by id.
	 *
	 * @since 0.19.0
	 *
	 * @return array<string, SiteFix>
	 */
	public function all(): array {
		return $this->fixes;
	}

	/**
	 * Returns one fix, or null when nothing answers to that id.
	 *
	 * @since 0.19.0
	 *
	 * @param string $id Fix identifier.
	 * @return SiteFix|null
	 */
	public function get( string $id ): ?SiteFix {
		return $this->fixes[ $id ] ?? null;
	}

	/**
	 * Returns the fixes that answer a given rule's findings.
	 *
	 * @since 0.19.0
	 *
	 * @param string $rule_id Rule identifier.
	 * @return SiteFix[]
	 */
	public function for_rule( string $rule_id ): array {
		return array_values(
			array_filter(
				$this->fixes,
				static fn( SiteFix $fix ): bool => in_array( $rule_id, $fix->rule_ids(), true )
			)
		);
	}
}
