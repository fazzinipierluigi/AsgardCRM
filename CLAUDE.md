# AsgardCRM (package: fazzinipierluigi/asgardcrm)

This repository **is** a Composer package, not a runnable Laravel application. There is no `artisan`, `bootstrap/app.php`, or `public/index.php` here — those were removed on 2026-08-13 when the standalone AsgardCRM app that used to live at this path was folded into this package (see `CHANGELOG.md` `[0.2.0]` and `dev-notes/package-conversion/` for the full history). Do not try to `php artisan serve` or hit an HTTP endpoint from this repo for a sanity check — there is nothing to boot. That verification now happens in the sibling `AsgardCRM-Scaffolding` repo (see below).

## Identity

- Composer package: `fazzinipierluigi/asgardcrm`
- PHP namespace: `Fazzinipierluigi\AsgardCRM\`
- Service provider: `Fazzinipierluigi\AsgardCRM\AsgardCRMServiceProvider` (auto-discovered via `composer.json`'s `extra.laravel.providers`)
- Supports Laravel 13 only (`illuminate/support: ^13.12`) — see "Laravel version support" below, this is not arbitrary.

**The internal short name `crm` is intentionally kept everywhere it already existed**, even though the package itself is now called `asgardcrm`: `config/crm.php` (file and `config('crm.*')` key), the `crm::` Blade view namespace, every `crm-*` publish tag (`crm-config`, `crm-migrations`, `crm-migrations-users`, `crm-views`, `crm-assets`, `crm-lang`), every `CRM_*` env var, and `vite.config.js`'s `buildDirectory: 'vendor/crm'`. This was a deliberate scope decision during the rename (2026-08-13) to avoid a Vite asset rebuild for no functional benefit — **do not "fix" this mismatch** by renaming these on your own initiative; it would require rebuilding and re-publishing every consuming host's compiled assets.

## The two-repo workflow

This package is developed alongside a reference consumer app, **`AsgardCRM-Scaffolding`** (sibling repo, `git@github.com:fazzinipierluigi/AsgardCRM-Scaffolding.git`, expected checked out at `../AsgardCRM-Scaffolding` relative to this repo). It requires this package via a `path` repository (`composer.json` → `../AsgardCRM`) and has no Auth/Admin/Install code of its own — those all come from this package (Modulo 5, folded in 2026-08-13).

When changing package code that affects host-visible behavior (routes, views, config defaults, migrations, the `CrmUser` contract, published assets):
1. Verify the change in **this** repo first: `vendor/bin/pest` must stay green (see Testing below).
2. Verify it against a real consumer in `AsgardCRM-Scaffolding`: `cd ../AsgardCRM-Scaffolding && composer update fazzinipierluigi/asgardcrm --no-interaction` to pick up the change through the path repository (a path repo doesn't auto-refresh; Composer treats it like any other version constraint and needs an explicit update), then re-run its own `php artisan test` and, for anything touching a real HTTP flow (auth, install wizard, a new route), do a real end-to-end check with `php artisan serve` + `curl` — not just the test suite. This project has a documented history of bugs that only a real host consumer catches (see "Package-suite isolation" below).
3. If the change affects published migrations/assets/config, re-run the relevant `vendor:publish --tag=...` in Scaffolding and commit the republished output there too — Scaffolding ships pre-published files (Breeze/Jetstream-style), not a "run this yourself" instruction.

If `AsgardCRM-Scaffolding` isn't checked out at `../AsgardCRM-Scaffolding` on your machine, either check it out there or adjust the `path` repository URL in its `composer.json` — don't assume the sibling path.

## Critical facts and known traps

Read this before touching anything non-trivial — every one of these cost real debugging time this session.

- **User model is never package-owned.** `App\Models\User` (or whatever a host names it) always stays in the consuming application. The package only ever talks to it through `config('crm.user_model')` and the `Fazzinipierluigi\AsgardCRM\Contracts\CrmUser` interface (`src/Contracts/CrmUser.php`). Never add a `use App\Models\User;` or any concrete host-model reference inside `src/` — resolve the configured class dynamically instead (`$modelClass = config('crm.user_model'); $modelClass::query()...`). Extend the `CrmUser` contract only when a real, concrete call site needs a new method — don't add speculative methods.
- **Laravel 11/12 are not supported, and this was actually verified, not assumed.** Every model uses the `#[Fillable]`/`#[Hidden]` Eloquent PHP attributes, which only exist starting in `laravel/framework` 13.x. A real install against Laravel 12.61.1 was tested: every `#[Fillable]` attribute is silently ignored, and 335/375 package tests fail with `MassAssignmentException`. `illuminate/support: ^13.12` is also the floor that clears 3 `laravel/framework` security advisories active below that version — one, CVE-2026-48019 (CRLF injection in the default email validation rule), was never patched on the 11.x line at all. Don't widen the Laravel version range without re-verifying both of these.
- **The package's own test suite (`tests/`) must be 100% self-contained — no reference to anything host-only.** This was violated repeatedly while the app and package coexisted in one monorepo (tests quietly depended on `Database\Seeders\ClientiEntitySeeder`, on `layouts/base.blade.php`'s sidebar menu, etc.) and it stayed invisible because the shared classpath papered over it. It only surfaced as real `BindingResolutionException`s and false passes once the suite ran genuinely standalone. Any new test must build its own fixtures (`Entity::create()` + `EntityInstaller`, package-local Blade stubs under `tests/resources/views`) — never reach for a host class or view.
- **`config('crm.icons.path')`'s default is `base_path('node_modules/@tabler/icons/icons')` — correct for a real host, wrong under Testbench.** Testbench's `base_path()` resolves inside `vendor/orchestra/testbench-core`'s own synthetic skeleton app, not this package's directory. `tests/TestCase.php` explicitly overrides `crm.icons.path` to point at this package's own `node_modules/@tabler/icons` (a real devDependency) — don't remove that override, and don't "fix" `base_path()` globally, since the default is exactly right for a real consuming host.
- **`vite.config.js`'s `buildDirectory` must match the `crm-assets` publish target (`public_path('vendor/crm')`) exactly.** Paths inside the compiled manifest and the font CSS's `@font-face src` URLs are baked in at build time, not resolved at request time — a mismatch silently serves broken font/asset paths on the consuming host. Known, accepted exception: `Vite::renderFontPreloads()` ignores the `buildDirectory` argument passed to `@vite()` per-call (uses the framework's own default property instead) — a confirmed Laravel core limitation, not a package bug. The actual `@font-face` CSS is correct either way; only the `<link rel="preload">` hint points at the wrong path. Not worth re-chasing.
- **Never sanitize HTML with `strip_tags($value, $allowedTags)` alone.** It only filters tag *names* — attributes on the tags it keeps (`onmouseover=`, a `javascript:` `href`) pass through completely untouched. This was a real stored-XSS hole in `EntityRecordController`'s RichText field handling (fixed 2026-08-13, see `CHANGELOG.md`). The fixed pattern — `EntityRecordController::sanitizeRichText()`/`sanitizeRichTextNode()` — walks the DOM, unwraps any disallowed tag (keeps its text/children, drops the tag), and strips *every* attribute from whatever tags remain. Copy this pattern for any other place that ever needs to accept/re-render user-supplied HTML.
- **A test run that exits 1 with zero output almost never means what it looks like.** `laravel/pao` (agent-friendly test output, a very new dependency) throws `stream_filter_remove(): Unable to flush filter, not removing` and swallows all output when something upstream already fataled — the real cause was, once, two migrations with the same filename/timestamp existing in both a host's `database/migrations/` and this package's own (RefreshDatabase tried to create the same table twice). If you hit a silent zero-output test failure, check `storage/logs/laravel.log` (in whichever app is running the tests) before assuming a mysterious pao bug.
- **`EnsureAppIsInstalled`/`EnsureAppIsUpToDate` are registered as router aliases (`crm.installed`, `crm.up-to-date`), not auto-applied.** A consuming host must explicitly add them to its own `bootstrap/app.php`'s `web` middleware group (see `AsgardCRM-Scaffolding`'s `bootstrap/app.php` for the reference wiring) — they gate the host's *entire* app, including host-defined routes outside this package, so forcing them on globally from the provider would make it impossible for a Testbench-style test app (or any host without an install wizard route) to opt out. `ApplyUserPreferences`, by contrast, *is* auto-pushed onto the `web` group by `AsgardCRMServiceProvider::boot()` — don't double-register it in a host's `bootstrap/app.php`.
- **Every `*EntitySeeder` is a built-in system entity, auto-seeded by the install wizard — none of them are optional "demo content".** All 14 `Fazzinipierluigi\AsgardCRM\Database\Seeders\*EntitySeeder` classes (Calendario, Documenti, E-mail, and the standard CRM set: Clienti, Fornitori, Prodotti, Lead, Contatti, Opportunità, Preventivi, Ordini di acquisto/vendita, Fatture, Ticket) set `is_system => true` and are called directly by `ApplicationInstaller::install()` (`src/Services/ApplicationInstaller.php`), in dependency order — a Relation field's FK needs its target entity's table to already exist (see `EntitySchemaBuilder`), so e.g. Clienti/Fornitori/Prodotti/Lead seed before anything that references them. This was a real bug once (`ApplicationInstaller` only seeded Calendario for a while after Documenti/E-mail were added elsewhere, and the other 11 were never wired in at all despite already being `is_system => true` in their own seeder code) — if you add a new system entity seeder, wire it into `ApplicationInstaller::install()`'s call sequence in the same pass, respecting dependency order, or it will silently never run on a fresh install. `LanguageSeeder`/`TranslationSeeder` are seeded the same way. A host bypassing the install wizard entirely has to call the same seeders itself, in the same order.
- **The `users`-altering migrations (`username`, `login_provider_id`, `phone`, `job_title`) are deliberately not in `crm-migrations`.** They're behind the separate `crm-migrations-users` tag, never auto-published — a host with its own `users` schema or column names must not have them silently applied. Same reasoning for `crm-lang` (the 3 custom `auth.provider_*` translation keys): a host's own customized `lang/en/auth.php` shouldn't be silently overwritten.

## Testing

No `artisan` here — the suite runs standalone via Orchestra Testbench:

```bash
composer install
vendor/bin/pest
vendor/bin/pest --filter=SomeTestName
```

`vendor/bin/testbench` is available if you ever need an artisan-equivalent command in the package's own sandboxed app (e.g. inspecting routes registered by `AsgardCRMServiceProvider` in isolation). There's no `make:test` — just create the Pest file directly under `tests/Feature/` or `tests/Unit/`, following the structure of sibling files in that directory.

CI (`.github/workflows/tests.yml`) runs PHP 8.3/8.4 against Laravel 13, plus `composer audit`.

## Pint

If you've modified any PHP files: `vendor/bin/pint --dirty --format agent` before finalizing. Don't run `--test` mode — just run it and let it fix things.

## PHP conventions

- Curly braces for every control structure, even one-liners.
- PHP 8 constructor property promotion (`public function __construct(public GitHub $github) {}`), no empty zero-parameter constructors unless private.
- Explicit return types and parameter type hints everywhere.
- TitleCase enum keys (`FavoritePerson`, not `favoritePerson`).
- PHPDoc blocks over inline comments; inline comments only for genuinely non-obvious logic (a hidden constraint, a workaround, a trap — not what the code visibly does).
- Array shape type definitions in PHPDoc where relevant.

## Documentation files

Only create documentation files if explicitly requested. `dev-notes/` (gitignored, not committed) is where internal working notes live — `dev-notes/package-conversion/` is the living log of the app→package extraction (Fasi 0–5) and the 2026-08-13 rename/reorg, and `dev-notes/architecture/` holds design notes for internal boundaries. Read them for background before large structural changes, but they aren't a substitute for `README.md`/`CHANGELOG.md`, which are committed. Never put design docs, implementation plans, or other working/tracking files in `docs/` (see below) — that tree is committed, published, and user-facing only; put anything else in `dev-notes/` instead.

## User-facing documentation site (Docsify)

`docs/` is the source for the published documentation site — **committed to git** (unlike `dev-notes/`), built with [Docsify](https://docsify.js.org/), and deployed to GitHub Pages by `.github/workflows/docs.yml` on every push to `main` that touches `docs/**`. It is bilingual: `docs/en/` (English) and `docs/it/` (Italiano) are parallel page trees — every page must exist, and stay in sync, in both.

Structure:

- `docs/index.html` — Docsify loader/config (sidebar, navbar, search, plugins). Rarely needs touching.
- `docs/README.md` — root language-picker landing page.
- `docs/en/`, `docs/it/` — one `.md` file per topic, plus each language's own `_sidebar.md` and `_navbar.md`.

**Keep this site up to date as the package changes.** Whenever you change something that would make an existing page inaccurate or that deserves a new page — routes, config defaults (`config/crm.php`), publish tags, the `CrmUser` contract, a new module/enum a user-facing page already documents, the install steps, supported Laravel/PHP versions — update the corresponding page in **both** `docs/en/` and `docs/it/` (and both `_sidebar.md` files if you add/remove a page) as part of that change, not as a separate follow-up. The installation page in particular must keep covering both install paths: `composer require fazzinipierluigi/asgardcrm` into an existing Laravel app, and starting from `AsgardCRM-Scaffolding`.
