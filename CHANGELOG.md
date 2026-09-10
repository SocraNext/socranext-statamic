# Changelog

## Unreleased

These changes are on the main branch and are being evaluated on the hosted pilot. They are not included in the published `0.1.0-beta.2` package. No stable version has been assigned.

### Fixed

- Make the FAQ accordion open and close reliably, with mobile answers using the available width and height.
- Accept valid CSS child selectors in the shared FAQ styling while retaining the checks against script and style breakouts. Saved platform styling now passes native synchronization and a fresh preview reload on the pilot.
- Return the platform's standard page, post, product and category types during discovery so FAQ actions reach the correct native endpoints; custom collections and managed articles retain their existing types.

### Changed

- Focus English and Dutch Control Panel copy and package descriptions on SocraNext's AI visibility purpose.
- Update installation and Marketplace preparation documentation to distinguish the published beta, the patched pilot and the remaining release gates.

### Acceptance status — 10 September 2026

Hosted connection, readiness, standard onboarding, styling, scheduled main-article publication and the normal **Publish now** UI have passed. Existing-page FAQs now pass enable, edit, lock, disable and re-enable checks with public HTML and schema verification. The candidate passes 77 PHP tests with 795 assertions. Offboarding remains unresolved; the final package still needs CI on its exact commit, clean installation and upgrade checks, and verification of the Marketplace installation command before submission.

## 0.1.0-beta.2

### Fixed

- Preserve Statamic's native translation namespace. The addon's Composer basename previously caused dashboard and licensing messages to display raw translation keys. SocraNext's English and Dutch labels retain their separate namespace, and website-specific Statamic translation overrides continue to work.
- Add native application regressions covering dashboard/licensing messages, both addon locales and a website translation override. The hosted failure was reproduced from the exact beta.1 artifact before applying this correction.

The exact `0.1.0-beta.2` version is published on GitHub/Packagist, with anonymous installation and the hosted beta.1 upgrade verified. This patch does not change publication, connection, licensing or subscription behavior. Hosted connected-service acceptance remains in progress.

## 0.1.0-beta.1

First beta scope for Statamic 6 Pro, intended for development and pilot evaluation. The [source repository](https://github.com/SocraNext/socranext-statamic) is public; consult [GitHub releases](https://github.com/SocraNext/socranext-statamic/releases) and [Packagist](https://packagist.org/packages/socranext/statamic) for published versions and installation availability. These release notes do not establish Marketplace publication or general availability of the connected service.

At the time of beta.1, the companion SocraNext platform integration was deployed with both Statamic flags disabled, pending verification on an isolated HTTPS test site. See the Unreleased acceptance status above for subsequent pilot progress.

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

Native Statamic templates require initial developer configuration. Automatic FAQ injection into arbitrary templates, universal SEO-addon compatibility, CMS-initiated generation and category slug changes are outside this beta. A separate headless/static frontend needs its own integration. The addon is free, with SocraNext subscriptions billed separately under the [Statamic service terms](docs/statamic-service-terms.md). See [LICENSE](LICENSE) for software distribution and use rights.
