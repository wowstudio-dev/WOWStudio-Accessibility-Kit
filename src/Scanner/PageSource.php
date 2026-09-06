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
 * Which of those happens is now a choice rather than an accident. A single-page
 * scan asks for the whole page, because somebody is waiting and the extra
 * coverage is worth a round trip. A bulk run asks for content, because a
 * hundred round trips is a different proposition from one — see decision F9.
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
	 * Whether this site can fetch itself.
	 *
	 * @since 0.12.0
	 * @var LoopbackProbe
	 */
	private LoopbackProbe $probe;

	/**
	 * Constructor.
	 *
	 * @since 0.12.0
	 *
	 * @param LoopbackProbe|null $probe Loopback verdict.
	 */
	public function __construct( ?LoopbackProbe $probe = null ) {
		$this->probe = $probe ?? new LoopbackProbe();
	}

	/**
	 * Fetches the markup for a post.
	 *
	 * @since 0.3.0
	 *
	 * @param int                $post_id  Post to fetch.
	 * @param FetchStrategy|null $strategy How to get it, or null for the whole page.
	 * @return PageMarkup|WP_Error Markup and its origin, or an error explaining what to do.
	 */
	public function for_post( int $post_id, ?FetchStrategy $strategy = null ) {
		$strategy = $strategy ?? FetchStrategy::Loopback;

		if ( FetchStrategy::Content === $strategy ) {
			// Asked for deliberately, so this is not a fallback and does not
			// carry a fallback's apologetic reason.
			return $this->content_only( $post_id );
		}
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

		// Established once for the site rather than rediscovered per page. On a
		// host that blocks loopback this turns a twenty-second timeout into no
		// request at all, which is the difference between a bulk run being slow
		// and a bulk run being unusable.
		if ( ! $this->probe->works() ) {
			return $this->fallback( $post_id, __( 'this site cannot make requests to itself', 'wowstudio-accessibility-kit' ) );
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
	 * Renders a post's own content, with no HTTP at all.
	 *
	 * The strategy a bulk run uses, and the reason bulk scanning works on hosts
	 * where nothing else does. Deliberately not routed through fallback(): this
	 * is a choice, and dressing a choice up as a failure would put an apology in
	 * front of somebody who got exactly what they asked for.
	 *
	 * @since 0.12.0
	 *
	 * @param int $post_id Post to render.
	 * @return PageMarkup|WP_Error
	 */
	private function content_only( int $post_id ) {
		$post = get_post( $post_id );

		if ( null === $post ) {
			return new WP_Error(
				'wsak_unknown_post',
				__( 'That content could not be found.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		$content = $this->rendered_content( $post );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		return new PageMarkup(
			$content,
			false,
			__( 'This checked the content of the page, not the theme around it. Faults in your header, navigation and footer are reported separately, against the theme, rather than repeated against every page that uses it.', 'wowstudio-accessibility-kit' )
		);
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

		$content = $this->rendered_content( $post );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		return new PageMarkup(
			$content,
			false,
			sprintf(
				/* translators: %s: the underlying reason the whole page could not be fetched. */
				__( 'Only this content was scanned, not the whole page, because %s. Checks that depend on the surrounding page — its language, its title, its landmarks — were skipped, so this scan covers less than usual. Ask your host about loopback requests to enable full-page scanning.', 'wowstudio-accessibility-kit' ),
				$reason
			)
		);
	}

	/**
	 * Renders a post's content the way a visitor would see it.
	 *
	 * Core's `the_content` first, which handles blocks, shortcodes and every
	 * builder that keeps its output in `post_content` — Divi and WP Bakery
	 * store shortcodes there, so they come out correctly with no special
	 * handling at all.
	 *
	 * Then the case that made this method necessary. Elementor keeps nothing in
	 * `post_content`: it stores a JSON tree in postmeta and renders it through
	 * hooks that only fire inside the loop on a real request. Applying
	 * `the_content` to the empty string it leaves behind returns the empty
	 * string, so a site where loopback is blocked would scan every Elementor
	 * page as though it had no content in it. Verified rather than assumed: an
	 * Elementor page with four faults in it produced zero bytes here while the
	 * rendered page was eighty kilobytes.
	 *
	 * Failing that, an explicit error. This used to fall through to the parser,
	 * which reported "the page could not be parsed as HTML" — true in a narrow
	 * sense and useless, because it named the symptom and not one of the two
	 * things the reader could actually do something about.
	 *
	 * @since 0.27.0
	 *
	 * Typed as `object` rather than `WP_Post` on purpose: it needs `post_content`
	 * and `ID` and nothing else, and the unit suite hands it a stdClass because
	 * WP_Post is not loaded there. A hint tighter than the method's actual
	 * requirement buys nothing and costs the tests.
	 *
	 * @param object $post The post.
	 * @return string|WP_Error
	 */
	private function rendered_content( object $post ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Applying core's content filter is the intent here, not declaring a new hook.
		$content = apply_filters( 'the_content', $post->post_content );
		$content = is_string( $content ) ? $content : '';

		if ( '' !== trim( $content ) ) {
			return $content;
		}

		$content = $this->builder_content( $post->ID );

		if ( '' !== trim( $content ) ) {
			return $content;
		}

		/*
		 * Both causes named, neither asserted. An empty content field means
		 * either that the page really is empty or that something else is
		 * producing what visitors see, and from here those look identical —
		 * claiming the second would be inventing a diagnosis, which is the
		 * fault this plugin reports on other people's markup.
		 */
		return new WP_Error(
			'wsak_no_scannable_content',
			__( 'There was nothing to check: this page\'s content field is empty, and no page builder supplied anything either. If visitors do see content here, something is producing it that this scan cannot reach — reaching it means fetching the whole page, so ask your host about loopback requests or whether a security plugin is blocking them.', 'wowstudio-accessibility-kit' ),
			array( 'status' => 422 )
		);
	}

	/**
	 * Asks a page builder for its rendered content.
	 *
	 * Elementor is handled directly because it is the most widely installed
	 * builder that keeps nothing in `post_content`, and because it exposes a
	 * public method for exactly this. Everything else goes through the filter,
	 * which is deliberately the whole extension point rather than a list of
	 * builders this file has to keep up with.
	 *
	 * @since 0.27.0
	 *
	 * @param int $post_id The post.
	 * @return string
	 */
	private function builder_content( int $post_id ): string {
		$content = '';

		if (
			class_exists( '\Elementor\Plugin' )
			&& isset( \Elementor\Plugin::$instance->frontend )
			&& method_exists( \Elementor\Plugin::$instance->frontend, 'get_builder_content_for_display' )
		) {
			// Second argument asks Elementor to render rather than return the
			// editor's own markup.
			$content = (string) \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id, true );
		}

		/**
		 * Filters content fetched from a page builder for scanning.
		 *
		 * The seam for any builder that keeps its content outside
		 * `post_content` — Oxygen, or anything else that renders from its own
		 * store. Return the rendered HTML for the post.
		 *
		 * Whatever is returned is scanned as the page's content, so it should
		 * be what a visitor sees rather than an editor representation of it.
		 *
		 * @since 0.27.0
		 *
		 * @param string $content Content found so far, empty when none.
		 * @param int    $post_id The post.
		 */
		$content = (string) apply_filters( 'wsak_builder_content', $content, $post_id );

		return $content;
	}
}
