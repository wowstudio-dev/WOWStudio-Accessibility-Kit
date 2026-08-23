<?php
/**
 * Links whose text says nothing on its own.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner\Rules;

use DOMElement;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\Document;
use WOWStudio\AccessibilityKit\Scanner\Finding;
use WOWStudio\AccessibilityKit\Scanner\Rule;
use WOWStudio\AccessibilityKit\Scanner\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Flags link text that carries no meaning out of context.
 *
 * Reported as needing review rather than settled automatically. Screen reader
 * users often pull up a list of every link on a page, where a row reading "read
 * more" is useless. But whether the surrounding text makes the destination
 * obvious is a judgement only a person can make, and sometimes "read more" is
 * genuinely fine.
 *
 * @since 0.3.0
 */
final class LinkTextNotDescriptive implements Rule {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'link-text-not-descriptive';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '2.4.4';
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
		return __( 'Link text may not make sense on its own', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Screen reader users often browse a list of every link on the page, with no surrounding text. Phrases like \'read more\' or \'click here\' give them nothing to go on. Consider rewriting the link to name its destination. If the wording is genuinely clear in context, mark this as reviewed.', 'wowstudio-accessibility-kit' );
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
		$findings = array();

		/**
		 * Filters the link phrases treated as uninformative on their own.
		 *
		 * Lower-case, and compared after collapsing whitespace. The defaults are
		 * English; a site in another language should add its own equivalents here
		 * rather than rely on this list.
		 *
		 * @since 0.3.0
		 *
		 * @param string[] $phrases Phrases considered uninformative.
		 */
		$phrases = (array) apply_filters(
			'wsak_vague_link_phrases',
			array( 'click here', 'click', 'here', 'read more', 'more', 'learn more', 'this', 'link', 'details', 'continue', 'go' )
		);

		foreach ( $document->find( '//a[@href]' ) as $link ) {
			if ( ! $link instanceof DOMElement || $document->is_hidden( $link ) ) {
				continue;
			}

			$name = $document->accessible_name( $link );

			if ( '' === $name ) {
				// An empty name is a harder failure, reported by LinkNameMissing.
				continue;
			}

			$normalised = trim( mb_strtolower( $name, 'UTF-8' ), " \t\n\r\0\x0B.!?…" );

			if ( ! in_array( $normalised, array_map( 'strval', $phrases ), true ) ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				sprintf(
					/* translators: %s: the link text. */
					__( 'The link text "%s" may not make sense when read on its own.', 'wowstudio-accessibility-kit' ),
					$name
				),
				$document->selector_for( $link ),
				$document->context_for( $link )
			);
		}

		return $findings;
	}
}
