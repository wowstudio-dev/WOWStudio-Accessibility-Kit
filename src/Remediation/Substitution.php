<?php
/**
 * Locating and replacing an element inside rendered content.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

use DOMDocument;
use DOMElement;
use DOMNode;

defined( 'ABSPATH' ) || exit;

/**
 * Swaps one element for another inside a fragment of HTML.
 *
 * Not a string replacement, and that is the whole point. The markup a scan
 * records comes from the fully rendered page, where WordPress has already added
 * attributes of its own — decoding, loading, sizes. The same element in post
 * content carries fewer. A byte-for-byte match between the two never succeeds,
 * so an override built on string replacement stores cleanly and then silently
 * does nothing.
 *
 * Matching therefore happens in the DOM, and is tolerant in one direction only:
 * a candidate matches when it is the same element type and every attribute it
 * carries also appears, with the same value, in the recorded markup. The
 * recorded markup may have more attributes; the candidate may not have
 * different ones. That accepts "WordPress added something on the way out" while
 * still refusing to touch a genuinely different element.
 *
 * @since 0.6.0
 */
final class Substitution {

	/**
	 * Replaces the first element matching $before with $after.
	 *
	 * @since 0.6.0
	 *
	 * @param string $content Content to operate on.
	 * @param string $before  Markup recorded when the issue was found.
	 * @param string $after   Reviewed replacement markup.
	 * @return string The content, changed only if a match was found.
	 */
	public static function replace( string $content, string $before, string $after ): string {
		if ( '' === trim( $content ) || '' === trim( $before ) || '' === trim( $after ) ) {
			return $content;
		}

		$document = self::parse( $content );
		$needle   = self::first_element( self::parse( $before ) );
		$repl     = self::first_element( self::parse( $after ) );

		if ( null === $document || null === $needle || null === $repl ) {
			return $content;
		}

		$match = self::find( $document, $needle );

		if ( null === $match ) {
			return $content;
		}

		$imported = $document->importNode( $repl, true );

		if ( ! $imported instanceof DOMNode || ! $match->parentNode instanceof DOMNode ) {
			return $content;
		}

		$match->parentNode->replaceChild( $imported, $match );

		return self::serialise( $document );
	}

	/**
	 * Reports whether an element matching $before is present.
	 *
	 * @since 0.6.0
	 *
	 * @param string $content Content to search.
	 * @param string $before  Markup to look for.
	 * @return bool
	 */
	public static function locatable( string $content, string $before ): bool {
		$document = self::parse( $content );
		$needle   = self::first_element( self::parse( $before ) );

		if ( null === $document || null === $needle ) {
			return false;
		}

		return null !== self::find( $document, $needle );
	}

	/**
	 * Finds the first element that matches the recorded one.
	 *
	 * @since 0.6.0
	 *
	 * @param DOMDocument $document Content document.
	 * @param DOMElement  $needle   Element recorded by the scan.
	 * @return DOMElement|null
	 */
	private static function find( DOMDocument $document, DOMElement $needle ): ?DOMElement {
		foreach ( $document->getElementsByTagName( $needle->nodeName ) as $candidate ) {
			if ( $candidate instanceof DOMElement && self::matches( $candidate, $needle ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Reports whether a candidate is the element the scan recorded.
	 *
	 * @since 0.6.0
	 *
	 * @param DOMElement $candidate Element in the content.
	 * @param DOMElement $needle    Element recorded by the scan.
	 * @return bool
	 */
	private static function matches( DOMElement $candidate, DOMElement $needle ): bool {
		if ( self::text_of( $candidate ) !== self::text_of( $needle ) ) {
			return false;
		}

		foreach ( $candidate->attributes as $attribute ) {
			if ( $needle->getAttribute( $attribute->nodeName ) !== $attribute->nodeValue ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns an element's collapsed text.
	 *
	 * @since 0.6.0
	 *
	 * @param DOMElement $element Element to read.
	 * @return string
	 */
	private static function text_of( DOMElement $element ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', (string) $element->textContent ) );
	}

	/**
	 * Parses a fragment into a document.
	 *
	 * @since 0.6.0
	 *
	 * @param string $html Markup to parse.
	 * @return DOMDocument|null
	 */
	private static function parse( string $html ): ?DOMDocument {
		$previous = libxml_use_internal_errors( true );
		$document = new DOMDocument( '1.0', 'UTF-8' );

		$loaded = $document->loadHTML(
			'<?xml encoding="UTF-8"><div id="wsak-root">' . $html . '</div>',
			LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $loaded ? $document : null;
	}

	/**
	 * Returns the first element inside the parsed wrapper.
	 *
	 * @since 0.6.0
	 *
	 * @param DOMDocument|null $document Parsed document.
	 * @return DOMElement|null
	 */
	private static function first_element( ?DOMDocument $document ): ?DOMElement {
		if ( null === $document ) {
			return null;
		}

		$root = $document->getElementById( 'wsak-root' );

		if ( ! $root instanceof DOMElement ) {
			return null;
		}

		foreach ( $root->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				return $child;
			}
		}

		return null;
	}

	/**
	 * Serialises the wrapper's contents back to a string.
	 *
	 * Only the wrapper's children are returned, so the html, head, and body
	 * elements libxml invents never reach the page.
	 *
	 * @since 0.6.0
	 *
	 * @param DOMDocument $document Document to serialise.
	 * @return string
	 */
	private static function serialise( DOMDocument $document ): string {
		$root = $document->getElementById( 'wsak-root' );

		if ( ! $root instanceof DOMElement ) {
			return '';
		}

		$html = '';

		foreach ( $root->childNodes as $child ) {
			$html .= (string) $document->saveHTML( $child );
		}

		return $html;
	}
}
