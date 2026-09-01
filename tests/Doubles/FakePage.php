<?php
/**
 * A page scanner that records instead of scanning.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Doubles;

use RuntimeException;
use WOWStudio\AccessibilityKit\Scanner\FetchStrategy;
use WOWStudio\AccessibilityKit\Scanner\PageScanner;

/**
 * Notes which posts it was asked about, and can be told to throw on one.
 */
final class FakePage extends PageScanner {

	/**
	 * Posts scanned, in order.
	 *
	 * @var int[]
	 */
	public array $scanned = array();

	/**
	 * The strategy each call asked for, so a test can assert bulk uses content.
	 *
	 * @var array<int, FetchStrategy|null>
	 */
	public array $strategies = array();

	/**
	 * Post to throw on, or 0 for none.
	 *
	 * @var int
	 */
	public int $throw_on = 0;

	/**
	 * Scan storage, so this double closes the row exactly as the real one does.
	 *
	 * @var FakeScanStore
	 */
	private FakeScanStore $scans;

	/**
	 * Constructor.
	 *
	 * @param FakeScanStore $scans Scan storage.
	 */
	public function __construct( FakeScanStore $scans ) {
		$this->scans = $scans;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int                $scan_id  Scan.
	 * @param int                $post_id  Post.
	 * @param FetchStrategy|null $strategy How the markup was to be fetched.
	 * @return array<string, mixed>
	 * @throws RuntimeException When told to fail on this post.
	 */
	public function run( int $scan_id, int $post_id, ?FetchStrategy $strategy = null ) {
		$this->strategies[] = $strategy;

		if ( $this->throw_on === $post_id ) {
			throw new RuntimeException( 'this page is no good' );
		}

		$this->scanned[] = $post_id;

		// The real scanner closes the row it was handed. A double that did not
		// would leave every run permanently unfinished, and the tests that
		// depend on a run settling would be testing the double.
		$this->scans->complete( $scan_id, 100 );

		return array( 'score' => 100 );
	}
}
