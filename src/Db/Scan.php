<?php
/**
 * Scan record.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Db;

use WOWStudio\AccessibilityKit\Scanner\BrowserPassStatus;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\Scanner\ScanStatus;

defined( 'ABSPATH' ) || exit;

/**
 * One row of the scans table.
 *
 * @since 0.2.0
 */
final class Scan {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param int                  $id          Row ID.
	 * @param ScanScope            $scope       What the scan covered.
	 * @param int                  $target_id   Post ID for a page scan, 0 for a site scan.
	 * @param ScanStatus           $status      Where the run got to.
	 * @param int|null             $score       Score out of 100, or null while running.
	 * @param BrowserPassStatus    $browser_pass Whether the render-dependent checks ran.
	 * @param array<string, mixed> $summary     Decoded summary payload.
	 * @param string               $started_at  MySQL datetime, site timezone.
	 * @param string|null          $finished_at MySQL datetime, or null while running.
	 * @param int                  $created_by  User ID that started the scan.
	 */
	public function __construct(
		public readonly int $id,
		public readonly ScanScope $scope,
		public readonly int $target_id,
		public readonly ScanStatus $status,
		public readonly ?int $score,
		public readonly BrowserPassStatus $browser_pass,
		public readonly array $summary,
		public readonly string $started_at,
		public readonly ?string $finished_at,
		public readonly int $created_by
	) {}

	/**
	 * Builds a scan from a database row.
	 *
	 * Unknown enum values fall back to a sane default rather than throwing: a
	 * row written by a newer version must never fatal an older one.
	 *
	 * @since 0.2.0
	 *
	 * @param object $row Row from $wpdb.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		$summary = json_decode( (string) ( $row->summary ?? '' ), true );

		return new self(
			(int) $row->id,
			ScanScope::tryFrom( (string) $row->scope ) ?? ScanScope::Page,
			(int) $row->target_id,
			ScanStatus::tryFrom( (string) $row->status ) ?? ScanStatus::Running,
			null === $row->score ? null : (int) $row->score,
			BrowserPassStatus::tryFrom( (string) ( $row->browser_pass ?? '' ) ) ?? BrowserPassStatus::Skipped,
			is_array( $summary ) ? $summary : array(),
			(string) $row->started_at,
			null === $row->finished_at ? null : (string) $row->finished_at,
			(int) $row->created_by
		);
	}
}
