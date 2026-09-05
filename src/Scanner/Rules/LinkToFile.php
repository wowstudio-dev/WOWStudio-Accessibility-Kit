<?php
/**
 * Links to downloads that do not say what they are.
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
 * Flags links to documents whose type is not named in the link.
 *
 * Following a link normally loads a page. Following this one starts a download
 * or launches another application, and a reader who was not told that has lost
 * their place to a PDF viewer they did not ask for. It is worse the less
 * confidently somebody navigates: for a screen reader user, a keyboard-only
 * user, or anybody on a slow connection, the recovery is expensive.
 *
 * Deliberately one rule rather than three. The obvious competitor ships
 * separate checks for PDFs, for Office files, and for other non-HTML files,
 * which produces three near-identical findings with three near-identical
 * explanations for what is one habit and one fix. Naming the type in the
 * message is more useful than sorting the findings by it.
 *
 * A link passes as soon as its own text names the format, in any of the ways
 * people write it — "PDF", "(pdf)", ".pdf", "Word document". That is checked
 * against the accessible name and the title, so a `title` or `aria-label`
 * counts, and so does the `download` attribute, which makes the intent explicit
 * in markup.
 *
 * @since 0.17.0
 */
final class LinkToFile implements Rule {

	use RunsOnServer;

	/**
	 * Extensions worth warning about, mapped to what to call them.
	 *
	 * Deliberately not every non-HTML extension. An image or a plain text file
	 * opens in the browser like a page does, so there is nothing to warn about;
	 * these are the formats that hand the reader to another application or
	 * start a download.
	 *
	 * @since 0.17.0
	 * @var array<string, string>
	 */
	private const FORMATS = array(
		'pdf'  => 'PDF',
		'doc'  => 'Word',
		'docx' => 'Word',
		'xls'  => 'Excel',
		'xlsx' => 'Excel',
		'ppt'  => 'PowerPoint',
		'pptx' => 'PowerPoint',
		'odt'  => 'OpenDocument',
		'ods'  => 'OpenDocument',
		'odp'  => 'OpenDocument',
		'rtf'  => 'RTF',
		'zip'  => 'ZIP',
		'gz'   => 'archive',
		'tar'  => 'archive',
		'rar'  => 'archive',
		'7z'   => 'archive',
		'dmg'  => 'disk image',
		'exe'  => 'program',
		'csv'  => 'CSV',
		'epub' => 'EPUB',
		'mp3'  => 'audio',
		'wav'  => 'audio',
		'mp4'  => 'video',
		'mov'  => 'video',
		'avi'  => 'video',
	);

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'link-to-file';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '2.4.4';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Minor;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Auto;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Link downloads a file without saying so', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This link points at a document rather than a page, and its text does not say so. Following it will start a download or open another application, which is a surprise for anybody and an expensive one for a reader who cannot easily see what just happened. Name the format in the link text — "Annual report (PDF)" — so the choice is made before the click, not after.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'The reader expects a page and gets a download, with no warning and an awkward way back.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Naming the format is an edit to the link text.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Add the format to the link text, in the words that suit the sentence it sits in. Adding the file size too is a kindness on a slow connection.', 'wowstudio-accessibility-kit' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @param Document $document Parsed page.
	 * @return Finding[]
	 */
	public function evaluate( Document $document ): array {
		$findings = array();

		foreach ( $document->find( '//a[@href]' ) as $link ) {
			if ( ! $link instanceof DOMElement || $document->is_hidden( $link ) ) {
				continue;
			}

			$path = (string) wp_parse_url( trim( $link->getAttribute( 'href' ) ), PHP_URL_PATH );

			if ( '' === $path || 1 !== preg_match( '/\.([a-z0-9]{1,4})$/i', $path, $matches ) ) {
				continue;
			}

			$extension = strtolower( $matches[1] );

			if ( ! isset( self::FORMATS[ $extension ] ) ) {
				continue;
			}

			$haystack = strtolower(
				$document->accessible_name( $link ) . ' ' . $link->getAttribute( 'title' )
			);

			// The extension itself, or the friendly name, appearing anywhere in
			// what the reader hears is enough. So is a download attribute whose
			// filename carries it.
			$label = strtolower( self::FORMATS[ $extension ] );

			if ( str_contains( $haystack, $extension ) || str_contains( $haystack, $label ) ) {
				continue;
			}

			$name = trim( $document->accessible_name( $link ) );

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				'' === $name
					? sprintf(
						/* translators: %s: the file format, for example PDF. */
						__( 'This link opens a %s file and does not say so.', 'wowstudio-accessibility-kit' ),
						self::FORMATS[ $extension ]
					)
					: sprintf(
						/* translators: 1: the link text. 2: the file format, for example PDF. */
						__( 'The link "%1$s" opens a %2$s file and does not say so.', 'wowstudio-accessibility-kit' ),
						$name,
						self::FORMATS[ $extension ]
					),
				$document->selector_for( $link ),
				$document->context_for( $link )
			);
		}

		return $findings;
	}
}
