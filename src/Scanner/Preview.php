<?php
/**
 * The page as the inspector frames it.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Serves a post to the inspector's preview frame with the admin bar removed.
 *
 * This exists to fix a bug that would otherwise make every highlight wrong.
 *
 * Scanning fetches the page over HTTP with no cookies, so the markup analysed
 * has no admin bar in it, and findings carry positional selectors — XPaths like
 * `/html/body/div[3]/p[2]` — derived from that markup. The preview frame loads
 * the same permalink in the user's own logged-in browser, where WordPress
 * inserts `<div id="wpadminbar">` as the body's first child. That single extra
 * element shifts every positional index after it by one, so every selector
 * would resolve to its neighbour and the inspector would confidently point at
 * the wrong thing.
 *
 * Suppressing the bar for this one request keeps the two documents aligned. The
 * alternative — teaching the resolver to compensate — would mean modelling every
 * difference between a logged-out fetch and a logged-in render, which is a much
 * larger surface and would fail silently the first time one was missed.
 *
 * Nothing here runs for visitors. The bar is only suppressed for a logged-in
 * user who could have started the scan in the first place, so an anonymous
 * request carrying the same query argument is served exactly what it would
 * otherwise have been served.
 *
 * @since 0.10.0
 */
final class Preview implements Registrable {

	/**
	 * Query argument marking a request as an inspector preview.
	 *
	 * @since 0.10.0
	 * @var string
	 */
	public const QUERY_ARG = 'wsak_preview';

	/**
	 * Hooks the admin-bar suppression.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'show_admin_bar', array( $this, 'maybe_hide_admin_bar' ), 99 );
	}

	/**
	 * Returns the URL the inspector should frame for a post.
	 *
	 * @since 0.10.0
	 *
	 * @param int $post_id Post to preview.
	 * @return string Empty when the post has no viewable permalink.
	 */
	public static function url_for( int $post_id ): string {
		$permalink = get_permalink( $post_id );

		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}

		return add_query_arg( self::QUERY_ARG, '1', $permalink );
	}

	/**
	 * Reports whether the current request is an inspector preview.
	 *
	 * Capability-gated rather than merely argument-gated, so the query argument
	 * cannot be used by an anonymous visitor to alter what the site serves.
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public static function is_preview_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display tweak for the current user; changes nothing and reveals nothing.
		if ( ! isset( $_GET[ self::QUERY_ARG ] ) ) {
			return false;
		}

		return is_user_logged_in() && current_user_can( Capabilities::RUN_SCAN );
	}

	/**
	 * Hides the admin bar on a preview request.
	 *
	 * @since 0.10.0
	 *
	 * @param bool $show Whether the bar would otherwise show.
	 * @return bool
	 */
	public function maybe_hide_admin_bar( $show ): bool {
		return self::is_preview_request() ? false : (bool) $show;
	}
}
