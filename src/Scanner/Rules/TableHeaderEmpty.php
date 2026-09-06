<?php
/**
 * Table header cells with nothing in them.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner\Rules;

use DOMElement;
use WOWStudio\AccessibilityKit\Remediation\FixKind;
use WOWStudio\AccessibilityKit\Remediation\FixPlan;
use WOWStudio\AccessibilityKit\Remediation\FixTarget;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\Document;
use WOWStudio\AccessibilityKit\Scanner\Finding;
use WOWStudio\AccessibilityKit\Scanner\Rule;
use WOWStudio\AccessibilityKit\Scanner\RunsOnServer;
use WOWStudio\AccessibilityKit\Scanner\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Flags `<th>` cells that contain no text.
 *
 * A header cell is not decoration; it is what a screen reader reads out before
 * each data cell so the reader knows what they are hearing. "Tuesday, 14:00"
 * only means anything if the software can say "Day: Tuesday, Opens: 14:00".
 *
 * An empty header breaks that for a whole column or row at once. The table
 * still looks fine — the column below it is full of data, and sighted readers
 * infer the heading from context — while anybody navigating the table cell by
 * cell gets the values with no idea what they are values of.
 *
 * The empty top-left corner cell of a cross-tabulated table is the one common
 * false positive, and it is a real exception: that cell genuinely has nothing
 * to say. It is skipped.
 *
 * @since 0.18.0
 */
final class TableHeaderEmpty implements Rule {

	use RunsOnServer;


	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'table-header-empty';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.3.1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Moderate;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Auto;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Table header cell is empty', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This table has a header cell with no text in it. Header cells are what a screen reader announces before each value, so an empty one leaves a whole row or column of data unlabelled — the table looks complete and reads as a list of numbers with no headings. Give the cell text, or make it a plain td if it is not really a header.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'A row or column of data is announced with no indication of what it is data about.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// What the column is called is a question about the data in it.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'What the column or row should be called is a question about what the data means, so the wording is yours. Edit the table in the editor.', 'wowstudio-accessibility-kit' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @param Document $document Parsed page.
	 * @return Finding[]
	 */
	public function evaluate( Document $document ): array {
		$findings = array();

		foreach ( $document->find( '//th' ) as $cell ) {
			if ( ! $cell instanceof DOMElement || $document->is_hidden( $cell ) ) {
				continue;
			}

			if ( '' !== trim( $document->accessible_name( $cell ) ) ) {
				continue;
			}

			/*
			 * The corner cell of a cross-tabulated table — first cell of the
			 * first row, with headers running both across and down — genuinely
			 * has nothing to say, and every such table has one. Reporting it
			 * would put a finding on the correct markup of every well-built
			 * data table on the site.
			 */
			if ( $this->is_corner_cell( $document, $cell ) ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				__( 'This table has a header cell with no text in it.', 'wowstudio-accessibility-kit' ),
				$document->selector_for( $cell ),
				$document->context_for( $cell )
			);
		}

		return $findings;
	}

	/**
	 * Reports whether this is the empty corner of a cross-tabulated table.
	 *
	 * @since 0.18.0
	 *
	 * @param Document   $document Parsed page.
	 * @param DOMElement $cell     The header cell.
	 * @return bool
	 */
	private function is_corner_cell( Document $document, DOMElement $cell ): bool {
		// First cell of the first row.
		if ( $document->find( 'preceding-sibling::*', $cell )->length > 0 ) {
			return false;
		}

		if ( $document->find( 'ancestor::tr[1]/preceding-sibling::tr', $cell )->length > 0 ) {
			return false;
		}

		// And the table has headers running down the side as well as across,
		// which is what makes the corner meaningless rather than missing.
		return $document->find( 'ancestor::table[1]//tr[position() > 1]/th', $cell )->length > 0;
	}
}
