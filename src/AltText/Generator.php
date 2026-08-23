<?php
/**
 * Alt-text generation.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AltText;

use WOWStudio\AccessibilityKit\AI\AiClientBridge;
use WOWStudio\AccessibilityKit\AI\ImageContext;
use WOWStudio\AccessibilityKit\AI\KeyStore;
use WOWStudio\AccessibilityKit\AI\ProviderRegistry;
use WOWStudio\AccessibilityKit\AI\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Turns an attachment into a proposed alt text.
 *
 * Suggestions only. Nothing here writes to the media library.
 *
 * @since 0.5.0
 */
final class Generator {

	/**
	 * Image types every provider accepts.
	 *
	 * @since 0.5.0
	 * @var string[]
	 */
	private const SUPPORTED_MIMES = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

	/**
	 * Sentinel the prompt asks for when an image carries no meaning.
	 *
	 * @since 0.5.0
	 * @var string
	 */
	private const DECORATIVE = 'DECORATIVE';

	/**
	 * Largest image payload sent to a provider, in bytes.
	 *
	 * @since 0.5.0
	 * @var int
	 */
	private const MAX_BYTES = 4194304;

	/**
	 * Available providers.
	 *
	 * @since 0.5.0
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $providers;

	/**
	 * Credential store.
	 *
	 * @since 0.5.0
	 * @var KeyStore
	 */
	private KeyStore $keys;

	/**
	 * AI settings.
	 *
	 * @since 0.5.0
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Daily allowance.
	 *
	 * @since 0.5.0
	 * @var UsageMeter
	 */
	private UsageMeter $usage;

	/**
	 * WordPress AI client bridge.
	 *
	 * @since 0.5.0
	 * @var AiClientBridge
	 */
	private AiClientBridge $bridge;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param ProviderRegistry|null $providers Available providers.
	 * @param KeyStore|null         $keys      Credential store.
	 * @param Settings|null         $settings  AI settings.
	 * @param UsageMeter|null       $usage     Daily allowance.
	 * @param AiClientBridge|null   $bridge    WordPress AI client bridge.
	 */
	public function __construct(
		?ProviderRegistry $providers = null,
		?KeyStore $keys = null,
		?Settings $settings = null,
		?UsageMeter $usage = null,
		?AiClientBridge $bridge = null
	) {
		$this->providers = $providers ?? ProviderRegistry::with_defaults();
		$this->keys      = $keys ?? new KeyStore();
		$this->settings  = $settings ?? new Settings();
		$this->usage     = $usage ?? new UsageMeter();
		$this->bridge    = $bridge ?? new AiClientBridge();
	}

	/**
	 * Proposes alt text for one attachment.
	 *
	 * @since 0.5.0
	 *
	 * @param int $attachment_id Image to describe.
	 * @param int $post_id       Post the image appears on, for context.
	 * @return Suggestion|WP_Error
	 */
	public function for_attachment( int $attachment_id, int $post_id = 0 ) {
		if ( $this->settings->is_opted_out( $post_id ) ) {
			return new WP_Error(
				'wsak_ai_opted_out',
				__( 'This content is marked as not to be sent to an AI provider.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 403 )
			);
		}

		// The cap is checked before any work is done and recorded only after a
		// provider actually answered, so a failure never costs an allowance.
		if ( ! $this->usage->allows( 1 ) ) {
			return new WP_Error(
				'wsak_ai_cap_reached',
				sprintf(
					/* translators: %d: number of images allowed per day. */
					__( 'You have used today\'s %d free alt-text generations. The allowance resets at midnight UTC, and Pro removes it.', 'wowstudio-accessibility-kit' ),
					$this->usage->cap()
				),
				array( 'status' => 429 )
			);
		}

		$image = $this->build_context( $attachment_id, $post_id );

		if ( is_wp_error( $image ) ) {
			return $image;
		}

		$settings = $this->settings->all();
		$result   = $this->describe( $image, (string) $settings['provider'], (string) $settings['model'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->usage->record( 1 );

		$text       = trim( $result['text'] );
		$decorative = self::DECORATIVE === strtoupper( $text );

		return new Suggestion(
			$decorative ? '' : $text,
			$decorative,
			$result['engine'],
			$result['provider'],
			$result['model'],
			$image->payload_kb()
		);
	}

	/**
	 * Returns how many generations remain today.
	 *
	 * @since 0.5.0
	 *
	 * @return UsageMeter
	 */
	public function usage(): UsageMeter {
		return $this->usage;
	}

	/**
	 * Sends the image to whichever engine is available.
	 *
	 * WordPress's own AI client is preferred when the site has configured it,
	 * so a site does not have to enter the same credentials twice.
	 *
	 * @since 0.5.0
	 *
	 * @param ImageContext $image       Image and context.
	 * @param string       $provider_id Chosen provider.
	 * @param string       $model       Chosen model.
	 * @return array{text: string, engine: string, provider: string, model: string}|WP_Error
	 */
	private function describe( ImageContext $image, string $provider_id, string $model ) {
		$provider = '' === $provider_id ? null : $this->providers->get( $provider_id );

		if ( null === $provider ) {
			return new WP_Error(
				'wsak_ai_no_provider',
				__( 'No AI provider is set up yet. Choose one in the Accessibility settings and add your API key.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 400 )
			);
		}

		$model = '' !== $model ? $model : $provider->default_model();

		if ( $this->bridge->is_configured_for( $provider_id ) ) {
			$prompt = $this->prompt_for( $image );
			$text   = $this->bridge->generate_alt_text( $image, $prompt, $provider_id, $model );

			if ( ! is_wp_error( $text ) ) {
				return array(
					'text'     => $text,
					'engine'   => 'wp-ai-client',
					'provider' => $provider_id,
					'model'    => $model,
				);
			}
		}

		$key = $this->keys->get( $provider_id );

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

		$text = $provider->generate_alt_text( $image, $key, $model );

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		return array(
			'text'     => $text,
			'engine'   => 'byok',
			'provider' => $provider_id,
			'model'    => $model,
		);
	}

	/**
	 * Builds the instruction for the WordPress AI client path.
	 *
	 * The bring-your-own-key providers build their own from the same shape.
	 *
	 * @since 0.5.0
	 *
	 * @param ImageContext $image Image and context.
	 * @return string
	 */
	private function prompt_for( ImageContext $image ): string {
		$prompt = "Write alternative text for this image, for someone who cannot see it.\n"
			. "Describe what it conveys in context, not every visual detail. One sentence, under 125 characters where possible.\n"
			. "Do not begin with \"image of\" or \"picture of\". If the image is purely decorative, reply with exactly: DECORATIVE\n"
			. 'Reply with the alt text alone.';

		$context = $image->describe();

		return '' === $context ? $prompt : $prompt . "\n\nContext:\n" . $context;
	}

	/**
	 * Gathers the image and the little context that goes with it.
	 *
	 * A resized copy is preferred over the original: it is faster, cheaper, and
	 * sends less of the user's data than the full-resolution file would.
	 *
	 * @since 0.5.0
	 *
	 * @param int $attachment_id Image to describe.
	 * @param int $post_id       Post the image appears on.
	 * @return ImageContext|WP_Error
	 */
	private function build_context( int $attachment_id, int $post_id ) {
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error(
				'wsak_ai_not_attachment',
				__( 'That is not an image in the media library.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		$mime = (string) get_post_mime_type( $attachment_id );

		if ( ! in_array( $mime, self::SUPPORTED_MIMES, true ) ) {
			return new WP_Error(
				'wsak_ai_unsupported_type',
				sprintf(
					/* translators: %s: the file's MIME type. */
					__( 'Images of type %s cannot be described. JPEG, PNG, GIF, and WebP are supported.', 'wowstudio-accessibility-kit' ),
					'' !== $mime ? $mime : __( 'unknown', 'wowstudio-accessibility-kit' )
				),
				array( 'status' => 415 )
			);
		}

		$path = $this->smallest_usable_path( $attachment_id );

		if ( '' === $path || ! is_readable( $path ) ) {
			return new WP_Error(
				'wsak_ai_file_missing',
				__( 'The image file could not be found on disk.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		$bytes = (int) filesize( $path );

		if ( $bytes > self::MAX_BYTES ) {
			return new WP_Error(
				'wsak_ai_too_large',
				__( 'This image is too large to send. Regenerate the site\'s image sizes, or use a smaller original.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 413 )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local file for encoding; the WP filesystem API has no equivalent that returns raw bytes reliably here.
		$raw = file_get_contents( $path );

		if ( false === $raw ) {
			return new WP_Error(
				'wsak_ai_file_unreadable',
				__( 'The image file could not be read.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 500 )
			);
		}

		$send_context = (bool) $this->settings->all()['send_page_context'];

		return new ImageContext(
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding image bytes for a JSON API, not obfuscating code.
			base64_encode( $raw ),
			$mime,
			$path,
			wp_basename( $path ),
			$send_context && $post_id > 0 ? (string) get_the_title( $post_id ) : '',
			$send_context ? $this->nearby_text( $post_id ) : ''
		);
	}

	/**
	 * Returns the path of the smallest image size worth sending.
	 *
	 * @since 0.5.0
	 *
	 * @param int $attachment_id Attachment to read.
	 * @return string
	 */
	private function smallest_usable_path( int $attachment_id ): string {
		$uploads = wp_get_upload_dir();

		foreach ( array( 'medium_large', 'medium', 'large' ) as $size ) {
			$intermediate = image_get_intermediate_size( $attachment_id, $size );

			if ( is_array( $intermediate ) && ! empty( $intermediate['path'] ) ) {
				return trailingslashit( $uploads['basedir'] ) . $intermediate['path'];
			}
		}

		$original = get_attached_file( $attachment_id );

		return is_string( $original ) ? $original : '';
	}

	/**
	 * Returns a short slice of the text around the image.
	 *
	 * Capped hard. The promise is to send what a describer needs, not the page.
	 *
	 * @since 0.5.0
	 *
	 * @param int $post_id Post to read.
	 * @return string
	 */
	private function nearby_text( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}

		$post = get_post( $post_id );

		if ( null === $post ) {
			return '';
		}

		$text = wp_strip_all_tags( (string) $post->post_content );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

		return mb_substr( $text, 0, 300, 'UTF-8' );
	}
}
