<?php
/**
 * What the theme contributes to a page, so a content-only scan can allow for it.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * The handful of facts a content-only scan needs from the page around it.
 *
 * Scanning post content alone is cheap, works everywhere, and is blind to the
 * theme. Mostly that is fine — the page-level rules already abstain when they
 * cannot see a document. Two rules are different, because they judge content
 * *relative to* what came before it, and without the theme they under-report:
 *
 * - A post whose content opens with `h1` is a genuine second top-level heading
 *   if the theme already printed the title as one. Content alone counts one and
 *   says nothing.
 * - A post starting at `h3` under the theme's `h1` is a genuine skipped level.
 *   Content alone treats `h3` as the beginning and says nothing.
 *
 * So this records what the theme puts before the content, gathered once per
 * theme version rather than per page. When it is unknown, the rules behave as
 * they did before: under-reporting is recoverable, and inventing findings is
 * not.
 *
 * @since 0.12.0
 */
final class TemplateProfile {

	/**
	 * Constructor.
	 *
	 * @since 0.12.0
	 *
	 * @param bool   $known         Whether the theme has actually been looked at.
	 * @param int    $h1_count      Top-level headings the theme contributes.
	 * @param int    $leading_level Heading level established before the content, 0 when unknown.
	 * @param bool   $has_main      Whether the theme provides a main landmark.
	 * @param bool   $has_title     Whether the document has a title.
	 * @param bool   $has_lang      Whether the html element declares a language.
	 * @param string $theme         Theme stylesheet the profile describes.
	 * @param string $version       Theme version the profile describes.
	 * @param string $captured_at   When it was gathered.
	 */
	public function __construct(
		public readonly bool $known = false,
		public readonly int $h1_count = 0,
		public readonly int $leading_level = 0,
		public readonly bool $has_main = false,
		public readonly bool $has_title = false,
		public readonly bool $has_lang = false,
		public readonly string $theme = '',
		public readonly string $version = '',
		public readonly string $captured_at = ''
	) {
	}

	/**
	 * A profile that admits it knows nothing.
	 *
	 * @since 0.12.0
	 *
	 * @return self
	 */
	public static function unknown(): self {
		return new self();
	}

	/**
	 * Rebuilds a stored profile.
	 *
	 * @since 0.12.0
	 *
	 * @param array<string, mixed> $stored Stored payload.
	 * @return self
	 */
	public static function from_array( array $stored ): self {
		if ( empty( $stored['known'] ) ) {
			return self::unknown();
		}

		return new self(
			true,
			(int) ( $stored['h1_count'] ?? 0 ),
			(int) ( $stored['leading_level'] ?? 0 ),
			(bool) ( $stored['has_main'] ?? false ),
			(bool) ( $stored['has_title'] ?? false ),
			(bool) ( $stored['has_lang'] ?? false ),
			(string) ( $stored['theme'] ?? '' ),
			(string) ( $stored['version'] ?? '' ),
			(string) ( $stored['captured_at'] ?? '' )
		);
	}

	/**
	 * The stored shape.
	 *
	 * @since 0.12.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'known'         => $this->known,
			'h1_count'      => $this->h1_count,
			'leading_level' => $this->leading_level,
			'has_main'      => $this->has_main,
			'has_title'     => $this->has_title,
			'has_lang'      => $this->has_lang,
			'theme'         => $this->theme,
			'version'       => $this->version,
			'captured_at'   => $this->captured_at,
		);
	}

	/**
	 * The heading level a content-only scan should start from.
	 *
	 * Zero means "do not assume anything", which is what every rule reading this
	 * treats as a reason to behave exactly as it did before profiles existed.
	 *
	 * @since 0.12.0
	 *
	 * @return int
	 */
	public function heading_context(): int {
		return $this->known ? $this->leading_level : 0;
	}

	/**
	 * How many top-level headings exist before the content starts.
	 *
	 * @since 0.12.0
	 *
	 * @return int
	 */
	public function top_headings_before_content(): int {
		return $this->known ? $this->h1_count : 0;
	}

	/**
	 * Whether this profile still describes the theme in use.
	 *
	 * WordPress stores nothing about when a theme's markup changed, so the
	 * version is the only signal available — and a theme that ships an unchanged
	 * version number after changing its header is a case this cannot catch. The
	 * profile expires on its own for that reason.
	 *
	 * @since 0.12.0
	 *
	 * @param string $theme   Current stylesheet.
	 * @param string $version Current version.
	 * @return bool
	 */
	public function describes( string $theme, string $version ): bool {
		return $this->known && $this->theme === $theme && $this->version === $version;
	}
}
