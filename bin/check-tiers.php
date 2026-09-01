#!/usr/bin/env php
<?php
/**
 * Tier guard.
 *
 * Asserts that every route this product sells is actually gated.
 *
 * The free-build guard answers a different question: it checks that Pro *code*
 * does not leak into the free zip. This one checks that Pro *features* are
 * refused to free sites — which is the failure that costs money quietly, and
 * the one nothing else would notice. A route that forgets its gate works
 * perfectly, passes every test, and gives the paid feature away.
 *
 * Deliberately a grep rather than a runtime check. The gate has to be visible
 * in the file somebody is editing, next to the handler they are changing.
 *
 * Usage:
 *   php bin/check-tiers.php
 *
 * @package WOWStudio\AccessibilityKit
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script, not web output.
// phpcs:disable WordPress.WP.AlternativeFunctions -- CLI script; the WP filesystem API is not loaded.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- CLI script, never loaded by WordPress.

declare( strict_types = 1 );

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

/**
 * Handlers that must refuse a free site, and what each one sells.
 *
 * Keyed by file, then by the method that handles the request. Adding a paid
 * feature means adding a line here; the guard then fails until the gate exists.
 *
 * @var array<string, array<string, string>>
 */
$wsak_paid_handlers = array(
	'src/Rest/RunController.php'        => array(
		'start' => 'Starting a bulk scan over many pages.',
	),
	'src/Rest/AltTextRunController.php' => array(
		'start' => 'Describing many images in one run.',
	),
);

/**
 * Things that count as a gate.
 *
 * @var string[]
 */
$wsak_gate_patterns = array(
	'Plan() )->is_pro()',
	'$plan->is_pro()',
	'wsak_can_use_premium_code()',
);

$wsak_root     = dirname( __DIR__ ) . '/';
$wsak_failures = array();
$wsak_checked  = 0;

foreach ( $wsak_paid_handlers as $wsak_file => $wsak_methods ) {
	$wsak_path = $wsak_root . $wsak_file;

	if ( ! is_readable( $wsak_path ) ) {
		$wsak_failures[] = sprintf( '%s — file is missing, so its gate cannot be checked.', $wsak_file );

		continue;
	}

	$wsak_source = (string) file_get_contents( $wsak_path );

	foreach ( $wsak_methods as $wsak_method => $wsak_sells ) {
		++$wsak_checked;

		$wsak_start = strpos( $wsak_source, 'function ' . $wsak_method . '(' );

		if ( false === $wsak_start ) {
			$wsak_failures[] = sprintf( '%s::%s — handler not found. Renamed without moving its gate?', $wsak_file, $wsak_method );

			continue;
		}

		// The gate has to be near the top of the handler, before any work. A
		// check buried after the side effects is not a gate.
		$wsak_body  = substr( $wsak_source, $wsak_start, 1200 );
		$wsak_found = false;

		foreach ( $wsak_gate_patterns as $wsak_pattern ) {
			if ( str_contains( $wsak_body, $wsak_pattern ) ) {
				$wsak_found = true;

				break;
			}
		}

		if ( ! $wsak_found ) {
			$wsak_failures[] = sprintf(
				'%s::%s — no tier check. This sells: %s',
				$wsak_file,
				$wsak_method,
				$wsak_sells
			);
		}
	}
}

if ( array() !== $wsak_failures ) {
	fwrite( STDOUT, sprintf( "FAIL: %d ungated paid feature(s). A route that forgets its gate gives the product away silently.\n", count( $wsak_failures ) ) );

	foreach ( $wsak_failures as $wsak_failure ) {
		fwrite( STDOUT, '  - ' . $wsak_failure . "\n" );
	}

	fwrite( STDOUT, "Add the check, or — if the boundary genuinely moved — update bin/check-tiers.php in the same commit.\n" );
	exit( 1 );
}

fwrite( STDOUT, sprintf( "PASS: all %d paid feature(s) are gated.\n", $wsak_checked ) );
exit( 0 );
