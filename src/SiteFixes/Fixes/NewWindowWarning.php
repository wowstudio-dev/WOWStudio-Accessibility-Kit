<?php
/**
 * Telling readers when a link opens a new tab.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\ProvidesCss;

defined( 'ABSPATH' ) || exit;

/**
 * Appends a hidden "opens in a new tab" to content links that open one.
 *
 * Opening a new tab takes the back button away. A sighted mouse user sees it
 * happen and recovers; somebody using a screen reader gets no announcement, and
 * the usual recovery — press Back — silently does nothing, because there is
 * nothing behind them any more.
 *
 * Two decisions worth writing down.
 *
 * **It only touches post content, through `the_content`.** Not the whole page.
 * SPEC decision F6 rules out buffering the response and rewriting whatever HTML
 * comes past, and the reason is not performance: rewriting everything is how an
 * overlay works, and it changes far more than it was asked to. A filter for
 * post content is a filter for post content.
 *
 * **It inserts, and never re-serialises.** The obvious implementation parses the
 * content into a DOM and writes it back out, which is more correct in principle
 * and in practice mangles entities, self-closing tags and anything the parser
 * does not recognise — on every render of every post. This matches the anchor,
 * inserts one span before its closing tag, and leaves every other byte exactly
 * as it found it. Content nobody asked us to touch comes out identical.
 *
 * The warning is visually hidden, because sighted readers already have the
 * convention and an inline "(opens in a new tab)" after every external link is
 * a lot of noise to add to somebody's prose. It is announced, not displayed.
 *
 * @since 0.19.0
 */
final class NewWindowWarning implements ProvidesCss {

	/**
	 * Phrases that mean the link already says so.
	 *
	 * @since 0.19.0
	 * @var string[]
	 */
	private const ALREADY_SAID = array( 'new window', 'new tab', 'opens in', 'external link' );

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'new-window-warning';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Say when a link opens a new tab', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Adds "(opens in a new tab)" to links in your content that open one, announced to screen readers but not shown on screen. A new tab removes the back button, and without warning the reader has no way to tell that is what happened.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array( 'link-opens-new-window' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'Covers links inside post and page content. Links your theme prints — in menus, headers, footers or widgets — are not reached by this, because changing those would mean rewriting the whole page rather than the part you wrote.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'the_content', array( $this, 'annotate' ), 20 );
	}

	/**
	 * Adds the warning to links that open a new tab.
	 *
	 * @since 0.19.0
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function annotate( $content ): string {
		$content = (string) $content;

		// Cheap gate first. Most content has no _blank in it at all, and the
		// pattern below should not run on every paragraph of every post.
		if ( ! str_contains( $content, '_blank' ) ) {
			return $content;
		}

		$replaced = preg_replace_callback(
			'#<a\s[^>]*>.*?</a>#is',
			array( $this, 'annotate_one' ),
			$content
		);

		// A catastrophic backtrack or an encoding error returns null. Returning
		// the content unchanged is the only safe answer; half-filtered content
		// would be worse than unfiltered.
		return null === $replaced ? $content : $replaced;
	}

	/**
	 * Annotates a single matched anchor.
	 *
	 * @since 0.19.0
	 *
	 * @param array<int, string> $matches Regex matches.
	 * @return string
	 */
	private function annotate_one( array $matches ): string {
		$anchor = $matches[0];

		if ( 1 !== preg_match( '#target\s*=\s*["\']?_blank#i', $anchor ) ) {
			return $anchor;
		}

		$haystack = strtolower( wp_strip_all_tags( $anchor ) . ' ' . $anchor );

		foreach ( self::ALREADY_SAID as $phrase ) {
			if ( str_contains( $haystack, $phrase ) ) {
				return $anchor;
			}
		}

		$warning = sprintf(
			'<span class="wsak-visually-hidden"> %s</span>',
			esc_html__( '(opens in a new tab)', 'wowstudio-accessibility-kit' )
		);

		// Inserted before the closing tag so it becomes part of the link's
		// name. Placed after it, a screen reader would read it as loose text
		// following the link rather than as part of what the link is called.
		$position = strripos( $anchor, '</a>' );

		if ( false === $position ) {
			return $anchor;
		}

		return substr( $anchor, 0, $position ) . $warning . substr( $anchor, $position );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function css(): string {
		return '/* Announced, not displayed. Our own class rather than the theme\'s
   screen-reader-text, which not every theme defines. */
.wsak-visually-hidden {
	position: absolute !important;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}';
	}
}
