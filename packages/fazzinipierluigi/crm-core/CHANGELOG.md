# Changelog

All notable changes to `fazzinipierluigi/crm-core` are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [SemVer](https://semver.org/), starting at `0.x` until an external app has installed the package from scratch (outside this monorepo) and verified it end-to-end — see the note in `README.md`.

## [Unreleased]

## [0.1.0] - 2026-08-13

First coherent, tested state of the package extracted from the AsgardCRM monorepo (`docs/package-conversion/` has the full phase-by-phase log).

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
