/**
 * The plain-language summary, written where the page is written.
 */

import { PanelBody, TextareaControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * The field for WCAG 3.1.5's simplified summary.
 *
 * Deliberately a plain textarea with no assistance of any kind. Summarising a
 * difficult page in plain language is writing, and it is the one part of 3.1.5
 * that actually helps somebody — measuring the reading level is easy and
 * changes nothing. Nothing here drafts it, suggests it, or scores it: a summary
 * produced by a machine would read plausibly and mean whatever the machine
 * guessed, which is worse than an empty box because it looks finished.
 *
 * @return {Element|null} The panel, or nothing on a post type without meta.
 */
export default function SummaryPanel() {
	const postType = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostType(),
		[]
	);

	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	if ( ! postType || ! meta || undefined === meta.wsak_simplified_summary ) {
		return null;
	}

	return (
		<PanelBody
			title={ __(
				'Plain-language summary',
				'wowstudio-accessibility-kit'
			) }
			initialOpen={ false }
		>
			<TextareaControl
				label={ __(
					'Summary of this page',
					'wowstudio-accessibility-kit'
				) }
				help={ __(
					'For readers who would struggle with the full text: what this page says, in the simplest words that still say it. Leave it empty if the page is already plain. Nothing writes this for you — a summary a machine guessed at reads convincingly and means whatever it guessed, which is worse than none.',
					'wowstudio-accessibility-kit'
				) }
				value={ meta.wsak_simplified_summary || '' }
				rows={ 6 }
				onChange={ ( value ) =>
					setMeta( { ...meta, wsak_simplified_summary: value } )
				}
				__nextHasNoMarginBottom
			/>
		</PanelBody>
	);
}
