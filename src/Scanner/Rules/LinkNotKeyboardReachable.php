<?php
/**
 * Clickable anchors the keyboard cannot reach.
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
 * Flags `<a>` elements that do something on click but have no `href`.
 *
 * An anchor without `href` is not a link. The browser gives it no role, no
 * focus, and no keyboard activation — it is a span that happens to be spelled
 * `<a>`. Hang a click handler on it and you have a control that works perfectly
 * with a mouse and is completely unreachable any other way: not by Tab, not by
 * a screen reader's list of links, not by voice control asking for it by name.
 *
 * The check is narrow on purpose. A bare `<a>` with no `href` is often a
 * perfectly legitimate named anchor — `<a name="section-3">` — or a wrapper
 * left behind by an editor, and flagging every one of those would bury the real
 * finding. So this reports only anchors that carry evidence of being
 * interactive: an inline event handler, or a role that claims they are a
 * control.
 *
 * That evidence is necessarily partial. A click handler attached in JavaScript
 * leaves no trace in the markup, so this finds the ones written inline and
 * misses the ones bound at runtime — which is worth knowing when the result
 * comes back empty.
 *
 * @since 0.17.0
 */
final class LinkNotKeyboardReachable implements Rule {

	use RunsOnServer;

	/**
	 * Roles that claim the element is an interactive control.
	 *
	 * @since 0.17.0
	 * @var string[]
	 */
	private const INTERACTIVE_ROLES = array( 'button', 'link', 'menuitem', 'tab', 'checkbox', 'radio', 'switch', 'option' );

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'link-not-keyboard-reachable';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '2.1.1';
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
		return __( 'Clickable link cannot be reached by keyboard', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This element does something when clicked, but it has no href, so the browser does not treat it as a link. It cannot be tabbed to, it does not appear in a screen reader\'s list of links, and pressing Enter on it does nothing. Give it a real href if it goes somewhere, or make it a button element if it performs an action.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Anyone not using a mouse cannot reach this control at all — it does not exist for them.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Whether it is a link or a button depends on what it does.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Which element it should be depends on what it does: somewhere to go is a link with a real href, something to happen is a button. Both are keyboard-operable for free; a div or a bare anchor never is.', 'wowstudio-accessibility-kit' )
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

		foreach ( $document->find( '//a[not(@href)]' ) as $link ) {
			if ( ! $link instanceof DOMElement || $document->is_hidden( $link ) ) {
				continue;
			}

			if ( ! $this->looks_interactive( $link ) ) {
				continue;
			}

			// A positive or zero tabindex puts it back in the tab order, which
			// answers the keyboard half. The semantics are still wrong, but
			// this rule is about reachability and it is now reachable.
			if ( $link->hasAttribute( 'tabindex' ) && '-1' !== trim( $link->getAttribute( 'tabindex' ) ) ) {
				continue;
			}

			$name = trim( $document->accessible_name( $link ) );

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				'' === $name
					? __( 'This element responds to a click but has no href, so the keyboard cannot reach it.', 'wowstudio-accessibility-kit' )
					: sprintf(
						/* translators: %s: the element's text. */
						__( '"%s" responds to a click but has no href, so the keyboard cannot reach it.', 'wowstudio-accessibility-kit' ),
						$name
					),
				$document->selector_for( $link ),
				$document->context_for( $link )
			);
		}

		return $findings;
	}

	/**
	 * Reports whether the markup claims this anchor is a control.
	 *
	 * @since 0.17.0
	 *
	 * @param DOMElement $link The anchor.
	 * @return bool
	 */
	private function looks_interactive( DOMElement $link ): bool {
		foreach ( $link->attributes ?? array() as $attribute ) {
			if ( str_starts_with( strtolower( $attribute->nodeName ), 'on' ) ) {
				return true;
			}
		}

		$role = strtolower( trim( $link->getAttribute( 'role' ) ) );

		return in_array( $role, self::INTERACTIVE_ROLES, true );
	}
}
