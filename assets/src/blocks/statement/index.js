/**
 * The accessibility statement block.
 *
 * Server-rendered on purpose. The statement's content, and whether it has been
 * approved, live in settings — so a block that saved its own copy of the markup
 * could go on showing an approved statement after the settings behind it had
 * changed. Rendering on the server means the page always shows the current
 * statement and the current draft state.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';
import './style.scss';

/**
 * The block as it appears in the editor.
 *
 * @return {Element} The editor view.
 */
function Edit() {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<ServerSideRender
				block={ metadata.name }
				EmptyResponsePlaceholder={ () => (
					<p>
						{ __(
							'Nothing to show yet. Write your accessibility statement in the Accessibility settings.',
							'wowstudio-accessibility-kit'
						) }
					</p>
				) }
			/>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
