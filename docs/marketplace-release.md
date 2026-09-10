# Release and Marketplace checklist

**Published: 0.2.0**, on 2026-09-10, from commit `f5d8bb7abd04fbe6ddcbdee05d9fc7895307c4a2`. The stable package is available through Packagist and the [SocraNext Marketplace listing](https://statamic.com/addons/socranext/socranext) is public. The creator dashboard reports **Published**; a separate signed-out browser confirmed version 0.2.0, the Free price and direct SocraNext billing disclosure.

## Verified 0.1.0 baseline

- [x] Public addon repository, free-addon license, owner-authorized distribution, GitHub release and Packagist synchronization.
- [x] Anonymous unversioned `composer require socranext/statamic` installs stable 0.1.0 with normal Composer stability.
- [x] All six PHP 8.3/8.4/8.5 × Laravel 12/13 combinations pass on the exact stable commit, plus FAQ JavaScript behavior checks.
- [x] Hosted beta.2-to-stable upgrade preserves configuration, content, connection, native translations and the other 119 locked packages.
- [x] Standard platform onboarding, generated articles, saved styling and fresh previews pass.
- [x] Main article published by the real guarded QA worker and a depth article through normal **Publish now**, with public output verified.
- [x] Existing-page FAQ enable/edit/restore/lock/unlock/disable/re-enable through the platform UI, including reloads and public HTML/schema.
- [x] Statamic-only FAQ offboarding and restoration tested through the real platform adapter: three FAQ sets removed and restored; two published articles and seven database article rows preserved.
- [x] Independent native before/after verification preserves 329 protected files and all non-FAQ connector state. No article write, payment operation, email send or global customer cron was used.
- [x] Platform regression tests and deployed FAQ-only branch retain the behavior of WordPress, Shopify and Headless. Existing 14-day/28-day cleanup timing is unchanged.
- [x] Creator account, free-addon positioning, SocraNext branding and support links prepared for publication.

The platform QA runtime was verified against 1,246 server/shared file hashes from `69e15ff65413cb4367b0fd5bf9fb893ae2461a3f`; subsequent client-only Shopify changes preserve those server files. The native FAQ cleanup/restoration acceptance passed on 2026-09-10 at 10:19 UTC.

## Verified 0.2.0 release

- [x] Publish `v0.2.0` from the exact release commit and verify public Packagist synchronization.
- [x] Pass all six PHP 8.3/8.4/8.5 × Laravel 12/13 CI jobs on that commit. The final local suite passes 144 PHP tests with 1,422 assertions, plus four JavaScript tests.
- [x] Verify native connect/setup, automatic metadata and FAQ placement, custom layouts, legacy resource ownership, protected/draft/multisite exclusions and recovery in the automated suite.
- [x] Install through anonymous, unversioned `composer require socranext/statamic`, using fresh Composer home, cache, lock and vendor directories. No published addon configuration or manual readiness confirmation is needed.
- [x] Verify native setup creates the article resources and public image storage; all six automatic readiness checks pass. Repeating the install command preserves all 52 checked files. Only the account connection remains pending on the intentionally unconnected fresh installation.
- [x] Verify all 11 addon assets publish automatically through native Composer installation hooks and match the package bytes. Native Dutch/English translations and customer translation overrides pass.
- [x] Upgrade the existing QA site to the exact public 0.2.0 package, preserving content, configuration, connection, revisions, assets and all 119 other locked packages. Verify all 64 addon runtime files and 14 readiness checks.
- [x] Verify the fresh native Control Panel shows **Klaar voor gebruik**, SocraNext styling and no manual readiness checkbox or mandatory developer setup steps. The existing Shopify privacy endpoint still returns HTTP 200.
- [x] Verify automatic FAQ output on the hosted native page without FAQ template tags: exactly one block containing five questions, unchanged FAQ styling, header and footer, and a working expand button. Restore the temporary QA template change and verify all protected files and state remain unchanged.
- [x] Repeat FAQ-only offboarding and restoration on the exact 0.2.0 runtime: 66 requests cover three FAQ sets, two public articles and seven article rows. FAQ HTML/schema are removed and restored; no article is written. Native verification confirms all 343 protected files and non-FAQ state are unchanged.

The release CI is [run 34467608938](https://github.com/SocraNext/socranext-statamic/actions/runs/34467608938). The earlier onboarding, scheduled publication and page-editing results above remain identified as baseline evidence; the hosted 0.2.0 checks specifically cover the upgrade, automatic output and FAQ cleanup/restoration.

## Marketplace publication

- [x] Save the AI visibility copy, minimum version 0.2.0, Free price, direct subscription billing disclosure, requirements and links.
- [x] Remove the obsolete Control Panel gallery screenshot and retain the SocraNext brand thumbnail.
- [x] Publish product 1011 through the creator dashboard and confirm **Published** status.
- [x] Verify the public listing from a separate signed-out browser, including version 0.2.0, Composer installation, Free price, direct billing and Statamic 6 Pro requirements.

A current Control Panel screenshot is available, but its gallery upload did not complete. The optional gallery did not block publication. No review queue was displayed; publication does not imply Statamic certification or endorsement.

Use product name **SocraNext** and SocraNext branding. Do not use the Statamic logo or imply Statamic endorsement. Follow the [brand guidelines](https://statamic.com/branding) and image requirements shown in the creator form. The [publishing documentation](https://statamic.dev/addons/building-an-addon#publishing-to-the-marketplace) describes the package and creator account requirements.

## Listing fields

- **Name:** SocraNext
- **Creator:** [SocraNext](https://statamic.com/creators/socranext)
- **Package:** `socranext/statamic`; current and minimum listed version `0.2.0`
- **Price:** Free
- **Categories:** Use the relevant categories available in the creator form.
- **Compatibility:** Statamic 6 Pro; Laravel 12 or 13; PHP 8.3, 8.4 or 8.5.
- **Website:** [socranext.ai](https://socranext.ai/)
- **Documentation:** [Setup guide](https://github.com/SocraNext/socranext-statamic#readme)
- **Support:** [GitHub issues](https://github.com/SocraNext/socranext-statamic/issues); account/billing: [info@socranext.ai](mailto:info@socranext.ai)
- **Service:** [Pricing](https://socranext.ai/prijzen/), [general terms](https://socranext.ai/algemene-voorwaarden/), [Statamic service description](statamic-service-terms.md), [privacy](https://socranext.ai/privacybeleid/)

## Stable listing copy

**Get found in AI.**

SocraNext helps your website become visible in ChatGPT, Gemini and Google AI. Measure your visibility, improve your website and track your progress, all in one platform.

Connect your Statamic website and [get started](https://socranext.ai/).

*Free addon. Active SocraNext subscription, billed directly by SocraNext, and Statamic 6 Pro required. Install with Composer, then connect from Statamic. [Setup guide](https://github.com/SocraNext/socranext-statamic#readme).*
