<?php
/**
 * The checks added in 0.17.0 and 0.18.0.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Scanner\Finding;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the twenty-three checks added in 0.17.0 and 0.18.0.
 *
 * Every rule gets a pair: markup that must trip it, and the nearest markup that
 * must not. The second half is the half that matters. A check that fires on
 * everything is worse than no check — somebody has to read each finding, and a
 * list padded with things that were already correct trains people to skim past
 * the ones that are not.
 *
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\AriaReferenceBroken
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\AudioNeedsTranscript
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\BoldTextAsHeading
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\DuplicateId
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\InputImageAltMissing
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\TabindexPositive
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\TableHeaderEmpty
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\TextBlinking
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\TextJustified
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\UnderlineNotALink
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\VideoNeedsCaptions
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\ViewportScalingDisabled
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\FormLabelOrphaned
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\HeadingEmpty
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\ImageAltIsFilename
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\ImageAltRedundant
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\ImageAltTooLong
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\ImageMapAreaAltMissing
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\LinkAnchorBroken
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\LinkNotKeyboardReachable
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\LinkOpensNewWindow
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\LinkToFile
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\PageHasNoHeadings
 */
final class ContentRulesTest extends TestCase {

	/**
	 * Wraps a body fragment in a page that trips nothing on its own.
	 *
	 * Several of these rules refuse to run on anything but a full page, because
	 * the element they are looking for may simply be in the part of the
	 * document a fragment does not contain. So the fixture has to be a whole
	 * document — and a quiet one, or every assertion would be counting other
	 * rules' findings.
	 *
	 * @param string $body Body markup.
	 * @return string
	 */
	private function page( string $body ): string {
		return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><title>A page</title></head>
<body>
<main>
<h1>A heading</h1>
{$body}
</main>
</body>
</html>
HTML;
	}

	/**
	 * Returns how many findings of one rule the markup produces.
	 *
	 * @param string $rule_id Rule to count.
	 * @param string $body    Body markup.
	 * @return int
	 */
	private function count_of( string $rule_id, string $body ): int {
		$result = ( new Engine() )->scan( $this->page( $body ) );

		$this->assertNotNull( $result );

		$matching = array_filter(
			$result->findings,
			static fn( Finding $finding ): bool => $finding->rule_id === $rule_id
		);

		return count( $matching );
	}

	/**
	 * Asserts a rule fires on one markup and not on the other.
	 *
	 * @param string $rule_id Rule under test.
	 * @param string $bad     Markup that must be reported.
	 * @param string $good    Markup that must not be.
	 * @return void
	 */
	private function assertFiresOnlyOn( string $rule_id, string $bad, string $good ): void {
		$this->assertSame( 1, $this->count_of( $rule_id, $bad ), $rule_id . ' did not fire on markup that should trip it.' );
		$this->assertSame( 0, $this->count_of( $rule_id, $good ), $rule_id . ' fired on markup that is correct.' );
	}

	/**
	 * Alt text that is a file name, a placeholder, or the src repeated.
	 *
	 * @return void
	 */
	public function test_alt_that_is_really_a_filename(): void {
		$this->assertFiresOnlyOn(
			'img-alt-is-filename',
			'<img src="/uploads/DSC_04412.jpg" alt="DSC_04412.jpg">',
			'<img src="/uploads/DSC_04412.jpg" alt="A heron on a wet fence post">'
		);

		$this->assertSame( 1, $this->count_of( 'img-alt-is-filename', '<img src="/a.png" alt="image">' ), 'A bare placeholder word is not a description.' );
		$this->assertSame( 0, $this->count_of( 'img-alt-is-filename', '<img src="/a.png" alt="Image of the north façade">' ), 'A description that merely contains "image" is doing its job.' );

		// The same name, punctuated differently. An importer that tidies the
		// hyphens has still not written a description.
		$this->assertSame( 1, $this->count_of( 'img-alt-is-filename', '<img src="/uploads/north-facade.jpg" alt="north facade">' ) );

		// An empty alt is a decision, not a gap, and none of this rule's business.
		$this->assertSame( 0, $this->count_of( 'img-alt-is-filename', '<img src="/a.png" alt="">' ) );
	}

	/**
	 * Alt text long enough to be a paragraph.
	 *
	 * @return void
	 */
	public function test_alt_long_enough_to_be_a_paragraph(): void {
		$long  = str_repeat( 'a description that keeps going ', 8 );
		$short = 'A heron on a wet fence post';

		$this->assertFiresOnlyOn(
			'img-alt-too-long',
			'<img src="/a.png" alt="' . $long . '">',
			'<img src="/a.png" alt="' . $short . '">'
		);
	}

	/**
	 * Alt text repeating the caption or the title attribute.
	 *
	 * @return void
	 */
	public function test_alt_repeating_what_is_already_said(): void {
		$this->assertFiresOnlyOn(
			'img-alt-redundant',
			'<figure><img src="/a.png" alt="Mayor Chen opens the library"><figcaption>Mayor Chen opens the library</figcaption></figure>',
			'<figure><img src="/a.png" alt="A woman cutting a ribbon"><figcaption>Mayor Chen opens the library</figcaption></figure>'
		);

		$this->assertSame( 1, $this->count_of( 'img-alt-redundant', '<img src="/a.png" alt="A heron" title="A heron">' ) );

		// Two figures, each with its own caption. The comparison has to be
		// relative to each image; an absolute one would compare every image
		// against the first caption on the page.
		$this->assertSame(
			0,
			$this->count_of(
				'img-alt-redundant',
				'<figure><img src="/a.png" alt="A heron"><figcaption>By the canal</figcaption></figure>'
				. '<figure><img src="/b.png" alt="A fence"><figcaption>A heron</figcaption></figure>'
			),
			'Captions must be matched to their own image.'
		);
	}

	/**
	 * Image-map regions with no name.
	 *
	 * @return void
	 */
	public function test_image_map_regions_without_alt(): void {
		$this->assertFiresOnlyOn(
			'image-map-area-alt-missing',
			'<map name="m"><area shape="rect" coords="0,0,9,9" href="/north"></map>',
			'<map name="m"><area shape="rect" coords="0,0,9,9" href="/north" alt="North wing"></map>'
		);

		$this->assertSame( 0, $this->count_of( 'image-map-area-alt-missing', '<map name="m"><area shape="rect" coords="0,0,9,9" href="/n" aria-label="North wing"></map>' ), 'aria-label names the region too.' );
	}

	/**
	 * Links that open a new tab without saying so.
	 *
	 * @return void
	 */
	public function test_links_opening_a_new_tab(): void {
		$this->assertFiresOnlyOn(
			'link-opens-new-window',
			'<a href="/report" target="_blank">The annual report</a>',
			'<a href="/report" target="_blank">The annual report (opens in a new tab)</a>'
		);

		$this->assertSame( 0, $this->count_of( 'link-opens-new-window', '<a href="/r" target="_self">Report</a>' ), 'Only _blank escapes the back button.' );
		$this->assertSame( 0, $this->count_of( 'link-opens-new-window', '<a href="/r" target="_blank" title="Opens in a new window">Report</a>' ), 'The warning may live in the title.' );
	}

	/**
	 * Links to downloads that do not name the format.
	 *
	 * @return void
	 */
	public function test_links_to_downloads(): void {
		$this->assertFiresOnlyOn(
			'link-to-file',
			'<a href="/files/annual-report.pdf">The annual report</a>',
			'<a href="/files/annual-report.pdf">The annual report (PDF)</a>'
		);

		$this->assertSame( 1, $this->count_of( 'link-to-file', '<a href="/files/budget.xlsx">Budget</a>' ) );
		$this->assertSame( 0, $this->count_of( 'link-to-file', '<a href="/files/budget.xlsx">Budget (Excel spreadsheet)</a>' ) );
		$this->assertSame( 0, $this->count_of( 'link-to-file', '<a href="/about">About us</a>' ), 'An ordinary page link is not a download.' );
		$this->assertSame( 0, $this->count_of( 'link-to-file', '<a href="/logo.png">Our logo</a>' ), 'An image opens in the browser like a page does.' );
	}

	/**
	 * In-page links whose target is not there.
	 *
	 * @return void
	 */
	public function test_in_page_links_pointing_at_nothing(): void {
		$this->assertFiresOnlyOn(
			'link-anchor-broken',
			'<a href="#content">Skip to content</a><div id="main-content">Hello</div>',
			'<a href="#content">Skip to content</a><div id="content">Hello</div>'
		);

		$this->assertSame( 0, $this->count_of( 'link-anchor-broken', '<a href="#top">Back to top</a>' ), '#top needs no element.' );
		$this->assertSame( 0, $this->count_of( 'link-anchor-broken', '<a href="#s">Jump</a><a name="s"></a>' ), 'The old name attribute is still a jump target.' );
		$this->assertSame( 0, $this->count_of( 'link-anchor-broken', '<a href="/elsewhere">Elsewhere</a>' ), 'Only in-page links are in scope.' );
	}

	/**
	 * Anchors that click but cannot be tabbed to.
	 *
	 * @return void
	 */
	public function test_anchors_the_keyboard_cannot_reach(): void {
		$this->assertFiresOnlyOn(
			'link-not-keyboard-reachable',
			'<a onclick="open()">Open the menu</a>',
			'<a href="/menu">Open the menu</a>'
		);

		$this->assertSame( 0, $this->count_of( 'link-not-keyboard-reachable', '<a name="section-3"></a>' ), 'A named anchor is not a control.' );
		$this->assertSame( 0, $this->count_of( 'link-not-keyboard-reachable', '<a onclick="open()" tabindex="0">Open</a>' ), 'A tabindex puts it back in the tab order.' );
		$this->assertSame( 1, $this->count_of( 'link-not-keyboard-reachable', '<a role="button">Open</a>' ), 'A role that claims it is a control counts as evidence.' );
	}

	/**
	 * Headings with nothing to announce.
	 *
	 * @return void
	 */
	public function test_headings_with_no_text(): void {
		$this->assertFiresOnlyOn(
			'heading-empty',
			'<h2></h2>',
			'<h2>Opening hours</h2>'
		);

		$this->assertSame( 0, $this->count_of( 'heading-empty', '<h2><img src="/logo.png" alt="Acme"></h2>' ), 'Alt text inside a heading is what it announces.' );
		$this->assertSame( 1, $this->count_of( 'heading-empty', '<h2><img src="/logo.png" alt=""></h2>' ), 'A decorative image announces nothing.' );
	}

	/**
	 * A page with no headings at all.
	 *
	 * @return void
	 */
	public function test_a_page_with_no_headings(): void {
		// The shared fixture has an h1, so this one is built by hand.
		$bare = '<!DOCTYPE html><html lang="en"><head><title>T</title></head><body><main><p>Just text.</p></main></body></html>';
		$with = '<!DOCTYPE html><html lang="en"><head><title>T</title></head><body><main><h1>A heading</h1><p>Text.</p></main></body></html>';

		$result = ( new Engine() )->scan( $bare );
		$this->assertNotNull( $result );
		$ids = array_map( static fn( Finding $f ): string => $f->rule_id, $result->findings );
		$this->assertContains( 'page-has-no-headings', $ids );

		$result = ( new Engine() )->scan( $with );
		$this->assertNotNull( $result );
		$ids = array_map( static fn( Finding $f ): string => $f->rule_id, $result->findings );
		$this->assertNotContains( 'page-has-no-headings', $ids );
	}

	/**
	 * Labels attached to nothing, and fields wearing two.
	 *
	 * @return void
	 */
	public function test_labels_attached_to_the_wrong_thing(): void {
		$this->assertFiresOnlyOn(
			'form-label-orphaned',
			'<label for="email">Email</label><input type="text" id="email-address">',
			'<label for="email">Email</label><input type="text" id="email">'
		);

		$this->assertSame(
			1,
			$this->count_of( 'form-label-orphaned', '<label for="e">Email</label><label for="e">Your email</label><input type="text" id="e">' ),
			'A second label for the same field is reported once, on the later one.'
		);
	}

	/**
	 * ARIA attributes pointing at ids that are not there.
	 *
	 * @return void
	 */
	public function test_aria_references_pointing_at_nothing(): void {
		$this->assertFiresOnlyOn(
			'aria-reference-broken',
			'<div role="group" aria-labelledby="billing-heading"><p>Fields</p></div>',
			'<h2 id="billing-heading">Billing</h2><div role="group" aria-labelledby="billing-heading"><p>Fields</p></div>'
		);

		$this->assertSame(
			1,
			$this->count_of( 'aria-reference-broken', '<h2 id="a">A</h2><div aria-describedby="a b">x</div>' ),
			'One finding per element, listing whichever ids are missing.'
		);
	}


	/**
	 * Header cells with nothing in them.
	 *
	 * @return void
	 */
	public function test_empty_table_header_cells(): void {
		$this->assertFiresOnlyOn(
			'table-header-empty',
			'<table><tr><th>Day</th><th></th></tr><tr><td>Mon</td><td>9am</td></tr></table>',
			'<table><tr><th>Day</th><th>Opens</th></tr><tr><td>Mon</td><td>9am</td></tr></table>'
		);

		// The corner of a cross-tabulated table genuinely has nothing to say,
		// and every such table has one.
		$this->assertSame(
			0,
			$this->count_of(
				'table-header-empty',
				'<table><tr><th></th><th>Mon</th></tr><tr><th>Opens</th><td>9am</td></tr></table>'
			),
			'The empty corner of a cross-tabulated table is correct markup.'
		);
	}

	/**
	 * Image buttons with no name.
	 *
	 * @return void
	 */
	public function test_image_buttons_without_alt(): void {
		$this->assertFiresOnlyOn(
			'input-image-alt-missing',
			'<input type="image" src="/search.png">',
			'<input type="image" src="/search.png" alt="Search">'
		);

		$this->assertSame( 0, $this->count_of( 'input-image-alt-missing', '<input type="image" src="/s.png" aria-label="Search">' ), 'aria-label names it too.' );
	}

	/**
	 * The same id twice.
	 *
	 * @return void
	 */
	public function test_ids_used_more_than_once(): void {
		$this->assertFiresOnlyOn(
			'duplicate-id',
			'<div id="signup">A</div><div id="signup">B</div>',
			'<div id="signup">A</div><div id="signup-2">B</div>'
		);

		// One finding per duplicated id, not one per extra copy: a template
		// rendered three times should not report twice for the same cause.
		$this->assertSame(
			1,
			$this->count_of( 'duplicate-id', '<p id="x">A</p><p id="x">B</p><p id="x">C</p>' )
		);
	}

	/**
	 * Positive tabindex, which rebuilds the focus order.
	 *
	 * @return void
	 */
	public function test_positive_tabindex(): void {
		$this->assertFiresOnlyOn(
			'tabindex-positive',
			'<button tabindex="3">Send</button>',
			'<button tabindex="0">Send</button>'
		);

		$this->assertSame( 0, $this->count_of( 'tabindex-positive', '<div tabindex="-1">Focus target</div>' ), 'Minus one is how dialogs and skip targets are meant to work.' );
	}

	/**
	 * A viewport that blocks zooming.
	 *
	 * @return void
	 */
	public function test_viewport_that_cannot_be_zoomed(): void {
		$head = static fn( string $viewport ): string => '<!DOCTYPE html><html lang="en"><head><title>T</title>'
			. $viewport . '</head><body><main><h1>A</h1></main></body></html>';

		$ids = static function ( string $html ): array {
			$result = ( new Engine() )->scan( $html );

			return array_map( static fn( Finding $f ): string => $f->rule_id, $result->findings );
		};

		$this->assertContains( 'viewport-scaling-disabled', $ids( $head( '<meta name="viewport" content="width=device-width, user-scalable=no">' ) ) );
		$this->assertContains( 'viewport-scaling-disabled', $ids( $head( '<meta name="viewport" content="width=device-width, maximum-scale=1.0">' ) ), 'A cap below 2 fails 1.4.4 just as surely.' );
		$this->assertNotContains( 'viewport-scaling-disabled', $ids( $head( '<meta name="viewport" content="width=device-width, initial-scale=1">' ) ) );
		$this->assertNotContains( 'viewport-scaling-disabled', $ids( $head( '<meta name="viewport" content="width=device-width, maximum-scale=5">' ) ) );
	}

	/**
	 * Content that moves on its own.
	 *
	 * @return void
	 */
	public function test_blinking_and_scrolling_content(): void {
		$this->assertFiresOnlyOn(
			'text-blinking',
			'<marquee>Latest news</marquee>',
			'<p>Latest news</p>'
		);
	}

	/**
	 * Underlines that are not links.
	 *
	 * @return void
	 */
	public function test_underlined_text_that_is_not_a_link(): void {
		$this->assertFiresOnlyOn(
			'underline-not-a-link',
			'<p>Please read the <u>terms</u> first.</p>',
			'<p>Please read the <em>terms</em> first.</p>'
		);

		$this->assertSame( 0, $this->count_of( 'underline-not-a-link', '<a href="/terms"><u>Terms</u></a>' ), 'Inside a link the underline is telling the truth.' );
	}

	/**
	 * Justified text.
	 *
	 * @return void
	 */
	public function test_justified_text(): void {
		$this->assertFiresOnlyOn(
			'text-justified',
			'<p style="text-align: justify">A paragraph.</p>',
			'<p style="text-align: left">A paragraph.</p>'
		);

		$this->assertSame(
			0,
			$this->count_of( 'text-justified', '<div style="display:flex;justify-content:space-between"><span>A</span></div>' ),
			'justify-content is a flex property and has nothing to do with text.'
		);
	}

	/**
	 * Video with no caption track.
	 *
	 * @return void
	 */
	public function test_video_without_captions(): void {
		$this->assertFiresOnlyOn(
			'video-needs-captions',
			'<video src="/talk.mp4"></video>',
			'<video src="/talk.mp4"><track kind="captions" src="/talk.vtt"></video>'
		);
	}

	/**
	 * Audio, which always needs asking about.
	 *
	 * @return void
	 */
	public function test_audio_prompts_for_a_transcript(): void {
		$this->assertSame( 1, $this->count_of( 'audio-needs-transcript', '<audio src="/episode.mp3"></audio>' ) );
		$this->assertSame( 0, $this->count_of( 'audio-needs-transcript', '<p>No audio here.</p>' ) );
	}

	/**
	 * Bold paragraphs standing in for headings.
	 *
	 * @return void
	 */
	public function test_bold_paragraphs_used_as_headings(): void {
		$this->assertFiresOnlyOn(
			'bold-text-as-heading',
			'<p><strong>Opening hours</strong></p>',
			'<h2>Opening hours</h2>'
		);

		$this->assertSame( 0, $this->count_of( 'bold-text-as-heading', '<p><strong>We are closed on Sunday.</strong></p>' ), 'Sentence punctuation says it is a sentence.' );
		$this->assertSame( 0, $this->count_of( 'bold-text-as-heading', '<p>Please note the <strong>new hours</strong> below</p>' ), 'The bold run has to be the whole paragraph.' );

		// The case the dogfooding test caught: a bold line inside something
		// that already declares what it is.
		$this->assertSame(
			0,
			$this->count_of( 'bold-text-as-heading', '<div role="note"><p><strong>Draft — not yet approved</strong></p></div>' ),
			'A role attribute means somebody has already decided what this region is.'
		);
		$this->assertSame( 0, $this->count_of( 'bold-text-as-heading', '<li><strong>Tuesday</strong></li>' ), 'A bold line in a list item is doing a different job.' );
	}

	/**
	 * None of the new rules fire on a page that is doing everything right.
	 *
	 * The single most useful assertion here. Each rule above proves it stays
	 * quiet on its own counterexample; this proves they stay quiet together, on
	 * ordinary correct markup of the kind most of a real site is made of.
	 *
	 * @return void
	 */
	public function test_nothing_fires_on_a_page_that_is_correct(): void {
		$body = '<h2>Opening hours</h2>'
			. '<p>We open at nine.</p>'
			. '<figure><img src="/uploads/heron.jpg" alt="A heron on a fence post"><figcaption>By the canal</figcaption></figure>'
			. '<img src="/uploads/divider.png" alt="">'
			. '<a href="/about">About us</a>'
			. '<a href="/report.pdf">The annual report (PDF)</a>'
			. '<a href="/partner" target="_blank">Our partner (opens in a new tab)</a>'
			. '<a href="#hours">Jump to opening hours</a><div id="hours">Nine.</div>'
			. '<h2 id="signup-heading">Sign up</h2>'
			. '<form aria-labelledby="signup-heading"><label for="email">Email</label><input type="text" id="email"></form>';

		$result = ( new Engine() )->scan( $this->page( $body ) );

		$this->assertNotNull( $result );

		$new = array(
			'img-alt-is-filename',
			'img-alt-too-long',
			'img-alt-redundant',
			'image-map-area-alt-missing',
			'link-opens-new-window',
			'link-to-file',
			'link-anchor-broken',
			'link-not-keyboard-reachable',
			'heading-empty',
			'page-has-no-headings',
			'form-label-orphaned',
			'aria-reference-broken',
			'input-image-alt-missing',
			'table-header-empty',
			'duplicate-id',
			'tabindex-positive',
			'viewport-scaling-disabled',
			'text-blinking',
			'text-justified',
			'underline-not-a-link',
			'bold-text-as-heading',
			'video-needs-captions',
			'audio-needs-transcript',
		);

		$fired = array_values(
			array_unique(
				array_filter(
					array_map( static fn( Finding $f ): string => $f->rule_id, $result->findings ),
					static fn( string $id ): bool => in_array( $id, $new, true )
				)
			)
		);

		$this->assertSame( array(), $fired, 'A new check fired on markup that is correct: ' . implode( ', ', $fired ) );
	}
}
