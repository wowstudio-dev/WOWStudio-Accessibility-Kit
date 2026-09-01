<?php
/**
 * Page language not declared.
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
 * Flags a page that does not declare its language.
 *
 * Without it a screen reader reads the page in whatever voice it defaults to,
 * which can make a page in one language unintelligible when read with another
 * language's pronunciation rules.
 *
 * @since 0.3.0
 */
final class HtmlLangMissing implements Rule {

	use RunsOnServer;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'html-lang-missing';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '3.1.1';
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
		return __( 'Page language is not set', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'The page does not say what language it is written in, so a screen reader may read it with the wrong pronunciation. Set a lang attribute on the html element, such as lang="en".', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.13.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// The right value is known — it is the site's own language setting — but the html element is printed by the theme and no filter reaches it.
		return new FixPlan(
			FixKind::Deterministic,
			FixTarget::Theme,
			__( 'The right value is known — it is the site\'s own language setting — but the html element is printed by the theme and no filter reaches it. Knowing the answer is not the same as being able to write it, which is why this is a hand-off rather than a button.', 'wowstudio-accessibility-kit' )
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

		$html = $document->first( '//html' );

		if ( null === $html ) {
			return array();
		}

		if ( '' !== trim( $html->getAttribute( 'lang' ) ) || '' !== trim( $html->getAttribute( 'xml:lang' ) ) ) {
			return array();
		}

		return array(
			new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				__( 'The page does not declare a language.', 'wowstudio-accessibility-kit' ),
				'/html'
			),
		);
	}
}
