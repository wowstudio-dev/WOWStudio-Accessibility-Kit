=== WOWStudio Accessibility Kit ===
Contributors: wowstudio
Tags: accessibility, wcag, a11y, alt text, accessibility scanner
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.16.0
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
* **Describe your images.** One screen lists every image in your media library that has never been described, with a field beside each. Type, save, move on — instead of opening forty media screens.
* **Do it site-wide.** Check every page and post in one run, in the background. There is no page limit and no paid tier.
* **Document.** Generate an accessibility statement you edit and publish, including the feedback contact mechanism European rules expect.

= Honesty about what automated testing can do =

Automated testing can only detect part of WCAG. Many criteria — meaningful sequence, focus order, whether your alt text is actually accurate — require a human being. This plugin tells you which is which, in a coverage panel you can read at any time, and it will never report your site as finished.

This plugin **does not** determine or certify whether your site complies with the ADA, the European Accessibility Act, Section 508, AODA, the UK Equality Act, or any other law. It is a tool that helps you do accessibility work and keep a record of it. It is not legal advice, and it is not a substitute for testing with disabled users.

= What it will not do =

* No front-end accessibility widget, toolbar, or overlay.
* No silent changes to your content. Every fix is reviewed by a person first.
* No claim, anywhere, that your site is compliant, certified, or protected from legal action.
* No conformance document published without your explicit review and attestation.

== Bundled libraries ==

This plugin bundles **Action Scheduler** by Automattic, which runs the
background work — checking many pages at once — so that long jobs never block
an admin page or time out half-finished. It is licensed GPLv3 or later; this
plugin's own code is GPLv2 or later.

Action Scheduler makes no outbound requests of its own. Source and documentation:
https://actionscheduler.org

== External services ==

This plugin contacts no external service, ever. There is no API, no account, no
telemetry, and no phone-home. The scanner, the fixes, the alt-text screen, the
statement generator and the reports all run entirely on your own site, and the
plugin works the same whether or not the server can reach the internet.

That is also why there are no scanning limits. Nothing is metered because
nothing is being paid for per page.

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

= Does it use AI? =

No. Nothing here is generated, and nothing is sent anywhere. Where an answer depends on what your content means — what an image is for, what a link promises — the plugin says so and gives you the fastest way to write it yourself, rather than producing a plausible sentence and leaving you to notice it is wrong.

= Is any of it paid? =

No. Everything in this plugin is free, including checking your whole site at once. There are no locked buttons and no upgrade prompts.

= Where is the alt text saved? =

In WordPress's own alt text field on the media item. It therefore applies everywhere that image is used, works with every theme and plugin, and stays behind if you remove this plugin.

== Changelog ==

= 0.16.0 =
* Everything in this plugin is now free. Checking your whole site at once was the paid feature; it is not paid any more, and there are no locked buttons left anywhere.
* Removed the licensing SDK entirely. No account, no opt-in screen, no telemetry.
* Removed the AI features. Nothing is generated and nothing leaves your site — this plugin now makes no outbound requests of any kind, to anyone, ever.
* Added: an Images screen listing every image in your media library that has never been described, with a field beside each and one button to save them all. It writes WordPress's own alt text, so it applies wherever the image is used and stays behind if you remove the plugin.
* Images already marked decorative are left alone. An empty description is a decision somebody made, not a gap to fill.
* Findings whose wording depends on what your content means — alt text, link names, button names, labels — are now grouped under "Needs a decision from you" rather than offering a draft. That is what they always were.

= 0.15.1 =
* Fixed: findings that a style rule answers — colour contrast, links marked by colour alone, and targets under 24 by 24 — offered a button that asked for an AI provider key. They need no AI at all. They now send you to the page view, where the fix has always been, and say so.

= 0.15.0 =
* The opt-in screen can now send its confirmation to an address you choose, instead of only the one on your WordPress account. If that address is one nobody reads, the confirmation used to go nowhere and the opt-in could not be finished. Opting in is still optional and the plugin still works fully without it.

= 0.14.0 =
* Added an accessibility panel inside the block editor, checking what you write as you write it. Fixes you apply there go straight into the block, so they are part of your content and undo works on them like any other edit.
* A finished bulk check can now go on to check colour, text size and layout, working through your pages in your own browser. That part only runs while the tab is open, and it says so before it starts.
* Your theme is now reported separately, with each fault sorted by who can fix it: the ones you can change in a setting, and the ones that need whoever maintains your theme — with a copyable summary written out for them.
* Fixed two accessibility faults in this plugin's own screens, found by auditing them against the standard we report on. Details in docs/accessibility-audit.md, including what has still not been checked.

= 0.13.0 =
* Findings are now grouped by what they ask of you — fix now, read then apply, decide for yourself, or hand to your developer — rather than by how serious they are. Severity tells you how bad something is; it never tells you where to start.
* Every finding now says, in one plain sentence, what it costs an actual person. The WCAG number is still there, one level down.
* Added bulk checking by content type, so you can check pages, posts or products together instead of one at a time.
* Added bulk image descriptions, generated in the background and shown to you on one screen. Nothing is saved to your media library until you have read it.
* You can now set a finding aside as not a problem. The reason is required, and it is kept with your name and the date — that is what makes it a record you can rely on later.
* Images you have deliberately marked as decorative are left alone. An empty description is a decision, not a gap.

= 0.12.0 =
* Bulk work now runs in the background, so a long job cannot time out halfway through an admin page. Runs can be stopped, and anything already found is kept.
* Checking many pages no longer asks your site to fetch each one. On hosts that block those requests — a common and reasonable setting — checking now works where it previously fell back to less.
* Your theme is checked once rather than reported against every page that uses it.
* Requires WordPress 6.8 or newer. This is needed by the background scheduler we now bundle, whose current version carries a security fix that was never added to the older line.

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

= 0.16.0 =
Everything is free now, and the AI features are gone. The plugin no longer contacts any external service. Site-wide scanning is no longer paid, and there is a new Images screen for writing alt text in bulk.

= 0.15.1 =
Contrast, colour-only links and small targets no longer ask for an AI key to fix something that needs no AI.

= 0.15.0 =
You can now opt in with an email address of your choosing, rather than only the one on your WordPress account.

= 0.14.0 =
Adds an accessibility panel to the block editor, a separate report for your theme, and colour and layout checking across a whole run.

= 0.13.0 =
Findings are now ordered by what you can actually do about them, and images and pages can be handled in bulk.

= 0.12.0 =
Requires WordPress 6.8. Bulk work moves to the background and now works on hosts that block loopback requests.

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
