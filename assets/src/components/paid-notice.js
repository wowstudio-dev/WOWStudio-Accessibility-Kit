/**
 * What a paid feature does, shown where the button would be.
 */

import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Explains a locked feature in place of hiding it.
 *
 * Hiding it would be tidier and worse. Somebody who cannot find bulk checking
 * concludes the plugin does not do it; somebody who can see it, and can see
 * exactly what the free tier still does, is being told the truth about a choice
 * rather than being sold at.
 *
 * The wording leads with what is not limited, because that is the part people
 * get wrong about free tiers — and here it is genuinely generous: every check,
 * every fix, the inspector, the editor panel and the theme report, on as many
 * individual pages as you like.
 *
 * @param {Object} props         Component props.
 * @param {string} props.feature What is locked.
 * @return {Element} The notice.
 */
export default function PaidNotice( { feature } ) {
	return (
		<Notice status="info" isDismissible={ false }>
			<strong>{ feature }</strong>{ ' ' }
			{ __(
				'is part of the paid version.',
				'wowstudio-accessibility-kit'
			) }{ ' ' }
			{ __(
				'Checking and fixing one page at a time is not limited in any way — every check, the inspector, the editor panel, your theme report, and as many pages as you like, one after another. What you are paying for is doing them all at once.',
				'wowstudio-accessibility-kit'
			) }
		</Notice>
	);
}
