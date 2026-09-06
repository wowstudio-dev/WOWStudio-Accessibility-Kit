<?php
/**
 * A language attribute on the html element.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\SiteFix;

defined( 'ABSPATH' ) || exit;

/**
 * Makes sure the page declares what language it is in.
 *
 * A screen reader chooses its pronunciation rules from the `lang` attribute.
 * Without one it falls back to whatever the user has configured, which for most
 * people is the language of their operating system — so an English page read by
 * somebody whose machine is set to German is pronounced with German phonetics,
 * and is close to unintelligible.
 *
 * WordPress has composed this correctly for years and prints it through
 * `language_attributes()`. The failure this fixes is not a WordPress one: it is
 * a theme whose `<html>` tag was pasted in from a static template and never
 * calls the function. There is nothing to compute, only somewhere to put it, so
 * the filter supplies the attributes for any theme that does call it and this
 * fix reports honestly that it cannot help a theme that does not.
 *
 * @since 0.19.0
 */
final class HtmlLangAndDir implements SiteFix {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'html-lang-dir';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Declare the page language', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Makes sure the page says which language it is written in, and which direction it reads. A screen reader picks its pronunciation from this; without it, an English page can be read aloud with the phonetics of whatever language the listener\'s computer is set to.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array( 'html-lang-missing' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'Only works if your theme calls language_attributes() in its html tag, which is what WordPress themes are supposed to do. If the attribute is still missing after switching this on, the theme has hard-coded its html tag and the fix has to be made there — the finding will tell you so.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'language_attributes', array( $this, 'ensure_attributes' ), 20, 2 );
	}

	/**
	 * Adds lang and dir when they are not already there.
	 *
	 * @since 0.19.0
	 *
	 * @param string $output The attributes WordPress composed.
	 * @param string $doctype The doctype being written.
	 * @return string
	 */
	public function ensure_attributes( $output, $doctype = 'html' ): string {
		$output = (string) $output;

		// WordPress normally composes this correctly on its own; this only has
		// something to do when a filter earlier in the chain has emptied it.
		if ( ! str_contains( $output, 'lang=' ) ) {
			$language = get_bloginfo( 'language' );

			if ( '' !== $language ) {
				$output = trim( $output . ' lang="' . esc_attr( $language ) . '"' );
			}
		}

		if ( 'html' === $doctype && is_rtl() && ! str_contains( $output, 'dir=' ) ) {
			$output = trim( $output . ' dir="rtl"' );
		}

		return $output;
	}
}
