<?php
/**
 * Parsed HTML document.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps DOMDocument and DOMXPath, and answers the questions rules keep asking.
 *
 * Rules should never touch libxml directly. Parsing quirks, encoding handling,
 * and accessible-name computation are hard to get right and would otherwise be
 * reimplemented slightly differently in every rule.
 *
 * @since 0.3.0
 */
final class Document {

	/**
	 * Longest markup snippet stored with a finding.
	 *
	 * Enough to identify the element in a preview, short enough that a scan of a
	 * large page does not balloon the database, and short enough to keep the
	 * later "only send what's needed" promise when a snippet reaches an AI
	 * provider.
	 *
	 * @since 0.3.0
	 * @var int
	 */
	private const MAX_CONTEXT = 500;

	/**
	 * Parsed document.
	 *
	 * @since 0.3.0
	 * @var DOMDocument
	 */
	private DOMDocument $dom;

	/**
	 * XPath engine bound to the document.
	 *
	 * @since 0.3.0
	 * @var DOMXPath
	 */
	private DOMXPath $xpath;

	/**
	 * Whether the source was a whole page rather than a fragment.
	 *
	 * @since 0.3.0
	 * @var bool
	 */
	private bool $is_full_page;

	/**
	 * What the theme puts around this markup, when that is known.
	 *
	 * Only ever consulted by rules that judge content relative to what precedes
	 * it. Everything else in here is a fact about the markup in hand; this is
	 * the one piece of knowledge from outside it.
	 *
	 * @since 0.12.0
	 * @var TemplateProfile
	 */
	private TemplateProfile $profile;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param DOMDocument          $dom          Parsed document.
	 * @param bool                 $is_full_page Whether the source was a whole page.
	 * @param TemplateProfile|null $profile      What the theme puts around it, when known.
	 */
	private function __construct( DOMDocument $dom, bool $is_full_page, ?TemplateProfile $profile = null ) {
		$this->profile      = $profile ?? TemplateProfile::unknown();
		$this->dom          = $dom;
		$this->xpath        = new DOMXPath( $dom );
		$this->is_full_page = $is_full_page;
	}

	/**
	 * Parses an HTML string.
	 *
	 * Returns null for markup libxml cannot make a document out of at all.
	 * Malformed markup on its own is not a failure: real pages are messy, and a
	 * scanner that gives up on them is useless.
	 *
	 * @since 0.3.0
	 *
	 * @param string               $html Raw HTML.
	 * @param TemplateProfile|null $profile What the theme puts around it, when known.
	 * @return self|null
	 */
	public static function from_html( string $html, ?TemplateProfile $profile = null ): ?self {
		if ( '' === trim( $html ) ) {
			return null;
		}

		/*
		 * Non-ASCII characters are converted to numeric entities so libxml reads
		 * the document as UTF-8. The older mb_convert_encoding() HTML-ENTITIES
		 * trick is deprecated as of PHP 8.2 and must not be used here.
		 */
		$encoded = mb_encode_numericentity( $html, array( 0x80, 0x10FFFF, 0, ~0 ), 'UTF-8' );

		$previous = libxml_use_internal_errors( true );
		$dom      = new DOMDocument( '1.0', 'UTF-8' );

		/*
		 * LIBXML_NONET blocks network access during parsing, and LIBXML_NOENT is
		 * deliberately not set, so external entities in scanned markup cannot be
		 * expanded. Scanned HTML is untrusted input.
		 */
		$loaded = $dom->loadHTML( $encoded, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		/*
		 * libxml always synthesises html, head, and body, so the parsed tree
		 * cannot tell us whether we were given a whole page or a fragment. That
		 * has to be decided from the source, before parsing: rules about the
		 * document itself, such as the lang attribute or the page title, would
		 * otherwise report a fragment as missing things a fragment never has.
		 */
		$is_full_page = 1 === preg_match( '/<(?:html|head)[\s>]/i', $html );

		return $loaded ? new self( $dom, $is_full_page, $profile ) : null;
	}

	/**
	 * Reports whether a whole page was scanned.
	 *
	 * Rules that check the document itself must skip when this is false.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_full_page(): bool {
		return $this->is_full_page;
	}

	/**
	 * What the theme contributes around this markup.
	 *
	 * Returns a profile that admits it knows nothing when nothing is known,
	 * rather than null, so no caller has to guard before asking.
	 *
	 * @since 0.12.0
	 *
	 * @return TemplateProfile
	 */
	public function profile(): TemplateProfile {
		return $this->profile;
	}

	/**
	 * Runs an XPath query.
	 *
	 * @since 0.3.0
	 *
	 * @param string       $expression XPath expression.
	 * @param DOMNode|null $context    Node to search within, or null for the whole document.
	 * @return DOMNodeList<DOMNode>
	 */
	public function find( string $expression, ?DOMNode $context = null ): DOMNodeList {
		$nodes = $this->xpath->query( $expression, $context );

		return false === $nodes ? new DOMNodeList() : $nodes;
	}

	/**
	 * Returns the first match, or null.
	 *
	 * @since 0.3.0
	 *
	 * @param string $expression XPath expression.
	 * @return DOMElement|null
	 */
	public function first( string $expression ): ?DOMElement {
		$node = $this->find( $expression )->item( 0 );

		return $node instanceof DOMElement ? $node : null;
	}

	/**
	 * Returns an XPath that locates the given node.
	 *
	 * @since 0.3.0
	 *
	 * @param DOMNode $node Node to locate.
	 * @return string
	 */
	public function selector_for( DOMNode $node ): string {
		return (string) $node->getNodePath();
	}

	/**
	 * Returns the element's own markup, truncated for storage.
	 *
	 * @since 0.3.0
	 *
	 * @param DOMNode $node Node to render.
	 * @return string
	 */
	public function context_for( DOMNode $node ): string {
		$html = (string) $this->dom->saveHTML( $node );
		$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$html = trim( (string) preg_replace( '/\s+/u', ' ', $html ) );

		if ( mb_strlen( $html, 'UTF-8' ) > self::MAX_CONTEXT ) {
			$html = mb_substr( $html, 0, self::MAX_CONTEXT, 'UTF-8' ) . '…';
		}

		return $html;
	}

	/**
	 * Returns an element's visible text, collapsed.
	 *
	 * @since 0.3.0
	 *
	 * @param DOMNode $node Node to read.
	 * @return string
	 */
	public function text_of( DOMNode $node ): string {
		$text = html_entity_decode( (string) $node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Computes an element's accessible name.
	 *
	 * A deliberately simplified reading of the accessible name computation:
	 * aria-labelledby, then aria-label, then contained text including the alt
	 * text of nested images, then the title attribute. It does not implement the
	 * full specification, which is why rules built on it flag an empty name as a
	 * problem but never claim a non-empty name is a good one. Whether a name is
	 * meaningful is a judgement for a person.
	 *
	 * @since 0.3.0
	 *
	 * @param DOMElement $element Element to name.
	 * @return string
	 */
	public function accessible_name( DOMElement $element ): string {
		$labelledby = trim( $element->getAttribute( 'aria-labelledby' ) );

		if ( '' !== $labelledby ) {
			$parts = array();

			$ids = preg_split( '/\s+/', $labelledby );

			foreach ( is_array( $ids ) ? $ids : array() as $id ) {
				$target = $this->first( sprintf( '//*[@id=%s]', self::quote( $id ) ) );

				if ( $target instanceof DOMElement ) {
					$parts[] = $this->text_of( $target );
				}
			}

			$name = trim( implode( ' ', array_filter( $parts ) ) );

			if ( '' !== $name ) {
				return $name;
			}
		}

		$label = trim( $element->getAttribute( 'aria-label' ) );

		if ( '' !== $label ) {
			return $label;
		}

		$text = $this->text_of( $element );

		foreach ( $this->find( './/img[@alt]', $element ) as $image ) {
			if ( $image instanceof DOMElement ) {
				$text .= ' ' . trim( $image->getAttribute( 'alt' ) );
			}
		}

		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

		if ( '' !== $text ) {
			return $text;
		}

		return trim( $element->getAttribute( 'title' ) );
	}

	/**
	 * Reports whether an element is hidden from assistive technology.
	 *
	 * Only markup-level hiding is detectable without rendering. CSS-based
	 * hiding is invisible to a server-side scan, which is one of the reasons
	 * the coverage panel exists.
	 *
	 * @since 0.3.0
	 *
	 * @param DOMElement $element Element to test.
	 * @return bool
	 */
	public function is_hidden( DOMElement $element ): bool {
		$node = $element;

		while ( $node instanceof DOMElement ) {
			if ( 'true' === $node->getAttribute( 'aria-hidden' ) || $node->hasAttribute( 'hidden' ) ) {
				return true;
			}

			if ( 'none' === $node->getAttribute( 'role' ) || 'presentation' === $node->getAttribute( 'role' ) ) {
				return true;
			}

			$node = $node->parentNode instanceof DOMElement ? $node->parentNode : null;
		}

		return false;
	}

	/**
	 * Escapes a string for safe inclusion in an XPath expression.
	 *
	 * XPath has no escape character, so a value containing both quote styles has
	 * to be assembled with concat().
	 *
	 * @since 0.3.0
	 *
	 * @param string $value Raw value.
	 * @return string XPath literal.
	 */
	public static function quote( string $value ): string {
		if ( false === strpos( $value, "'" ) ) {
			return "'" . $value . "'";
		}

		if ( false === strpos( $value, '"' ) ) {
			return '"' . $value . '"';
		}

		return "concat('" . str_replace( "'", "', \"'\", '", $value ) . "')";
	}
}
