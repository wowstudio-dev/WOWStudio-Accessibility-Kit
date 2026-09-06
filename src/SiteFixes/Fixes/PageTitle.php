<?php
/**
 * The document title, for themes that never asked for one.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\SiteFix;

defined( 'ABSPATH' ) || exit;

/**
 * Declares `title-tag` support on the theme's behalf.
 *
 * A page with no `<title>` has exactly one correct answer and WordPress already
 * knows it — `wp_get_document_title()` has composed it for years. The only
 * reason it is missing is that the theme never declared support for
 * `title-tag`, and a plugin may declare that instead.
 *
 * The title is how a page is named in a browser tab, in a bookmark, in search
 * results, and in the list a screen reader user hears when moving between open
 * windows. A site whose every page is called the same thing is navigable by
 * none of them.
 *
 * Timing, verified against core rather than assumed: `add_theme_support()`
 * refuses once `wp_loaded` has fired, which is why the whole site-fix manager
 * hooks `after_setup_theme` rather than `init`. Core's `_wp_render_title_tag` is
 * already attached to `wp_head` and returns early unless the support is
 * declared — so declaring it is the entire fix.
 *
 * Supersedes the standalone Remediation\TitleTagFix that shipped from 0.13.0 to
 * 0.18.0. Its option is carried into the site-fixes set by Installer on
 * upgrade, so a site that had switched it on keeps it: a page that had a title
 * yesterday and does not today would be a regression this plugin caused.
 *
 * @since 0.19.0
 */
final class PageTitle implements SiteFix {

	/**
	 * The option the previous standalone fix wrote.
	 *
	 * @since 0.19.0
	 * @var string
	 */
	public const LEGACY_OPTION = 'wsak_force_title_tag';

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'page-title';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Give every page a title', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Asks WordPress to write the page title your theme never asked for. The title names the page in browser tabs, bookmarks, search results and the window list a screen reader reads out — a site where every page is called the same thing cannot be navigated by any of them. WordPress composes the text itself; nothing is written into your content.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array( 'document-title-missing' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'Takes effect the next time a page loads: theme support is declared long before a settings request arrives, so it cannot change the request that switched it on. A theme that already declares support needs nothing from this.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		// Called from after_setup_theme, which is where this has to happen, so
		// there is no further hook to add — this is the fix.
		add_theme_support( 'title-tag' );
	}
}
