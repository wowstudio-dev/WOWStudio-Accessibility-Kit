<?php
/**
 * Page without a main landmark.
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
 * Flags a page with no main landmark.
 *
 * Reported as needing review. Landmarks let a keyboard or screen reader user
 * skip straight past the navigation to the content, but whether a given page
 * has a single obvious main region is a judgement about the design.
 *
 * @since 0.3.0
 */
final class MainLandmarkMissing implements Rule {

	use RunsOnServer;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'landmark-main-missing';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.3.1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Moderate;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Manual;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Page has no main landmark', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Landmarks let someone using a screen reader jump straight to the content instead of listening through the header and navigation on every page. Wrap the primary content of the page in a main element.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.13.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'There is no way to skip past the header, so keyboard and screen reader users go through it again on every page.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.13.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// The landmark has to wrap the page's main content, which is a decision about the template and belongs in the theme.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Theme,
			__( 'The landmark has to wrap the page\'s main content, which is a decision about the template and belongs in the theme.', 'wowstudio-accessibility-kit' )
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

		if ( $document->find( '//main | //*[@role="main"]' )->length > 0 ) {
			return array();
		}

		return array(
			new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				__( 'The page has no main landmark, so there is no quick way to skip to the content.', 'wowstudio-accessibility-kit' ),
				'/html/body'
			),
		);
	}
}
