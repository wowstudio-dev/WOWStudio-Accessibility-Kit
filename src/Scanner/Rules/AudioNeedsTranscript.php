<?php
/**
 * Audio with no transcript in sight.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner\Rules;

use DOMElement;
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
 * Flags an `<audio>` element and asks whether a transcript exists.
 *
 * Audio-only content has exactly one alternative: a transcript. There is no
 * caption track to check for and no player setting that fixes it, so unlike
 * video this cannot be settled from the markup at all — a transcript is
 * ordinary text somewhere on the page, and no attribute distinguishes it from
 * any other paragraph.
 *
 * That is why this reports every audio element rather than trying to be clever.
 * It is a prompt, not a verdict, and the wording says so: a check that guessed
 * whether a nearby paragraph "looked like" a transcript would be wrong in both
 * directions and confident in each.
 *
 * @since 0.18.0
 */
final class AudioNeedsTranscript implements Rule {

	use RunsOnServer;


	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'audio-needs-transcript';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.2.1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Serious;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Manual;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Audio needs a transcript', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This page plays audio, and audio has only one alternative: a transcript of what is said. Nothing in the markup distinguishes a transcript from any other text, so this cannot be checked automatically — it is here as a prompt. If there is already a transcript on the page, mark this as reviewed and it will not ask again.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Anybody who cannot hear the recording has no way to get at what it says.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Nothing here can tell a transcript from any other paragraph.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Publish the transcript as text on the page, near the player. Nothing here can tell a transcript from any other paragraph, so this will keep asking until you mark it reviewed.', 'wowstudio-accessibility-kit' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @param Document $document Parsed page.
	 * @return Finding[]
	 */
	public function evaluate( Document $document ): array {
		$findings = array();

		foreach ( $document->find( '//audio' ) as $audio ) {
			if ( ! $audio instanceof DOMElement ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				__( 'This page plays audio. Check that a transcript is published with it.', 'wowstudio-accessibility-kit' ),
				$document->selector_for( $audio ),
				$document->context_for( $audio )
			);
		}

		return $findings;
	}
}
