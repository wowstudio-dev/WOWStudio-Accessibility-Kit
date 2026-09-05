/**
 * Where an undescribed image gets described.
 */

import { __ } from '@wordpress/i18n';

/**
 * Points a missing-alt finding at the screen that answers it.
 *
 * There is no button here on purpose. A description depends on why the image
 * was put on the page, which nothing in this plugin can work out, so offering
 * to produce one would be offering a guess dressed as an answer. What it can
 * do is take you straight to the field.
 *
 * @param {Object} props                Component props.
 * @param {number} [props.attachmentId] The image, when the scan identified one.
 * @return {Element} The handoff row.
 */
export default function AltTextHandoff( { attachmentId } ) {
	const editUrl = attachmentId
		? `${
				window.wsakSettings?.adminUrl || ''
		  }post.php?post=${ attachmentId }&action=edit`
		: '';

	return (
		<p className="wsak-issue__handoff">
			<span className="wsak-issue__handoff-note">
				{ __(
					'What this image tells the reader depends on why it is on the page, so nothing here can write it for you. The Images screen lists every undescribed image with a field beside each, which is faster than opening them one at a time.',
					'wowstudio-accessibility-kit'
				) }
			</span>

			{ editUrl && (
				<a
					className="wsak-issue__handoff-link"
					href={ editUrl }
					target="_blank"
					rel="noreferrer"
				>
					{ __(
						'Describe this image',
						'wowstudio-accessibility-kit'
					) }
					<span className="screen-reader-text">
						{ ' ' }
						{ __(
							'(opens the media screen in a new tab)',
							'wowstudio-accessibility-kit'
						) }
					</span>
				</a>
			) }
		</p>
	);
}
