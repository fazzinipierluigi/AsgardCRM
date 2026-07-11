# DOCUMENTATION.md

Low-level reference for AI agents working on this codebase. Dense, structured, no prose padding. Update this file whenever a feature/procedure changes its implementation, not just README.md.

---

## project

- name: AsgardCRM
- type: CRM (Laravel app), built incrementally
- framework: laravel/framework ^13.8, php ^8.3
- db (dev): sqlite, file `database/database.sqlite`
- ui template: Tabler (`@tabler/core`, npm) — Bootstrap-based, NOT Tailwind-driven UI. Tailwind is still present (Laravel 13 skeleton default) but unused for actual CRM screens; `resources/css/app.css` imports both `tailwindcss` and `@tabler/core/dist/css/tabler.min.css` + `raccoon-tables/styles/raccoon-tables.css`.
- rule: never add a self-attribution/co-author tag to any git commit (subject or body).
- rule: every feature/procedure ships with Pest and/or Dusk tests before considered done.
- rule: docs split — README.md (procedures, human-facing), DOCUMENTATION.md (this file), SDK.md (API/webservice/SDK ref).

## stack packages

| package | version constraint | role | install surface |
|---|---|---|---|
| `fazzinipierluigi/just-a-gate` | ^1.0 | ACL (roles/permissions) | composer, on Packagist |
| `fazzinipierluigi/laraccoon_datasource` | ^1.0 | server-side datatable request handling | composer, on Packagist |
| `raccoon-tables` | ^1.1 | frontend grid/datatable | npm |
| `@tabler/core` | ^1.4 | UI template | npm |
| `laravel/dusk` | ^8.6 | browser testing | composer --dev |

## authentication

Custom, NOT a starter-kit scaffold. Auth field is **`username`**, not email.

- `app/Models/User.php` — `Authorizable` trait (Just A Gate), `#[Fillable(['name','username','email','password'])]`.
- `database/migrations/2026_07_11_082453_add_username_to_users_table.php` — adds `username` (string, unique) after `name`. **down() must `dropUnique(['username'])` before `dropColumn('username')`** — SQLite errors ("no such column after drop column") if you drop the column first while the unique index still references it.
- `app/Http/Requests/Auth/LoginRequest.php` — validates `username`+`password`, rate-limits via `RateLimiter` (5 attempts, keyed by `strtolower(username)|ip`), throws `ValidationException` with `auth.failed` / `auth.throttle` translation keys on failure.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — `create()` (GET /login view), `store()` (calls `$request->authenticate()`, regenerates session, redirects to `route('dashboard')` via `->intended()`), `destroy()` (logout: `Auth::logout()`, invalidate session, regenerate CSRF token).
- Routes in `routes/web.php`: `login` (GET/POST, `guest` middleware), `logout` (POST, `auth` middleware), `dashboard` (GET, `auth` middleware, inline closure view).
- Views: `resources/views/auth/login.blade.php`, `resources/views/dashboard.blade.php` — Tabler markup (`page`, `page-center`, `card card-md`, `form-control`, etc), each own full `<html>` document with `@vite(['resources/css/app.css','resources/js/app.js'])`.
- `lang/en/auth.php` published (was not present in Laravel 13 skeleton by default) — needed for `trans('auth.failed')`/`trans('auth.throttle')`.
- `database/factories/UserFactory.php` — generates `username` via `fake()->unique()->userName()`.
- Seeded user (via `DatabaseSeeder`): `username=test`, `email=test@example.com`, `password=password`.

## ACL (Just A Gate)

- Config: `config/acl.php`. Keys: `middleware` (default `'acl'`), `additional` (manual `key => name` map), `role_user_creation` (bool, auto-generates a `user.create.role_{slug}` permission per role), `clean_permission` (bool, deletes stale permissions on import), `assign` (`permission_key => [role_slug, ...]` auto-assignment map), `translate` (`permission_key => display name` override).
- Tables (package migrations, not in this repo's `database/migrations`, loaded from vendor): `roles`, `permissions`, `permission_role`, `role_user`.
- Models: `Fazzinipierluigi\JustAGate\Models\{Role,Permission,PermissionRole,RoleUser}`.
- Artisan commands: `permission:init` (first run — migrates + creates `admin` role), `permission:create {key} {name?}`, `permission:assign {key} {role}`, `permission:import` (bulk regenerate from `config/acl.php` + route scan + role scan, applies `assign`/`clean_permission`/`translate`).
- Gating Livewire: `#[Fazzinipierluigi\JustAGate\Attributes\RequiresPermission('key')]` on a class (checked every lifecycle) or a method (checked only on that action).
- `User` model must `use Authorizable` (already wired).

## testing infrastructure

Two suites, deliberately separated because **SQLite `:memory:` cannot be shared across processes** (the Dusk browser drives the app over real HTTP, hitting a separate PHP process from the one running the test assertions).

### Pest (Feature/Unit)

- Config: `phpunit.xml`. Forces `DB_DATABASE=:memory:`, `SESSION_DRIVER=array`, `CACHE_STORE=array` — fast, isolated, in-process only.
- `tests/Pest.php`: `pest()->extend(TestCase::class)->in('Feature')` (RefreshDatabase commented out — tests that hit the DB opt in per-file via `uses(RefreshDatabase::class)`, see `tests/Feature/AuthenticationTest.php`).
- Run: `composer test` (= `php artisan config:clear && php artisan test`).

### Dusk (Browser)

- Config: `phpunit.dusk.xml.dist` — separate phpunit config, `testsuite Browser -> tests/Browser`, intentionally has **no `<php><env>` overrides** so it inherits whatever `.env` is active at run time.
- `.env.dusk.local` — committed (not secret), points `DB_DATABASE` at an absolute path `database/testing.sqlite` (gitignored via `database/.gitignore` `*.sqlite*`), `SESSION_DRIVER=database`, `CACHE_STORE=database`, `BCRYPT_ROUNDS=4`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `APP_URL=http://127.0.0.1:8000`.
- `tests/Pest.php`: `pest()->extend(DuskTestCase::class)->use(DatabaseMigrations::class)->in('Browser')`.
  - **Gotcha (hit once, fixed):** the `use Tests\DuskTestCase;` / `use Illuminate\Foundation\Testing\DatabaseMigrations;` import statements MUST appear textually before the `pest()->extend(...)` line that references the short class names. Pint's `ordered_imports`/`fully_qualified_strict_types` fixers will happily move `use` statements below the code that references them (valid plain PHP, since `use` is compile-time) — but Pest's own file loader is sensitive to source order and throws `Pest\Exceptions\TestCaseClassOrTraitNotFound` if the import comes after the reference. Keep all `use` statements at the top of `tests/Pest.php`.
- `scripts/dusk.sh` — orchestrates a full Dusk run:
  1. backs up `.env` to `.env.backup.dusk`, copies `.env.dusk.local` over `.env`
  2. `php artisan migrate:fresh --force` (against the dusk sqlite file)
  3. starts `php artisan serve --host=127.0.0.1 --port=8000` in background, polls `/login` until it responds
  4. runs `php artisan dusk "$@"`
  5. `trap ... EXIT` always kills the serve process and restores the real `.env`, even on failure/Ctrl-C.
- Composer scripts: `composer test:dusk` (= `bash scripts/dusk.sh`), `composer test:all` (= `@test` then `@test:dusk`).
- System requirement: Chromium/Chrome binary must be installed (`chromium` via pacman on this box). `laravel/dusk`'s bundled ChromeDriver binary alone is not sufficient — it drives an actual browser binary that must exist on `$PATH`.
- Dusk tests must use `DatabaseMigrations` (or `DatabaseTruncation`), never `RefreshDatabase` (transactions don't cross the HTTP-request process boundary).
- Prefer `waitForLocation(...)` / `waitForText(...)` over immediate `assertPathIs(...)`/`assertSee(...)` right after `->press(...)` — a redirect race causes intermittent `stale element reference` errors otherwise (hit and fixed in `tests/Browser/LoginTest.php`).

## known non-obvious gotchas (chronological)

1. SQLite `dropColumn` on a uniquely-indexed column requires `dropUnique` first (see migration above).
2. Pint can silently break `tests/Pest.php` if it reorders `use` past a `pest()->extend()` call — always keep imports at the top of that file, re-check after running Pint on it.
3. Dusk cannot use `:memory:` SQLite — must be a real file, shared between the test process and the served app process via a matching `.env`.
4. `php artisan serve` and the Dusk test-runner process are separate PHP processes started at different times — any env swap must happen *before* starting `serve`, not just before running `dusk`.
