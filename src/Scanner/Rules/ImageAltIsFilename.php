<?php
/**
 * Alt text that is really a file name.
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
 * Flags alt text that describes the file rather than the picture.
 *
 * This is what a media library does to you. Upload `DSC_04412.jpg` and some
 * themes, page builders and import tools helpfully populate the alt attribute
 * from the file name, so the attribute is present, no "missing alt" check
 * fires, and a screen reader reads out "D S C zero four four one two dot jay
 * peg". The check that looks for a *missing* attribute cannot see this at all,
 * which is exactly why it needs its own rule.
 *
 * Three shapes are caught, and all three are safe to settle automatically
 * because none of them can be a real description:
 *
 * - the alt matches the file name in `src`, with or without the extension;
 * - the alt is any string ending in an image extension;
 * - the alt is one of a short list of placeholder words a person would never
 *   write as a description on purpose.
 *
 * The placeholder list is deliberately short. "Photo of a heron" is a fine
 * description and must not be flagged, so only the bare word is matched, never
 * a phrase containing it.
 *
 * @since 0.17.0
 */
final class ImageAltIsFilename implements Rule {

	use RunsOnServer;

	/**
	 * Extensions that give a file name away.
	 *
	 * @since 0.17.0
	 * @var string[]
	 */
	private const IMAGE_EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp', 'tif', 'tiff', 'ico' );

	/**
	 * Words that are never a description on their own.
	 *
	 * Matched only as the entire alt value. A description that merely contains
	 * one of these — "image of the north façade" — is doing its job.
	 *
	 * @since 0.17.0
	 * @var string[]
	 */
	private const PLACEHOLDERS = array(
		'image',
		'images',
		'img',
		'photo',
		'photos',
		'picture',
		'pic',
		'graphic',
		'logo',
		'icon',
		'banner',
		'thumbnail',
		'thumb',
		'untitled',
		'placeholder',
		'spacer',
		'blank',
		'alt',
		'alt text',
		'no alt',
		'none',
		'null',
		'undefined',
	);

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'img-alt-is-filename';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.1.1';
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
		return __( 'Alt text is a file name or a placeholder', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'The alt attribute is filled in, but with the file name or a placeholder word rather than a description. This is worse than leaving it empty in one specific way: it looks answered, so nothing else will flag it. Replace it with what the image tells the reader, or with alt="" if the image is decorative.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'A screen reader reads the file name aloud, letter by letter, in place of what the picture shows.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// What the image conveys depends on why it is on the page.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Media,
			__( 'What the image conveys depends on why it is on the page, so the wording is yours. The Images screen is the quickest route: it lists undescribed images with a field beside each, and what you write goes to the media library.', 'wowstudio-accessibility-kit' )
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
		$findings = array();

		foreach ( $document->find( '//img[@alt]' ) as $image ) {
			if ( ! $image instanceof DOMElement || $document->is_hidden( $image ) ) {
				continue;
			}

			$alt = trim( $image->getAttribute( 'alt' ) );

			// An empty alt is a deliberate statement that the image is
			// decorative, which is a correct answer and none of this rule's
			// business.
			if ( '' === $alt ) {
				continue;
			}

			$reason = $this->why_it_is_not_a_description( $alt, $image->getAttribute( 'src' ) );

			if ( null === $reason ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				$reason,
				$document->selector_for( $image ),
				$document->context_for( $image )
			);
		}

		return $findings;
	}

	/**
	 * Returns why this alt is not a description, or null when it might be.
	 *
	 * @since 0.17.0
	 *
	 * @param string $alt    The alt attribute, trimmed.
	 * @param string $source The src attribute.
	 * @return string|null
	 */
	private function why_it_is_not_a_description( string $alt, string $source ): ?string {
		$extensions = implode( '|', self::IMAGE_EXTENSIONS );

		if ( 1 === preg_match( '/\.(' . $extensions . ')$/i', $alt ) ) {
			return sprintf(
				/* translators: %s: the alt text found on the image. */
				__( 'The alt text is a file name: "%s".', 'wowstudio-accessibility-kit' ),
				$alt
			);
		}

		if ( in_array( strtolower( $alt ), self::PLACEHOLDERS, true ) ) {
			return sprintf(
				/* translators: %s: the alt text found on the image. */
				__( 'The alt text is "%s", which describes nothing about this particular image.', 'wowstudio-accessibility-kit' ),
				$alt
			);
		}

		if ( '' !== $source ) {
			$file = wp_basename( (string) wp_parse_url( $source, PHP_URL_PATH ) );
			$stem = (string) preg_replace( '/\.[a-z0-9]+$/i', '', $file );

			/*
			 * Compared with separators folded to spaces, because "north-facade"
			 * as alt text on north-facade.jpg is the file name however it is
			 * punctuated, and an importer that tidies the hyphens has not
			 * written a description.
			 */
			if ( '' !== $stem && $this->folded( $alt ) === $this->folded( $stem ) ) {
				return sprintf(
					/* translators: %s: the alt text found on the image. */
					__( 'The alt text repeats the image file name: "%s".', 'wowstudio-accessibility-kit' ),
					$alt
				);
			}
		}

		return null;
	}

	/**
	 * Reduces a string to lowercase words for comparison.
	 *
	 * @since 0.17.0
	 *
	 * @param string $value Value to fold.
	 * @return string
	 */
	private function folded( string $value ): string {
		$value = (string) preg_replace( '/[_\-\.\s]+/u', ' ', strtolower( $value ) );

		return trim( $value );
	}
}
