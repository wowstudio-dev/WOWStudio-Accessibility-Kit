/**
 * Setting a finding aside, and saying why.
 */

import { Button, Notice, TextareaControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { ignoreIssue, readableError, reopenIssue } from '../api';

/**
 * Dismissing a finding that is not a problem.
 *
 * Every scanner produces some of these — a table that lays out a form, a link
 * whose sentence supplies the meaning the rule cannot see. Without a way to say
 * so, the list stays permanently dirty and people stop reading it.
 *
 * The reason is required rather than optional. These records are what a
 * conformance document is eventually built from, and "somebody decided this was
 * fine" is not something anybody can stand behind a year later.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.issue    The finding.
 * @param {Function} props.onChange Called with the new review state.
 * @return {Element} The action.
 */
export default function DismissAction( { issue, onChange } ) {
	const [ open, setOpen ] = useState( false );
	const [ note, setNote ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ review, setReview ] = useState( {
		status: issue.status,
		note: issue.note ?? '',
		name: issue.resolved_by_name ?? '',
	} );

	const dismissed = review.status === 'ignored';

	const save = () => {
		setBusy( true );
		setError( '' );

		ignoreIssue( issue.id, note )
			.then( ( data ) => {
				setReview( {
					status: data.status,
					note: data.note,
					name: data.resolved_by_name,
				} );
				setOpen( false );
				setNote( '' );

				if ( onChange ) {
					onChange( data );
				}
			} )
			.catch( ( caught ) => setError( readableError( caught ) ) )
			.finally( () => setBusy( false ) );
	};

	const restore = () => {
		setBusy( true );

		reopenIssue( issue.id )
			.then( ( data ) => {
				setReview( {
					status: data.status,
					note: data.note,
					name: data.resolved_by_name,
				} );

				if ( onChange ) {
					onChange( data );
				}
			} )
			.catch( ( caught ) => setError( readableError( caught ) ) )
			.finally( () => setBusy( false ) );
	};

	if ( dismissed ) {
		return (
			<div className="wsak-dismiss wsak-dismiss--done">
				<p className="wsak-dismiss__record">
					<strong>
						{ __(
							'Marked a false positive.',
							'wowstudio-accessibility-kit'
						) }
					</strong>{ ' ' }
					{ review.note }
					{ review.name &&
						' ' +
							sprintf(
								/* translators: %s: name of the person who dismissed the finding. */
								__( '— %s', 'wowstudio-accessibility-kit' ),
								review.name
							) }
				</p>
				<Button variant="link" disabled={ busy } onClick={ restore }>
					{ __(
						'Not a false positive after all',
						'wowstudio-accessibility-kit'
					) }
				</Button>
			</div>
		);
	}

	if ( ! open ) {
		return (
			<Button
				variant="link"
				className="wsak-dismiss__open"
				onClick={ () => setOpen( true ) }
			>
				{ __(
					'Add to false positives',
					'wowstudio-accessibility-kit'
				) }
			</Button>
		);
	}

	return (
		<div className="wsak-dismiss">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<TextareaControl
				__nextHasNoMarginBottom
				label={ __(
					'Why is this a false positive?',
					'wowstudio-accessibility-kit'
				) }
				help={ __(
					'Kept with the finding, with your name and the date. This is the part that makes it a record somebody can rely on later rather than an unexplained dismissal — so write it for whoever reads it in a year, which may well be you.',
					'wowstudio-accessibility-kit'
				) }
				value={ note }
				rows={ 3 }
				onChange={ setNote }
			/>

			<div className="wsak-dismiss__actions">
				<Button variant="secondary" disabled={ busy } onClick={ save }>
					{ __(
						'Add to false positives',
						'wowstudio-accessibility-kit'
					) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ () => {
						setOpen( false );
						setError( '' );
					} }
				>
					{ __( 'Cancel', 'wowstudio-accessibility-kit' ) }
				</Button>
			</div>
		</div>
	);
}
