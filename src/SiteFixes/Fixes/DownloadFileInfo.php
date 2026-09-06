<?php
/**
 * Naming the format and size of a linked download.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\SiteFix;

defined( 'ABSPATH' ) || exit;

/**
 * Appends "(PDF, 1.2 MB)" to content links that point at a document.
 *
 * Following a link normally loads a page. Following this one hands the reader to
 * another application or starts a download, and somebody who was not told has
 * lost their place to a PDF viewer they did not ask for. The cost is highest for
 * the people who navigate least confidently, and for anybody paying for their
 * data by the megabyte — which is why the size is worth saying as well as the
 * format.
 *
 * Unlike the new-tab warning, this is shown rather than hidden. It is
 * information every reader benefits from, and putting it on screen is the
 * long-standing convention for exactly that reason.
 *
 * Sizes come from WordPress's own attachment metadata, looked up by URL, and are
 * omitted when the file is not in the media library. That is a deliberate limit:
 * the alternative is a filesystem call, or worse a network request, for every
 * link on every render of every post, and a fix that makes pages slow is a fix
 * people switch off.
 *
 * Inserts and never re-serialises, for the same reason NewWindowWarning does.
 *
 * @since 0.19.0
 */
final class DownloadFileInfo implements SiteFix {

	/**
	 * Extensions worth naming, mapped to what to call them.
	 *
	 * @since 0.19.0
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
		'csv'  => 'CSV',
		'zip'  => 'ZIP',
		'epub' => 'EPUB',
	);

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'download-file-info';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Name the format of linked files', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Adds "(PDF, 1.2 MB)" after links in your content that point at a document rather than a page, so the reader knows a download is coming before they commit to it. The size is included when the file is in your media library.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array( 'link-to-file' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'Covers links inside post and page content, not links your theme prints. Links that already name their format are left alone. The file size only appears for files in your media library, because finding the size of anything else would mean a request to another server every time the page loads.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'the_content', array( $this, 'annotate' ), 21 );
	}

	/**
	 * Adds format and size to document links.
	 *
	 * @since 0.19.0
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function annotate( $content ): string {
		$content = (string) $content;

		if ( ! str_contains( $content, '<a ' ) && ! str_contains( $content, '<a\t' ) ) {
			return $content;
		}

		$replaced = preg_replace_callback(
			'#<a\s[^>]*>.*?</a>#is',
			array( $this, 'annotate_one' ),
			$content
		);

		return null === $replaced ? $content : $replaced;
	}

	/**
	 * Annotates a single matched anchor.
	 *
	 * @since 0.19.0
	 *
	 * @param array<int, string> $matches Regex matches.
	 * @return string
	 */
	private function annotate_one( array $matches ): string {
		$anchor = $matches[0];

		if ( 1 !== preg_match( '#href\s*=\s*["\']([^"\']+)["\']#i', $anchor, $href ) ) {
			return $anchor;
		}

		$path = (string) wp_parse_url( $href[1], PHP_URL_PATH );

		if ( '' === $path || 1 !== preg_match( '/\.([a-z0-9]{1,4})$/i', $path, $extension ) ) {
			return $anchor;
		}

		$key = strtolower( $extension[1] );

		if ( ! isset( self::FORMATS[ $key ] ) ) {
			return $anchor;
		}

		$label = self::FORMATS[ $key ];
		$text  = strtolower( wp_strip_all_tags( $anchor ) );

		// Already says so, in either the extension or the friendly name.
		if ( str_contains( $text, $key ) || str_contains( $text, strtolower( $label ) ) ) {
			return $anchor;
		}

		$size   = $this->size_of( $href[1] );
		$suffix = '' === $size
			? sprintf(
				/* translators: %s: file format, for example PDF. */
				__( '(%s)', 'wowstudio-accessibility-kit' ),
				$label
			)
			: sprintf(
				/* translators: 1: file format, for example PDF. 2: file size, already formatted. */
				__( '(%1$s, %2$s)', 'wowstudio-accessibility-kit' ),
				$label,
				$size
			);

		$position = strripos( $anchor, '</a>' );

		if ( false === $position ) {
			return $anchor;
		}

		return substr( $anchor, 0, $position )
			. '<span class="wsak-file-info"> ' . esc_html( $suffix ) . '</span>'
			. substr( $anchor, $position );
	}

	/**
	 * Returns a readable file size for a media library URL, or an empty string.
	 *
	 * @since 0.19.0
	 *
	 * @param string $url The link target.
	 * @return string
	 */
	private function size_of( string $url ): string {
		static $cache = array();

		if ( isset( $cache[ $url ] ) ) {
			return $cache[ $url ];
		}

		$cache[ $url ] = '';

		$attachment_id = attachment_url_to_postid( $url );

		if ( $attachment_id > 0 ) {
			$path = get_attached_file( $attachment_id );

			if ( is_string( $path ) && file_exists( $path ) ) {
				$bytes = filesize( $path );

				if ( is_int( $bytes ) && $bytes > 0 ) {
					$cache[ $url ] = size_format( $bytes, 1 );
				}
			}
		}

		return $cache[ $url ];
	}
}
