# Marketplace release preparation

**Internal draft — not submitted, approved or published.** The connector repository is private, its Composer license is still `proprietary`, and the companion platform integration is not deployed for general use. This document does not change the license or authorize publication.

## Release checklist

- [ ] Decide and document the connector's distribution license. A free price does not imply an open-source license.
- [ ] Approve public connector distribution, create the release tag and finalize release notes. The SocraNext platform repository can remain private.
- [ ] Publish `socranext/statamic` on Packagist, configure release synchronization and verify installation without private GitHub access.
- [ ] Deploy and verify the companion platform integration, its CMS migration and both opt-in Statamic feature flags before offering the public customer workflow.
- [ ] Complete a clean installation and public article/FAQ check on representative sites using the [installation sequence](../README.md#installation-for-a-development-or-pilot-site).
- [ ] Update the public SocraNext service description/terms to cover Statamic accurately; the current terms describe the WordPress plugin. Confirm the privacy disclosure matches the exchanged content.
- [ ] Have an authorized account holder complete or verify the Statamic creator and Packagist accounts, including any additional terms shown during onboarding.
- [ ] Create a **Free** Marketplace product linked to the package and public repository. Disclose the required separate SocraNext subscription, add real screenshots and preview the listing.
- [ ] Follow the actual submission/publication step shown in the creator dashboard. Do not promise a review timeline or certification.

The official route requires a [Packagist package and Statamic seller account](https://statamic.dev/addons/building-an-addon#publishing-to-the-marketplace). Packagist requires a [public repository URL](https://packagist.org/about). Statamic's [creator page](https://statamic.com/creator/begin) supports free products; a [current connector listing](https://statamic.com/addons/html2img/auto-open-graph-images) demonstrates free code with external paid service plans. This supports the proposed billing model but does not establish individual approval or waive additional creator terms. No mandatory review duration was found in the public documentation.

Use **SocraNext for Statamic** with SocraNext branding. Statamic's [brand guidelines](https://statamic.com/branding) require clear distinction from its own products and prohibit implied endorsement. Exact image requirements should be checked in the creator form.

## Listing fields

- **Name:** SocraNext for Statamic
- **Creator:** SocraNext; account/shop name subject to availability.
- **Package:** `socranext/statamic`; not yet on Packagist.
- **Price:** Free
- **Suggested categories:** Integration, AI, SEO
- **Compatibility:** Statamic 6 Pro; Laravel 12 or 13; PHP 8.3, 8.4 or 8.5.
- **Website:** [socranext.ai](https://socranext.ai/)
- **Repository/documentation:** [SocraNext/socranext-statamic](https://github.com/SocraNext/socranext-statamic#readme); currently private.
- **Addon support:** [GitHub issues](https://github.com/SocraNext/socranext-statamic/issues); available publicly only after repository publication.
- **Account/billing contact:** [info@socranext.ai](mailto:info@socranext.ai)
- **Service links:** [Pricing](https://socranext.ai/prijzen/), [terms](https://socranext.ai/algemene-voorwaarden/), [privacy](https://socranext.ai/privacybeleid/). Finalize the terms before linking them from a published listing.

## Public listing copy — draft

**Publish articles, FAQs and metadata from your SocraNext workspace to your Statamic website.**

Connect your website to SocraNext to publish native articles into a dedicated collection, add FAQs to selected content, update supported metadata, and preview article, archive and FAQ styling. Multilingual articles use Statamic's native sites and translation relationships. The addon also publishes `llms.txt` and `llms-full.txt`.

**The addon is free. An active SocraNext account with a suitable subscription is required to use the connected service.** Subscriptions and service usage are billed directly by SocraNext under its own pricing and terms. The addon price does not include a SocraNext subscription or a Statamic Pro license.

Requires Statamic 6 Pro, PHP 8.3–8.5, Laravel 12 or 13, and persistent writable content and storage. PHP extensions Sodium, cURL, DOM, Fileinfo and Mbstring must be available. Enable native multisite before connecting additional sites.

Your developer installs the addon with Composer, configures the exposed collections and asset container, and places the FAQ and metadata tags in the website's Antlers templates. Open **Tools → SocraNext**, connect your project, check templates and styling previews, then save website readiness. Publish a test article and FAQ and verify their public output before regular publishing.

The supported profile uses native Statamic templates. FAQ placement and integration with an existing SEO addon require website setup. A separate headless or static frontend needs its own integration.

After connection, SocraNext can read content configured for the integration and send publication, FAQ, metadata and styling changes to the website. See the documentation for permissions, supported content, backups and disconnection behavior. Use the issue tracker for addon issues and SocraNext for account or subscription support.

Once a stable release is available on Packagist, the installation command can be `composer require socranext/statamic`. Until then, follow the private pilot instructions in the README. Do not invent a Marketplace URL before the creator dashboard assigns one.
