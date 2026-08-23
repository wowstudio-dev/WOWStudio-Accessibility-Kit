<?php
/**
 * Claim guard.
 *
 * Product rule #1 is that this plugin assists and never guarantees: nothing it
 * ships may tell a user their site is "compliant", "certified", or protected
 * from legal action. That rule is legal-risk critical and too easy to break by
 * accident in a hurried string change, so it is enforced mechanically.
 *
 * A forbidden phrase is allowed only when the line also negates it (an explicit
 * disclaimer), carries the "wsak:claim-reviewed" annotation on that line or the
 * line above it, or appears verbatim
 * in bin/claims-allowlist.txt. Everything else fails the build. The allowlist
 * matches on exact line text rather than line numbers so that approvals survive
 * edits elsewhere in the file and cannot drift onto a different sentence.
 *
 * Usage:
 *   php bin/check-claims.php            Check the shipped source.
 *   php bin/check-claims.php --verbose  Also list the allowed disclaimers.
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
 * Phrases that must never be asserted about a user's site.
 *
 * @var string[]
 */
const WSAK_FORBIDDEN_CLAIMS = array(
	'compliant',
	'compliance guaranteed',
	'fully accessible',
	'lawsuit',
	'legally safe',
	'legal protection',
	'certified',
	'certification',
	'guarantee',
	'guaranteed',
	'100% accessible',
	'ada compliant',
	'eaa compliant',
	'wcag compliant',
	'risk-free',
);

/**
 * Words that turn a forbidden phrase into an acceptable disclaimer.
 *
 * @var string[]
 */
const WSAK_NEGATIONS = array( 'no ', 'not ', 'never', 'cannot', "n't", 'without', 'does not', 'do not', 'is not', 'are not', 'no claim', 'beware', 'careful' );

/**
 * Explicit opt-out annotation for a reviewed line.
 *
 * @var string
 */
const WSAK_REVIEWED = 'wsak:claim-reviewed';

/**
 * Directories that are not shipped to users.
 *
 * @var string[]
 */
const WSAK_SKIP = array( 'node_modules', '.git', 'vendor', 'freemius', 'dist', 'build', 'tests', 'bin', '.github', 'languages' );

/**
 * File extensions whose contents reach a user.
 *
 * Markdown is excluded on purpose: README.md, CHANGELOG.md, SPEC.md, and the
 * docs/ folder are developer documentation, are listed in .distignore, and have
 * to quote the forbidden words in order to define the rule.
 *
 * @var string[]
 */
const WSAK_EXTENSIONS = array( 'php', 'js', 'jsx', 'ts', 'tsx', 'txt', 'html' );

/**
 * Path to the reviewed-wording allowlist.
 *
 * @var string
 */
const WSAK_ALLOWLIST = __DIR__ . '/claims-allowlist.txt';

/**
 * Loads the approved line texts.
 *
 * @return string[]
 */
function wsak_allowlist(): array {
	if ( ! is_readable( WSAK_ALLOWLIST ) ) {
		return array();
	}

	$lines    = (array) file( WSAK_ALLOWLIST, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	$approved = array();

	foreach ( $lines as $line ) {
		$line = trim( (string) $line );

		if ( '' === $line || str_starts_with( $line, '#' ) ) {
			continue;
		}

		$approved[] = $line;
	}

	return $approved;
}

/**
 * Prints a line to stdout.
 *
 * @param string $message Message to print.
 * @return void
 */
function wsak_say( string $message ): void {
	fwrite( STDOUT, $message . PHP_EOL );
}

/**
 * Returns the user-facing files to inspect.
 *
 * @param string $root Directory to walk.
 * @return string[]
 */
function wsak_files( string $root ): array {
	$root  = rtrim( $root, '/' );
	$found = array();

	$iterator = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			static function ( SplFileInfo $file ): bool {
				return ! ( $file->isDir() && in_array( $file->getFilename(), WSAK_SKIP, true ) );
			}
		)
	);

	foreach ( $iterator as $file ) {
		if ( $file instanceof SplFileInfo && in_array( strtolower( $file->getExtension() ), WSAK_EXTENSIONS, true ) ) {
			$found[] = $file->getPathname();
		}
	}

	sort( $found );

	return $found;
}

/**
 * Reports whether a line negates the claim it contains.
 *
 * @param string $line Lowercased line.
 * @return bool
 */
function wsak_is_disclaimer( string $line ): bool {
	foreach ( WSAK_NEGATIONS as $negation ) {
		if ( false !== strpos( $line, $negation ) ) {
			return true;
		}
	}

	return false;
}

$wsak_root     = __DIR__ . '/..';
$wsak_verbose  = in_array( '--verbose', $argv, true );
$wsak_failures = array();
$wsak_allowed  = array();
$wsak_approved = wsak_allowlist();

foreach ( wsak_files( $wsak_root ) as $wsak_path ) {
	$wsak_lines    = (array) file( $wsak_path, FILE_IGNORE_NEW_LINES );
	$wsak_relative = ltrim( str_replace( realpath( $wsak_root ), '', (string) realpath( $wsak_path ) ), '/' );

	foreach ( $wsak_lines as $wsak_number => $wsak_line ) {
		$wsak_lower = strtolower( (string) $wsak_line );

		foreach ( WSAK_FORBIDDEN_CLAIMS as $wsak_claim ) {
			if ( false === strpos( $wsak_lower, $wsak_claim ) ) {
				continue;
			}

			$wsak_where = sprintf( '%s:%d', $wsak_relative, $wsak_number + 1 );
			$wsak_text  = trim( (string) $wsak_line );

			$wsak_previous  = strtolower( (string) ( $wsak_lines[ $wsak_number - 1 ] ?? '' ) );
			$wsak_annotated = false !== strpos( $wsak_lower, WSAK_REVIEWED ) || false !== strpos( $wsak_previous, WSAK_REVIEWED );

			if ( in_array( $wsak_text, $wsak_approved, true ) || $wsak_annotated || wsak_is_disclaimer( $wsak_lower ) ) {
				$wsak_allowed[] = sprintf( '%s — "%s"', $wsak_where, $wsak_text );
			} else {
				$wsak_failures[] = sprintf( '%s — unqualified "%s" in: %s', $wsak_where, $wsak_claim, $wsak_text );
			}

			break;
		}
	}
}

if ( array() !== $wsak_failures ) {
	wsak_say( sprintf( 'FAIL: %d unqualified compliance claim(s). Product rule #1 is assist, never guarantee.', count( $wsak_failures ) ) );

	foreach ( $wsak_failures as $wsak_failure ) {
		wsak_say( '  - ' . $wsak_failure );
	}

	wsak_say( 'Rewrite as "helps you find / fix / document / monitor", or annotate a reviewed disclaimer with ' . WSAK_REVIEWED . '.' );

	exit( 1 );
}

wsak_say( sprintf( 'PASS: no unqualified compliance claims. %d disclaimer line(s) allowed.', count( $wsak_allowed ) ) );

if ( $wsak_verbose ) {
	foreach ( $wsak_allowed as $wsak_line ) {
		wsak_say( '  - ' . $wsak_line );
	}
}

exit( 0 );
