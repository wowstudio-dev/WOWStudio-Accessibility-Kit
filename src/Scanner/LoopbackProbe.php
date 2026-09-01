<?php
/**
 * Asking once whether this site can fetch itself.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Establishes whether loopback requests work, and remembers the answer.
 *
 * Loopback is blocked on a great many hosts, and before this existed the
 * scanner rediscovered that once per page. A hundred-page run spent the better
 * part of half an hour waiting for identical timeouts in order to learn one
 * fact — and then reported it a hundred times, as a caveat attached to every
 * result rather than as the single site-wide condition it is.
 *
 * So it is asked once, with a short timeout rather than the scanner's long one,
 * and cached. The cache is deliberately short-lived: a host that starts
 * permitting loopback should not have to wait a week to be believed, and one
 * that stops should be noticed.
 *
 * @since 0.12.0
 */
final class LoopbackProbe {

	/**
	 * Where the verdict is kept.
	 *
	 * @since 0.12.0
	 * @var string
	 */
	public const TRANSIENT = 'wsak_loopback_ok';

	/**
	 * How long a verdict stands.
	 *
	 * @since 0.12.0
	 * @var int
	 */
	public const TTL = HOUR_IN_SECONDS;

	/**
	 * How long to wait before calling it blocked.
	 *
	 * Much shorter than a scan's own timeout. This is a yes-or-no question, and
	 * a site that cannot answer it in five seconds is not one we should be
	 * making a hundred more requests to.
	 *
	 * @since 0.12.0
	 * @var int
	 */
	private const TIMEOUT = 5;

	/**
	 * Reports whether loopback works, asking only if the answer is not known.
	 *
	 * @since 0.12.0
	 *
	 * @return bool
	 */
	public function works(): bool {
		$cached = get_transient( self::TRANSIENT );

		if ( '1' === $cached ) {
			return true;
		}

		if ( '0' === $cached ) {
			return false;
		}

		return $this->test();
	}

	/**
	 * Reports what is already known, without asking.
	 *
	 * Used by anything that must not make an HTTP request of its own — the
	 * interface deciding what to tell somebody before they press a button.
	 *
	 * @since 0.12.0
	 *
	 * @return bool|null True, false, or null when it has not been established.
	 */
	public function known(): ?bool {
		$cached = get_transient( self::TRANSIENT );

		if ( '1' === $cached ) {
			return true;
		}

		if ( '0' === $cached ) {
			return false;
		}

		return null;
	}

	/**
	 * Asks the site to fetch its own front page, and records the answer.
	 *
	 * @since 0.12.0
	 *
	 * @return bool
	 */
	public function test(): bool {
		/**
		 * Short-circuits the loopback probe.
		 *
		 * Returning a boolean skips the request entirely. Intended for hosts
		 * that know their own answer, and for tests.
		 *
		 * @since 0.12.0
		 *
		 * @param bool|null $verdict Whether loopback works, or null to find out.
		 */
		$supplied = apply_filters( 'wsak_loopback_works', null );

		if ( is_bool( $supplied ) ) {
			$this->remember( $supplied );

			return $supplied;
		}

		$response = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 2,
				'sslverify'   => apply_filters( 'wsak_page_source_sslverify', true, 0 ),
				'headers'     => array( 'Accept' => 'text/html' ),
				'user-agent'  => 'WOWStudio Accessibility Kit/' . WSAK_VERSION . '; ' . home_url( '/' ),
			)
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		// A redirect or an error page still proves the request completed, which
		// is the only thing being asked. Anything in the 200s or 300s counts.
		$works = $code >= 200 && $code < 400;

		$this->remember( $works );

		return $works;
	}

	/**
	 * Stores a verdict.
	 *
	 * @since 0.12.0
	 *
	 * @param bool $works Whether loopback succeeded.
	 * @return void
	 */
	private function remember( bool $works ): void {
		set_transient( self::TRANSIENT, $works ? '1' : '0', self::TTL );
	}

	/**
	 * Forgets the verdict, so the next question asks again.
	 *
	 * @since 0.12.0
	 *
	 * @return void
	 */
	public function forget(): void {
		delete_transient( self::TRANSIENT );
	}
}
