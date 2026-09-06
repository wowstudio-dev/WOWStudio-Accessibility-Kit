<?php
/**
 * Site fixes that answer with a style rule.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes;

defined( 'ABSPATH' ) || exit;

/**
 * A site fix whose whole answer is CSS.
 *
 * Kept separate from `hooks()` so that every CSS fix on the site shares one
 * inline `<style>` element rather than each adding its own, and so the manager
 * can print nothing at all when none are switched on.
 *
 * Deliberately *not* written into the site's Additional CSS, which is where the
 * per-issue style fixes go. Those are edits somebody made about a particular
 * element and should be visible and editable as text. These are toggles: the
 * way to change one is to switch it off, and having the same rule exist in two
 * places that can disagree — a stored flag and a block of CSS somebody may have
 * edited — is a bug waiting for a support ticket. Nothing is written to the
 * site's own settings at all, so switching a fix off leaves no trace behind.
 *
 * @since 0.19.0
 */
interface ProvidesCss extends SiteFix {

	/**
	 * Returns the CSS this fix contributes.
	 *
	 * Returned unminified and commented. It is printed into the page where
	 * anybody can read it in view-source, and a site owner who wants to know
	 * what this plugin changed about their design should be able to find out
	 * without asking us.
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function css(): string;
}
