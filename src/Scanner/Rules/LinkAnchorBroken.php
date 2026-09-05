<?php
/**
 * In-page links that point at nothing.
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
 * Flags `href="#something"` where nothing on the page has that id.
 *
 * The case that matters most is the skip link. "Skip to content" is usually the
 * first thing in the tab order and, for a keyboard user, the difference between
 * reaching the article in one keystroke and tabbing through forty navigation
 * items on every page of the site. When its target has been renamed — a theme
 * update, a rebuilt header, a page builder that regenerates ids — the link
 * stays visible, still looks right, and does nothing. Silently.
 *
 * Every other in-page link fails the same way: focus does not move, and a
 * screen reader user is left believing it did.
 *
 * Only runs against a full page. A scan of one block's markup does not contain
 * the rest of the document, so every anchor pointing outside that block would
 * look broken — and a rule that reports confidently from half the evidence is
 * the failure this product exists to avoid.
 *
 * @since 0.17.0
 */
final class LinkAnchorBroken implements Rule {

	use RunsOnServer;

	/**
	 * Fragments that are valid without matching an id.
	 *
	 * `#` and `#top` both mean the top of the document, per the HTML
	 * specification, and neither needs an element to exist.
	 *
	 * @since 0.17.0
	 * @var string[]
	 */
	private const ALWAYS_VALID = array( '', 'top' );

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'link-anchor-broken';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '2.4.1';
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
		return __( 'In-page link points at nothing', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This link jumps to a part of the page that does not exist, so activating it does nothing at all — focus stays where it was, with no error and no message. If it is a skip link, keyboard users are tabbing through your whole header on every page. Either give the target element the matching id, or correct the link.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'The link does nothing when activated, and nothing says so — focus simply stays put.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Which end is wrong depends on which one was renamed.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Theme,
			__( 'Two ends, and only you know which one moved: either the target lost its id, or the link points at the wrong name. Skip links and their targets almost always live in the theme header rather than in your content.', 'wowstudio-accessibility-kit' )
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
		if ( ! $document->is_full_page() ) {
			return array();
		}

		$findings = array();

		foreach ( $document->find( '//a[@href]' ) as $link ) {
			if ( ! $link instanceof DOMElement ) {
				continue;
			}

			$href = trim( $link->getAttribute( 'href' ) );

			if ( '' === $href || ! str_starts_with( $href, '#' ) ) {
				continue;
			}

			$fragment = rawurldecode( substr( $href, 1 ) );

			if ( in_array( strtolower( $fragment ), self::ALWAYS_VALID, true ) ) {
				continue;
			}

			if ( $this->target_exists( $document, $fragment ) ) {
				continue;
			}

			$name = trim( $document->accessible_name( $link ) );

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				'' === $name
					? sprintf(
						/* translators: %s: the fragment the link points at, including the hash. */
						__( 'A link points to %s, but nothing on this page has that id.', 'wowstudio-accessibility-kit' ),
						$href
					)
					: sprintf(
						/* translators: 1: the link text. 2: the fragment the link points at, including the hash. */
						__( 'The link "%1$s" points to %2$s, but nothing on this page has that id.', 'wowstudio-accessibility-kit' ),
						$name,
						$href
					),
				$document->selector_for( $link ),
				$document->context_for( $link )
			);
		}

		return $findings;
	}

	/**
	 * Reports whether anything on the page answers to this fragment.
	 *
	 * @since 0.17.0
	 *
	 * @param Document $document Parsed page.
	 * @param string   $fragment The fragment, without its hash.
	 * @return bool
	 */
	private function target_exists( Document $document, string $fragment ): bool {
		if ( null !== $document->first( sprintf( '//*[@id=%s]', Document::quote( $fragment ) ) ) ) {
			return true;
		}

		// The old form, still honoured by every browser and still emitted by
		// plenty of themes: <a name="..."> as a jump target.
		return null !== $document->first( sprintf( '//a[@name=%s]', Document::quote( $fragment ) ) );
	}
}
