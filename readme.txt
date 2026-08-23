=== WOWStudio Accessibility Kit ===
Contributors: wowstudio
Tags: accessibility, wcag, a11y, alt text, accessibility scanner
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find, fix, document, and monitor WCAG accessibility issues at the code level. Real fixes you review before they apply — not an overlay.

== Description ==

WOWStudio Accessibility Kit helps you **find, fix, document, and monitor** accessibility issues in your WordPress site. It scans your pages against WCAG 2.2 A and AA success criteria, explains what it found in plain language, and proposes real changes to your markup that you review before anything is applied.

It is not an accessibility overlay. Nothing is injected into your front end, and no widget or toolbar is added for your visitors. Fixes are changes to the code itself.

= How it works =

* **Scan.** A server-side scan reads the rendered HTML of a page and checks it against a registry of WCAG rules. No external service is contacted to run a scan.
* **Understand.** Every issue is tagged with its WCAG success criterion and severity, and labelled either *auto-detected* or *needs manual review*.
* **Fix.** Suggested fixes are shown as a preview and a diff. You approve each one. Applied fixes are stored in a reversible override layer and can be undone.
* **Document.** Generate an accessibility statement you edit and publish, including the feedback contact mechanism European rules expect.

= Honesty about what automated testing can do =

Automated testing can only detect part of WCAG. Many criteria — meaningful sequence, focus order, whether your alt text is actually accurate — require a human being. This plugin tells you which is which, in a coverage panel you can read at any time, and it will never report your site as finished.

This plugin **does not** determine or certify whether your site complies with the ADA, the European Accessibility Act, Section 508, AODA, the UK Equality Act, or any other law. It is a tool that helps you do accessibility work and keep a record of it. It is not legal advice, and it is not a substitute for testing with disabled users.

= What it will not do =

* No front-end accessibility widget, toolbar, or overlay.
* No silent changes to your content. Every fix is reviewed by a person first.
* No claim, anywhere, that your site is compliant, certified, or protected from legal action.
* No conformance document published without your explicit review and attestation.

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

= 0.1.0 =
* Initial scaffold: plugin bootstrap, capabilities, activation and uninstall handling.

== Upgrade Notice ==

= 0.1.0 =
First development release.
