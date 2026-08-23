<?php
/**
 * Free-build guard.
 *
 * Asserts that no premium-only code and no Freemius secret can reach the zip
 * that ships to WordPress.org. Freemius strips these automatically on deploy;
 * this is the second lock, because a stripping failure is only discovered after
 * the code is already public.
 *
 * Usage:
 *   php bin/check-free-build.php                 Audit the source tree.
 *   php bin/check-free-build.php dist/free       Audit a built free directory.
 *   php bin/check-free-build.php dist/free.zip   Audit a built free zip.
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

const WSAK_ROOT = __DIR__ . '/../';

/**
 * Markers that must never appear in the free build, mapped to why they matter.
 *
 * @var array<string, string>
 */
const WSAK_FORBIDDEN_MARKERS = array(
	'__premium_only'    => 'Pro-only method suffix.',
	'@fs_premium_only'  => 'Pro-only code block tag.',
	'wp_org_gatekeeper' => 'Freemius wp.org gatekeeper secret.',
);

/**
 * Filename patterns that must never appear in the free build.
 *
 * @var string[]
 */
const WSAK_FORBIDDEN_FILES = array( '*-premium.php' );

/**
 * Directories excluded from the marker scan.
 *
 * The freemius/ directory is deliberately here. The SDK contains "__premium_only" and
 * "@fs_premium_only" as part of its own machinery, and Freemius does not strip
 * its own SDK, so a genuine Freemius-generated free zip contains those strings.
 * Scanning it would fail every real release and teach everyone to ignore this
 * guard. What matters is our code, which is what Freemius actually strips.
 *
 * @var string[]
 */
const WSAK_SKIP_DIRS = array( 'node_modules', '.git', 'vendor', 'freemius', 'dist', 'build' );

/**
 * Directories skipped when auditing the source tree only.
 *
 * These are development-only (see .distignore) and this script itself lives in
 * bin/, so scanning them would match on the marker names written here.
 *
 * @var string[]
 */
const WSAK_SOURCE_ONLY_DIRS = array( 'bin', 'tests', '.github' );

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
 * Reports whether a path sits inside a skipped directory.
 *
 * Applies to zip entries, which have no filesystem iterator to filter them.
 *
 * @param string   $path Path relative to the build root.
 * @param string[] $skip Directory names to skip.
 * @return bool
 */
function wsak_is_skipped_path( string $path, array $skip ): bool {
	$segments = explode( '/', str_replace( '\\', '/', $path ) );

	foreach ( $segments as $segment ) {
		if ( in_array( $segment, $skip, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns the scannable PHP files inside a directory.
 *
 * @param string   $directory Directory to walk.
 * @param string[] $skip      Directory names to skip, in addition to WSAK_SKIP_DIRS.
 * @return string[] Relative file paths.
 */
function wsak_php_files( string $directory, array $skip = array() ): array {
	$directory = rtrim( $directory, '/' );
	$found     = array();
	$skip      = array_merge( WSAK_SKIP_DIRS, $skip );

	$iterator = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			static function ( SplFileInfo $file ) use ( $skip ): bool {
				return ! ( $file->isDir() && in_array( $file->getFilename(), $skip, true ) );
			}
		)
	);

	foreach ( $iterator as $file ) {
		if ( $file instanceof SplFileInfo && 'php' === strtolower( $file->getExtension() ) ) {
			$found[] = ltrim( str_replace( $directory, '', $file->getPathname() ), '/' );
		}
	}

	sort( $found );

	return $found;
}

/**
 * Reads every PHP entry of a build target as name => contents.
 *
 * @param string   $target Directory or zip path.
 * @param string[] $skip   Extra directory names to skip.
 * @return array<string, string>
 */
function wsak_read_target( string $target, array $skip = array() ): array {
	$entries = array();

	if ( is_dir( $target ) ) {
		foreach ( wsak_php_files( $target, $skip ) as $relative ) {
			$entries[ $relative ] = (string) file_get_contents( rtrim( $target, '/' ) . '/' . $relative );
		}

		return $entries;
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		wsak_say( 'ERROR: the zip extension is required to audit a zip file.' );
		exit( 1 );
	}

	$zip = new ZipArchive();

	if ( true !== $zip->open( $target ) ) {
		wsak_say( sprintf( 'ERROR: could not open %s', $target ) );
		exit( 1 );
	}

	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive's own property name.
	$total = $zip->numFiles;

	for ( $i = 0; $i < $total; $i++ ) {
		$name = (string) $zip->getNameIndex( $i );

		if ( 'php' !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			continue;
		}

		// Merge in the base list, exactly as wsak_php_files() does for directories.
		if ( wsak_is_skipped_path( $name, array_merge( WSAK_SKIP_DIRS, $skip ) ) ) {
			continue;
		}

		$entries[ $name ] = (string) $zip->getFromIndex( $i );
	}

	$zip->close();

	return $entries;
}

/**
 * Fails the build when a free target contains premium markers.
 *
 * @param string $target Directory or zip believed to be the free build.
 * @return int Exit code.
 */
function wsak_audit_free_build( string $target ): int {
	$entries  = wsak_read_target( $target );
	$failures = array();

	foreach ( $entries as $name => $contents ) {
		foreach ( WSAK_FORBIDDEN_FILES as $pattern ) {
			if ( fnmatch( $pattern, basename( $name ) ) ) {
				$failures[] = sprintf( '%s — matches the Pro-only filename pattern %s', $name, $pattern );
			}
		}

		foreach ( WSAK_FORBIDDEN_MARKERS as $marker => $why ) {
			if ( false !== strpos( $contents, $marker ) ) {
				// The marker name is safe to print; its value never is.
				$failures[] = sprintf( '%s — contains "%s" (%s)', $name, $marker, $why );
			}
		}

		if ( preg_match( "/'is_premium'\s*=>\s*true/", $contents ) ) {
			$failures[] = sprintf( '%s — sets is_premium to true; the free build must set it to false.', $name );
		}
	}

	if ( array() !== $failures ) {
		wsak_say( sprintf( 'FAIL: %d premium leak(s) found in the free build at %s', count( $failures ), $target ) );

		foreach ( $failures as $failure ) {
			wsak_say( '  - ' . $failure );
		}

		return 1;
	}

	wsak_say( sprintf( 'PASS: no premium code or Freemius secret found in %s (%d PHP files checked).', $target, count( $entries ) ) );

	return 0;
}

/**
 * Audits the premium source tree.
 *
 * Premium markers are expected here; what matters is that the gatekeeper secret
 * exists exactly once so Freemius can strip it, and that it is not duplicated
 * into files Freemius does not process.
 *
 * @return int Exit code.
 */
function wsak_audit_source(): int {
	$entries       = wsak_read_target( WSAK_ROOT, WSAK_SOURCE_ONLY_DIRS );
	$gatekeeper_in = array();
	$premium_in    = array();

	foreach ( $entries as $name => $contents ) {
		if ( false !== strpos( $contents, 'wp_org_gatekeeper' ) ) {
			$gatekeeper_in[] = $name;
		}

		if ( false !== strpos( $contents, '__premium_only' ) || false !== strpos( $contents, '@fs_premium_only' ) ) {
			$premium_in[] = $name;
		}
	}

	wsak_say( sprintf( 'Source audit — %d PHP files checked.', count( $entries ) ) );
	wsak_say( sprintf( '  Pro-only code in %d file(s)%s', count( $premium_in ), array() === $premium_in ? '.' : ': ' . implode( ', ', $premium_in ) ) );

	if ( 1 !== count( $gatekeeper_in ) ) {
		wsak_say( sprintf( 'FAIL: the wp.org gatekeeper must appear in exactly one file, found %d.', count( $gatekeeper_in ) ) );

		return 1;
	}

	wsak_say( sprintf( '  Gatekeeper present once, in %s.', $gatekeeper_in[0] ) );
	wsak_say( 'PASS: source tree is shaped correctly for Freemius stripping.' );
	wsak_say( 'NOTE: run this again against the generated free zip before any wp.org submission.' );

	return 0;
}

$wsak_target = $argv[1] ?? '';

exit( '' === $wsak_target ? wsak_audit_source() : wsak_audit_free_build( $wsak_target ) );
