/**
 * AI provider configuration.
 */

import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { fetchAiSettings, readableError, saveAiSettings } from '../api';
import { ErrorState, Skeleton } from './states';

/**
 * Settings for the bring-your-own-key AI providers.
 *
 * The key field is write-only. Nothing in this component ever receives a stored
 * key, because no route returns one — the most it shows is a masked hint.
 *
 * @return {Element} The settings panel.
 */
export default function AiSettings() {
	const [ state, setState ] = useState( {
		status: 'loading',
		data: null,
		error: '',
	} );
	const [ key, setKey ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );

	const load = () => {
		setState( ( previous ) => ( { ...previous, status: 'loading' } ) );

		fetchAiSettings()
			.then( ( data ) =>
				setState( { status: 'ready', data, error: '' } )
			)
			.catch( ( error ) =>
				setState( {
					status: 'error',
					data: null,
					error: readableError( error ),
				} )
			);
	};

	useEffect( load, [] );

	const save = ( changes, message ) => {
		setNotice( '' );

		saveAiSettings( changes )
			.then( ( data ) => {
				setState( { status: 'ready', data, error: '' } );
				setKey( '' );
				setNotice( message );
			} )
			.catch( ( error ) => setNotice( readableError( error ) ) );
	};

	if ( state.status === 'loading' ) {
		return (
			<Skeleton
				label={ __(
					'Loading AI settings…',
					'wowstudio-accessibility-kit'
				) }
			/>
		);
	}

	if ( state.status === 'error' ) {
		return <ErrorState message={ state.error } onRetry={ load } />;
	}

	const {
		settings,
		providers,
		encryption,
		usage,
		wp_ai_client: wpAi,
		ai_permitted: aiPermitted,
	} = state.data;
	const active = providers.find(
		( provider ) => provider.id === settings.provider
	);

	return (
		<section
			className="wsak-settings"
			aria-labelledby="wsak-settings-title"
		>
			<h2 className="wsak-settings__title" id="wsak-settings-title">
				{ __( 'AI settings', 'wowstudio-accessibility-kit' ) }
			</h2>

			<p className="wsak-settings__lede">
				{ __(
					'Alt-text generation uses your own account with an AI provider. You pay them directly, we never see your key after it is stored, and nothing is sent anywhere until you ask for a suggestion.',
					'wowstudio-accessibility-kit'
				) }
			</p>

			{ notice && (
				<Notice status="info" onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }

			{ ! aiPermitted && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'AI features are switched off for this site, so no suggestions can be generated. That switch belongs to the site or its host — the WP_AI_SUPPORT constant, or the wp_supports_ai filter — and this plugin will not work around it.',
						'wowstudio-accessibility-kit'
					) }
				</Notice>
			) }

			{ ! encryption.available && (
				<Notice status="error" isDismissible={ false }>
					{ encryption.note }
				</Notice>
			) }

			{ wpAi.available && (
				<Notice status="info" isDismissible={ false }>
					{ wpAi.configured
						? __(
								'WordPress has its own AI provider configured, and it will be used before the key stored here.',
								'wowstudio-accessibility-kit'
						  )
						: __(
								'This WordPress version has a built-in AI client. If you configure a provider there, it will be used before the key stored here.',
								'wowstudio-accessibility-kit'
						  ) }
				</Notice>
			) }

			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Provider', 'wowstudio-accessibility-kit' ) }
				value={ settings.provider }
				options={ [
					{
						label: __( 'Not set', 'wowstudio-accessibility-kit' ),
						value: '',
					},
					...providers.map( ( provider ) => ( {
						label: provider.has_key
							? sprintf(
									/* translators: 1: provider name, 2: masked key hint. */
									__(
										'%1$s — key stored (%2$s)',
										'wowstudio-accessibility-kit'
									),
									provider.label,
									provider.key_hint
							  )
							: provider.label,
						value: provider.id,
					} ) ),
				] }
				onChange={ ( provider ) =>
					save(
						{ provider, model: '' },
						__( 'Provider saved.', 'wowstudio-accessibility-kit' )
					)
				}
			/>

			{ active && (
				<>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Model', 'wowstudio-accessibility-kit' ) }
						help={ __(
							'Larger models describe images more carefully and cost more per image at your provider.',
							'wowstudio-accessibility-kit'
						) }
						value={ settings.model || active.default_model }
						options={ Object.entries( active.models ).map(
							( [ value, label ] ) => ( {
								label,
								value,
							} )
						) }
						onChange={ ( model ) =>
							save(
								{ model },
								__(
									'Model saved.',
									'wowstudio-accessibility-kit'
								)
							)
						}
					/>

					<div className="wsak-settings__key">
						<TextControl
							__nextHasNoMarginBottom
							type="password"
							autoComplete="off"
							label={ sprintf(
								/* translators: %s: provider name. */
								__(
									'API key for %s',
									'wowstudio-accessibility-kit'
								),
								active.label
							) }
							help={
								active.has_key
									? sprintf(
											/* translators: %s: masked key hint. */
											__(
												'A key ending %s is stored. Enter a new one to replace it.',
												'wowstudio-accessibility-kit'
											),
											active.key_hint
									  )
									: __(
											'Stored encrypted. It is never shown again, and never sent to your browser.',
											'wowstudio-accessibility-kit'
									  )
							}
							value={ key }
							disabled={ ! encryption.available }
							onChange={ setKey }
						/>
						<Button
							variant="secondary"
							disabled={ ! key || ! encryption.available }
							onClick={ () =>
								save(
									{ api_key: key, key_provider: active.id },
									__(
										'Key stored.',
										'wowstudio-accessibility-kit'
									)
								)
							}
						>
							{ __( 'Store key', 'wowstudio-accessibility-kit' ) }
						</Button>
						<p className="wsak-settings__keylink">
							<a
								href={ active.key_url }
								target="_blank"
								rel="noreferrer noopener"
							>
								{ sprintf(
									/* translators: %s: provider name. */
									__(
										'Get a key from %s',
										'wowstudio-accessibility-kit'
									),
									active.label
								) }
							</a>
						</p>
					</div>
				</>
			) }

			<h3 className="wsak-settings__subtitle">
				{ __( 'What gets sent', 'wowstudio-accessibility-kit' ) }
			</h3>

			<ToggleControl
				__nextHasNoMarginBottom
				label={ __(
					'Send the page title and a little surrounding text',
					'wowstudio-accessibility-kit'
				) }
				help={ __(
					'Context makes alt text markedly better, because the same photograph means different things on different pages. Turn this off to send the image alone.',
					'wowstudio-accessibility-kit'
				) }
				checked={ settings.send_page_context }
				onChange={ ( sendContext ) =>
					save(
						{ send_page_context: sendContext },
						__(
							'Privacy setting saved.',
							'wowstudio-accessibility-kit'
						)
					)
				}
			/>

			<p className="wsak-settings__privacy">
				{ __(
					'Nothing else leaves your site: not the rest of the page, not other images, not your visitors’ data. Individual posts can be excluded entirely with the _wsak_skip_ai post meta.',
					'wowstudio-accessibility-kit'
				) }
			</p>

			<h3 className="wsak-settings__subtitle">
				{ __( 'Your allowance today', 'wowstudio-accessibility-kit' ) }
			</h3>

			<p className="wsak-settings__usage">
				{ usage.unlimited
					? __(
							'Unlimited alt-text generation.',
							'wowstudio-accessibility-kit'
					  )
					: sprintf(
							/* translators: 1: number used, 2: daily cap. */
							__(
								'%1$d of %2$d used. The allowance resets at midnight UTC.',
								'wowstudio-accessibility-kit'
							),
							usage.used,
							usage.cap
					  ) }
			</p>
		</section>
	);
}
