# Marketplace release preparation

**Beta release preparation — `0.1.0-beta.2`.** The [source repository](https://github.com/SocraNext/socranext-statamic) is public. Check [GitHub releases](https://github.com/SocraNext/socranext-statamic/releases) and [Packagist](https://packagist.org/packages/socranext/statamic) for current release and package availability. The checklist below records verified preparation on 9 September 2026; unchecked publication steps still require confirmation. The companion platform code is deployed with both Statamic feature flags disabled, and the connected service is not generally available.

## Public beta checklist

The first beta is published on GitHub and Packagist, with release synchronization configured and anonymous installation verified. This checklist now tracks the `0.1.0-beta.2` translation correction; its exact release and installation still need verification.

- [x] Owner authorized public connector distribution, subject to passing validation and release requirements. The platform repository remains private.
- [x] Publish the connector source repository following explicit owner approval.
- [x] Add [LICENSE](../LICENSE) and the [Statamic service terms](statamic-service-terms.md). Software rights and a separate service subscription are distinct.
- [x] Complete Packagist account access through GitHub OAuth.
- [ ] Approve the final beta release notes and verify CI on the exact release commit.
- [ ] Create `v0.1.0-beta.2` and its GitHub prerelease.
- [ ] Verify the public GitHub tag installs without credentials in a clean Statamic project.
- [x] Publish `socranext/statamic` on Packagist and configure release synchronization.
- [ ] Verify the beta.2 package installs without private GitHub access.
- [ ] Record completed publication steps and verify the release/package links. Do not mark a Marketplace listing or general activation complete based on package publication.

The beta can be distributed for installation and evaluation while the hosted acceptance work continues. Keep both production Statamic flags disabled until the hosted pilot has passed. Do not create the stable `v0.1.0` tag or offer general activation based only on package publication.

The Marketplace preview generates the unversioned command `composer require socranext/statamic`. Verify that command against an available stable release before general publication. Beta evaluators must use the exact prerelease installation command from the README.

## Hosted acceptance and Marketplace checklist

- [x] Deploy the companion platform code and additive CMS migration; verify the normal production deployment and existing CMS regressions.
- [ ] Verify the complete HTTPS pilot: project creation, connection, native permissions, article and FAQ publication, public output, translations, styling, scheduled delivery and lifecycle recovery.
- [ ] Complete clean installation and upgrade on representative sites using the [installation sequence](../README.md#installation-for-a-development-or-pilot-site).
- [ ] Enable both Statamic feature flags for the intended customer workflow only after acceptance; record the active production status.
- [ ] Publish the Statamic-specific service description and terms through the applicable customer flow, with an accurate privacy disclosure. Existing WordPress, Shopify and Headless customer agreements remain unchanged.
- [ ] Have an authorized account holder complete or verify the Statamic creator account, including any additional terms shown during onboarding.
- [ ] Create a **Free** Marketplace product linked to the package and public repository. Disclose the required separate SocraNext subscription, add real screenshots and preview the listing.
- [ ] Follow the actual submission/publication step shown in the creator dashboard. Do not promise a review timeline or certification.

Verified during beta preparation on 9 September 2026: the migration and companion platform code are deployed, with Statamic still disabled. Platform regression tests, normal production deployment and public health checks passed. The previous development build passed all six PHP/Laravel CI combinations. A native Composer update published addon assets without changing any of the test site's 33 configuration files. Public source/history review found no confirmed secrets or unintended customer data. The connector repository is public and includes the license and service terms. Packagist publication and release synchronization are complete; the first beta installs anonymously. Hosted Control Panel testing identified the native translation collision addressed in beta.2. Full connected HTTPS onboarding, scheduled delivery and beta.2 anonymous installation remain acceptance items. Exact-release CI, the beta.2 tag, Statamic creator onboarding and Marketplace submission remain checklist items until individually confirmed.

The official route requires a [Packagist package and Statamic seller account](https://statamic.dev/addons/building-an-addon#publishing-to-the-marketplace). Packagist requires a [public repository URL](https://packagist.org/about). Statamic's [creator page](https://statamic.com/creator/begin) supports free products; a [current connector listing](https://statamic.com/addons/html2img/auto-open-graph-images) demonstrates free code with external paid service plans. This supports the proposed billing model but does not establish individual approval or waive additional creator terms. No mandatory review duration was found in the public documentation.

Use **SocraNext for Statamic** with SocraNext branding. Statamic's [brand guidelines](https://statamic.com/branding) require clear distinction from its own products and prohibit implied endorsement. Exact image requirements should be checked in the creator form.

## Listing fields

- **Name:** SocraNext for Statamic
- **Creator:** SocraNext; account/shop name subject to availability.
- **Package/version:** `socranext/statamic`, target beta `0.1.0-beta.2`; confirm its availability on [Packagist](https://packagist.org/packages/socranext/statamic).
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
