/**
 * Suggesting alt text for one image, and saving it once reviewed.
 */

import { Button, Notice, TextareaControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { applyAltText, generateAltText, readableError } from '../api';
import { Busy } from './states';

/**
 * The generate-review-save loop for a single image.
 *
 * The suggestion is always shown in an editable field before anything is
 * saved. Alt text written by a model and applied without a person reading it
 * is exactly the kind of unreviewed automation this plugin exists to avoid —
 * the model cannot know what the image is doing on the page.
 *
 * @param {Object}   props              Component props.
 * @param {number}   props.attachmentId Media item to describe.
 * @param {number}   props.postId       Page it appears on.
 * @param {Function} props.onUsage      Called with the updated allowance.
 * @return {Element} The action.
 */
export default function AltTextAction( { attachmentId, postId, onUsage } ) {
	const [ status, setStatus ] = useState( 'idle' );
	const [ text, setText ] = useState( '' );
	const [ decorative, setDecorative ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ saved, setSaved ] = useState( false );

	if ( ! attachmentId ) {
		return (
			<p className="wsak-alt__unavailable">
				{ __(
					'This image is not in the media library, so alt text has to be added wherever it is defined.',
					'wowstudio-accessibility-kit'
				) }
			</p>
		);
	}

	const suggest = () => {
		setStatus( 'working' );
		setError( '' );
		setSaved( false );

		generateAltText( attachmentId, postId )
			.then( ( data ) => {
				setText( data.suggestion?.text ?? '' );
				setDecorative( Boolean( data.suggestion?.is_decorative ) );
				setStatus( 'review' );

				if ( onUsage && data.usage ) {
					onUsage( data.usage );
				}
			} )
			.catch( ( caught ) => {
				setError( readableError( caught ) );
				setStatus( 'idle' );
			} );
	};

	const save = () => {
		setStatus( 'saving' );

		applyAltText( attachmentId, decorative ? '' : text )
			.then( () => {
				setSaved( true );
				setStatus( 'review' );
			} )
			.catch( ( caught ) => {
				setError( readableError( caught ) );
				setStatus( 'review' );
			} );
	};

	return (
		<div className="wsak-alt">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ status === 'idle' && (
				<Button variant="secondary" onClick={ suggest }>
					{ __( 'Suggest alt text', 'wowstudio-accessibility-kit' ) }
				</Button>
			) }

			{ status === 'working' && (
				<Busy
					label={ __(
						'Asking your AI provider…',
						'wowstudio-accessibility-kit'
					) }
				/>
			) }

			{ ( status === 'review' || status === 'saving' ) && (
				<div className="wsak-alt__review">
					{ decorative ? (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'The model judged this image decorative, which means empty alt text. Only save that if the image really adds nothing the surrounding text does not already say.',
								'wowstudio-accessibility-kit'
							) }
						</Notice>
					) : (
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __(
								'Suggested alt text — edit before saving',
								'wowstudio-accessibility-kit'
							) }
							help={ __(
								'A model cannot know why this image is on the page. Read it, and change it if it describes the picture rather than the point.',
								'wowstudio-accessibility-kit'
							) }
							value={ text }
							rows={ 3 }
							onChange={ setText }
						/>
					) }

					<div className="wsak-alt__actions">
						<Button
							variant="primary"
							disabled={ status === 'saving' }
							onClick={ save }
						>
							{ decorative
								? __(
										'Save as decorative',
										'wowstudio-accessibility-kit'
								  )
								: __(
										'Save alt text',
										'wowstudio-accessibility-kit'
								  ) }
						</Button>
						<Button variant="tertiary" onClick={ suggest }>
							{ __( 'Try again', 'wowstudio-accessibility-kit' ) }
						</Button>
					</div>

					{ saved && (
						<p className="wsak-alt__saved" role="status">
							{ sprintf(
								/* translators: %d: media item ID. */
								__(
									'Saved to media item %d. Re-scan the page to confirm the issue is resolved.',
									'wowstudio-accessibility-kit'
								),
								attachmentId
							) }
						</p>
					) }
				</div>
			) }
		</div>
	);
}
