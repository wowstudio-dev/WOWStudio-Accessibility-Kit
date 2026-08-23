<?php
/**
 * Markup comparison.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

defined( 'ABSPATH' ) || exit;

/**
 * Produces a readable comparison of two pieces of markup.
 *
 * Returns structured segments rather than rendered HTML so the interface can
 * present them accessibly — with text labels rather than colour alone, which
 * matters rather a lot in this plugin of all plugins.
 *
 * @since 0.6.0
 */
final class Diff {

	/**
	 * Compares two strings word by word.
	 *
	 * Word granularity rather than line: the changes this plugin makes are
	 * usually a single attribute inside one long line of markup, and a line diff
	 * would report the whole line as replaced, which tells a reviewer nothing.
	 *
	 * @since 0.6.0
	 *
	 * @param string $before Original markup.
	 * @param string $after  Proposed markup.
	 * @return array<int, array{type: string, value: string}>
	 */
	public static function compare( string $before, string $after ): array {
		$old      = self::tokenise( $before );
		$proposed = self::tokenise( $after );

		$table = self::longest_common_subsequence( $old, $proposed );

		return self::walk( $old, $proposed, $table, count( $old ), count( $proposed ) );
	}

	/**
	 * Reports whether the two differ at all.
	 *
	 * @since 0.6.0
	 *
	 * @param string $before Original markup.
	 * @param string $after  Proposed markup.
	 * @return bool
	 */
	public static function differ( string $before, string $after ): bool {
		return trim( $before ) !== trim( $after );
	}

	/**
	 * Splits markup into comparable tokens.
	 *
	 * @since 0.6.0
	 *
	 * @param string $markup Markup to split.
	 * @return string[]
	 */
	private static function tokenise( string $markup ): array {
		$parts = preg_split( '/(\s+)/u', $markup, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

		return is_array( $parts ) ? $parts : array();
	}

	/**
	 * Builds the length table for the longest common subsequence.
	 *
	 * @since 0.6.0
	 *
	 * @param string[] $old      Original tokens.
	 * @param string[] $proposed Proposed tokens.
	 * @return array<int, array<int, int>>
	 */
	private static function longest_common_subsequence( array $old, array $proposed ): array {
		$rows  = count( $old );
		$cols  = count( $proposed );
		$table = array_fill( 0, $rows + 1, array_fill( 0, $cols + 1, 0 ) );

		for ( $i = 1; $i <= $rows; $i++ ) {
			for ( $j = 1; $j <= $cols; $j++ ) {
				$table[ $i ][ $j ] = $old[ $i - 1 ] === $proposed[ $j - 1 ]
					? $table[ $i - 1 ][ $j - 1 ] + 1
					: max( $table[ $i - 1 ][ $j ], $table[ $i ][ $j - 1 ] );
			}
		}

		return $table;
	}

	/**
	 * Walks the table back into an ordered list of segments.
	 *
	 * @since 0.6.0
	 *
	 * @param string[]                    $old   Original tokens.
	 * @param string[]                    $proposed   Proposed tokens.
	 * @param array<int, array<int, int>> $table Length table.
	 * @param int                         $i     Position in the original.
	 * @param int                         $j     Position in the proposal.
	 * @return array<int, array{type: string, value: string}>
	 */
	private static function walk( array $old, array $proposed, array $table, int $i, int $j ): array {
		$segments = array();

		while ( $i > 0 || $j > 0 ) {
			if ( $i > 0 && $j > 0 && $old[ $i - 1 ] === $proposed[ $j - 1 ] ) {
				array_unshift(
					$segments,
					array(
						'type'  => 'same',
						'value' => $old[ $i - 1 ],
					)
				);
				--$i;
				--$j;
			} elseif ( $j > 0 && ( 0 === $i || $table[ $i ][ $j - 1 ] >= $table[ $i - 1 ][ $j ] ) ) {
				array_unshift(
					$segments,
					array(
						'type'  => 'added',
						'value' => $proposed[ $j - 1 ],
					)
				);
				--$j;
			} else {
				array_unshift(
					$segments,
					array(
						'type'  => 'removed',
						'value' => $old[ $i - 1 ],
					)
				);
				--$i;
			}
		}

		return self::merge( $segments );
	}

	/**
	 * Joins neighbouring segments of the same kind.
	 *
	 * @since 0.6.0
	 *
	 * @param array<int, array{type: string, value: string}> $segments Raw segments.
	 * @return array<int, array{type: string, value: string}>
	 */
	private static function merge( array $segments ): array {
		$merged = array();

		foreach ( $segments as $segment ) {
			$last = count( $merged ) - 1;

			if ( $last >= 0 && $merged[ $last ]['type'] === $segment['type'] ) {
				$merged[ $last ]['value'] .= $segment['value'];

				continue;
			}

			$merged[] = $segment;
		}

		return $merged;
	}
}
