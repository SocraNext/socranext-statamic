# Release and Marketplace checklist

**Target: 0.1.0.** The latest published package is `0.1.0-beta.2`. The stable README and listing copy below are prepared for the final release; they do not mean that the stable package or Marketplace listing is already published. Keep unfinished checks open until verified.

## Completed preparation

- [x] Public addon repository, license, owner-authorized distribution and Packagist synchronization.
- [x] Published beta.2, anonymous installation, native translation checks and hosted beta.1 upgrade.
- [x] Platform deployment and enabled Statamic integration, with existing CMS regression checks.
- [x] Hosted connection, website readiness, standard onboarding and generation.
- [x] Saved styling, fresh previews and mobile FAQ rendering.
- [x] Scheduled main article through the real publication worker and depth article through normal **Publish now**, with public output verified.
- [x] Existing-page FAQ enable/edit/restore/lock/unlock/disable/re-enable through the normal UI, including reloads and public HTML/schema.
- [x] Adapter/native publication and translation lifecycle checks using isolated fixtures, followed by fixture cleanup.
- [x] Owner selected Statamic FAQ-only offboarding: preserve blogs, retain existing deadlines, keep other integrations unchanged.
- [x] Creator account and **Free** Marketplace draft with SocraNext branding, short AI visibility copy and support links.
- [x] Save and verify the real 1200 × 800 native Control Panel screenshot.

The main-article acceptance check invoked the real worker for one guarded QA article; it did not trigger the global customer cron. The page FAQ cycle did not repeat regeneration with locked answers. These checks used a reviewed candidate patched onto beta.2, so the final package still requires the checks below.

## Final acceptance and package

- [ ] Verify the implemented Statamic FAQ-only offboarding path: 14 days after ending, 28 days after payment freeze, prior warning and healthy-subscription skip. Confirm articles and supporting data remain intact.
- [ ] Verify unavailable/disconnected-site handling and FAQ synchronization after an active subscription and restored connection.
- [ ] Verify final service copy and privacy links in the customer flow against the implemented behavior. The general terms remain leading; no blog-deletion addendum or other-CMS contract change is required.
- [ ] Select the final release commit, set the runtime version to `0.1.0`, and finalize the changelog. Do not replace an existing beta tag.
- [ ] Pass all six PHP/Laravel CI combinations and the FAQ JavaScript behavior tests on that exact commit.
- [ ] Verify clean installation and beta.2 upgrade of the final artifact, including native translations, locally served assets, configuration, content and connection preservation.
- [ ] Complete the final hosted acceptance review against the exact package.
- [ ] Publish the reviewed `v0.1.0` GitHub release and verify Packagist synchronization.
- [ ] Verify `composer require socranext/statamic` installs the intended stable package anonymously with Composer's normal stability setting.

## Marketplace submission

- [ ] Save the final listing copy below and verify the Free price, subscription disclosure, requirements and links.
- [ ] Confirm the screenshot still matches the final installed addon.
- [ ] Submit through the creator dashboard and record its actual review/publication status.
- [ ] Verify the public listing independently of the signed-in draft preview.

The existing product is draft 1011. Its saved preview and screenshot are verified; submission is still pending. Do not promise a review timeline or certification.

Use product name **SocraNext** and SocraNext branding. Do not use the Statamic logo or imply Statamic endorsement. Follow the [brand guidelines](https://statamic.com/branding) and image requirements shown in the creator form. The [publishing documentation](https://statamic.dev/addons/building-an-addon#publishing-to-the-marketplace) describes the package and creator account requirements.

## Listing fields

- **Name:** SocraNext
- **Creator:** [SocraNext](https://statamic.com/creators/socranext)
- **Package:** `socranext/statamic`; target stable `0.1.0`
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

*Free addon. Active SocraNext subscription, billed directly by SocraNext, and Statamic 6 Pro required. One-time developer setup with native template configuration. [Setup guide](https://github.com/SocraNext/socranext-statamic#readme).*
