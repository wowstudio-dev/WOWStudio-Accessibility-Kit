<?php
/**
 * Saying something when a search is submitted empty.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\ProvidesCss;
use WOWStudio\AccessibilityKit\SiteFixes\RunsInBrowser;

defined( 'ABSPATH' ) || exit;

/**
 * Stops an empty search and says why, instead of submitting nothing.
 *
 * Submitting an empty search usually loads a page that either lists everything
 * or says "no results", and in neither case does anything explain that the
 * reader simply did not type anything. For somebody using a screen reader the
 * whole page has changed and the reason is nowhere on it.
 *
 * The correction is to refuse the submission and say so where the field is: a
 * message in a live region, and focus moved back to the input so the next
 * keystroke goes where it is needed. That is the behaviour a validation error
 * should have anyway, which this form never had.
 *
 * It only acts on a form that is recognisably WordPress's search — one with
 * `role="search"`, or a field named `s` — so that a plugin's own filter form is
 * not quietly given behaviour it never asked for.
 *
 * @since 0.20.0
 */
final class EmptySearchMessage implements RunsInBrowser, ProvidesCss {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'empty-search-message';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Explain an empty search', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'When somebody presses Search without typing anything, this keeps them on the page and says so beside the field, instead of loading a results page that cannot explain itself. Focus moves back to the search box so the next keystroke lands where it should.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'Changes what your search form does: an empty search no longer submits. Only WordPress\'s own search form is affected, and with JavaScript off the form behaves exactly as it does now.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		// Carried out in the browser. The manager enqueues the one script that
		// runs every enabled fix of this kind, and tells it this one is on.
	}

	/**
	 * {@inheritDoc}
	 *
	 * Deliberately almost nothing: enough for the message to be readable and
	 * to sit under the field, and no colour of its own beyond the one WCAG
	 * cares about. A validation message styled to match this plugin rather
	 * than the site it appears on would look like something went wrong with
	 * the site.
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function css(): string {
		return '/* The empty-search message. Kept plain so it inherits the site. */
.wsak-search-message {
	margin: 0.5em 0 0;
	font-size: 0.9em;
	color: #b42318;
}';
	}
}
