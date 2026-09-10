# SocraNext for Statamic

**Get found in AI.** SocraNext helps your website become visible in ChatGPT, Gemini and Google AI. Measure your visibility, improve your website and track your progress, all in one platform. This addon connects your Statamic website to SocraNext.

The addon is free. An active [SocraNext subscription](https://socranext.ai/prijzen/) is required and is billed directly by SocraNext. Statamic 6 Pro and hosting are separate requirements.

## Installation

A website developer completes the initial setup. Requirements: Statamic 6 Pro, PHP 8.3–8.5 with Sodium, cURL, DOM, Fileinfo and Mbstring, and persistent writable content and Laravel storage. The addon supports native Statamic templates. A separate headless/static frontend needs its own rendering and deployment integration.

From your website's project directory:

```sh
composer require socranext/statamic
php artisan vendor:publish --tag=socranext-config
php artisan vendor:publish --tag=socranext-assets --force
```

Configure `config/socranext.php` before running the installer:

- Set `site_url` to the canonical public HTTPS website URL used by the SocraNext project.
- Choose existing collections for pages, posts, products and custom content. Only explicitly exposed collections and sites are accessible to the connector. Empty `content.sites` selects only the default site.
- Before exposing another site/language, use Statamic's `php please multisite` conversion command and configure the native sites. Declaring site records alone does not enable multisite storage. The connector rejects non-default sites while native multisite is disabled, including during installation and publishing.
- Set `content.asset_container` to a configured Statamic asset container with a public URL. Images are stored inside its `socranext/` directory.
- Choose unused handles for the managed article collection and taxonomy. The installer rejects collisions with existing resources.
- Set `content.article_layout` to the website's Antlers layout. Verify its article, archive and FAQ placement on the actual website.

```sh
php please socranext:install
```

Add `{{ socranext:faq }}` to the intended place in templates for existing pages. The managed article template includes its own FAQ. Use `{{ socranext:metadata }}` in the site's `<head>` for article and page metadata, or map these fields into the existing SEO addon; avoid duplicate metadata output. The managed collection and archive use the website layout.

Open **Tools → SocraNext** in the native Statamic control panel. The screen follows the user's English or Dutch Control Panel language preference and shows connection, configuration checks and website readiness separately. Only a superuser or a user with `configure socranext` may connect, disconnect or approve website readiness.

Complete the first installation in this order:

1. Create the corresponding Statamic project in SocraNext, using the same website address, then select **Connect with SocraNext** in Statamic.
2. Finish the configuration checks. Inspect the website's templates and the SocraNext styling previews, including FAQ placement, metadata integration, links and mobile layout. Previews can show sample content before the first article is published.
3. Confirm website readiness using the checkbox and save the website status. This enables the publication actions that require a prepared website.
4. Publish a test article and enable a test FAQ from SocraNext. Check their actual public output before continuing with regular publishing. If the website setup needs more work, clear the readiness checkbox while correcting it.

```sh
php please socranext:doctor --json
```

Install and update through the website's normal Composer/test/deploy process. Statamic 6 provides Composer commands in its control panel, rather than executing package installation there.

The standard Statamic installation/update hook publishes the Control Panel assets to `public/vendor/socranext`. If your deployment skips Composer scripts, run `php artisan vendor:publish --tag=socranext-assets --force` when deploying an addon update. This tag updates only SocraNext assets; it does not publish or overwrite Statamic configuration. Logos and fonts are served locally, without third-party font requests. Font license notices are included in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

## Integration boundaries

API base: `/api/socranext/v1`. Authentication uses `x-socranext-token` or a Bearer token. Conflicting credentials are rejected. Tokens are delivered directly from the trusted SocraNext backend using a short-lived, single-use state created in the authorized control panel; the addon stores only hashes. Disconnect revokes the credential and preserves content.

The addon exposes native content for selection, and writes articles into its owned collection. It preserves unrelated fields and never attempts to flatten arbitrary customer Bard or Replicator structures into article HTML. Existing page FAQ placement is explicit through the template tag. Browser-only installation, automatic injection into arbitrary templates and a universal SEO/page-builder integration are outside this supported profile.

Global custom JavaScript must carry a valid SocraNext Ed25519 signature bound to the website and the slot. Per-page unsigned JavaScript is rejected. Preview sessions are short-lived and restricted to configured platform origins. Only public HTTPS image downloads are allowed, with pinned DNS, no redirects, MIME validation and a size limit.

`GET /status` advertises the contract version, addon version, readiness and supported capabilities. The platform must check those capabilities instead of assuming that every CMS supports every optional action. Native article translations and translated styling are supported, with distinct identities for each Statamic site. The platform worker enables them only after capability and website-readiness checks.

The connector does not support category slug changes, automatic FAQ injection, generation requests initiated from the CMS, and bulk translated archive-slug maps. Category title and description edits, native entry slug changes, native multilingual articles and translated styling are supported. Each site can have its archive slug configured through the API. A rejected operation returns an error; it is never reported as a successful publication.

## Subscription and privacy

Your subscription and payments are managed by SocraNext. After the subscription ends, published blog articles remain on your website. SocraNext schedules removal of its FAQs after 14 days; the payment-freeze deadline is 28 days after the freeze. Reconnection and an active subscription allow FAQs to be synchronized again from SocraNext. Disconnecting in Statamic revokes the connection and does not cancel your subscription.

The connection gives SocraNext access to the collections and languages you configure, and permission to manage its supported articles, FAQs, metadata and styling. It does not create a Statamic administrator account. The addon stores connection-token hashes locally; the connected cloud service processes website content to provide SocraNext's features. See the [service description](docs/statamic-service-terms.md), [general terms](https://socranext.ai/algemene-voorwaarden/) and [privacy policy](https://socranext.ai/privacybeleid/).

## Durable identities and recovery

The platform retains its existing numeric article and category columns. The addon maps native Statamic IDs plus site identity to stable positive 52-bit numeric IDs, detecting rather than accepting a collision. This avoids a global database identity migration affecting WordPress, Shopify or Headless.

Back up `storage/app/private/socranext/` **together with content, revisions and assets**. It contains the identity registry, publication journal, connection hash, FAQ data and styling. Keep it persistent across deploys. Production workers must share storage with reliable file locks; independent local disks are not supported. The registry is not a disposable cache.

Publication uses a stable `blogId` and a journal to avoid duplicate native entries on retries. Native revisions and fingerprints protect live content and detect changes made in Statamic. Explicit deletion and bulk purge are ownership-scoped. A purged binding can be restored only through an explicit restore request; ordinary delayed writes must not resurrect deleted content. Assets are retained because customer pages may share them.

## Development and validation

```sh
composer install
composer test
node --test tests/JavaScript/*.test.mjs
```

Tests boot an actual Statamic 6 application through Testbench, with Laravel's real global input normalizers enabled. CI explicitly tests Laravel 12 with Testbench 10/PHPUnit 11 and Laravel 13 with Testbench 11/PHPUnit 12, each on PHP 8.3, 8.4 and 8.5. Every matrix job resolves its own compatible dependencies. Tests cover authentication, native permissions, content identities/publication, cold file reloads and editor conflicts, multisite configuration, FAQ rendering, exact signed code/text payloads and previews. Additional platform tests protect the existing CMS branches. No deployment, shared database migration or production website writes are part of running this suite.

Connector JSON preserves whitespace and empty strings so code signatures, `llms.txt` and explicit style clearing survive Laravel's global middleware. That exception applies only to the addon's JSON API; native website forms and the Statamic control panel retain their existing input handling. Content conflict detection uses Statamic's persisted representation, including blueprint resolution, root empty-value removal and front-matter parsing. Explicit empty translation overrides remain distinct from inherited values.

Release validation covers CI on the exact commit, anonymous installation, upgrades, the hosted SocraNext workflow and public output. The [release checklist](docs/marketplace-release.md) records package verification and Marketplace submission separately.

Software distribution and use are governed by [LICENSE](LICENSE). The [Statamic service description](docs/statamic-service-terms.md) explains the connection and offboarding behavior under the applicable SocraNext agreement.

See the [changelog](CHANGELOG.md) and [Marketplace release checklist and draft](docs/marketplace-release.md). Report addon issues through the public [issue tracker](https://github.com/SocraNext/socranext-statamic/issues); account and billing questions can go to [info@socranext.ai](mailto:info@socranext.ai).
