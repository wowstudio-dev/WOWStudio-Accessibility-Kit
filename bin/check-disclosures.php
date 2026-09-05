<?php
/**
 * Disclosure guard.
 *
 * The claim guard in bin/check-claims.php stops the plugin saying something it
 * must not. This is the other half: it stops the plugin quietly dropping
 * something it must say.
 *
 * Product rules 1, 4 and 5 all rest on wording that lives in ordinary strings —
 * the caveat under the score, the coverage lede, the warning above a suggested
 * fix, the draft banner on an unsigned statement. Any of those can be deleted
 * in a routine refactor without breaking a test or tripping the claim guard,
 * and the result would be a screen that overstates what the tool knows.
 *
 * Each surface below is a place where the product makes, or could be read as
 * making, a statement about conformance. Each names the disclosure it must
 * carry. Reword them freely — update the pattern when you do — but removing one
 * has to be a deliberate act with this file edited to match.
 *
 * Usage:
 *   php bin/check-disclosures.php            Check every surface.
 *   php bin/check-disclosures.php --verbose  Also list what was found.
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
 * Every conformance surface, and the disclosure it must carry.
 *
 * @var array<string, array<string, string>>
 */
const WSAK_SURFACES = array(
	'assets/src/app.js'                           => array(
		'the standing legal-scope disclaimer' => '/does not determine whether your site meets/i',
	),
	'assets/src/components/score-card.js'         => array(
		'the score-is-only-automated caveat'      => '/only what automated testing can settle/i',
		'the score-is-not-a-legal-verdict caveat' => '/does not tell you whether your site meets any legal requirement/i',
	),
	'assets/src/components/coverage-panel.js'     => array(
		'the partial-coverage lede'        => '/finds a portion of accessibility problems, not all of them/i',
		'the clean-scan-means-little line' => '/a clean scan means the automated checks passed, and nothing more/i',
		'the browser-pass caveat'          => '/A scheduled scan, or a scan of a page that will not open in a frame, does not include them/i',
	),
	'assets/src/components/issue-list.js'         => array(
		'the nothing-found caveat'             => '/not the same as the page being accessible/i',
		// The groups became "what this asks of you" in 0.13.0, so the honesty
		// tag moved from the group heading onto every card. It must still be
		// rendered somewhere in this file.
		'the per-finding honesty tag'          => '/<DetectionTag/',
		// Said once, at the top of the band that offers unreviewed fixes.
		'the nothing-here-is-a-guess claim'    => '/Nothing here is a guess/i',
		// And the matching admission at the top of the band that does guess.
		// That band has been unreachable since generation was removed in
		// 0.16.0, so this currently guards a sentence nobody sees. It stays:
		// the day something drafts a fix again, the warning has to already be
		// there, and a guard that was quietly dropped in the meantime would
		// not put it back.
		'the a-draft-is-not-an-answer warning' => '/a draft is not an answer/i',
	),
	// The alt-text screen is the one place this plugin gathers up work it
	// cannot do for you. Two things have to stay on it: that an empty
	// description is somebody's decision rather than a gap, and that the media
	// library is not the whole site.
	'assets/src/components/alt-text-editor.js'    => array(
		'the empty-is-a-decision note'  => '/an empty description is somebody’s decision, not a gap/iu',
		'the not-the-whole-site caveat' => '/it does not cover images added by a theme, a plugin, or a page builder/iu',
	),
	'assets/src/components/alt-text-handoff.js'   => array(
		'the nothing-can-write-it-for-you line' => '/nothing here can write it for you/i',
	),
	'assets/src/components/statement-settings.js' => array(
		'the plugin-cannot-verify-this note' => '/Nothing in this plugin can verify it/i',
		'the sign-off-is-yours note'         => '/not that the plugin has, because it cannot/i',
	),
	'assets/src/components/inspector.js'          => array(
		'the refusal to point at the wrong element' => '/Rather than point at the wrong one, we are pointing at none/i',
		'the blocked-preview caveat'                => '/nothing that depends on seeing the page — colour, text size, layout — was checked/iu',
		'the unplaceable-findings note'             => '/findings from the markup cannot be pointed at here/i',
		'the cannot-edit-CSS note'                  => '/your account is not allowed to change/i',
	),
	'assets/src/scanner/repair.js'                => array(
		// Moved here in 0.11.0 from the inspector, when these findings gained
		// a fix of their own. The sentence has to keep saying that a stylesheet
		// cannot reach them, because that is the part somebody acts on.
		'the attributes-not-styles note' => '/no stylesheet can add them/i',
		'the markup-not-styles note'     => '/which a stylesheet cannot change/i',
	),
	'assets/src/components/tags.js'               => array(
		'the auto-detected honesty tag'       => '/Auto-detected/',
		'the needs-manual-review honesty tag' => '/Needs manual review/',
	),
	// The editor panel checks what it can see from inside the editor, which is
	// less than a scan sees. A panel that goes quiet reads as "this page is
	// fine", so the scope has to be stated on every reply rather than only when
	// nothing is found.
	'src/Rest/BlockCheckController.php'           => array(
		'the editor-scope caveat' => '/Colour, text size and layout need the page open in a browser/i',
	),
	'assets/src/components/theme-panel.js'        => array(
		// The theme panel can come back empty, and empty is easily read as a
		// clean bill of health. It is not.
		'the clean-theme-is-not-a-verdict caveat' => '/not a clean bill of health/i',
	),
	'src/Rest/RunController.php'                  => array(
		// The document somebody emails to a developer travels furthest of
		// anything this plugin writes, so it carries the coverage caveat with it.
		'the handover-is-not-an-audit caveat' => '/a starting point rather than a complete audit/i',
	),
	'assets/src/components/rendered-queue.js'     => array(
		// The one thing here that cannot be left to run. Said before it starts,
		// not discovered when somebody closes the tab.
		'the needs-your-browser caveat' => '/only runs while this tab is open/i',
		// And the reason a page can come back with nothing.
		'the frame-refused caveat'      => '/would not open here/i',
	),
	'assets/src/editor/block-finding.js'          => array(
		'the writes-into-your-content note' => '/writes into the block itself/i',
	),
	'src/Conformance/StatementGenerator.php'      => array(
		'the automated-testing-is-partial caveat'   => '/Automated testing finds only some accessibility problems/iu',
		'the draft banner on an unsigned statement' => '/Draft — not yet reviewed or approved/iu',
		'the nobody-has-checked-this line'          => '/nobody has confirmed that it is accurate/iu',
	),
	'readme.txt'                                  => array(
		'the legal-scope disclaimer'            => '/\*\*does not\*\* determine or certify/i',
		'the automated-testing-is-partial line' => '/Automated testing can only detect part of WCAG/i',
	),
);

/**
 * Prints a line to stdout.
 *
 * @param string $message Message to print.
 * @return void
 */
function wsak_say( string $message ): void {
	fwrite( STDOUT, $message . PHP_EOL );
}

$wsak_root = dirname( __DIR__ );
// register_argc_argv can be off even under the CLI SAPI, so $argv is not
// guaranteed; $_SERVER carries it wherever it is set at all.
$wsak_verbose  = in_array( '--verbose', (array) ( $_SERVER['argv'] ?? array() ), true );
$wsak_failures = array();
$wsak_found    = array();

foreach ( WSAK_SURFACES as $wsak_surface => $wsak_required ) {
	$wsak_path = $wsak_root . '/' . $wsak_surface;

	if ( ! is_readable( $wsak_path ) ) {
		$wsak_failures[] = sprintf( '%s — surface is missing. If it moved, update bin/check-disclosures.php.', $wsak_surface );

		continue;
	}

	$wsak_contents = (string) file_get_contents( $wsak_path );

	foreach ( $wsak_required as $wsak_label => $wsak_pattern ) {
		if ( 1 === preg_match( $wsak_pattern, $wsak_contents ) ) {
			$wsak_found[] = sprintf( '%s — %s', $wsak_surface, $wsak_label );
		} else {
			$wsak_failures[] = sprintf( '%s — lost %s (no match for %s)', $wsak_surface, $wsak_label, $wsak_pattern );
		}
	}
}

if ( array() !== $wsak_failures ) {
	wsak_say( sprintf( 'FAIL: %d missing disclosure(s). The product may not quietly stop saying what it knows it cannot do.', count( $wsak_failures ) ) );

	foreach ( $wsak_failures as $wsak_failure ) {
		wsak_say( '  - ' . $wsak_failure );
	}

	wsak_say( 'Restore the wording, or — if it genuinely moved or was reworded — update bin/check-disclosures.php in the same commit.' );

	exit( 1 );
}

wsak_say( sprintf( 'PASS: all %d disclosure(s) present across %d conformance surface(s).', count( $wsak_found ), count( WSAK_SURFACES ) ) );

if ( $wsak_verbose ) {
	foreach ( $wsak_found as $wsak_line ) {
		wsak_say( '  - ' . $wsak_line );
	}
}

exit( 0 );
