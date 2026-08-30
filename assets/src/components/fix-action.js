/**
 * Proposing, reviewing, applying, and undoing one fix.
 */

import { Button, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { applyFix, previewFix, readableError, revertFix } from '../api';
import { Busy } from './states';

/**
 * Renders the comparison between current and proposed markup.
 *
 * Additions and removals are labelled in text as well as marked visually.
 * Colour alone would fail the very criterion this plugin reports on, and a
 * screen reader user reviewing a change needs to know which half is which.
 *
 * @param {Object} props          Component props.
 * @param {Array}  props.segments Diff segments from the server.
 * @return {Element} The comparison.
 */
function DiffView( { segments } ) {
	return (
		<div className="wsak-diff">
			<h5 className="wsak-diff__title">
				{ __( 'What would change', 'wowstudio-accessibility-kit' ) }
			</h5>
			<pre
				className="wsak-diff__body"
				tabIndex="0"
				role="group"
				aria-label={ __(
					'Proposed markup change',
					'wowstudio-accessibility-kit'
				) }
			>
				<code>
					{ segments.map( ( segment, index ) => {
						if ( segment.type === 'same' ) {
							return <span key={ index }>{ segment.value }</span>;
						}

						const isAdded = segment.type === 'added';

						return (
							<span
								key={ index }
								className={ `wsak-diff__${
									isAdded ? 'added' : 'removed'
								}` }
							>
								<span className="screen-reader-text">
									{ isAdded
										? __(
												'Added:',
												'wowstudio-accessibility-kit'
										  )
										: __(
												'Removed:',
												'wowstudio-accessibility-kit'
										  ) }
								</span>
								{ segment.value }
								<span className="screen-reader-text">
									{ __(
										'end of change.',
										'wowstudio-accessibility-kit'
									) }
								</span>
							</span>
						);
					} ) }
				</code>
			</pre>
		</div>
	);
}

/**
 * The propose-review-apply-undo loop for one finding.
 *
 * Nothing is changed until a person presses apply, and applying stores an
 * override rather than editing the content, so undo is always available and
 * always complete.
 *
 * @param {Object} props         Component props.
 * @param {number} props.issueId Finding to fix.
 * @return {Element} The action.
 */
export default function FixAction( { issueId } ) {
	const [ status, setStatus ] = useState( 'idle' );
	const [ preview, setPreview ] = useState( null );
	const [ fix, setFix ] = useState( null );
	const [ error, setError ] = useState( '' );

	// A live region has to be in the document before its text changes, or the
	// announcement is made to a node the screen reader was not yet watching
	// and is simply lost. So this one is always mounted and always empty until
	// there is something to say.
	const [ announcement, setAnnouncement ] = useState( '' );

	const propose = () => {
		setStatus( 'working' );
		setError( '' );

		previewFix( issueId )
			.then( ( data ) => {
				setPreview( data );
				setStatus( 'review' );
				setAnnouncement(
					__(
						'A fix has been suggested. Review the proposed change before applying it.',
						'wowstudio-accessibility-kit'
					)
				);
			} )
			.catch( ( caught ) => {
				setError( readableError( caught ) );
				setStatus( 'idle' );
			} );
	};

	const accept = () => {
		setStatus( 'applying' );

		applyFix( issueId, preview.after )
			.then( ( data ) => {
				setFix( data.fix );
				setStatus( 'applied' );
				setAnnouncement(
					__(
						'Fix applied as an override. Your content is unchanged and this can be undone.',
						'wowstudio-accessibility-kit'
					)
				);
			} )
			.catch( ( caught ) => {
				setError( readableError( caught ) );
				setStatus( 'review' );
			} );
	};

	const undo = () => {
		setStatus( 'reverting' );

		revertFix( fix.id )
			.then( () => {
				setFix( null );
				setPreview( null );
				setStatus( 'idle' );
				setAnnouncement(
					__( 'Fix undone.', 'wowstudio-accessibility-kit' )
				);
			} )
			.catch( ( caught ) => {
				setError( readableError( caught ) );
				setStatus( 'applied' );
			} );
	};

	return (
		<div className="wsak-fix">
			<p className="screen-reader-text" role="status" aria-live="polite">
				{ announcement }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ status === 'idle' && (
				<Button variant="secondary" onClick={ propose }>
					{ __( 'Suggest a fix', 'wowstudio-accessibility-kit' ) }
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

			{ ( status === 'review' || status === 'applying' ) && preview && (
				<div className="wsak-fix__review">
					{ ! preview.applicable && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'This markup is not in the post content — it comes from the theme, so an override cannot reach it. The change needs making in the theme or a template.',
								'wowstudio-accessibility-kit'
							) }
						</Notice>
					) }

					<DiffView segments={ preview.diff } />

					<p className="wsak-fix__note">
						{ __(
							'Read this before applying. A model can produce markup that is valid and still wrong for your page. Nothing is written to your content: applying stores an override that you can undo at any time.',
							'wowstudio-accessibility-kit'
						) }
					</p>

					<div className="wsak-fix__actions">
						<Button
							variant="primary"
							disabled={
								status === 'applying' || ! preview.applicable
							}
							onClick={ accept }
						>
							{ __(
								'Apply this fix',
								'wowstudio-accessibility-kit'
							) }
						</Button>
						<Button variant="tertiary" onClick={ propose }>
							{ __(
								'Suggest another',
								'wowstudio-accessibility-kit'
							) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => {
								setPreview( null );
								setStatus( 'idle' );
							} }
						>
							{ __( 'Discard', 'wowstudio-accessibility-kit' ) }
						</Button>
					</div>
				</div>
			) }

			{ status === 'reverting' && (
				<Busy
					label={ __( 'Undoing…', 'wowstudio-accessibility-kit' ) }
				/>
			) }

			{ status === 'applied' && fix && (
				<div className="wsak-fix__applied">
					<p>
						{ __(
							'Applied as an override. Your content is unchanged, and this can be undone at any time.',
							'wowstudio-accessibility-kit'
						) }
					</p>
					<Button variant="secondary" onClick={ undo }>
						{ __( 'Undo this fix', 'wowstudio-accessibility-kit' ) }
					</Button>
				</div>
			) }
		</div>
	);
}
