<?php
/**
 * Fix generation and application.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

use WOWStudio\AccessibilityKit\AI\AiClientBridge;
use WOWStudio\AccessibilityKit\AI\KeyStore;
use WOWStudio\AccessibilityKit\AI\ProviderRegistry;
use WOWStudio\AccessibilityKit\AI\Settings;
use WOWStudio\AccessibilityKit\AltText\UsageMeter;
use WOWStudio\AccessibilityKit\Db\Issue;
use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Proposes a corrected fragment, and applies it as a reversible override.
 *
 * Every fix is a suggestion until a person presses apply, and every applied fix
 * can be undone without touching the original content.
 *
 * @since 0.6.0
 */
final class FixManager {

	/**
	 * How much longer than the original a proposal may be.
	 *
	 * A correction is a small edit. A reply several times the size of the input
	 * means the model has written an essay, or rewritten the page, and should
	 * not be offered as a diff.
	 *
	 * @since 0.6.0
	 * @var int
	 */
	private const MAX_GROWTH = 4;

	/**
	 * Issue storage.
	 *
	 * @since 0.6.0
	 * @var IssueRepository
	 */
	private IssueRepository $issues;

	/**
	 * Override storage.
	 *
	 * @since 0.6.0
	 * @var OverrideStore
	 */
	private OverrideStore $overrides;

	/**
	 * Available providers.
	 *
	 * @since 0.6.0
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $providers;

	/**
	 * Credential store.
	 *
	 * @since 0.6.0
	 * @var KeyStore
	 */
	private KeyStore $keys;

	/**
	 * AI settings.
	 *
	 * @since 0.6.0
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Daily allowance, shared with alt-text generation.
	 *
	 * @since 0.6.0
	 * @var UsageMeter
	 */
	private UsageMeter $usage;

	/**
	 * WordPress AI client bridge.
	 *
	 * @since 0.6.0
	 * @var AiClientBridge
	 */
	private AiClientBridge $bridge;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param IssueRepository|null  $issues    Issue storage.
	 * @param OverrideStore|null    $overrides Override storage.
	 * @param ProviderRegistry|null $providers Available providers.
	 * @param KeyStore|null         $keys      Credential store.
	 * @param Settings|null         $settings  AI settings.
	 * @param UsageMeter|null       $usage     Daily allowance.
	 * @param AiClientBridge|null   $bridge    WordPress AI client bridge.
	 */
	public function __construct(
		?IssueRepository $issues = null,
		?OverrideStore $overrides = null,
		?ProviderRegistry $providers = null,
		?KeyStore $keys = null,
		?Settings $settings = null,
		?UsageMeter $usage = null,
		?AiClientBridge $bridge = null
	) {
		$this->issues    = $issues ?? new IssueRepository();
		$this->overrides = $overrides ?? new OverrideStore();
		$this->providers = $providers ?? ProviderRegistry::with_defaults();
		$this->keys      = $keys ?? new KeyStore();
		$this->settings  = $settings ?? new Settings();
		$this->usage     = $usage ?? new UsageMeter();
		$this->bridge    = $bridge ?? new AiClientBridge();
	}

	/**
	 * Proposes a correction for one issue.
	 *
	 * @since 0.6.0
	 *
	 * @param int $issue_id Issue to fix.
	 * @return array<string, mixed>|WP_Error
	 */
	public function preview( int $issue_id ) {
		$issue = $this->issues->find( $issue_id );

		if ( null === $issue ) {
			return new WP_Error(
				'wsak_unknown_issue',
				__( 'That issue could not be found.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		$guard = $this->guard( $issue );

		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$before = $issue->context;

		if ( '' === trim( $before ) ) {
			return new WP_Error(
				'wsak_no_markup',
				__( 'This issue has no stored markup to correct. Re-scan the page and try again.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 422 )
			);
		}

		// Stored markup is capped in length. A truncated fragment cannot be
		// matched in the page, so an override built from it would silently never
		// apply. Better to say so than to offer a fix that does nothing.
		if ( str_ends_with( $before, '…' ) ) {
			return new WP_Error(
				'wsak_markup_truncated',
				__( 'The markup for this issue is too long to correct automatically. It needs fixing in the editor or the theme.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 422 )
			);
		}

		$generated = $this->ask_for_correction( $issue, $before );

		if ( is_wp_error( $generated ) ) {
			return $generated;
		}

		$after = $generated['text'];

		if ( ! Diff::differ( $before, $after ) ) {
			return new WP_Error(
				'wsak_no_change',
				__( 'The model returned the markup unchanged, so there is nothing to apply.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 422 )
			);
		}

		if ( strlen( $after ) > strlen( $before ) * self::MAX_GROWTH ) {
			return new WP_Error(
				'wsak_suspicious_output',
				__( 'The suggested markup is far longer than the original, which usually means the model rewrote more than the problem. It was not offered as a fix.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 422 )
			);
		}

		$this->usage->record( 1 );

		return array(
			'issue_id'   => $issue->id,
			'post_id'    => $issue->post_id,
			'rule_id'    => $issue->rule_id,
			'before'     => $before,
			'after'      => $after,
			'diff'       => Diff::compare( $before, $after ),
			'applicable' => $this->appears_in_content( $issue->post_id, $before ),
			'engine'     => $generated['engine'],
			'provider'   => $generated['provider'],
			'model'      => $generated['model'],
		);
	}

	/**
	 * Applies a reviewed correction as an override.
	 *
	 * The markup is passed through wp_kses before it is stored. It came from a
	 * language model and will be substituted into a public page, so it is
	 * untrusted input no matter how carefully it was reviewed in the interface.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $issue_id Issue being fixed.
	 * @param string $after    The reviewed markup.
	 * @param int    $user_id  Who approved it.
	 * @return Fix|WP_Error
	 */
	public function apply( int $issue_id, string $after, int $user_id ) {
		$issue = $this->issues->find( $issue_id );

		if ( null === $issue ) {
			return new WP_Error(
				'wsak_unknown_issue',
				__( 'That issue could not be found.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		if ( $issue->post_id > 0 && ! current_user_can( 'edit_post', $issue->post_id ) ) {
			return new WP_Error(
				'wsak_forbidden_post',
				__( 'You do not have permission to change that content.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$clean = $this->sanitise( $after );

		if ( '' === trim( $clean ) ) {
			return new WP_Error(
				'wsak_empty_fix',
				__( 'Nothing was left of the suggested markup once it had been checked for safety, so it was not applied.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 422 )
			);
		}

		$fix_id = $this->overrides->add(
			array(
				'issue_id'   => $issue->id,
				'post_id'    => $issue->post_id,
				'rule_id'    => $issue->rule_id,
				'before'     => $issue->context,
				'after'      => $clean,
				'applied_by' => $user_id,
			)
		);

		if ( 0 === $fix_id ) {
			return new WP_Error(
				'wsak_fix_not_stored',
				__( 'The fix could not be stored.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 500 )
			);
		}

		$this->issues->set_status( $issue->id, IssueStatus::Fixed, '' );

		/**
		 * Fires when an override is applied.
		 *
		 * @since 0.6.0
		 *
		 * @param int $fix_id   Stored override.
		 * @param int $issue_id Issue it resolves.
		 */
		do_action( 'wsak_fix_applied', $fix_id, $issue->id );

		return $this->overrides->find( $fix_id );
	}

	/**
	 * Takes an override out of force and reopens its issue.
	 *
	 * @since 0.6.0
	 *
	 * @param int $fix_id  Override to undo.
	 * @param int $user_id Who undid it.
	 * @return Fix|WP_Error
	 */
	public function revert( int $fix_id, int $user_id ) {
		$fix = $this->overrides->find( $fix_id );

		if ( null === $fix ) {
			return new WP_Error(
				'wsak_unknown_fix',
				__( 'That fix could not be found.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		if ( $fix->post_id > 0 && ! current_user_can( 'edit_post', $fix->post_id ) ) {
			return new WP_Error(
				'wsak_forbidden_post',
				__( 'You do not have permission to change that content.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( FixStatus::Applied !== $fix->status ) {
			return new WP_Error(
				'wsak_already_reverted',
				__( 'That fix has already been undone.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 409 )
			);
		}

		$this->overrides->revert( $fix_id, $user_id );

		if ( $fix->issue_id > 0 ) {
			$this->issues->set_status( $fix->issue_id, IssueStatus::Open, '' );
		}

		/**
		 * Fires when an override is undone.
		 *
		 * @since 0.6.0
		 *
		 * @param int $fix_id   Override undone.
		 * @param int $issue_id Issue reopened.
		 */
		do_action( 'wsak_fix_reverted', $fix_id, $fix->issue_id );

		return $this->overrides->find( $fix_id );
	}

	/**
	 * Refuses generation when the site or the allowance says no.
	 *
	 * @since 0.6.0
	 *
	 * @param Issue $issue Issue being fixed.
	 * @return true|WP_Error
	 */
	private function guard( Issue $issue ) {
		if ( ! $this->bridge->site_permits_ai() ) {
			return new WP_Error(
				'wsak_ai_disabled',
				__( 'AI features are switched off for this site, so nothing was sent to a provider.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 403 )
			);
		}

		if ( $this->settings->is_opted_out( $issue->post_id ) ) {
			return new WP_Error(
				'wsak_ai_opted_out',
				__( 'This content is marked as not to be sent to an AI provider.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $this->usage->allows( 1 ) ) {
			return new WP_Error(
				'wsak_ai_cap_reached',
				sprintf(
					/* translators: %d: number of AI requests allowed per day. */
					__( 'You have used today\'s %d free AI requests, which alt text and fixes share. The allowance resets at midnight UTC, and Pro removes it.', 'wowstudio-accessibility-kit' ),
					$this->usage->cap()
				),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Asks the configured engine for a corrected fragment.
	 *
	 * @since 0.6.0
	 *
	 * @param Issue  $issue  Issue being fixed.
	 * @param string $before Markup to correct.
	 * @return array{text: string, engine: string, provider: string, model: string}|WP_Error
	 */
	private function ask_for_correction( Issue $issue, string $before ) {
		$config      = $this->settings->all();
		$provider_id = (string) $config['provider'];
		$provider    = '' === $provider_id ? null : $this->providers->get( $provider_id );

		if ( null === $provider ) {
			return new WP_Error(
				'wsak_ai_no_provider',
				__( 'No AI provider is set up yet. Choose one in the Accessibility settings and add your API key.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 400 )
			);
		}

		$model  = '' !== (string) $config['model'] ? (string) $config['model'] : $provider->default_model();
		$prompt = $this->prompt_for( $issue, $before );
		$key    = $this->keys->get( $provider_id );

		if ( '' === $key ) {
			return new WP_Error(
				'wsak_ai_no_key',
				sprintf(
					/* translators: %s: provider name. */
					__( 'No API key is stored for %s. Add one in the Accessibility settings.', 'wowstudio-accessibility-kit' ),
					$provider->label()
				),
				array( 'status' => 400 )
			);
		}

		$text = $provider->generate_text( $prompt, $key, $model );

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		return array(
			'text'     => $this->unwrap( $text ),
			'engine'   => 'byok',
			'provider' => $provider_id,
			'model'    => $model,
		);
	}

	/**
	 * Builds the instruction for a correction.
	 *
	 * @since 0.6.0
	 *
	 * @param Issue  $issue  Issue being fixed.
	 * @param string $before Markup to correct.
	 * @return string
	 */
	private function prompt_for( Issue $issue, string $before ): string {
		$rule        = ( new Engine() )->registry()->get( $issue->rule_id );
		$title       = null === $rule ? $issue->rule_id : $rule->title();
		$explanation = null === $rule ? '' : $rule->description();

		return implode(
			"\n",
			array(
				'Correct one accessibility problem in this fragment of HTML from a web page.',
				'',
				'Problem: ' . $title,
				'Why it matters: ' . $explanation,
				'Detail: ' . $issue->message,
				'',
				'Rules:',
				'- Return the corrected HTML fragment and nothing else.',
				'- Change as little as possible. Keep every attribute, class, and inline style that is not part of the problem.',
				'- Do not invent visible text. If a name or label is needed, use the clearest wording the markup itself supports.',
				'- Never add script, style, iframe, or event-handler attributes.',
				'- No code fences, no explanation, no commentary.',
				'',
				'HTML:',
				$before,
			)
		);
	}

	/**
	 * Strips code fences a model may have added despite being asked not to.
	 *
	 * @since 0.6.0
	 *
	 * @param string $text Raw model output.
	 * @return string
	 */
	private function unwrap( string $text ): string {
		$text = trim( $text );
		$text = (string) preg_replace( '/^```[a-z]*\s*/i', '', $text );
		$text = (string) preg_replace( '/\s*```$/', '', $text );

		return trim( $text );
	}

	/**
	 * Removes anything unsafe from proposed markup.
	 *
	 * @since 0.6.0
	 *
	 * @param string $markup Proposed markup.
	 * @return string
	 */
	private function sanitise( string $markup ): string {
		$allowed = wp_kses_allowed_html( 'post' );

		/**
		 * Filters the HTML an applied fix may contain.
		 *
		 * @since 0.6.0
		 *
		 * @param array<string, mixed> $allowed Allowed tags and attributes.
		 */
		$allowed = (array) apply_filters( 'wsak_fix_allowed_html', $allowed );

		return wp_kses( $markup, $allowed );
	}

	/**
	 * Reports whether the markup can actually be found in the post's content.
	 *
	 * An override substitutes into rendered post content. Markup that lives in
	 * the theme — a header, a navigation menu, a footer — is never seen by that
	 * filter, so a fix for it would store cleanly and then do nothing. Saying so
	 * up front is the honest alternative.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $post_id Post to check.
	 * @param string $before  Markup to look for.
	 * @return bool
	 */
	private function appears_in_content( int $post_id, string $before ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( null === $post ) {
			return false;
		}

		// Checked against the raw content, because the override runs early on
		// the_content, before WordPress adds attributes of its own.
		return Substitution::locatable( (string) $post->post_content, $before );
	}
}
