# SocraNext for Statamic

A Statamic 6 addon that connects a website to the existing SocraNext workspace. The connector uses the same article, FAQ and styling workflows as the platform's other CMS integrations. The SocraNext subscription remains billed by SocraNext.

**Development preview.** This private repository is not yet a Packagist or Marketplace release. Platform integration is developed separately on `SocraNext/SocraNextRebuild` branch `codex/statamic-integration`, with Statamic disabled by default. Do not advertise production parity until the pilot acceptance checks have passed.

The companion platform integration has not been deployed for general use. Connecting requires a pilot SocraNext environment with that integration and both Statamic feature flags enabled. Installing this addon alone does not enable Statamic in the platform.

## Installation for a development or pilot site

Requirements: Statamic 6 Pro, PHP 8.3 or newer with Sodium, cURL, DOM, Fileinfo and Mbstring, and persistent writable content and Laravel storage. The initial release targets native Statamic rendering. A separate headless/static frontend needs its own rendering and deployment integration.

From your website's project directory, using a GitHub account with access to this repository:

```sh
composer config repositories.socranext vcs https://github.com/SocraNext/socranext-statamic.git
composer require socranext/statamic:dev-main
php artisan vendor:publish --tag=socranext-config
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

1. Create the corresponding Statamic project in the enabled SocraNext pilot environment, using the same website address, then select **Connect with SocraNext** in Statamic.
2. Finish the configuration checks. Inspect the website's templates and the SocraNext styling previews, including FAQ placement, metadata integration, links and mobile layout. Previews can show sample content before the first article is published.
3. Confirm website readiness using the checkbox and save the website status. This enables the publication actions that require a prepared website.
4. Publish a test article and enable a test FAQ from SocraNext. Check their actual public output before continuing with regular publishing. If the website setup needs more work, clear the readiness checkbox while correcting it.

```sh
php please socranext:doctor --json
```

Install and update through the website's normal Composer/test/deploy process. Statamic 6 provides Composer commands in its control panel, rather than executing package installation there.

## Integration boundaries

API base: `/api/socranext/v1`. Authentication uses `x-socranext-token` or a Bearer token. Conflicting credentials are rejected. Tokens are delivered directly from the trusted SocraNext backend using a short-lived, single-use state created in the authorized control panel; the addon stores only hashes. Disconnect revokes the credential and preserves content.

The addon exposes native content for selection, and writes articles into its owned collection. It preserves unrelated fields and never attempts to flatten arbitrary customer Bard or Replicator structures into article HTML. Existing page FAQ placement is explicit through the template tag. Browser-only installation, automatic injection into arbitrary templates and a universal SEO/page-builder integration are outside this supported profile.

Global custom JavaScript must carry a valid SocraNext Ed25519 signature bound to the website and the slot. Per-page unsigned JavaScript is rejected. Preview sessions are short-lived and restricted to configured platform origins. Only public HTTPS image downloads are allowed, with pinned DNS, no redirects, MIME validation and a size limit.

`GET /status` advertises the contract version, addon version, readiness and supported capabilities. The platform must check those capabilities instead of assuming that every CMS supports every optional action. Native article translations and translated styling are supported, with distinct identities for each Statamic site. The platform worker enables them only after capability and website-readiness checks.

The pilot explicitly rejects category slug changes, automatic FAQ injection, generation requests initiated from the CMS, and bulk translated archive-slug maps. Category title and description edits, native entry slug changes, native multilingual articles and translated styling are supported. Each site can have its archive slug configured through the API. A rejected operation returns an error; it is never reported as a successful publication.

## Platform rollout

The companion platform branch adds a `statamic` CMS value and a separate adapter. Its flags are opt-in:

```dotenv
# Backend, evaluated at runtime
SOCRANEXT_STATAMIC_ENABLED=false
# Frontend, evaluated at build time
VITE_SOCRANEXT_STATAMIC_ENABLED=false
```

Review and test the platform PR before deploying it. Apply its additive CMS constraint migration before allowing Statamic projects to be created. Enable both flags in a pilot environment, create a Statamic project, connect the addon and verify the published output. Disabling the flags stops new platform Statamic actions; existing published native content remains available. The existing WordPress, Shopify and Headless billing and connector routes retain their current behavior.

## Durable identities and recovery

The platform retains its existing numeric article and category columns. The addon maps native Statamic IDs plus site identity to stable positive 52-bit numeric IDs, detecting rather than accepting a collision. This avoids a global database identity migration affecting WordPress, Shopify or Headless.

Back up `storage/app/private/socranext/` **together with content, revisions and assets**. It contains the identity registry, publication journal, connection hash, FAQ data and styling. Keep it persistent across deploys. Production workers must share storage with reliable file locks; independent local disks are not supported. The registry is not a disposable cache.

Publication uses a stable `blogId` and a journal to avoid duplicate native entries on retries. Native revisions and fingerprints protect live content and detect changes made in Statamic. Explicit deletion and bulk purge are ownership-scoped. A purged binding can be restored only through an explicit restore request; ordinary delayed writes must not resurrect deleted content. Assets are retained because customer pages may share them.

## Development and validation

```sh
composer install
composer test
```

Tests boot an actual Statamic 6 application through Testbench, with Laravel's real global input normalizers enabled. CI explicitly tests Laravel 12 with Testbench 10/PHPUnit 11 and Laravel 13 with Testbench 11/PHPUnit 12, each on PHP 8.3, 8.4 and 8.5. Every matrix job resolves its own compatible dependencies. Tests cover authentication, native permissions, content identities/publication, cold file reloads and editor conflicts, multisite configuration, FAQ rendering, exact signed code/text payloads and previews. Additional platform tests protect the existing CMS branches. No deployment, shared database migration or production website writes are part of running this suite.

Connector JSON preserves whitespace and empty strings so code signatures, `llms.txt` and explicit style clearing survive Laravel's global middleware. That exception applies only to the addon's JSON API; native website forms and the Statamic control panel retain their existing input handling. Content conflict detection uses Statamic's persisted representation, including blueprint resolution, root empty-value removal and front-matter parsing. Explicit empty translation overrides remain distinct from inherited values.

Before release, test a clean install and upgrade on representative websites, verify the full SocraNext screen workflow and public HTML, and check the Marketplace's applicable creator terms. The intended listing is a free addon requiring a separate SocraNext subscription. The distribution license and public release must be finalized before publishing beyond this private development repository.

See the [changelog](CHANGELOG.md) and [Marketplace release checklist and draft](docs/marketplace-release.md). The repository and issue tracker currently require private repository access; account and billing questions can go to [info@socranext.ai](mailto:info@socranext.ai).
