<?php
/**
 * Stopping PDFs being added without anybody thinking about them.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\SiteFix;

defined( 'ABSPATH' ) || exit;

/**
 * Refuses PDF uploads, with an explanation.
 *
 * The most honest fix on this list, because it does not fix anything — it stops
 * a problem being created.
 *
 * A PDF is a separate document with its own accessibility, and almost none of
 * them have any: no tags, no reading order, no alt text, scanned images of text
 * that no screen reader can read at all. Nothing in this plugin, or any
 * WordPress plugin, can repair one. Meanwhile the same content as a web page is
 * accessible by default, searchable, printable, and readable on a phone.
 *
 * So this is a policy switch rather than a repair, and it is off unless somebody
 * turns it on. Organisations that have decided their content belongs on pages
 * rather than in attachments get a way to hold that line; everybody else is not
 * lectured about it.
 *
 * Administrators are exempt, deliberately. The person who can install plugins
 * can already put a file anywhere, so blocking them achieves nothing except
 * making the setting look broken to whoever switched it on.
 *
 * @since 0.19.0
 */
final class BlockPdfUploads implements SiteFix {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'block-pdf-uploads';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Do not allow PDF uploads', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Stops anyone but an administrator adding a PDF to the media library, and explains why when it happens. A PDF is a separate document with its own accessibility, which nothing here can inspect or repair — the same content published as a page is accessible by default. This is a policy, not a repair, so it changes nothing already on your site.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'Administrators can still upload PDFs, because anybody who can install a plugin can put a file wherever they like and pretending otherwise would only make this look broken. PDFs already in your media library are untouched, and existing links to them keep working.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'upload_mimes', array( $this, 'remove_pdf' ), 20 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'explain_refusal' ) );
	}

	/**
	 * Takes PDF out of the allowed upload types.
	 *
	 * @since 0.19.0
	 *
	 * @param array<string, string> $mimes Allowed types.
	 * @return array<string, string>
	 */
	public function remove_pdf( $mimes ): array {
		$mimes = (array) $mimes;

		if ( $this->is_exempt() ) {
			return $mimes;
		}

		unset( $mimes['pdf'] );

		return $mimes;
	}

	/**
	 * Replaces the generic refusal with one that says why.
	 *
	 * WordPress's own message is "Sorry, you are not allowed to upload this
	 * file type", which for somebody who uploaded a PDF here last week reads as
	 * a bug rather than a decision.
	 *
	 * @since 0.19.0
	 *
	 * @param array<string, mixed> $file The upload being handled.
	 * @return array<string, mixed>
	 */
	public function explain_refusal( $file ): array {
		$file = (array) $file;

		if ( $this->is_exempt() ) {
			return $file;
		}

		$name = isset( $file['name'] ) ? strtolower( (string) $file['name'] ) : '';

		if ( ! str_ends_with( $name, '.pdf' ) ) {
			return $file;
		}

		$file['error'] = __( 'PDF uploads are switched off on this site. A PDF carries its own accessibility, which cannot be checked or repaired from WordPress — publishing the content as a page instead makes it readable by everybody, and searchable. An administrator can change this in the accessibility settings.', 'wowstudio-accessibility-kit' );

		return $file;
	}

	/**
	 * Reports whether the current user is allowed to upload one anyway.
	 *
	 * @since 0.19.0
	 *
	 * @return bool
	 */
	private function is_exempt(): bool {
		/**
		 * Filters who may still upload a PDF while this fix is on.
		 *
		 * @since 0.19.0
		 *
		 * @param bool $exempt Whether the current user is exempt.
		 */
		return (bool) apply_filters( 'wsak_may_upload_pdf', current_user_can( 'manage_options' ) );
	}
}
