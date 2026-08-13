# Changelog

All notable changes to `fazzinipierluigi/asgardcrm` (published as `fazzinipierluigi/crm-core` before the 0.2.0 rename) are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [SemVer](https://semver.org/), starting at `0.x` until an external app has installed the package from scratch (outside a sibling repo on the same disk) and verified it end-to-end — see the note in `README.md`.

## [Unreleased]

### Fixed
- `crm-assets` now publishes AsgardCRM's own `logo.svg` and favicon set (`favicon.ico`, `favicon-16x16.png`, `favicon-32x32.png`, `apple-touch-icon.png`, `site.webmanifest`, `android-chrome-192x192.png`, `android-chrome-512x512.png`) to the host's public root. The login and install/update wizard views have always referenced these by bare filename, but the files themselves were dropped in `0.2.0`'s app-shell removal and never carried into the package's own publishable assets — every consuming host got a broken image on those pages until now.
- `install/database.blade.php` fatally errored with `ViteManifestNotFoundException` on every host: it called `@vite(['resources/js/install-wizard.js'])` without the `'vendor/crm'` build directory, so it resolved against the *host's own* (nonexistent) `public/build/manifest.json` instead of the package's published one — and `install-wizard.js` itself didn't exist yet, wasn't in `vite.config.js`'s `input`, and its `#install-test-connection-button`/driver-toggle behavior on the database step was entirely unimplemented. Wrote the file, added it to the build, and fixed the `@vite()` call.
- **All 14 `*EntitySeeder` classes are `is_system => true` and were always meant to be built-in, not optional demo content — `ApplicationInstaller::install()` (the install wizard) only ever called 1 of them.** Fresh installs ended up with just "Calendario" (or, briefly, plus "Documenti"/"E-mail" — added later on 2026-07-31 and 2026-08-09 and never wired in either) and were missing the entire standard CRM entity set (Clienti, Fornitori, Prodotti, Lead, Contatti, Opportunità, Preventivi, Ordini di acquisto/vendita, Fatture, Ticket) along with every one of their menu entries. `ApplicationInstaller::install()` now seeds all 14, in dependency order (a Relation field's foreign key needs its target entity's table to already exist). This also corrects `README.md`/`CLAUDE.md`/the docs site, which previously — incorrectly — described these as opt-in "demo content" a host had to seed itself.

### Added
- Bilingual (English/Italiano) Docsify documentation site under `docs/`, published to GitHub Pages via `.github/workflows/docs.yml`.

## [0.2.0] - 2026-08-13

### Changed
- **Renamed** `fazzinipierluigi/crm-core` → `fazzinipierluigi/asgardcrm`; PHP namespace `Fazzinipierluigi\CrmCore\` → `Fazzinipierluigi\AsgardCRM\`; `CrmServiceProvider` → `AsgardCRMServiceProvider`. The internal short name `crm` is unchanged everywhere else on purpose (config file/key, `crm::` view namespace, `crm-*` publish tags, `CRM_*` env vars, `vite.config.js`'s `buildDirectory: 'vendor/crm'`) — renaming those would have forced a Vite asset rebuild for no requested benefit.
- **Moved to repo root**: this repository (formerly the standalone AsgardCRM app, with the package nested under `packages/fazzinipierluigi/crm-core/`) *is* the package now — no more monorepo wrapper. History preserved (`git log --follow` still works on every moved file). The old app's own Laravel-skeleton files (`app/`, `bootstrap/`, `artisan`, `public/index.php`, etc.) were removed — this repo no longer boots as a runnable application, only as a Composer library.
- `starter-kit/` removed from this repo; rebuilt fresh as the separate [`AsgardCRM-Scaffolding`](https://github.com/fazzinipierluigi/AsgardCRM-Scaffolding) repository, now with no custom Auth/Admin code of its own (see Added, below) — just a `User` model and bootstrap wiring.

### Added
- **Modulo 5 — Auth, Admin, Install/Update wizard, demo content**, the last pieces that lived in the standalone app, folded into the package:
  - Full auth: classic login, SAML, social login, LDAP.
  - Admin CRUD: Users, Roles, Login-providers.
  - Install wizard and Update wizard (`EnsureAppIsInstalled`/`EnsureAppIsUpToDate`, now registered as router aliases `crm.installed`/`crm.up-to-date` a host applies explicitly, rather than a host-owned middleware).
  - `TicketTimerController` and 14 `*EntitySeeder` demo-content classes (Clienti, Fatture, Preventivi, Ticket, and so on) plus `LanguageSeeder`/`TranslationSeeder` — all package-owned, `DatabaseSeeder` in a consuming host calls them optionally.
  - `CrmUser` contract gained `effectiveLoginProvider()` (Auth/Admin/Install controllers needed to resolve a user's login provider without a concrete `App\Models\User` reference).
  - New publish tag `crm-lang` (the 3 custom `auth.provider_*` translation keys) — separate/explicit like `crm-migrations-users`, so a host's own customized `lang/en/auth.php` isn't silently overwritten.
- `RichTextSanitizationTest` — first coverage of `EntityRecordController`'s RichText sanitization path (0 tests existed for it before, see 0.1.0's Fixed entry below for the bug it now guards).

## [0.1.0] - 2026-08-13

First coherent, tested state of the package extracted from the AsgardCRM monorepo (`dev-notes/package-conversion/` has the full phase-by-phase log).

### Added
- Package scaffold: `Fazzinipierluigi\CrmCore\` namespace, `CrmServiceProvider`, `config/crm.php`.
- Modulo 1 — Entity/Workflow/Importer core (dynamic entities, workflow engine, importers), with its Pest suite ported to run standalone under Orchestra Testbench.
- Modulo 2 — Calendar connectors.
- Modulo 3 — Documenti.
- Modulo 4 — Webmail.
- Group A/B "thin consumer" controllers folded into Modulo 1/2.
- Shared substrate: `Setting`/`Translation`/`Language` models and the `t()`/`icon()`/`preferences()` helpers, consolidated out of the host app.
- Independent Vite asset pipeline (`vite.config.js`, `buildDirectory: 'vendor/crm'`), pre-built and committed under `public/vendor/crm/`, published via the `crm-assets` tag — the package ships compiled assets since a Composer-installed package can't run the host's `npm run build`.
- `crm-migrations-users` tag: the 3 migrations that alter a host's `users` table (`username`, `login_provider_id`, `phone`/`job_title`), published separately from `crm-migrations` and never auto-applied — see README.
- `starter-kit/` (in this repo): a minimal, independent Laravel 13 project consuming the package via a path repository, with classic login-only auth, an adapted layout, a seeded admin, and a real end-to-end verification (authenticated login → dashboard → a protected package route).
- CI: `.github/workflows/crm-core-tests.yml`, PHP 8.3/8.4 matrix against Laravel 13, plus `composer audit`.
- `RichTextSanitizationTest` — first coverage of `EntityRecordController`'s RichText sanitization path (0 tests existed for it before).

### Changed
- `illuminate/support` restricted from a provisional `^11.0|^12.0|^13.0` to `^13.12`: every model's `#[Fillable]`/`#[Hidden]` attribute only exists in Laravel 13 (verified by actually installing Laravel 12.61.1 and running the suite — 335/375 tests failed with `MassAssignmentException`), and `^13.12` is also the floor clearing 3 open `laravel/framework` security advisories (one, CVE-2026-48019, was never patched on the 11.x line at all).

### Fixed
- **Security**: `EntityRecordController::sanitizeRichText()` used `strip_tags($value, $allowedTags)`, which only filters tag names — attributes on kept tags (`onmouseover=`, a `javascript:` `href`) passed through untouched, a stored-XSS hole reachable by any user with edit rights on one entity, against any later viewer of the record including admins. Replaced with a `DOMDocument`-based sanitizer that also strips every attribute; dropped `<a>` from the allowlist (the field's toolbar never produces one).
- `crm-core`'s own Testbench suite depended on host-only classes (`Database\Seeders\ClientiEntitySeeder`/`CalendarEntitySeeder`) and host-only views (`layouts/base.blade.php`'s sidebar menu) — invisible while it only ran inside the monorepo, but a real failure/false-pass under a genuinely standalone install. Rebuilt the affected tests on package-local fixtures; moved the one lost regression (icon rendering as inline svg, not a webfont class) to the AsgardCRM app's own `SidebarMenuTest.php`, where the real host view it exercises actually exists.
- `config('crm.icons.path')`'s default (`base_path('node_modules/@tabler/icons/icons')`) resolves correctly for a real host app, but under Testbench `base_path()` points inside `vendor/orchestra/testbench-core`'s own synthetic skeleton — icon lookups silently returned nothing, failing any test that touched an entity icon. Test environment now points explicitly at the package's own `node_modules/@tabler/icons`.
- `Vite::renderFontPreloads()` font preload `<link>` tags ignored the `buildDirectory` override passed to `@vite()` (a Laravel core limitation, not a package bug) — worked around by setting `buildDirectory: 'vendor/crm'` in `vite.config.js` itself so every other path resolves correctly; the font preload hint is the one known cosmetic exception (fonts still load correctly via the real `@font-face` CSS).
