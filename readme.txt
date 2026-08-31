=== WOWStudio Accessibility Kit ===
Contributors: wowstudio
Tags: accessibility, wcag, a11y, alt text, accessibility scanner
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.11.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find, fix, document, and monitor WCAG accessibility issues at the code level. Real fixes you review before they apply — not an overlay.

== Description ==

WOWStudio Accessibility Kit helps you **find, fix, document, and monitor** accessibility issues in your WordPress site. It scans your pages against WCAG 2.2 A and AA success criteria, explains what it found in plain language, and proposes real changes to your markup that you review before anything is applied.

It is not an accessibility overlay. Nothing is injected into your front end, and no widget or toolbar is added for your visitors. Fixes are changes to the code itself.

= How it works =

* **Scan, twice.** A server-side scan reads the rendered HTML and checks it against a registry of WCAG rules. A second pass then runs in your browser, on the page as it was actually painted, to check the things no parser can know: real contrast, real target sizes, real layout. No external service is contacted for either.
* **See it.** The inspector puts the findings beside a live preview of the page. Choosing a finding highlights the element it is about, so you are never left guessing which paragraph or which button.
* **Understand.** Every issue is tagged with its WCAG success criterion and severity, and labelled either *auto-detected* or *needs manual review*.
* **Fix.** Suggested markup fixes are shown as a preview and a diff, and applied fixes are stored in a reversible override layer that never overwrites your content. Findings caused by styling instead get a style rule, written into your own Additional CSS — where you can read, edit or delete it without this plugin.
* **Check the fix worked.** After a style rule is applied, the page is loaded again and measured again. If something in your theme overrode the rule, you are told that, rather than being told it is fixed.
* **Document.** Generate an accessibility statement you edit and publish, including the feedback contact mechanism European rules expect.

= Honesty about what automated testing can do =

Automated testing can only detect part of WCAG. Many criteria — meaningful sequence, focus order, whether your alt text is actually accurate — require a human being. This plugin tells you which is which, in a coverage panel you can read at any time, and it will never report your site as finished.

This plugin **does not** determine or certify whether your site complies with the ADA, the European Accessibility Act, Section 508, AODA, the UK Equality Act, or any other law. It is a tool that helps you do accessibility work and keep a record of it. It is not legal advice, and it is not a substitute for testing with disabled users.

= What it will not do =

* No front-end accessibility widget, toolbar, or overlay.
* No silent changes to your content. Every fix is reviewed by a person first.
* No claim, anywhere, that your site is compliant, certified, or protected from legal action.
* No conformance document published without your explicit review and attestation.

== External services ==

This plugin does not contact any external service on its own. The scanner,
the statement generator and the reports all run entirely on your own site.

The AI features are **bring your own key** and opt-in: nothing is sent anywhere
until you have connected a provider account and then pressed a button asking for
a suggestion. You choose the provider, you hold the account, and you are billed
by them directly. If you never configure a provider, this plugin makes no
outbound requests at all.

= When a request is made =

* **"Suggest alt text"** sends the image itself, its file name, and — only if the "Send the page title and a little surrounding text" setting is on, which it is by default — the page title and at most 300 characters of the text around the image. Turning that setting off sends the image alone.
* **"Suggest a fix"** sends the markup of the single element that failed a check, plus a description of the check it failed. It does not send the rest of the page.

Nothing else leaves your site: not your other content, not your visitors' data,
not your database. Individual posts can be excluded entirely with the
`_wsak_skip_ai` post meta, and the whole feature respects the site's own AI
switch (`WP_AI_SUPPORT` / the `wp_supports_ai` filter).

On WordPress 7.0 and later, if you have configured a provider in WordPress's own
AI client, that is used in preference to the key stored here and the request is
made by WordPress rather than by this plugin.

= The providers you can choose =

**OpenAI** — requests go to `https://api.openai.com/v1/chat/completions`.
Get a key at https://platform.openai.com/api-keys
Terms: https://openai.com/policies/terms-of-use
Privacy: https://openai.com/policies/privacy-policy

**Anthropic** — requests go to `https://api.anthropic.com/v1/messages`.
Get a key at https://console.anthropic.com/settings/keys
Terms: https://www.anthropic.com/legal/consumer-terms
Privacy: https://www.anthropic.com/legal/privacy

**Google Gemini** — requests go to `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`.
Get a key at https://aistudio.google.com/app/apikey
Terms: https://ai.google.dev/gemini-api/terms
Privacy: https://policies.google.com/privacy

**OpenRouter** — requests go to `https://openrouter.ai/api/v1/chat/completions`.
OpenRouter is a router: it forwards your request to whichever model you pick,
so that model's provider also receives the data.
Get a key at https://openrouter.ai/keys
Terms: https://openrouter.ai/terms
Privacy: https://openrouter.ai/privacy

Your API key is stored encrypted on your own site and is sent only to the
provider it belongs to. It is never shown again after you save it, never
returned to your browser, and never sent to WOWStudio.

= Licensing =

Licensing, updates and optional telemetry are handled by Freemius. Telemetry is
opt-in: it is offered when you activate the plugin and you can decline. See
https://freemius.com/privacy/ for what Freemius collects.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/wowstudio-accessibility-kit`, or install it through **Plugins → Add New**.
2. Activate it through the **Plugins** menu.
3. Open **Accessibility Kit** in the admin menu and run your first scan.

== Frequently Asked Questions ==

= Will this make my site compliant? =

No, and be careful of any tool that says it will. Accessibility conformance is a property of your site and your organisation's practices, not something a plugin can confer. This plugin helps you find issues, fix them properly, document what you did, and notice when something regresses. The remaining work — manual testing, testing with disabled users, editorial judgement — is yours.

= Is this an accessibility overlay? =

No. Overlays layer a script over your site at run time and are widely criticised by disabled users, and have drawn regulatory action. This plugin changes the underlying markup instead, and adds nothing to your front end for visitors.

= Can I undo a fix? =

Yes. Fixes are stored in a reversible override layer rather than being written over your content, and each one can be reverted.

= Does it require an AI subscription? =

AI features are bring-your-own-key. You connect your own provider account, and you can use the scanner without any AI provider at all.

== Changelog ==

= 0.11.0 =
* Findings caused by styling can now be fixed, not just reported: text contrast, links marked by colour alone, and controls too small to hit reliably. The proposed colour keeps the hue and saturation you chose and moves only as far as it has to.
* Fixes of this kind are written into your own Additional CSS, under Appearance → Customise, where you can read, edit or delete them with or without this plugin. Everything already in that stylesheet is preserved exactly.
* Before applying, you see the selector, how many elements it matches, and a warning when the selector is one that will break as your content changes. The selector is editable.
* After applying, the page is loaded again and measured again — so you are told when a rule in your theme overrode the fix, instead of being told it worked.
* The two rules a stylesheet cannot answer now say why, and what to change instead.
* Redesigned admin screens.
* Fixed a finding that reported a control as "170 by 24 pixels" and then asked for 24 by 24. The measurement was rounded and the comparison was not.

= 0.10.0 =
* Added a second scanning pass that runs in your browser, checking what only a rendered page can show: text contrast, target size, links distinguished by colour alone, scrollable regions no keyboard can reach, and hidden elements still in the tab order.
* Added the inspector: findings on the left, a live preview of the page on the right. Choosing a finding highlights the element on the page.
* Contrast that cannot be measured — text over a photograph, stacked translucency — is reported as needing a person rather than guessed at and reported as a pass.
* Fixed a build failure on a fresh checkout, where static analysis ran before the admin bundle existed.

= 0.9.0 =
* Security: closed a hole that let anyone, without logging in, work out which post IDs existed on your site — including drafts and private posts. No titles or content were ever exposed, only whether an ID was in use. Please update.
* Added the External services section describing exactly what each AI provider receives, and when.
* Fixed JavaScript strings not being checked for their text domain, which had been quietly leaving some of them untranslatable.

= 0.8.0 =
* Held the plugin's own admin UI to the standard it reports on. Named the focusable code and diff blocks, fixed the statement preview competing with the panel around it in the heading outline, and fixed three status messages that announced nothing to a screen reader.
* Added guards that run on every push: all 41 colour pairings in the admin UI are checked against WCAG 2.2 AA contrast, and 17 required honesty disclosures across 10 screens are checked for still being there.
* Added the audit of our own interface, including a full account of what has not been checked. See docs/accessibility-audit.md in the repository.

= 0.7.0 =
* Accessibility statement generator, as a block and a shortcode, with the feedback contact route European rules expect.
* Sign-off: an unsigned statement publishes as a draft, and editing the wording withdraws the sign-off automatically.

= 0.6.0 =
* Suggested fixes, one at a time, with a preview, a diff, and an undo. Applied fixes are stored in a reversible override layer and never overwrite your content.

= 0.5.0 =
* Bring-your-own-key AI providers (OpenAI, Anthropic, Gemini, OpenRouter), with keys encrypted at rest and never returned to the browser.
* AI alt-text suggestions for a single image, always shown in an editable field before anything is saved.

= 0.4.0 =
* The dashboard: run a scan, read the findings grouped by whether a machine settled them, and open the coverage panel listing every check and what it can decide.

= 0.3.0 =
* The scanner: server-side WCAG 2.2 checks over the rendered HTML of a page, with every finding tagged auto-detected or needs manual review.

= 0.2.0 =
* Storage layer for scans and findings.

= 0.1.0 =
* Initial scaffold: plugin bootstrap, capabilities, activation and uninstall handling.

== Upgrade Notice ==

= 0.11.0 =
Style-caused findings can now be fixed as well as found, written into your own Additional CSS and verified by re-measuring the page.

= 0.10.0 =
Adds a browser-level scanning pass and the inspector, which shows each finding on the page it came from.

= 0.9.0 =
Security fix: an unauthenticated visitor could determine which post IDs existed on your site, drafts included. No content was exposed. Update recommended.

= 0.8.0 =
Accessibility fixes to the plugin's own admin screens, and new checks that keep them fixed. No changes to your site's content.

= 0.1.0 =
First development release.
