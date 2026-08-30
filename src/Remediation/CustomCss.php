<?php
/**
 * The block of Additional CSS this plugin manages.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and rewrites our own rules inside the site's Additional CSS.
 *
 * Applied CSS fixes go into WordPress's Additional CSS rather than a stylesheet
 * of our own. That keeps this plugin from putting anything of its own in front
 * of visitors, and it puts the change somewhere the site owner already has: they
 * can read it, edit it, or delete it in Appearance → Customise, with or without
 * this plugin installed. A fix only we can undo is a fix that holds the site
 * hostage.
 *
 * Which means this class is editing a file somebody else owns, and the whole
 * design follows from that. Our rules live between two marker comments. On every
 * write, everything outside those markers is preserved exactly — not
 * reformatted, not reordered, not normalised. If the markers are missing or
 * malformed we append a fresh block rather than guess at what was meant: a
 * duplicate block is an inconvenience somebody can fix in a minute, and a
 * mangled stylesheet is a broken site.
 *
 * Markers are matched only at the start of a line. A stylesheet that happens to
 * contain our marker text inside a string — `content: "/* wsak … "` — is
 * therefore left alone rather than being treated as a block boundary.
 *
 * @since 0.11.0
 */
final class CustomCss {

	/**
	 * Opening marker for the managed block.
	 *
	 * @since 0.11.0
	 * @var string
	 */
	public const START = '/* BEGIN WOWStudio Accessibility Kit — managed rules. Edit above or below this block, not inside it. */';

	/**
	 * Closing marker for the managed block.
	 *
	 * @since 0.11.0
	 * @var string
	 */
	public const END = '/* END WOWStudio Accessibility Kit */';

	/**
	 * Returns the site's whole Additional CSS.
	 *
	 * @since 0.11.0
	 *
	 * @return string
	 */
	public function all(): string {
		return (string) wp_get_custom_css();
	}

	/**
	 * Returns our managed rules, keyed by the issue each one answers.
	 *
	 * @since 0.11.0
	 *
	 * @return array<int, string>
	 */
	public function rules(): array {
		$block = $this->block( $this->all() );

		if ( '' === $block ) {
			return array();
		}

		$rules = array();

		if ( ! preg_match_all( '/^\s*\/\* wsak:issue:(\d+) \*\/\R(.*?)(?=^\s*\/\* wsak:issue:\d+ \*\/|\z)/ms', $block, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		foreach ( $matches as $match ) {
			$rules[ (int) $match[1] ] = trim( (string) $match[2] );
		}

		return $rules;
	}

	/**
	 * Adds or replaces the rule answering one issue.
	 *
	 * @since 0.11.0
	 *
	 * @param int    $issue_id Issue the rule addresses.
	 * @param string $css      The rule itself.
	 * @return bool
	 */
	public function put( int $issue_id, string $css ): bool {
		$rules              = $this->rules();
		$rules[ $issue_id ] = trim( $css );

		return $this->write( $rules );
	}

	/**
	 * Removes the rule answering one issue, leaving the others alone.
	 *
	 * @since 0.11.0
	 *
	 * @param int $issue_id Issue whose rule should go.
	 * @return bool
	 */
	public function remove( int $issue_id ): bool {
		$rules = $this->rules();

		unset( $rules[ $issue_id ] );

		return $this->write( $rules );
	}

	/**
	 * Returns the contents of the managed block, without its markers.
	 *
	 * Returns an empty string when there is no complete, well-formed block —
	 * including when a start marker appears without an end, which is the case
	 * that must never be treated as "everything after here is ours".
	 *
	 * @since 0.11.0
	 *
	 * @param string $css Whole stylesheet.
	 * @return string
	 */
	public function block( string $css ): string {
		$start = $this->marker_position( $css, self::START );
		$end   = $this->marker_position( $css, self::END );

		if ( null === $start || null === $end || $end <= $start ) {
			return '';
		}

		$from = $start + strlen( self::START );

		return trim( substr( $css, $from, $end - $from ) );
	}

	/**
	 * Writes the managed block back, preserving everything around it.
	 *
	 * @since 0.11.0
	 *
	 * @param array<int, string> $rules Rules keyed by issue ID.
	 * @return bool
	 */
	private function write( array $rules ): bool {
		$existing = $this->all();
		$outside  = $this->without_block( $existing );
		$block    = $this->compose( $rules );

		$css = '' === $block
			? rtrim( $outside )
			: rtrim( $outside ) . ( '' === trim( $outside ) ? '' : "\n\n" ) . $block;

		$saved = wp_update_custom_css_post( $css );

		return ! is_wp_error( $saved );
	}

	/**
	 * Returns the stylesheet with our block removed and nothing else changed.
	 *
	 * @since 0.11.0
	 *
	 * @param string $css Whole stylesheet.
	 * @return string
	 */
	private function without_block( string $css ): string {
		$start = $this->marker_position( $css, self::START );
		$end   = $this->marker_position( $css, self::END );

		// No usable block. Everything is theirs, so nothing is removed — a
		// stray opening marker with no close is deliberately left in place
		// rather than treated as the start of something we may delete.
		if ( null === $start || null === $end || $end <= $start ) {
			return $css;
		}

		return substr( $css, 0, $start ) . substr( $css, $end + strlen( self::END ) );
	}

	/**
	 * Builds the managed block from the rules it should contain.
	 *
	 * @since 0.11.0
	 *
	 * @param array<int, string> $rules Rules keyed by issue ID.
	 * @return string
	 */
	private function compose( array $rules ): string {
		$rules = array_filter( array_map( 'trim', $rules ), static fn( string $rule ): bool => '' !== $rule );

		if ( array() === $rules ) {
			return '';
		}

		ksort( $rules );

		$lines = array( self::START );

		foreach ( $rules as $issue_id => $rule ) {
			$lines[] = sprintf( '/* wsak:issue:%d */', $issue_id );
			$lines[] = $rule;
		}

		$lines[] = self::END;

		return implode( "\n", $lines );
	}

	/**
	 * Finds a marker, but only where it begins a line.
	 *
	 * A stylesheet containing our marker text inside a string or a content
	 * property is not declaring a block boundary, and treating it as one would
	 * mean rewriting the wrong region of somebody's CSS.
	 *
	 * @since 0.11.0
	 *
	 * @param string $css    Stylesheet to search.
	 * @param string $marker Marker to find.
	 * @return int|null Byte offset, or null when absent.
	 */
	private function marker_position( string $css, string $marker ): ?int {
		$pattern = '/^[ \t]*' . preg_quote( $marker, '/' ) . '/m';

		if ( ! preg_match( $pattern, $css, $matches, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		return (int) $matches[0][1];
	}
}
