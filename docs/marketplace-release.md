# Marketplace release preparation

**Release preparation — updated 10 September 2026.** The [source repository](https://github.com/SocraNext/socranext-statamic) is public and `0.1.0-beta.2` is published on [GitHub](https://github.com/SocraNext/socranext-statamic/releases/tag/v0.1.0-beta.2) and [Packagist](https://packagist.org/packages/socranext/statamic). Main contains unreleased fixes, which are being evaluated as patches on the hosted pilot. Both platform Statamic feature flags are enabled. Full acceptance remains incomplete, no stable release has been created, and the Marketplace product remains a draft.

## Public beta checklist

The published beta.2 translation correction, anonymous installation and hosted upgrade have been verified. These completed checks apply to beta.2; they do not establish installation or release readiness for the additional fixes on main.

- [x] Owner authorized public connector distribution, subject to passing validation and release requirements. The platform repository remains private.
- [x] Publish the connector source repository following explicit owner approval.
- [x] Add [LICENSE](../LICENSE) and a [Statamic service terms document](statamic-service-terms.md). Reconciliation of the offboarding policy and its customer agreement flow remains open below.
- [x] Complete Packagist account access through GitHub OAuth.
- [x] Verify CI for the published beta.2 correction.
- [x] Create `v0.1.0-beta.2` and its GitHub prerelease.
- [x] Publish `socranext/statamic` on Packagist and configure release synchronization.
- [x] Verify the beta.2 package installs without private GitHub access.
- [x] Upgrade the hosted beta.1 installation to beta.2 and verify native translations and asset publishing.
- [x] Record the published beta tag and package links. Marketplace submission remains separate.

The beta remains available for evaluation while hosted acceptance continues. Enabling the platform flags for the pilot does not close the remaining acceptance gates or establish general release readiness. Keep the Marketplace product in draft until those gates and the final package checks pass.

The Marketplace preview generates the unversioned command `composer require socranext/statamic`. Verify that command against an available stable release before general publication. Beta evaluators must use the exact prerelease installation command from the README.

## Hosted acceptance

- [x] Deploy the companion platform code and additive CMS migration; verify the normal production deployment and existing CMS regressions.
- [x] Enable both Statamic flags and verify the isolated public HTTPS pilot's project identity, connection and native readiness.
- [x] Complete standard platform onboarding and content generation, including the onboarding tour.
- [x] Save styling through the platform and verify native synchronization, a fresh preview reload and mobile FAQ rendering.
- [x] Schedule a main article through the UI and publish that due article using a guarded invocation of the real production worker; verify the platform result, public article and its FAQ.
- [x] Publish one depth article using the normal **Publish now** UI; verify the public parent link and that the other five depth article dates remain unchanged.
- [x] Verify adapter/native receiver preview and publication lifecycle checks using isolated fixtures, then remove those fixtures.
- [x] Resolve existing-page FAQ discovery types and verify the normal UI cycle: enable, edit/restore, lock/unlock, disable/re-enable, reload persistence, and public HTML/schema.
- [ ] Resolve the Statamic offboarding policy and reconcile the service terms, implementation and applicable customer agreement flow. Do not change existing WordPress, Shopify or Headless agreements through this release.
- [ ] Complete the final hosted acceptance review, including the supported translation and lifecycle behavior, after the remaining fixes.

The main-article check invoked the real worker for exactly one guarded QA article, with deployed runtime source hashes verified before and after. It did not trigger the global publication cron across customer projects. The later existing-page FAQ cycle passed with five questions restored, matching public FAQ schema and purple toggle styling. Page-level regeneration with locked answers was not repeated in that UI cycle.

The hosted addon remains beta.2 with reviewed FAQ, admin-copy, CSS validation and discovery-type patches. The candidate passes 77 PHP tests with 795 assertions, including six discovery/FAQ contract cases. The earlier candidate at `8ac60b4` passed all six PHP/Laravel CI combinations; the next release commit still needs its own CI result and package checks.

## Final package and Marketplace submission

- [ ] After acceptance, prepare the final version and release notes, and pass CI on the exact release commit.
- [ ] Verify clean installation and beta.2 upgrade of the final artifact on representative sites using the [installation sequence](../README.md#installation-for-a-development-or-pilot-site), including persistence of existing content, configuration and connection.
- [ ] Publish the stable GitHub release and verify its availability on Packagist.
- [ ] Verify the Marketplace command `composer require socranext/statamic` installs the stable package without private credentials or a relaxed global stability setting.
- [ ] Verify the final Statamic service description, terms and accurate privacy disclosure in the applicable customer flow.
- [x] Complete the Statamic creator account and link the public repository/package.
- [x] Create the **Free** Marketplace draft, disclose the separate SocraNext subscription, and save the short AI visibility copy, branding and support links.
- [x] Upload the real native Control Panel screenshot at 1200 × 800 and verify that the product form saves it.
- [ ] Review the final listing against the released artifact and completed acceptance record.
- [ ] Complete the actual submission/publication step shown in the creator dashboard and verify its resulting status. Do not promise a review timeline or certification.

The Marketplace product is draft 1011. Its preview and saved screenshot are verified; it has not been submitted. Package availability and creator account setup do not establish Marketplace publication.

The official route requires a [Packagist package and Statamic seller account](https://statamic.dev/addons/building-an-addon#publishing-to-the-marketplace). Packagist requires a [public repository URL](https://packagist.org/about). Statamic's [creator page](https://statamic.com/creator/begin) supports free products; a [current connector listing](https://statamic.com/addons/html2img/auto-open-graph-images) demonstrates free code with external paid service plans. This supports the proposed billing model but does not establish individual approval or waive additional creator terms. No mandatory review duration was found in the public documentation.

Use the saved product name **SocraNext** and SocraNext branding. Do not use the Statamic brand logo. Statamic's [brand guidelines](https://statamic.com/branding) require clear distinction from its own products and prohibit implied endorsement. Exact image requirements should be checked in the creator form.

## Listing fields

- **Name:** SocraNext
- **Creator:** [SocraNext](https://statamic.com/creators/socranext).
- **Package/version:** `socranext/statamic`; published beta `0.1.0-beta.2`. A stable package and its unversioned installation check remain prerequisites for submission.
- **Price:** Free
- **Suggested categories:** Integration, AI, SEO
- **Compatibility:** Statamic 6 Pro; Laravel 12 or 13; PHP 8.3, 8.4 or 8.5.
- **Website:** [socranext.ai](https://socranext.ai/)
- **Repository/documentation:** public [SocraNext/socranext-statamic](https://github.com/SocraNext/socranext-statamic#readme).
- **Addon support:** public [GitHub issues](https://github.com/SocraNext/socranext-statamic/issues).
- **Account/billing contact:** [info@socranext.ai](mailto:info@socranext.ai)
- **Service links:** [Pricing](https://socranext.ai/prijzen/), [Statamic service terms](statamic-service-terms.md), [privacy](https://socranext.ai/privacybeleid/). Confirm the Statamic terms are available in the applicable customer flow before publishing the listing; do not rewrite existing CMS customer agreements.

## Public listing copy — draft

## Get found in AI.

SocraNext helps your website become visible in ChatGPT, Gemini and Google AI. Measure your visibility, improve your website and track your progress, all in one platform.

Connect your Statamic website and [get started](https://socranext.ai/).

*Free addon. Paid SocraNext subscription and Statamic 6 Pro required. Beta: developer setup and pilot access required. [Setup guide](https://github.com/SocraNext/socranext-statamic#readme).*
