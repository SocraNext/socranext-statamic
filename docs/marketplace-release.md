# Marketplace release preparation

**Public beta preparation — publication pending.** The target version is `0.1.0-beta.1`. The repository is still private, the beta tag has not been created, and no package or Marketplace listing has been published. The companion platform code is deployed with both Statamic feature flags disabled. This document records preparation; it does not establish completion of the publication steps.

## Public beta checklist

- [x] Owner authorized public connector distribution, subject to passing validation and release requirements. The platform repository remains private.
- [ ] Finalize [LICENSE](../LICENSE), the [Statamic service terms](statamic-service-terms.md) and beta release notes. Software rights and a separate service subscription are distinct.
- [ ] Verify CI on the exact release commit, publish the connector repository, and create `v0.1.0-beta.1` as a GitHub prerelease.
- [ ] Verify the public GitHub tag installs without credentials in a clean Statamic project.
- [ ] Publish `socranext/statamic` on Packagist, configure release synchronization and verify installation without private GitHub access.
- [ ] Update the README and changelog to record actual publication status only after each publication step succeeds.

The beta can be distributed for installation and evaluation while the hosted acceptance work continues. Keep both production Statamic flags disabled until the hosted pilot has passed. Do not create the stable `v0.1.0` tag or offer general activation based only on package publication.

## Hosted acceptance and Marketplace checklist

- [x] Deploy the companion platform code and additive CMS migration; verify the normal production deployment and existing CMS regressions.
- [ ] Verify the complete HTTPS pilot: project creation, connection, native permissions, article and FAQ publication, public output, translations, styling, scheduled delivery and lifecycle recovery.
- [ ] Complete clean installation and upgrade on representative sites using the [installation sequence](../README.md#installation-for-a-development-or-pilot-site).
- [ ] Enable both Statamic feature flags for the intended customer workflow only after acceptance; record the active production status.
- [ ] Publish the Statamic-specific service description and terms through the applicable customer flow, with an accurate privacy disclosure. Existing WordPress, Shopify and Headless customer agreements remain unchanged.
- [ ] Have an authorized account holder complete or verify the Statamic creator and Packagist accounts, including any additional terms shown during onboarding.
- [ ] Create a **Free** Marketplace product linked to the package and public repository. Disclose the required separate SocraNext subscription, add real screenshots and preview the listing.
- [ ] Follow the actual submission/publication step shown in the creator dashboard. Do not promise a review timeline or certification.

Verified before beta preparation on 9 September 2026: the migration and companion platform code are deployed, with Statamic still disabled. Platform regression tests, normal production deployment and public health checks passed. The previous development build passed all six PHP/Laravel CI combinations. A native Composer update published addon assets without changing any of the test site's 33 configuration files. Public source/history review found no confirmed secrets or unintended customer data. Public HTTPS onboarding, scheduled delivery and the final beta's anonymous installation remain unverified. Statamic and Packagist account steps and Marketplace submission are pending.

The official route requires a [Packagist package and Statamic seller account](https://statamic.dev/addons/building-an-addon#publishing-to-the-marketplace). Packagist requires a [public repository URL](https://packagist.org/about). Statamic's [creator page](https://statamic.com/creator/begin) supports free products; a [current connector listing](https://statamic.com/addons/html2img/auto-open-graph-images) demonstrates free code with external paid service plans. This supports the proposed billing model but does not establish individual approval or waive additional creator terms. No mandatory review duration was found in the public documentation.

Use **SocraNext for Statamic** with SocraNext branding. Statamic's [brand guidelines](https://statamic.com/branding) require clear distinction from its own products and prohibit implied endorsement. Exact image requirements should be checked in the creator form.

## Listing fields

- **Name:** SocraNext for Statamic
- **Creator:** SocraNext; account/shop name subject to availability.
- **Package/version:** `socranext/statamic`, intended beta `0.1.0-beta.1`; not yet on Packagist.
- **Price:** Free
- **Suggested categories:** Integration, AI, SEO
- **Compatibility:** Statamic 6 Pro; Laravel 12 or 13; PHP 8.3, 8.4 or 8.5.
- **Website:** [socranext.ai](https://socranext.ai/)
- **Repository/documentation:** [SocraNext/socranext-statamic](https://github.com/SocraNext/socranext-statamic#readme); currently private.
- **Addon support:** [GitHub issues](https://github.com/SocraNext/socranext-statamic/issues); available publicly only after repository publication.
- **Account/billing contact:** [info@socranext.ai](mailto:info@socranext.ai)
- **Service links:** [Pricing](https://socranext.ai/prijzen/), [Statamic service terms](statamic-service-terms.md), [privacy](https://socranext.ai/privacybeleid/). Confirm the Statamic terms are available in the applicable customer flow before publishing the listing; do not rewrite existing CMS customer agreements.

## Public listing copy — draft

The following copy is for the connected customer workflow after the hosted acceptance checks and activation. While publication or activation is pending, display that status prominently and direct testing users to the beta instructions.

**Publish articles, FAQs and metadata from your SocraNext workspace to your Statamic website.**

Connect your website to SocraNext to publish native articles into a dedicated collection, add FAQs to selected content, update supported metadata, and preview article, archive and FAQ styling. Multilingual articles use Statamic's native sites and translation relationships. The addon also publishes `llms.txt` and `llms-full.txt`.

**The addon is free. An active SocraNext account with a suitable subscription is required to use the connected service.** Subscriptions and service usage are billed directly by SocraNext under its own pricing and terms. The addon price does not include a SocraNext subscription or a Statamic Pro license.

Requires Statamic 6 Pro, PHP 8.3–8.5, Laravel 12 or 13, and persistent writable content and storage. PHP extensions Sodium, cURL, DOM, Fileinfo and Mbstring must be available. Enable native multisite before connecting additional sites.

Your developer installs the addon with Composer, configures the exposed collections and asset container, and places the FAQ and metadata tags in the website's Antlers templates. Open **Tools → SocraNext**, connect your project, check templates and styling previews, then save website readiness. Publish a test article and FAQ and verify their public output before regular publishing.

The supported profile uses native Statamic templates. FAQ placement and integration with an existing SEO addon require website setup. A separate headless or static frontend needs its own integration.

After connection, SocraNext can read content configured for the integration and send publication, FAQ, metadata and styling changes to the website. See the documentation for permissions, supported content, backups and disconnection behavior. Use the issue tracker for addon issues and SocraNext for account or subscription support.

After the beta tag is published on Packagist, its installation command is `composer require socranext/statamic:0.1.0-beta.1`. If the repository and tag become public first, use the README's VCS repository instruction with that same exact beta version. Neither command is a claim that publication has already happened. Reserve the unversioned `composer require socranext/statamic` instruction for an available stable release. Do not invent a Marketplace URL before the creator dashboard assigns one.
