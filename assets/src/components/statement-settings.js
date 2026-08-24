/**
 * Writing and signing off the accessibility statement.
 */

import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
	attestStatement,
	fetchStatement,
	readableError,
	saveStatement,
	withdrawStatement,
} from '../api';
import { ErrorState, Skeleton } from './states';

/**
 * The statement editor, preview, and sign-off.
 *
 * Sign-off is the point of this screen. Until a named person confirms they have
 * read the statement, everything it produces is watermarked as a draft — and
 * changing any of the wording afterwards withdraws that sign-off again, so an
 * approved statement can never quietly say something its approver never read.
 *
 * @return {Element} The panel.
 */
export default function StatementSettings() {
	const [ state, setState ] = useState( {
		status: 'loading',
		data: null,
		error: '',
	} );
	const [ name, setName ] = useState( '' );
	const [ role, setRole ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );

	const load = () => {
		setState( ( previous ) => ( { ...previous, status: 'loading' } ) );

		fetchStatement()
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

	const save = ( changes ) => {
		setNotice( '' );

		saveStatement( changes )
			.then( ( data ) =>
				setState( { status: 'ready', data, error: '' } )
			)
			.catch( ( error ) => setNotice( readableError( error ) ) );
	};

	if ( state.status === 'loading' ) {
		return (
			<Skeleton
				label={ __(
					'Loading your statement…',
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
		statuses,
		standards,
		missing,
		attested,
		preview,
		shortcode,
	} = state.data;

	return (
		<section
			className="wsak-statement-panel"
			aria-labelledby="wsak-statement-title"
		>
			<h2 className="wsak-settings__title" id="wsak-statement-title">
				{ __(
					'Accessibility statement',
					'wowstudio-accessibility-kit'
				) }
			</h2>

			<p className="wsak-settings__lede">
				{ __(
					'A statement tells people what you know about your own site and how to reach you when something does not work for them. This writes the draft; what it says is yours to decide, and yours to stand behind.',
					'wowstudio-accessibility-kit'
				) }
			</p>

			{ notice && (
				<Notice status="error" onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }

			<Notice
				status={ attested ? 'success' : 'warning' }
				isDismissible={ false }
			>
				{ attested
					? __(
							'Signed off. This statement is published as a finished document.',
							'wowstudio-accessibility-kit'
					  )
					: __(
							'Draft. Until somebody signs this off it publishes with a notice saying nobody has checked it.',
							'wowstudio-accessibility-kit'
					  ) }
			</Notice>

			<TextControl
				__nextHasNoMarginBottom
				label={ __(
					'Who this statement is from',
					'wowstudio-accessibility-kit'
				) }
				value={ settings.organisation }
				onChange={ ( organisation ) => save( { organisation } ) }
			/>

			<SelectControl
				__nextHasNoMarginBottom
				label={ __(
					'Standard you are aiming for',
					'wowstudio-accessibility-kit'
				) }
				value={ settings.standard }
				options={ standards.map( ( s ) => ( {
					label: s.label,
					value: s.value,
				} ) ) }
				onChange={ ( standard ) => save( { standard } ) }
			/>

			<SelectControl
				__nextHasNoMarginBottom
				label={ __(
					'Where you think you stand',
					'wowstudio-accessibility-kit'
				) }
				help={ __(
					'This is your assessment, published under your name. Nothing in this plugin can verify it — automated testing covers only part of the standard, and the statement says so.',
					'wowstudio-accessibility-kit'
				) }
				value={ settings.status }
				options={ statuses.map( ( s ) => ( {
					label: s.label,
					value: s.value,
				} ) ) }
				onChange={ ( status ) => save( { status } ) }
			/>

			<TextareaControl
				__nextHasNoMarginBottom
				label={ __(
					'Known problems, one per line',
					'wowstudio-accessibility-kit'
				) }
				help={ __(
					'Plain descriptions of what does not work yet. This is the part readers find most useful.',
					'wowstudio-accessibility-kit'
				) }
				rows={ 4 }
				value={ settings.known_limitations }
				onChange={ ( limitations ) =>
					setState( ( previous ) => ( {
						...previous,
						data: {
							...previous.data,
							settings: {
								...previous.data.settings,
								known_limitations: limitations,
							},
						},
					} ) )
				}
				onBlur={ () =>
					save( { known_limitations: settings.known_limitations } )
				}
			/>

			<h3 className="wsak-settings__subtitle">
				{ __( 'How people reach you', 'wowstudio-accessibility-kit' ) }
			</h3>

			<TextControl
				__nextHasNoMarginBottom
				type="email"
				label={ __( 'Email address', 'wowstudio-accessibility-kit' ) }
				value={ settings.feedback_email }
				onChange={ ( email ) => save( { feedback_email: email } ) }
			/>

			<TextControl
				__nextHasNoMarginBottom
				label={ __(
					'Contact form address',
					'wowstudio-accessibility-kit'
				) }
				value={ settings.feedback_url }
				onChange={ ( url ) => save( { feedback_url: url } ) }
			/>

			<TextControl
				__nextHasNoMarginBottom
				type="number"
				label={ __(
					'Working days you aim to reply within',
					'wowstudio-accessibility-kit'
				) }
				value={ String( settings.response_days ) }
				onChange={ ( days ) => save( { response_days: days } ) }
			/>

			<TextControl
				__nextHasNoMarginBottom
				label={ __(
					'Who people can escalate to (optional)',
					'wowstudio-accessibility-kit'
				) }
				help={ __(
					'The body that handles complaints where you operate. This differs by country, so it is left to you.',
					'wowstudio-accessibility-kit'
				) }
				value={ settings.enforcement_body }
				onChange={ ( body ) => save( { enforcement_body: body } ) }
			/>

			{ missing.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<p>
						{ __(
							'Still to do before this can be signed off:',
							'wowstudio-accessibility-kit'
						) }
					</p>
					<ul className="wsak-statement-panel__missing">
						{ missing.map( ( item ) => (
							<li key={ item }>{ item }</li>
						) ) }
					</ul>
				</Notice>
			) }

			<h3 className="wsak-settings__subtitle">
				{ __( 'Sign-off', 'wowstudio-accessibility-kit' ) }
			</h3>

			{ attested ? (
				<>
					<p className="wsak-settings__usage">
						{ __( 'Signed off by', 'wowstudio-accessibility-kit' ) }{ ' ' }
						<strong>{ settings.attested_by }</strong>
						{ settings.attested_role
							? `, ${ settings.attested_role }`
							: '' }
						{ settings.attested_at
							? ` — ${ settings.attested_at }`
							: '' }
					</p>
					<Button
						variant="secondary"
						onClick={ () =>
							withdrawStatement()
								.then( ( data ) =>
									setState( {
										status: 'ready',
										data,
										error: '',
									} )
								)
								.catch( ( error ) =>
									setNotice( readableError( error ) )
								)
						}
					>
						{ __(
							'Withdraw sign-off',
							'wowstudio-accessibility-kit'
						) }
					</Button>
				</>
			) : (
				<>
					<p className="wsak-settings__privacy">
						{ __(
							'Read the preview below before signing off. Putting your name to it says you have checked that it is accurate — not that the plugin has, because it cannot.',
							'wowstudio-accessibility-kit'
						) }
					</p>
					<TextControl
						__nextHasNoMarginBottom
						label={ __(
							'Your name',
							'wowstudio-accessibility-kit'
						) }
						value={ name }
						onChange={ setName }
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={ __(
							'Your role (optional)',
							'wowstudio-accessibility-kit'
						) }
						value={ role }
						onChange={ setRole }
					/>
					<Button
						variant="primary"
						disabled={ ! name || missing.length > 0 }
						onClick={ () =>
							attestStatement( name, role )
								.then( ( data ) =>
									setState( {
										status: 'ready',
										data,
										error: '',
									} )
								)
								.catch( ( error ) =>
									setNotice( readableError( error ) )
								)
						}
					>
						{ __(
							'I have read this and it is accurate',
							'wowstudio-accessibility-kit'
						) }
					</Button>
				</>
			) }

			<h3 className="wsak-settings__subtitle">
				{ __( 'Publishing it', 'wowstudio-accessibility-kit' ) }
			</h3>

			<p className="wsak-settings__usage">
				{ __(
					'Add the Accessibility statement block to any page, or use this shortcode:',
					'wowstudio-accessibility-kit'
				) }{ ' ' }
				<code>{ shortcode }</code>
			</p>

			<h3 className="wsak-settings__subtitle">
				{ __( 'Preview', 'wowstudio-accessibility-kit' ) }
			</h3>

			<div
				className="wsak-statement-panel__preview"
				// The preview is generated by StatementGenerator, which escapes
				// every value it renders. It is our own output, not user input
				// echoed back.
				dangerouslySetInnerHTML={ { __html: preview } }
			/>
		</section>
	);
}
