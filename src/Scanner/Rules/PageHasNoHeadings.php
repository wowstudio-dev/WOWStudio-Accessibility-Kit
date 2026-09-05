<?php
/**
 * Pages with no headings at all.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner\Rules;

use WOWStudio\AccessibilityKit\Remediation\FixKind;
use WOWStudio\AccessibilityKit\Remediation\FixPlan;
use WOWStudio\AccessibilityKit\Remediation\FixTarget;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\Document;
use WOWStudio\AccessibilityKit\Scanner\Finding;
use WOWStudio\AccessibilityKit\Scanner\Rule;
use WOWStudio\AccessibilityKit\Scanner\RunsOnServer;
use WOWStudio\AccessibilityKit\Scanner\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Flags a page that contains no heading elements whatsoever.
 *
 * Headings are the table of contents a screen reader user actually navigates
 * with. Most will pull up a list of every heading on the page and jump straight
 * to the part they want; on a long page that is the difference between arriving
 * in one keystroke and reading everything in order to find out where things
 * are. A page with none offers no such list, and the only way through it is
 * from the top.
 *
 * This is different from the other heading checks, which are about a structure
 * that exists and has a flaw. This is about there being no structure at all,
 * which is usually a page built entirely out of styled paragraphs — text made
 * large and bold to *look* like a heading without ever being one.
 *
 * Only runs against a full page, and reports once. A fragment scan sees one
 * block's markup, where having no heading is perfectly normal.
 *
 * @since 0.17.0
 */
final class PageHasNoHeadings implements Rule {

	use RunsOnServer;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'page-has-no-headings';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.3.1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Serious;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Auto;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Page has no headings', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'There is not a single heading element on this page. Screen reader users navigate long pages by pulling up a list of headings and jumping to the part they want; with none, the only way through is from the top, in order. If the page looks like it has headings, they are probably paragraphs made large and bold — which conveys structure to the eye and nothing to anything else.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'There is no way to skim or jump around this page; it can only be read from the top.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Which text is a heading is a judgement about the document.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Which parts of the page are headings is a judgement about what the page says, and only you can make it. In the editor, change the blocks that act as headings from paragraph to heading.', 'wowstudio-accessibility-kit' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @param Document $document Parsed page.
	 * @return Finding[]
	 */
	public function evaluate( Document $document ): array {
		if ( ! $document->is_full_page() ) {
			return array();
		}

		if ( $document->find( '//h1|//h2|//h3|//h4|//h5|//h6|//*[@role="heading"]' )->length > 0 ) {
			return array();
		}

		return array(
			new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				__( 'This page contains no heading elements at all.', 'wowstudio-accessibility-kit' ),
				'',
				''
			),
		);
	}
}
