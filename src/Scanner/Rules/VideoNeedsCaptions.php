<?php
/**
 * Video with no caption track declared.
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
 * Flags a `<video>` with no caption or subtitle track.
 *
 * Captions are what make video usable without hearing it, and they are relied
 * on well beyond the Deaf and hard-of-hearing audience — anybody on a train, in
 * an office, or with the sound off is reading them too.
 *
 * Reported as needing review rather than settled, because the markup cannot see
 * everything. A video may carry burned-in captions, or be captioned by the
 * player rather than by a track element, and neither leaves a trace here. What
 * this can say is that no track was declared, which is worth checking.
 *
 * It also cannot see embedded video at all. A YouTube or Vimeo embed is an
 * iframe, and whether the video inside it is captioned is not knowable from
 * this side of the frame — so an empty result here is not a statement about
 * embedded video.
 *
 * @since 0.18.0
 */
final class VideoNeedsCaptions implements Rule {

	use RunsOnServer;


	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'video-needs-captions';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.2.2';
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
		return __( 'Video may have no captions', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This video declares no caption or subtitle track. Captions are what make a video usable with the sound off, which covers far more people than the Deaf and hard-of-hearing audience alone. The markup cannot see burned-in captions or captions supplied by a player, so this is worth confirming rather than assuming — and note that video embedded from YouTube or Vimeo sits inside an iframe, where nothing here can look at all.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Anybody who cannot hear the video, or has the sound off, gets nothing from it.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Captions have to be written and timed; nothing here can produce them.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Media,
			__( 'Captions have to be written and timed against the video, which is work no check can do for you. Add a track element pointing at a VTT file, or use a player that supplies them.', 'wowstudio-accessibility-kit' )
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

		foreach ( $document->find( '//video' ) as $video ) {
			if ( ! $video instanceof DOMElement ) {
				continue;
			}

			$tracks = $document->find( 'track[@kind="captions"]|track[@kind="subtitles"]', $video );

			if ( $tracks->length > 0 ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				__( 'This video declares no caption or subtitle track.', 'wowstudio-accessibility-kit' ),
				$document->selector_for( $video ),
				$document->context_for( $video )
			);
		}

		return $findings;
	}
}
