<?php
/**
 * Labels on the two forms WordPress puts on nearly every site.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\SiteFix;

defined( 'ABSPATH' ) || exit;

/**
 * Makes sure the search field and the comment fields have names.
 *
 * These two forms are worth a fix of their own because they are on almost every
 * WordPress site and neither is usually written by the site owner — they come
 * from the theme, so a person looking at an unlabelled search box has nowhere
 * obvious to go and fix it.
 *
 * A search input with no label is announced as "edit, blank". The reader knows
 * there is a text field and nothing about what it searches or whether it is the
 * search at all. Comment fields are worse, because there are four of them in a
 * row and they are indistinguishable.
 *
 * The search form is reached by filtering its rendered output, which is a small
 * well-defined fragment rather than the page. Rather than add a visible label to
 * a design that did not plan for one — which would move things around and get
 * this switched off — it adds `aria-label` when, and only when, the field has no
 * accessible name at all. The comment form is reached through
 * `comment_form_defaults`, which is structured data, so nothing has to be parsed.
 *
 * @since 0.19.0
 */
final class CommentAndSearchLabels implements SiteFix {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'comment-search-labels';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Label the search and comment fields', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Gives your search box and comment fields a name where the theme left them without one. An unlabelled field is announced as "edit, blank" — the reader can tell there is something to type in, and nothing about what belongs there. Nothing moves on screen; the names are added for assistive technology only.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array( 'form-control-label-missing' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'Covers WordPress\'s own search and comment forms. Forms built by a plugin — contact forms, checkouts, newsletter sign-ups — are not reached by this and have to be labelled where they are built.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'get_search_form', array( $this, 'label_search_field' ), 20 );
		add_filter( 'comment_form_defaults', array( $this, 'label_comment_field' ), 20 );
	}

	/**
	 * Adds an accessible name to a search field that has none.
	 *
	 * @since 0.19.0
	 *
	 * @param string $form The rendered search form.
	 * @return string
	 */
	public function label_search_field( $form ): string {
		$form = (string) $form;

		// A label element or an existing aria-label means somebody has already
		// dealt with this, and a second name would fight the first.
		if ( str_contains( $form, '<label' ) || 1 === preg_match( '#aria-label\s*=#i', $form ) ) {
			return $form;
		}

		$replaced = preg_replace(
			'#(<input\b(?=[^>]*\bname\s*=\s*["\']?s["\']?))#i',
			'$1 aria-label="' . esc_attr__( 'Search this site', 'wowstudio-accessibility-kit' ) . '"',
			$form,
			1
		);

		return null === $replaced ? $form : $replaced;
	}

	/**
	 * Makes sure the comment textarea is labelled.
	 *
	 * @since 0.19.0
	 *
	 * @param array<string, mixed> $defaults Comment form arguments.
	 * @return array<string, mixed>
	 */
	public function label_comment_field( $defaults ): array {
		$defaults = (array) $defaults;

		if ( empty( $defaults['comment_field'] ) || ! is_string( $defaults['comment_field'] ) ) {
			return $defaults;
		}

		$field = $defaults['comment_field'];

		if ( str_contains( $field, '<label' ) || 1 === preg_match( '#aria-label\s*=#i', $field ) ) {
			return $defaults;
		}

		$replaced = preg_replace(
			'#(<textarea\b)#i',
			'$1 aria-label="' . esc_attr__( 'Comment', 'wowstudio-accessibility-kit' ) . '"',
			$field,
			1
		);

		if ( null !== $replaced ) {
			$defaults['comment_field'] = $replaced;
		}

		return $defaults;
	}
}
