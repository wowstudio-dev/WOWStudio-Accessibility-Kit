<?php
/**
 * Checking the theme once, instead of once per post.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Db\ScanRepository;
use WOWStudio\AccessibilityKit\Remediation\Substitution;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Scans a handful of representative pages and attributes what it finds.
 *
 * A missing landmark is one fault in one template. Filed against every post
 * that uses that template it becomes four hundred findings, and the list stops
 * being readable at exactly the moment somebody needed it. So the theme is
 * checked on its own: a few representative URLs, once per theme version, with
 * everything found recorded against the theme rather than against a post.
 *
 * Attribution is by subtraction. A finding on a representative page whose
 * markup also appears in that post's own content belongs to the content — the
 * per-post scan will report it — and everything else is the theme's. The test
 * for "is this markup in this content" already existed for deciding whether an
 * override could reach a fix, and it answers the same question here.
 *
 * @since 0.12.0
 */
final class TemplateScan {

	/**
	 * Where the profile and its provenance are kept.
	 *
	 * @since 0.12.0
	 * @var string
	 */
	public const PROFILE_OPTION = 'wsak_template_profile';

	/**
	 * Scan storage.
	 *
	 * @since 0.12.0
	 * @var ScanRepository
	 */
	private ScanRepository $scans;

	/**
	 * Issue storage.
	 *
	 * @since 0.12.0
	 * @var IssueRepository
	 */
	private IssueRepository $issues;

	/**
	 * Markup source.
	 *
	 * @since 0.12.0
	 * @var PageSource
	 */
	private PageSource $source;

	/**
	 * Rule engine.
	 *
	 * @since 0.12.0
	 * @var Engine
	 */
	private Engine $engine;

	/**
	 * Constructor.
	 *
	 * @since 0.12.0
	 *
	 * @param ScanRepository|null  $scans  Scan storage.
	 * @param IssueRepository|null $issues Issue storage.
	 * @param PageSource|null      $source Markup source.
	 * @param Engine|null          $engine Rule engine.
	 */
	public function __construct(
		?ScanRepository $scans = null,
		?IssueRepository $issues = null,
		?PageSource $source = null,
		?Engine $engine = null
	) {
		$this->scans  = $scans ?? new ScanRepository();
		$this->issues = $issues ?? new IssueRepository();
		$this->source = $source ?? new PageSource();
		$this->engine = $engine ?? new Engine();
	}

	/**
	 * Returns the stored profile when it still describes the active theme.
	 *
	 * @since 0.12.0
	 *
	 * @return TemplateProfile
	 */
	public function profile(): TemplateProfile {
		$stored  = get_option( self::PROFILE_OPTION, array() );
		$profile = TemplateProfile::from_array( is_array( $stored ) ? $stored : array() );

		$theme = wp_get_theme();

		return $profile->describes( (string) $theme->get_stylesheet(), (string) $theme->get( 'Version' ) )
			? $profile
			: TemplateProfile::unknown();
	}

	/**
	 * Forgets the profile, so the next run gathers it again.
	 *
	 * @since 0.12.0
	 *
	 * @return void
	 */
	public function forget(): void {
		delete_option( self::PROFILE_OPTION );
	}

	/**
	 * Picks the posts whose pages stand in for the theme.
	 *
	 * One published post per public type, on the theory that a product page and
	 * a blog post rarely share a template. Not exhaustive, and not claimed to
	 * be: a site whose every page is individually built by a page builder is
	 * covered by the rendered strategy instead.
	 *
	 * @since 0.12.0
	 *
	 * @return int[] Post IDs.
	 */
	public function representatives(): array {
		$ids = array();

		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $type ) {
			if ( 'attachment' === $type ) {
				continue;
			}

			$found = get_posts(
				array(
					'post_type'        => $type,
					'post_status'      => 'publish',
					'numberposts'      => 1,
					'fields'           => 'ids',
					'orderby'          => 'modified',
					'order'            => 'DESC',
					'suppress_filters' => false,
				)
			);

			if ( is_array( $found ) && array() !== $found ) {
				$ids[] = (int) $found[0];
			}
		}

		/**
		 * Filters which posts stand in for the theme.
		 *
		 * @since 0.12.0
		 *
		 * @param int[] $ids Representative post IDs.
		 */
		return array_values( array_unique( (array) apply_filters( 'wsak_template_representatives', $ids ) ) );
	}

	/**
	 * Checks the theme and records what belongs to it.
	 *
	 * @since 0.12.0
	 *
	 * @param int $user_id Who asked.
	 * @return array<string, mixed>|WP_Error
	 */
	public function run( int $user_id = 0 ) {
		$representatives = $this->representatives();

		if ( array() === $representatives ) {
			return new WP_Error(
				'wsak_no_representatives',
				__( 'There is no published content to check the theme against. Publish a page or a post, then try again.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 422 )
			);
		}

		$scan_id = $this->scans->start( ScanScope::Template, 0, $user_id );

		if ( 0 === $scan_id ) {
			return new WP_Error(
				'wsak_scan_not_started',
				__( 'The theme check could not be recorded. Check that the plugin tables exist.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 500 )
			);
		}

		$rows     = array();
		$seen     = array();
		$profile  = null;
		$examined = 0;

		foreach ( $representatives as $post_id ) {
			$page = $this->source->for_post( $post_id, FetchStrategy::Loopback );

			// Without loopback there is no whole page to look at, so there is no
			// theme to describe. Every page-level check simply does not run,
			// which is the honest outcome and the one the coverage notice
			// already explains.
			if ( is_wp_error( $page ) || ! $page->from_loopback ) {
				continue;
			}

			$result = $this->engine->scan( $page->html );

			if ( null === $result ) {
				continue;
			}

			++$examined;
			$content = $this->content_of( $post_id );

			foreach ( $result->findings as $finding ) {
				$row = $finding->to_row( 0 );

				// Anything visible in the post's own content is the content's
				// problem and the per-post scan will report it. Filing it here
				// as well would double-count it.
				if ( '' !== $content && Substitution::locatable( $content, (string) $row['context'] ) ) {
					continue;
				}

				// The same header appears on every representative page, so the
				// same fault arrives once per page.
				$key = $row['rule_id'] . '|' . $row['selector'] . '|' . $row['context'];

				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ] = true;
				$rows[]       = $row;
			}

			$profile = $profile ?? $this->describe( $page->html, $content );
		}

		if ( 0 === $examined ) {
			$this->scans->fail( $scan_id, __( 'The theme could not be checked, because this site cannot fetch its own pages.', 'wowstudio-accessibility-kit' ) );

			return new WP_Error(
				'wsak_no_loopback',
				__( 'Your theme could not be checked, because this site cannot make requests to itself. Everything else still works: pages are checked on their own content, and opening one in the inspector checks it in full.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 503 )
			);
		}

		$this->issues->add_many( $scan_id, $rows );
		$this->scans->complete(
			$scan_id,
			null,
			array(
				'examined' => $examined,
				'findings' => count( $rows ),
			)
		);

		if ( $profile instanceof TemplateProfile ) {
			update_option( self::PROFILE_OPTION, $profile->to_array(), false );
		}

		/**
		 * Fires after the theme has been checked.
		 *
		 * @since 0.12.0
		 *
		 * @param int                  $scan_id Scan holding the findings.
		 * @param TemplateProfile|null $profile What the theme was found to contribute.
		 */
		do_action( 'wsak_template_scanned', $scan_id, $profile );

		return array(
			'scan_id'  => $scan_id,
			'examined' => $examined,
			'findings' => count( $rows ),
			'profile'  => ( $profile ?? TemplateProfile::unknown() )->to_array(),
		);
	}

	/**
	 * Renders a post's own content, for subtracting from the whole page.
	 *
	 * @since 0.12.0
	 *
	 * @param int $post_id Post to render.
	 * @return string
	 */
	private function content_of( int $post_id ): string {
		$post = get_post( $post_id );

		if ( null === $post ) {
			return '';
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Applying core's content filter is the intent here.
		$content = apply_filters( 'the_content', $post->post_content );

		return is_string( $content ) ? $content : '';
	}

	/**
	 * Works out what the theme puts around the content.
	 *
	 * @since 0.12.0
	 *
	 * @param string $html    Whole rendered page.
	 * @param string $content That page's own post content.
	 * @return TemplateProfile
	 */
	private function describe( string $html, string $content ): TemplateProfile {
		$page = Document::from_html( $html );

		if ( null === $page ) {
			return TemplateProfile::unknown();
		}

		$page_h1s    = $this->count_h1( $page );
		$content_h1s = 0;

		if ( '' !== trim( $content ) ) {
			$parsed = Document::from_html( $content );

			if ( null !== $parsed ) {
				$content_h1s = $this->count_h1( $parsed );
			}
		}

		$theme_h1s = max( 0, $page_h1s - $content_h1s );

		$theme = wp_get_theme();

		return new TemplateProfile(
			true,
			$theme_h1s,
			// In practice the theme prints the post title as an h1 immediately
			// before the content, so a theme with a top-level heading sets the
			// level the content continues from. Where it does not, this stays
			// zero and the heading rules behave as they always did — the
			// failure is under-reporting, never an invented finding.
			$theme_h1s > 0 ? 1 : 0,
			null !== $page->first( '//main | //*[@role="main"]' ),
			$this->has_title( $page ),
			'' !== $this->lang_of( $page ),
			(string) $theme->get_stylesheet(),
			(string) $theme->get( 'Version' ),
			gmdate( 'Y-m-d H:i:s' )
		);
	}

	/**
	 * Reports whether the document has a non-empty title.
	 *
	 * @since 0.12.0
	 *
	 * @param Document $document Parsed page.
	 * @return bool
	 */
	private function has_title( Document $document ): bool {
		$title = $document->first( '//head/title' );

		return null !== $title && '' !== trim( $document->text_of( $title ) );
	}

	/**
	 * Counts visible top-level headings.
	 *
	 * @since 0.12.0
	 *
	 * @param Document $document Parsed markup.
	 * @return int
	 */
	private function count_h1( Document $document ): int {
		$count = 0;

		foreach ( $document->find( '//h1' ) as $heading ) {
			if ( $heading instanceof \DOMElement && ! $document->is_hidden( $heading ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Reads the document language.
	 *
	 * @since 0.12.0
	 *
	 * @param Document $document Parsed page.
	 * @return string
	 */
	private function lang_of( Document $document ): string {
		$html = $document->first( '//html' );

		return null === $html ? '' : trim( $html->getAttribute( 'lang' ) );
	}
}
