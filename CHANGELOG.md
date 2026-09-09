# Changelog

## 0.1.0-beta.1 — prepared, not published

Intended first public beta for Statamic 6 Pro. Repository publication, the beta tag and Packagist registration are pending; no public beta or Marketplace listing has been released. The companion SocraNext platform integration is deployed with both Statamic flags disabled, pending verification on an isolated HTTPS test site. A stable release requires the hosted pilot and scheduled-delivery checks to pass.

### Added

- Native Statamic Control Panel screen with English and Dutch translations, connection actions, configuration checks and separate website-readiness status.
- SocraNext branding shared with the Agent workspace: original logos, locally hosted Poppins and Montserrat fonts, purple actions and the same animated space background, with dark mode and reduced-motion support.
- Scoped discovery of configured native collections and taxonomies, with stable numeric identities per Statamic site.
- Native publication of SocraNext-owned articles, categories and archives, including multilingual article relationships, managed URL changes and redirects.
- FAQ storage, toggling and Antlers rendering for supported content, with FAQ structured data and per-site language selection.
- Article, archive and FAQ styling with translated text settings, signed custom JavaScript and short-lived previews restricted to approved platform origins.
- Supported page/article metadata updates and explicit metadata template integration.
- Publication of `llms.txt` and `llms-full.txt`, with collision checks for existing public files.
- Installation and diagnostic commands, native static-cache invalidation, ownership-scoped deletion/purge and explicit recovery of purged publications.

### Publication safeguards and validation

- Single-use connection handshake, hashed credentials, native CP permissions and CSRF protection. Disconnect revokes connection credentials and existing previews while preserving published content.
- Native working copies, file checks and revision fingerprints protect editor changes. Fingerprints remain stable across native file reloads and preserve the distinction between empty translation overrides and inherited values.
- Connector JSON preserves exact signed code and text payloads; native website forms retain Laravel and Statamic input normalization.
- Multisite configuration is validated before exposing additional sites. Public image downloads are restricted and validated before storage.
- Native application tests cover Laravel 12 and 13 on PHP 8.3, 8.4 and 8.5 through the CI matrix.
- A dedicated asset publishing tag updates only the addon assets and preserves the host website's Statamic configuration.
- Status, diagnostics, the Control Panel and preview responses report the same beta version.

### Supported profile

Native Statamic templates require initial developer configuration. Automatic FAQ injection into arbitrary templates, universal SEO-addon compatibility, CMS-initiated generation and category slug changes are outside this beta. A separate headless/static frontend needs its own integration. The addon is intended to be free, with SocraNext subscriptions billed separately under the [Statamic service terms](docs/statamic-service-terms.md). See [LICENSE](LICENSE) for software distribution and use rights.
