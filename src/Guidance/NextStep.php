<?php
/**
 * The one thing worth doing next.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Guidance;

use WOWStudio\AccessibilityKit\AltText\MediaIndex;
use WOWStudio\AccessibilityKit\SiteFixes\SiteFixManager;

defined( 'ABSPATH' ) || exit;

/**
 * Works out the single most useful next action, or says nothing.
 *
 * A first-run dashboard that reports "nothing has been scanned yet" is honest
 * and useless: it names the absence and leaves the reader to work out what to
 * do about it. This answers the question the empty state raises.
 *
 * Three rules, and they are what keep it from becoming the thing everybody
 * hates:
 *
 * 1. **One step, never a checklist.** A list with ticks turns a tool into
 *    homework, and the unfinished items sit there accusing somebody for months.
 *    This returns exactly one thing, the one that would help most right now.
 * 2. **It goes quiet.** When there is nothing genuinely worth suggesting it
 *    returns null and the interface shows nothing at all. A panel that always
 *    has something to say is furniture within a week, and then it is ignored on
 *    the day it matters.
 * 3. **It never invents urgency.** Each step is phrased as what is available,
 *    not as what is wrong. This plugin already tells people what is wrong; it
 *    does not also need to nag them about the plugin's own features.
 *
 * Computed on the server rather than in the interface so that the command line
 * and anything else can ask the same question and get the same answer.
 *
 * @since 0.28.0
 */
final class NextStep {

	/**
	 * Returns how many site fixes are switched off.
	 *
	 * @since 0.28.0
	 * @var callable(): int
	 */
	private $fixes_off;

	/**
	 * Returns how many images have never been described.
	 *
	 * @since 0.28.0
	 * @var callable(): int
	 */
	private $undescribed;

	/**
	 * Constructor.
	 *
	 * Takes two callables rather than the objects they come from, and both are
	 * called only if the answer is needed. Two reasons, in order of importance.
	 *
	 * Counting undescribed images is a database query, and on a site where the
	 * first suggestion is "check a page" nobody should pay for it — the
	 * dashboard is the most-loaded screen this plugin has.
	 *
	 * And `SiteFixManager` and `MediaIndex` are both final, deliberately. A
	 * test wanting to say "pretend six fixes are off" should not need either of
	 * them opened up: what this class actually depends on is two integers, and
	 * saying so in the signature is more honest than naming two collaborators it
	 * calls one method on each.
	 *
	 * @since 0.28.0
	 *
	 * @param callable(): int|null $fixes_off   How many fixes are off.
	 * @param callable(): int|null $undescribed How many images lack a description.
	 */
	public function __construct( ?callable $fixes_off = null, ?callable $undescribed = null ) {
		$this->fixes_off = $fixes_off ?? static function (): int {
			$off = 0;

			foreach ( ( new SiteFixManager() )->to_array() as $fix ) {
				if ( empty( $fix['enabled'] ) ) {
					++$off;
				}
			}

			return $off;
		};

		$this->undescribed = $undescribed ?? static fn (): int => ( new MediaIndex() )->undescribed_count();
	}

	/**
	 * Returns the next step, or null when there is nothing worth saying.
	 *
	 * @since 0.28.0
	 *
	 * @param array<string, mixed> $overview The report the dashboard is showing.
	 * @return array<string, mixed>|null
	 */
	public function for_overview( array $overview ): ?array {
		$scanned = (int) ( $overview['scanned']['pages'] ?? 0 );
		$open    = (int) ( $overview['issues']['open'] ?? 0 );

		$step = $this->choose( $scanned, $open );

		/**
		 * Filters the next step offered on the dashboard.
		 *
		 * Return null to show nothing at all.
		 *
		 * @since 0.28.0
		 *
		 * @param array<string, mixed>|null $step     The step, or null.
		 * @param array<string, mixed>      $overview The report.
		 */
		return apply_filters( 'wsak_next_step', $step, $overview );
	}

	/**
	 * Picks the step, in order of how much it would help.
	 *
	 * @since 0.28.0
	 *
	 * @param int $scanned How many pages have been scanned.
	 * @param int $open    How many findings are open.
	 * @return array<string, mixed>|null
	 */
	private function choose( int $scanned, int $open ): ?array {
		if ( 0 === $scanned ) {
			return $this->step(
				'scan',
				__( 'Check one page first', 'wowstudio-accessibility-kit' ),
				__( 'Pick a page and check it. It takes a few seconds, nothing is changed, and it is the quickest way to see what this plugin actually reports on your site.', 'wowstudio-accessibility-kit' ),
				__( 'Check a page', 'wowstudio-accessibility-kit' ),
				'scan'
			);
		}

		$off = $this->fixes_available();

		if ( $off > 0 ) {
			return $this->step(
				'fixes',
				__( 'Switch on the site-wide fixes', 'wowstudio-accessibility-kit' ),
				sprintf(
					/* translators: %d: how many site-wide fixes are switched off. */
					_n(
						'There is %d fix you have not switched on. Each one supplies something your theme leaves out — a skip link, a visible focus outline — on every page at once, and switching it off again leaves your site exactly as it was.',
						'There are %d fixes you have not switched on. Each supplies something your theme leaves out — a skip link, a visible focus outline — on every page at once, and switching them off again leaves your site exactly as it was.',
						$off,
						'wowstudio-accessibility-kit'
					),
					$off
				),
				__( 'See the fixes', 'wowstudio-accessibility-kit' ),
				'fixes'
			);
		}

		$undescribed = $this->undescribed();

		if ( $undescribed > 0 ) {
			return $this->step(
				'images',
				__( 'Describe your images', 'wowstudio-accessibility-kit' ),
				sprintf(
					/* translators: %d: how many images have never been described. */
					_n(
						'%d image in your media library has never been described. The Images screen lists them with a field beside each, which is faster than opening them one at a time.',
						'%d images in your media library have never been described. The Images screen lists them with a field beside each, which is faster than opening them one at a time.',
						$undescribed,
						'wowstudio-accessibility-kit'
					),
					$undescribed
				),
				__( 'Open the Images screen', 'wowstudio-accessibility-kit' ),
				'images'
			);
		}

		if ( $open > 0 ) {
			return $this->step(
				'review',
				__( 'Work through what is open', 'wowstudio-accessibility-kit' ),
				sprintf(
					/* translators: %d: how many findings are open. */
					_n(
						'%d finding is waiting. The page view puts each one beside the part of the page it is about, which is usually the quickest way to deal with them.',
						'%d findings are waiting. The page view puts each one beside the part of the page it is about, which is usually the quickest way to deal with them.',
						$open,
						'wowstudio-accessibility-kit'
					),
					$open
				),
				__( 'Go to your content', 'wowstudio-accessibility-kit' ),
				'bulk'
			);
		}

		/*
		 * Nothing left to suggest. Deliberately silent rather than
		 * congratulatory: a clean automated scan means the checks passed, which
		 * is a much smaller statement than the page being usable, and this is
		 * the last place that should imply otherwise.
		 */
		return null;
	}

	/**
	 * Builds one step.
	 *
	 * @since 0.28.0
	 *
	 * @param string $id    Identifier, so an add-on can recognise it.
	 * @param string $title Heading.
	 * @param string $body  What to do, and why.
	 * @param string $label The button.
	 * @param string $view  Which screen the button opens.
	 * @return array<string, string>
	 */
	private function step( string $id, string $title, string $body, string $label, string $view ): array {
		return array(
			'id'    => $id,
			'title' => $title,
			'body'  => $body,
			'label' => $label,
			'view'  => $view,
		);
	}

	/**
	 * Counts site fixes that are available and not switched on.
	 *
	 * @since 0.28.0
	 *
	 * @return int
	 */
	private function fixes_available(): int {
		return (int) ( $this->fixes_off )();
	}

	/**
	 * Counts images that have never been described.
	 *
	 * @since 0.28.0
	 *
	 * @return int
	 */
	private function undescribed(): int {
		return (int) ( $this->undescribed )();
	}
}
