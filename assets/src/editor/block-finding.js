/**
 * One block's findings, and the fixes that can be made from here.
 */

import { Button, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * The findings on one block, with a way to jump to it.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.block    Block id and its findings.
 * @param {Function} props.onSelect Selects the block in the editor.
 * @return {Element} The group.
 */
export default function BlockFinding( { block, onSelect } ) {
	const name = useSelect(
		( select ) => {
			const found = select( 'core/block-editor' ).getBlock( block.id );

			if ( ! found ) {
				return '';
			}

			const type = select( 'core/blocks' ).getBlockType( found.name );

			return type?.title ?? found.name;
		},
		[ block.id ]
	);

	return (
		<div className="wsak-editor__block">
			<Button
				variant="link"
				className="wsak-editor__jump"
				onClick={ onSelect }
			>
				{ name
					? sprintf(
							/* translators: %s: block type, such as "Image". */
							__(
								'Go to this %s',
								'wowstudio-accessibility-kit'
							),
							name
					  )
					: __( 'Go to this block', 'wowstudio-accessibility-kit' ) }
			</Button>

			<ul className="wsak-editor__findings">
				{ block.findings.map( ( finding, index ) => (
					<li
						key={ `${ finding.rule_id }-${ index }` }
						className="wsak-editor__finding"
					>
						<p className="wsak-editor__finding-title">
							{ finding.rule_title }
						</p>
						{ finding.consequence && (
							<p className="wsak-editor__finding-why">
								{ finding.consequence }
							</p>
						) }

						<AltFix blockId={ block.id } finding={ finding } />
					</li>
				) ) }
			</ul>
		</div>
	);
}

/**
 * Writing a description straight into the image block.
 *
 * The point of checking inside the editor rather than against a rendered page.
 * This is not an override and not a filter: it sets the block's own attribute,
 * so the fix is in the post content, it is visible in the editor immediately,
 * and it is undone with the editor's own undo like any other edit somebody
 * makes. Nothing about it depends on this plugin still being installed.
 *
 * Deliberately only offered for the image block. Everything else in the panel
 * either needs a description written somewhere the editor cannot reach, or needs
 * a judgement the block does not contain — and a text box that quietly did
 * nothing would be worse than no text box.
 *
 * @param {Object} props         Component props.
 * @param {string} props.blockId Block being fixed.
 * @param {Object} props.finding The finding.
 * @return {?Element} The fix, or nothing when this finding has none here.
 */
function AltFix( { blockId, finding } ) {
	const [ text, setText ] = useState( '' );

	const block = useSelect(
		( select ) => select( 'core/block-editor' ).getBlock( blockId ),
		[ blockId ]
	);

	const { updateBlockAttributes } = useDispatch( 'core/block-editor' );

	if (
		'img-alt-missing' !== finding.rule_id ||
		block?.name !== 'core/image'
	) {
		return finding.fix?.summary ? (
			<p className="wsak-editor__finding-plan">{ finding.fix.summary }</p>
		) : null;
	}

	return (
		<div className="wsak-editor__fix">
			<TextControl
				__nextHasNoMarginBottom
				label={ __(
					'Describe this image',
					'wowstudio-accessibility-kit'
				) }
				help={ __(
					'Say what somebody would miss if the image did not load. If it is purely decorative, save it empty — that tells a screen reader to skip it.',
					'wowstudio-accessibility-kit'
				) }
				value={ text }
				onChange={ setText }
			/>
			<div className="wsak-editor__fix-actions">
				<Button
					variant="primary"
					onClick={ () => {
						updateBlockAttributes( blockId, { alt: text } );
						setText( '' );
					} }
				>
					{ __( 'Add it', 'wowstudio-accessibility-kit' ) }
				</Button>
			</div>
			<p className="wsak-editor__fix-note">
				{ __(
					'This writes into the block itself, so it is part of your content and undo works on it like any other edit.',
					'wowstudio-accessibility-kit'
				) }
			</p>
		</div>
	);
}
