<?php
/**
 * Saying where a finding is, in terms a person can use.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

use DOMDocument;
use DOMElement;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a finding's markup into a short phrase naming the element.
 *
 * Every finding already carried a location: `getNodePath()`, which produces
 * things like
 *
 *     /html/body/main/div/div[2]/div/div/div/div/div/div/div/div[1]/div/h3/span
 *
 * That is exactly right for the code that has to find the element again in a
 * preview frame, and useless to the person reading the report. It says where
 * the element sits in a tree they cannot see, in a notation most people who
 * write for the web have never met. Somebody looking at it cannot tell whether
 * the finding is about their headline or their cookie banner.
 *
 * So the path stays — the inspector still needs it — and this is what gets
 * shown instead: the tag, the id or first class the author actually wrote, and
 * the words on screen.
 *
 *     h3.first-title — "About the Conference"
 *     img — "team-photo.jpg"
 *     a.eb-button — "Expert Speakers"
 *
 * Derived from the stored markup rather than computed during the scan, which
 * matters for three reasons: the two passes cannot disagree about it, findings
 * recorded before this existed get one without being rescanned, and neither
 * scanner grows a second job.
 *
 * @since 0.29.0
 */
final class Locator {

	/**
	 * How much of the element's text to quote.
	 *
	 * Long enough to recognise a heading, short enough that the phrase stays
	 * one line beside the tag it belongs to.
	 *
	 * @since 0.29.0
	 * @var int
	 */
	private const MAX_TEXT = 42;

	/**
	 * Attributes worth quoting when an element has no text of its own.
	 *
	 * In priority order. An image's alt is what a screen reader would say; its
	 * file name is what the author will recognise in the media library. A
	 * control's accessible name is the only thing distinguishing it from the
	 * eleven others beside it.
	 *
	 * @since 0.29.0
	 * @var string[]
	 */
	private const NAMING_ATTRIBUTES = array( 'alt', 'aria-label', 'title', 'placeholder', 'value', 'name' );

	/**
	 * Describes where a finding is.
	 *
	 * @since 0.29.0
	 *
	 * @param string $context The offending markup, as stored.
	 * @return string A short phrase, or '' when the markup cannot be read.
	 */
	public static function describe( string $context ): string {
		$element = self::first_element( $context );

		if ( ! $element instanceof DOMElement ) {
			return '';
		}

		$selector = self::selector_of( $element );
		$label    = self::label_of( $element );

		if ( '' === $label ) {
			return $selector;
		}

		return sprintf( '%s — “%s”', $selector, $label );
	}

	/**
	 * Parses the first element out of a markup fragment.
	 *
	 * @since 0.29.0
	 *
	 * @param string $context The offending markup.
	 * @return DOMElement|null
	 */
	private static function first_element( string $context ): ?DOMElement {
		$context = trim( $context );

		if ( '' === $context || ! str_contains( $context, '<' ) ) {
			return null;
		}

		$dom = new DOMDocument();

		$previous = libxml_use_internal_errors( true );

		// A fragment, not a document. The meta declares UTF-8 because
		// loadHTML assumes Latin-1 otherwise and mangles every quoted heading
		// that contains an accent.
		$loaded = $dom->loadHTML(
			'<?xml encoding="UTF-8"?><div>' . $context . '</div>',
			LIBXML_NOERROR | LIBXML_NOWARNING
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $loaded ) {
			return null;
		}

		$wrapper = $dom->getElementsByTagName( 'div' )->item( 0 );

		if ( null === $wrapper ) {
			return null;
		}

		foreach ( $wrapper->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				return $child;
			}
		}

		return null;
	}

	/**
	 * Builds the CSS-ish selector half of the phrase.
	 *
	 * One class, not all of them. Utility-first themes put a dozen on an
	 * element, and `div.mt-4.flex.items-center.justify-between.gap-2.rounded-lg`
	 * is the same wall of noise the XPath was, only wider.
	 *
	 * @since 0.29.0
	 *
	 * @param DOMElement $element Element at fault.
	 * @return string
	 */
	private static function selector_of( DOMElement $element ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMElement's own property.
		$selector = strtolower( $element->tagName );

		$id = trim( $element->getAttribute( 'id' ) );

		if ( '' !== $id ) {
			return $selector . '#' . $id;
		}

		$classes = preg_split( '/\s+/u', trim( $element->getAttribute( 'class' ) ) );

		if ( is_array( $classes ) && '' !== ( $classes[0] ?? '' ) ) {
			$selector .= '.' . $classes[0];
		}

		return $selector;
	}

	/**
	 * Finds the words that identify this element on the page.
	 *
	 * Its own text first, because that is what somebody will be looking at.
	 * Failing that, whichever naming attribute it has — and for an image with
	 * no name at all, the file, since a missing description is precisely the
	 * case where there is nothing else to go on.
	 *
	 * @since 0.29.0
	 *
	 * @param DOMElement $element Element at fault.
	 * @return string
	 */
	private static function label_of( DOMElement $element ): string {
		$text = self::shorten( (string) $element->textContent );

		if ( '' !== $text ) {
			return $text;
		}

		foreach ( self::NAMING_ATTRIBUTES as $attribute ) {
			$value = self::shorten( $element->getAttribute( $attribute ) );

			if ( '' !== $value ) {
				return $value;
			}
		}

		$src = trim( $element->getAttribute( 'src' ) );

		if ( '' !== $src ) {
			$path = strtok( $src, '?' );

			return self::shorten( wp_basename( false === $path ? $src : $path ) );
		}

		return '';
	}

	/**
	 * Collapses whitespace and trims to length.
	 *
	 * @since 0.29.0
	 *
	 * @param string $value Raw text.
	 * @return string
	 */
	private static function shorten( string $value ): string {
		/*
		 * Markup stripped before anything else. Context is truncated to a fixed
		 * length when it is stored, so a long block arrives cut off mid-tag; the
		 * parser then hands back text with the remains of markup still in it,
		 * and the label reads `div.wrapper — "<div class="eb-parent-wrapper e…"`.
		 * A location that looks like markup is not a location — it is the same
		 * unreadable thing this class exists to replace, one level in.
		 */
		$value = (string) preg_replace( '/<[^>]*>?/u', ' ', $value );
		$value = trim( (string) preg_replace( '/\s+/u', ' ', $value ) );

		if ( mb_strlen( $value, 'UTF-8' ) <= self::MAX_TEXT ) {
			return $value;
		}

		return rtrim( mb_substr( $value, 0, self::MAX_TEXT, 'UTF-8' ) ) . '…';
	}
}
