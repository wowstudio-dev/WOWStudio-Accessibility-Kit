=== WOWStudio Accessibility Kit ===
Contributors: wowstudioplugin
Tags: accessibility, wcag, a11y, alt text, accessibility scanner
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
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

== Screenshots ==

1. The report. What was found across everything checked, what needs a person rather than a machine, and what each figure is made of. Every bar opens the findings behind it.
2. A page being inspected. The findings sit beside a live preview of the page, and choosing one highlights the element it is about, so you are never guessing which paragraph or which button.
3. Choosing what to check. Posts and pages, with what is already known about each, checked in the background so nothing blocks the admin.
4. Images that have never been described, with a field beside each. What you write goes into WordPress's own alt text, so it applies everywhere the image is used and stays behind if this plugin is removed.
5. Site-wide fixes. Switches that supply what a theme leaves out — a skip link, a visible focus outline, a page title. Each one says what it might disturb before you turn it on.
6. The accessibility statement. A draft you review, edit and attest to, including the feedback contact mechanism European rules expect. The plugin never asserts conformance on your behalf.
7. The setup, on first activation. Three steps, none of them mandatory, and nothing switched on for you.

== Changelog ==

= 1.0.0 =
First public release.

* Finds accessibility problems on your posts and pages, checking against WCAG 2.2 A and AA with 41 checks across two passes — one on the server that reads the rendered HTML, and one in your own browser for the things no parser can know: real contrast, real target sizes, real layout.
* Puts every finding beside a live preview of the page it is on, so choosing one highlights the element it is about.
* Fixes what can honestly be fixed from here. Markup fixes are shown as a preview and a diff and stored in a reversible layer that never overwrites your content; styling problems get a rule written into your own Additional CSS, weighted so it actually wins against your theme, then measured again to confirm it worked.
* 14 site-wide fixes for the things a theme leaves out — skip link, focus outline, page title, form labels and more — each one stating what it might disturb before you switch it on. Nothing is on by default.
* Lists every image in your media library that has never been described, with a field beside each. What you write goes into WordPress's own alt text, so it applies everywhere and stays behind if you remove this plugin.
* Generates an accessibility statement you review, edit and attest to, including the feedback contact mechanism European rules expect.
* Labels every finding as either settled by an automated check or needing a person to look at it, and says plainly which parts of WCAG automation cannot reach.
* Contacts no external service. No API, no account, no telemetry, no phone-home — which is also why there are no scanning limits.
* Free, all of it. No paid tier, no locked controls, no upgrade prompts.

The plugin was built over 29 development versions before this one. None of them
were released publicly; the full history is in CHANGELOG.md in the source
repository.
