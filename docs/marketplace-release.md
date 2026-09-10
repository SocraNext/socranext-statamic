# Release and Marketplace checklist

**Target: 0.2.0.** The published stable package is `0.1.0` (commit `776c16f0a4adc5692ffc08a56bea2db66944106a`). Marketplace product 1011 remains a draft while automatic setup is finalized. A published Composer package is separate from a published Marketplace listing.

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
- [x] Creator account and **Free** Marketplace draft have short AI visibility copy, SocraNext branding and support links.

The platform QA runtime was verified against 1,246 server/shared file hashes from `69e15ff65413cb4367b0fd5bf9fb893ae2461a3f`; subsequent client-only Shopify changes preserve those server files. The native FAQ cleanup/restoration acceptance passed on 2026-09-10 at 10:19 UTC.

## 0.2.0 automatic setup acceptance

- [ ] Verify the standard **Connect with SocraNext** flow creates its resources, reports readiness and renders enabled FAQs without tags or a confirmation checkbox.
- [ ] Verify automatic metadata and FAQ output preserve existing placement, SocraNext styling, unrelated site HTML and structured data.
- [ ] Verify existing-site upgrade, custom layout/placement and legacy resource ownership, including protected/draft/multisite exclusions and conservative recovery.
- [ ] Pass every PHP/Laravel CI combination and JavaScript behavior test on the exact final commit.
- [ ] Publish the new `v0.2.0` tag without moving existing tags; verify anonymous stable Composer installation and the hosted upgrade.
- [ ] Complete hosted automatic-output and FAQ-only removal/restoration checks against the exact new package.

## Marketplace submission

- [ ] Save the final listing copy below and verify the Free price, subscription disclosure, requirements and links.
- [ ] Confirm the screenshot still matches the final installed addon.
- [ ] Submit through the creator dashboard and record its actual review/publication status.
- [ ] Verify the public listing independently of the signed-in draft preview.

The existing product is draft 1011. Submission is pending; replace the screenshot with the final simplified native Control Panel before publishing. Do not promise a review timeline or certification.

Use product name **SocraNext** and SocraNext branding. Do not use the Statamic logo or imply Statamic endorsement. Follow the [brand guidelines](https://statamic.com/branding) and image requirements shown in the creator form. The [publishing documentation](https://statamic.dev/addons/building-an-addon#publishing-to-the-marketplace) describes the package and creator account requirements.

## Listing fields

- **Name:** SocraNext
- **Creator:** [SocraNext](https://statamic.com/creators/socranext)
- **Package:** `socranext/statamic`; target stable `0.2.0`
- **Price:** Free
- **Categories:** Use the relevant categories available in the creator form.
- **Compatibility:** Statamic 6 Pro; Laravel 12 or 13; PHP 8.3, 8.4 or 8.5.
- **Website:** [socranext.ai](https://socranext.ai/)
- **Documentation:** [Setup guide](https://github.com/SocraNext/socranext-statamic#readme)
- **Support:** [GitHub issues](https://github.com/SocraNext/socranext-statamic/issues); account/billing: [info@socranext.ai](mailto:info@socranext.ai)
- **Service:** [Pricing](https://socranext.ai/prijzen/), [general terms](https://socranext.ai/algemene-voorwaarden/), [Statamic service description](statamic-service-terms.md), [privacy](https://socranext.ai/privacybeleid/)

## Stable listing copy — ready to save after package verification

**Get found in AI.**

SocraNext helps your website become visible in ChatGPT, Gemini and Google AI. Measure your visibility, improve your website and track your progress, all in one platform.

Connect your Statamic website and [get started](https://socranext.ai/).

*Free addon. Active SocraNext subscription, billed directly by SocraNext, and Statamic 6 Pro required. Install with Composer, then connect from Statamic. [Setup guide](https://github.com/SocraNext/socranext-statamic#readme).*
