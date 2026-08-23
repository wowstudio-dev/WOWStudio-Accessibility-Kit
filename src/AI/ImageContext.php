<?php
/**
 * What gets sent to an AI provider about one image.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AI;

defined( 'ABSPATH' ) || exit;

/**
 * The image, and the minimum context needed to describe it well.
 *
 * This class is the boundary of the "only send what's needed" promise. If a
 * field is not here, it does not leave the site. Whole post content, other
 * images, user details, and site metadata are all deliberately absent: alt text
 * needs the picture, roughly where it sits, and nothing else.
 *
 * @since 0.5.0
 */
final class ImageContext {

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param string $base64     Base64-encoded image bytes.
	 * @param string $mime       Image MIME type.
	 * @param string $path       Absolute path to the image on disk.
	 * @param string $filename   Original file name, which often hints at subject.
	 * @param string $page_title Title of the page the image appears on.
	 * @param string $nearby     A short slice of the surrounding text.
	 */
	public function __construct(
		public readonly string $base64,
		public readonly string $mime,
		public readonly string $path = '',
		public readonly string $filename = '',
		public readonly string $page_title = '',
		public readonly string $nearby = ''
	) {}

	/**
	 * Returns the textual context as a short instruction fragment.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function describe(): string {
		$parts = array();

		if ( '' !== $this->filename ) {
			$parts[] = sprintf( 'File name: %s', $this->filename );
		}

		if ( '' !== $this->page_title ) {
			$parts[] = sprintf( 'Page title: %s', $this->page_title );
		}

		if ( '' !== $this->nearby ) {
			$parts[] = sprintf( 'Nearby text: %s', $this->nearby );
		}

		return implode( "\n", $parts );
	}

	/**
	 * Returns the approximate size of the payload in kilobytes.
	 *
	 * Shown to the user so "what is being sent" is a number, not a promise.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	public function payload_kb(): int {
		return (int) ceil( strlen( $this->base64 ) / 1024 );
	}
}
