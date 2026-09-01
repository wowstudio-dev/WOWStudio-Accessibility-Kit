<?php
/**
 * Page without a title.
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
 * Flags a page with no title, or an empty one.
 *
 * The title is the first thing a screen reader announces and the label a person
 * sees when choosing between open tabs or browser-history entries.
 *
 * @since 0.3.0
 */
final class DocumentTitleMissing implements Rule {

	use RunsOnServer;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'document-title-missing';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '2.4.2';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Serious;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Auto;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Page has no title', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'The page title is the first thing a screen reader announces, and the label people see in browser tabs, bookmarks, and search results. Give the page a title that describes what is on it.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.13.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// WordPress writes the title itself once the theme declares support for it, and a plugin can declare that support on the theme's behalf before wp_loaded.
		return new FixPlan(
			FixKind::Deterministic,
			FixTarget::Setting,
			__( 'WordPress writes the title itself once the theme declares support for it, and a plugin can declare that support on the theme\'s behalf before wp_loaded. One right answer, and somewhere to put it.', 'wowstudio-accessibility-kit' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @param Document $document Parsed page.
	 * @return Finding[]
	 */
	public function evaluate( Document $document ): array {
		if ( ! $document->is_full_page() ) {
			return array();
		}

		$title = $document->first( '//head/title' );

		if ( null !== $title && '' !== $document->text_of( $title ) ) {
			return array();
		}

		return array(
			new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				null === $title
					? __( 'The page has no title element.', 'wowstudio-accessibility-kit' )
					: __( 'The page title is empty.', 'wowstudio-accessibility-kit' ),
				'/html/head/title'
			),
		);
	}
}
