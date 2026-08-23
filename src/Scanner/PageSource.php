<?php
/**
 * Rendered page retrieval.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Fetches the HTML a visitor would actually receive.
 *
 * The whole page is fetched over a loopback request, exactly as a visitor would
 * see it, because most of what an accessibility scan cares about — the page
 * language, the title, landmarks, the theme's own markup — lives outside
 * post_content.
 *
 * Plenty of hosts block loopback requests, so a failure falls back to rendering
 * post_content. That fallback is always labelled: the returned markup is a
 * fragment, the document-level rules detect that and stay silent rather than
 * reporting things a fragment never has, and the caller is told coverage was
 * reduced and why. A partial scan presented as a full one would misreport a
 * site in both directions.
 *
 * @since 0.3.0
 */
final class PageSource {

	/**
	 * Seconds to wait for the page.
	 *
	 * @since 0.3.0
	 * @var int
	 */
	private const TIMEOUT = 20;

	/**
	 * Fetches the rendered HTML for a post.
	 *
	 * @since 0.3.0
	 *
	 * @param int $post_id Post to fetch.
	 * @return PageMarkup|WP_Error Markup and its origin, or an error explaining what to do.
	 */
	public function for_post( int $post_id ) {
		/**
		 * Filters the HTML the scanner analyses, before it is fetched.
		 *
		 * Returning a string short-circuits the loopback request. Useful for
		 * sites where loopback requests are blocked, and for tests.
		 *
		 * @since 0.3.0
		 *
		 * @param string|null $html    Markup to scan, or null to fetch it.
		 * @param int         $post_id Post being scanned.
		 */
		$supplied = apply_filters( 'wsak_page_html', null, $post_id );

		if ( is_string( $supplied ) ) {
			return new PageMarkup( $supplied, true );
		}

		$permalink = get_permalink( $post_id );

		if ( false === $permalink ) {
			return $this->fallback( $post_id, __( 'that content has no public address', 'wowstudio-accessibility-kit' ) );
		}

		$response = wp_remote_get(
			$permalink,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 3,
				'sslverify'   => apply_filters( 'wsak_page_source_sslverify', true, $post_id ),
				'headers'     => array( 'Accept' => 'text/html' ),
				'user-agent'  => 'WOWStudio Accessibility Kit/' . WSAK_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->fallback( $post_id, $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return $this->fallback(
				$post_id,
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'the page returned HTTP %d', 'wowstudio-accessibility-kit' ),
					$code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );

		if ( '' === trim( $body ) ) {
			return $this->fallback( $post_id, __( 'the page returned no content', 'wowstudio-accessibility-kit' ) );
		}

		return new PageMarkup( $body, true );
	}

	/**
	 * Falls back to scanning the post content alone.
	 *
	 * @since 0.3.0
	 *
	 * @param int    $post_id Post being scanned.
	 * @param string $reason  Why the whole page could not be fetched.
	 * @return PageMarkup|WP_Error
	 */
	private function fallback( int $post_id, string $reason ) {
		/**
		 * Filters whether a failed page fetch may fall back to post content.
		 *
		 * Set to false to make a loopback failure a hard error instead of a
		 * scan with reduced coverage.
		 *
		 * @since 0.3.0
		 *
		 * @param bool $allowed Whether the fallback may be used.
		 * @param int  $post_id Post being scanned.
		 */
		$allowed = (bool) apply_filters( 'wsak_allow_content_fallback', true, $post_id );

		if ( ! $allowed ) {
			return new WP_Error(
				'wsak_fetch_failed',
				sprintf(
					/* translators: %s: the underlying reason. */
					__( 'The page could not be fetched for scanning because %s. This usually means the site cannot make requests to itself; ask your host about loopback requests.', 'wowstudio-accessibility-kit' ),
					$reason
				),
				array( 'status' => 502 )
			);
		}

		$post = get_post( $post_id );

		if ( null === $post ) {
			return new WP_Error(
				'wsak_unknown_post',
				__( 'That content could not be found.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		// Core's own filter, applied deliberately: the point of the fallback is to
		// see the content as a visitor would, with shortcodes and blocks rendered.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Applying core's content filter is the intent here, not declaring a new hook.
		$content = apply_filters( 'the_content', $post->post_content );

		return new PageMarkup(
			is_string( $content ) ? $content : '',
			false,
			sprintf(
				/* translators: %s: the underlying reason the whole page could not be fetched. */
				__( 'Only this content was scanned, not the whole page, because %s. Checks that depend on the surrounding page — its language, its title, its landmarks — were skipped, so this scan covers less than usual. Ask your host about loopback requests to enable full-page scanning.', 'wowstudio-accessibility-kit' ),
				$reason
			)
		);
	}
}
