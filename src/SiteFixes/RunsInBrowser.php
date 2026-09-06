<?php
/**
 * Site fixes that need the rendered page.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes;

defined( 'ABSPATH' ) || exit;

/**
 * A site fix that can only be carried out in the browser.
 *
 * This interface exists to make one decision visible rather than to add any
 * behaviour, so it declares no methods. It marks the fixes that need front-end
 * JavaScript, and being able to answer "which of these run script on my
 * visitors' pages?" by reading `implements RunsInBrowser` is the point.
 *
 * ## Why any of this runs in the browser
 *
 * Everything else in this layer supplies something *missing* through a filter:
 * a link that was not there, a title nobody asked for, an attribute the theme
 * left off. Five fixes are different in kind, because the fault is markup the
 * theme has already printed and the correction is to change it. A `tabindex="4"`
 * has to be found and altered. A `<meta name="viewport">` has to be rewritten.
 * No filter reaches those, because by the time the page is a string it is too
 * late, and the only server-side way to reach them would be to buffer the whole
 * response and rewrite it — which SPEC decision F6 rules out, and rightly:
 * rewriting whatever HTML happens to come past is how an overlay works.
 *
 * So either these five do not exist, or they run in the browser. They run in
 * the browser.
 *
 * ## What this is not
 *
 * It is emphatically not the overlay that product rule 2 forbids, and the
 * difference is not a matter of degree. An overlay adds a widget: a button, a
 * panel, controls for font size and contrast and greyscale, sitting on top of a
 * site that is still broken underneath. This adds **no interface of any kind**.
 * It renders nothing, it has no controls, and a visitor cannot tell it is
 * there. It makes a short list of specific, named corrections to specific,
 * named faults and then stops.
 *
 * The rules it follows, which the script itself must keep:
 *
 * - **Nothing is invented.** Where a fix would have to guess at meaning it does
 *   not run. The form-field fix promotes wording the author already wrote; it
 *   never manufactures a label out of a field's name attribute.
 * - **No interface.** Nothing is rendered, nothing is styled, nothing appears.
 * - **Narrow.** Each fix selects the exact fault it is named for. None of them
 *   walks the document looking for things to improve.
 * - **Once, and idempotent.** Running twice changes nothing the first run did
 *   not already do.
 * - **Degrades to nothing.** With JavaScript off, the page is exactly the page
 *   the theme produced. These fixes therefore repair the experience for people
 *   who have script; they are not a substitute for fixing the theme, and the
 *   caveat on each one says so.
 *
 * @since 0.20.0
 */
interface RunsInBrowser extends SiteFix {
}
