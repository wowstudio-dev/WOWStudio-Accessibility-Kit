=== WOWStudio Accessibility Kit ===
Contributors: wowstudio
Tags: accessibility, wcag, a11y, alt text, accessibility scanner
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.29.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find, fix and document WCAG accessibility issues at the code level. Real fixes you review before they apply — not an overlay.

== Description ==

WOWStudio Accessibility Kit helps you **find, fix and document** accessibility issues in your WordPress site. It scans your pages against WCAG 2.2 A and AA success criteria, explains what it found in plain language, and proposes real changes to your markup that you review before anything is applied.

It is not an accessibility overlay. Nothing is injected into your front end, and no widget or toolbar is added for your visitors. Fixes are changes to the code itself.

= How it works =

* **Scan, twice.** A server-side scan reads the rendered HTML and checks it against a registry of WCAG rules. A second pass then runs in your browser, on the page as it was actually painted, to check the things no parser can know: real contrast, real target sizes, real layout. No external service is contacted for either.
* **See it.** The inspector puts the findings beside a live preview of the page. Choosing a finding highlights the element it is about, so you are never left guessing which paragraph or which button.
* **Understand.** Every issue is tagged with its WCAG success criterion and severity, and labelled either *auto-detected* or *needs manual review*.
* **Fix.** Suggested markup fixes are shown as a preview and a diff, and applied fixes are stored in a reversible override layer that never overwrites your content. Findings caused by styling instead get a style rule, written into your own Additional CSS — where you can read, edit or delete it without this plugin.
* **Check the fix worked.** After a style rule is applied, the page is loaded again and measured again. If something in your theme overrode the rule, you are told that, rather than being told it is fixed.
* **Check how hard it is to read.** Pages are measured against the Flesch–Kincaid grade level, and you can add a plain-language summary where the text is heavy going.
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

= 0.29.0 =
* New: the figures on the report screen now open. "What comes up most" and "Pages with the most to do" were counts you could read and not act on; each row is now a control that lists the findings behind it, with an explanation of the check at the top rather than repeated against every row.
* Fixed: dismissals are no longer lost when a page is scanned again. A finding you set aside, and the reason you gave for it, now stay with that finding across every future scan. Previously the decision was kept only on the record the scan happened to be holding, so the next scan quietly put the finding back with no sign that anything had been discarded.
* Fixed: site-wide counts no longer include findings from earlier scans. A page scanned repeatedly was contributing a separate copy of each of its findings every time, so the totals grew the more the plugin was used. Only the most recent scan of each page is counted now, which is what the score on the same screen was already doing.
* Upgrading tidies this up in one pass: existing dismissals are preserved first, then superseded findings are cleared out. Expect the open counts to drop — the earlier numbers were counting the same findings several times over, and nothing has been hidden.

= 0.28.0 =
* New: the dashboard now suggests one thing to do next, worked out from your own site — check a page, switch on the site-wide fixes, describe your images, or work through what is open. One suggestion at a time, never a checklist, and it disappears entirely when there is nothing worth suggesting.
* New: a link to "what these checks cover, and what they cannot" from the dashboard and from the foot of every findings list. It documents all forty-one checks and was previously hard to find.

= 0.27.0 =
* Fixed: pages built with Elementor were not being scanned properly on sites where the plugin cannot fetch the whole page. Elementor keeps the real page outside WordPress's own content field and leaves a short text summary in its place, so the scan was reading the summary — and reporting a clean result for a page it had barely seen. It now asks Elementor for the page itself. Divi and WP Bakery were never affected.
* Fixed: when there is genuinely nothing to scan, the message now says so and names what to check, instead of reporting that the page could not be parsed.
* Changed: the generated accessibility statement is written in plainer language, and now passes this plugin's own reading-level check.

= 0.26.0 =
* For developers: hooks so a separate add-on can register a report export format, and restrict who may set a finding aside. Nothing changes for you — the dismissal hook can only ever narrow permission, never widen it, and no export control appears unless something has registered a format, so there are no locked buttons.

= 0.25.0 =
* New: reading level. Pages are checked against the Flesch–Kincaid grade level, and flagged when they read above lower secondary school level — the point at which WCAG 3.1.5 asks for a simpler version.
* New: a plain-language summary field in the editor's Accessibility sidebar, which you can optionally show above your content.
* The reading level is reported as something to look at, never as a failure. The formula counts sentence length and syllables and understands nothing about meaning, so a page written for a specialist audience may be right as it is. It stays quiet on short pages and on sites that are not in English, where it would produce a number that means nothing.
* Nothing writes the summary for you. A summary a machine guessed at reads convincingly and means whatever it guessed, which is worse than none.

= 0.24.0 =
* New: WP-CLI support. `wp wsak scan --all` checks your whole site from the command line, `wp wsak issues` lists what it found, `wp wsak checks` lists every check, and `wp wsak fixes` switches the site-wide fixes on and off.
* Bulk scanning is included, so this works in a deployment pipeline or on staging.

= 0.23.0 =
* New: an Accessibility column on your Posts and Pages screens, showing each page's score and how many findings are open. Pages nobody has checked say "Not checked" rather than showing a zero.
* New: a "Set aside" screen listing every finding somebody chose not to act on, with the reason they gave, who they were and when. Anybody who can view reports can read it, not only the people who can dismiss.

= 0.22.0 =
* Scheduled checking has been moved out of this plugin. It was added in 0.21.0 and is now part of a separate paid add-on, along with the change report that went with it.
* "Monitor" has been removed from how this plugin describes itself, because it no longer does it. Everything else is unchanged: every check, every fix, site-wide scanning and the full report all stay here and stay free.

= 0.21.0 =
* New: scheduled checking. Your site can now re-check itself every week or every month, so a problem introduced by an edit, a plugin update or a theme change is noticed without anybody having to remember to look.
* New: a "What has changed" screen showing which pages started failing something they used to pass, and which stopped.
* Off by default. Each run covers the fifty most recently updated pages in the background.
* Nothing is emailed and nothing is sent anywhere — this plugin still contacts no outside service at all.

= 0.20.0 =
* Five more site-wide fixes, completing the set at fourteen.
* Lets people pinch-zoom on a phone where the theme blocked it.
* Puts the tab order back in reading order where something claimed a place ahead of the page.
* Removes tooltips that only repeat the link text, which some screen readers announce twice.
* Gives a form field the name its own placeholder already carries — and never invents one where there is no placeholder, because a made-up label is worse than a missing one.
* Explains an empty search beside the search box instead of loading a results page that cannot say what went wrong.
* These five are carried out in the reader's browser, because they correct markup your theme has already printed. They add no widget, no toolbar and no controls — nothing appears on your site — and the settings screen says plainly which fixes need JavaScript.

= 0.19.0 =
* New: fixes for the whole site. Nine switches that supply what your theme leaves out, on every page at once.
* A skip link, so keyboard users can jump past your menu instead of tabbing through it on every page.
* A visible outline on whatever has keyboard focus, for themes that switched the browser's own one off.
* Underlines on links inside body text, where colour alone does not mark them.
* The page language, and a title on every page.
* Names for your search box and comment fields where the theme left them without one.
* "(opens in a new tab)" and "(PDF, 1.2 MB)" added to links in your content that need them.
* An option to refuse PDF uploads from anyone but an administrator.
* Nothing is written into your content: every one of these is reversible by switching it back off.

= 0.18.0 =
* Eleven new checks, taking the scanner from 29 to 40.
* Tables and forms: header cells with no text; image buttons with no name; the same id used twice, which quietly stops labels and ARIA references reaching the right element.
* Keyboard and zoom: a positive tabindex, which moves an element ahead of everything else when tabbing; a viewport tag that stops the page being pinch-zoomed on a phone.
* Presentation: blinking and scrolling text; text justified to both margins; underlined text that is not a link; a bold paragraph standing in for a heading, which is how a page ends up looking structured while having no outline.
* Media: video with no caption track, and audio with no transcript.

= 0.17.0 =
* Twelve new checks, taking the scanner from 17 to 29.
* Images: alt text that is really a file name or a placeholder; alt text long enough to be a paragraph; alt text that just repeats the caption; image-map regions with no name.
* Links: links that open a new tab without saying so; links that download a PDF, spreadsheet or archive without saying so; in-page links pointing at a section that does not exist — which is how skip links quietly stop working; links that respond to a click but cannot be reached by keyboard.
* Structure and forms: empty headings; pages with no headings at all; labels attached to a field that is not there, or two labels on one field; ARIA attributes naming an element that does not exist.

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

= 0.29.0 =
The report's figures now open onto the findings behind them. Dismissed findings survive a rescan, and the site-wide counts no longer count the same finding once per scan, so your open totals will drop after upgrading.

= 0.28.0 =
The dashboard now tells you what to do next, and the list of what the checks cover is easier to find.

= 0.27.0 =
Fixes Elementor pages being scanned as empty, and makes the generated accessibility statement easier to read.

= 0.26.0 =
Developer hooks only; nothing changes in how the plugin behaves.

= 0.25.0 =
Adds reading-level checking and a plain-language summary field, for WCAG 3.1.5.

= 0.24.0 =
Adds WP-CLI commands, including whole-site scanning for use in a pipeline.

= 0.23.0 =
Adds an accessibility column to your Posts and Pages lists, and a screen recording what has been set aside and why.

= 0.22.0 =
Scheduled checking has moved to a separate paid add-on. Everything else is unchanged and still free.

= 0.21.0 =
Your site can now re-check itself on a schedule and tell you what changed since last time.

= 0.20.0 =
Five more site-wide fixes: pinch-zoom, tab order, repeated tooltips, placeholder-only form fields, and empty searches.

= 0.19.0 =
Adds nine site-wide fixes — skip link, focus outline, link underlines, page language and title, form labels, and warnings on links that open a new tab or download a file.

= 0.18.0 =
Eleven new checks, covering tables, image buttons, duplicate ids, tab order, pinch-zoom, blinking and justified text, and captions for video and audio.

= 0.17.0 =
Twelve new checks, covering alt-text quality, links that surprise you, broken skip links, empty headings and mislabelled form fields.

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
