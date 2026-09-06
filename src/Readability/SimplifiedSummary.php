<?php
/**
 * A plain-language summary, where the text is hard going.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Readability;

use WOWStudio\AccessibilityKit\Core\Registrable;

defined( 'ABSPATH' ) || exit;

/**
 * Stores a simplified summary for a post, and puts it on the page.
 *
 * This is the other half of WCAG 3.1.5, and the half that actually helps
 * somebody. Measuring reading level is easy and changes nothing; writing a
 * plain-language version of a difficult page is the work, and no tool can do it
 * — which is why this stores what a person wrote and never generates anything.
 *
 * Shown above the content rather than below it. A summary underneath a
 * three-thousand-word page is only reachable by the people who did not need it,
 * and the point is to be the first thing available to somebody who is going to
 * struggle with what follows.
 *
 * Whether it appears at all is the site's choice, because a summary is
 * editorial content and some organisations will want to place it themselves.
 * The `wsak_simplified_summary` filter and the stored meta are both public, so
 * a theme can render it wherever it likes with the automatic output switched
 * off.
 *
 * @since 0.25.0
 */
final class SimplifiedSummary implements Registrable {

	/**
	 * Where the summary is kept.
	 *
	 * Prefixed and registered rather than hidden, so it appears in the REST
	 * API for the post and travels with an export. Somebody's writing should
	 * not be trapped inside this plugin.
	 *
	 * @since 0.25.0
	 * @var string
	 */
	public const META = 'wsak_simplified_summary';

	/**
	 * Where the "show it automatically" choice is kept.
	 *
	 * @since 0.25.0
	 * @var string
	 */
	public const OPTION = 'wsak_show_simplified_summary';

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.25.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'the_content', array( $this, 'prepend' ), 5 );
	}

	/**
	 * Registers the meta so the REST API and exports can see it.
	 *
	 * @since 0.25.0
	 *
	 * @return void
	 */
	public function register_meta(): void {
		foreach ( array_keys( get_post_types( array( 'public' => true ), 'names' ) ) as $type ) {
			register_post_meta(
				(string) $type,
				self::META,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => 'wp_kses_post',
					'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
				)
			);
		}
	}

	/**
	 * Returns the summary stored for a post.
	 *
	 * @since 0.25.0
	 *
	 * @param int $post_id The post.
	 * @return string
	 */
	public function for_post( int $post_id ): string {
		return trim( (string) get_post_meta( $post_id, self::META, true ) );
	}

	/**
	 * Stores a summary, or clears it.
	 *
	 * @since 0.25.0
	 *
	 * @param int    $post_id The post.
	 * @param string $summary What to store.
	 * @return bool
	 */
	public function save( int $post_id, string $summary ): bool {
		$summary = trim( wp_kses_post( $summary ) );

		if ( '' === $summary ) {
			delete_post_meta( $post_id, self::META );

			return true;
		}

		return false !== update_post_meta( $post_id, self::META, $summary );
	}

	/**
	 * Reports whether summaries are shown on the front end automatically.
	 *
	 * @since 0.25.0
	 *
	 * @return bool
	 */
	public function shows_automatically(): bool {
		return (bool) get_option( self::OPTION, false );
	}

	/**
	 * Puts the summary above the content.
	 *
	 * @since 0.25.0
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function prepend( $content ): string {
		$content = (string) $content;

		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( ! $this->shows_automatically() ) {
			return $content;
		}

		$summary = $this->for_post( get_the_ID() );

		if ( '' === $summary ) {
			return $content;
		}

		$html = sprintf(
			'<aside class="wsak-summary" aria-labelledby="wsak-summary-heading">
				<h2 id="wsak-summary-heading" class="wsak-summary__heading">%1$s</h2>
				%2$s
			</aside>',
			esc_html__( 'In short', 'wowstudio-accessibility-kit' ),
			wpautop( wp_kses_post( $summary ) )
		);

		/**
		 * Filters the simplified summary markup before it is added to a page.
		 *
		 * Return an empty string to place it yourself instead.
		 *
		 * @since 0.25.0
		 *
		 * @param string $html    The markup.
		 * @param string $summary The summary as it was written.
		 * @param int    $post_id The post.
		 */
		$html = (string) apply_filters( 'wsak_simplified_summary', $html, $summary, (int) get_the_ID() );

		return $html . $content;
	}
}
