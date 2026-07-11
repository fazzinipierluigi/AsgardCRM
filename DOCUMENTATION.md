# DOCUMENTATION.md

Low-level reference for AI agents working on this codebase. Dense, structured, no prose padding. Update this file whenever a feature/procedure changes its implementation, not just README.md.

---

## project

- name: AsgardCRM
- type: CRM (Laravel app), built incrementally
- framework: laravel/framework ^13.8, php ^8.3
- db (dev): mariadb (see `.env.example`); Pest/Dusk still use sqlite (`:memory:` and `database/testing.sqlite` respectively) regardless of the app's own `.env`.
- ui template: Tabler (`@tabler/core`, npm) — Bootstrap-based. **Tailwind CSS is NOT used and must never be re-added to `resources/css/app.css`** — see gotcha #10 below.
- home page (`/`) redirects straight to `/login`; no Laravel welcome view (deleted).
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

## layout & menus

- `resources/views/layouts/app.blade.php` — single shared shell for every authenticated page: `.page` > fixed vertical `<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">` (Tabler's vertical-nav layout is fixed by default, no extra CSS needed) + `<header class="navbar ... sticky-top">` + `.page-wrapper > .page-body > .container-fluid` for `@yield('content')`. `@yield('menu')` inside the aside is filled by whichever of the two menu layouts below is used.
- `resources/views/layouts/base.blade.php` (`@extends('layouts.app')`) — "Dashboard" link + bottom-anchored (`.mt-auto`) "Amministrazione" link. The admin link is wrapped in `@can('admin.access') ... @endcan`.
- `resources/views/layouts/admin.blade.php` (`@extends('layouts.app')`) — Utenti / Ruoli / Permessi links + "back to dashboard" link.
- Top navbar right-side cluster order: notifications bell (`data-testid="notifications-toggle"`, static empty dropdown) → user dropdown (`data-testid="user-menu-toggle"`, shows name + comma-joined role names via `auth()->user()->getRoles()->pluck('name')->join(', ')`, dropdown has Impostazioni + Logout).
- `data-testid="*"` attributes are sprinkled through the layout/menu markup specifically so Dusk tests can target elements without depending on Tabler's own CSS classes (which could change with a Tabler upgrade). Keep adding `data-testid` to new interactive elements you want to test.
- **`@can`/`@endcan` in Blade is Just A Gate's, not Laravel's.** `JustAGateServiceProvider::bootBladeDirectives()` does `Blade::if('can', fn ($ability) => Auth::user()->can($ability))`, overriding the framework directive. This means `@can('some.key')` correctly calls Just A Gate's `User::can()` (role/permission based). **Laravel's `can:` route middleware and `Gate::authorize()`/`$this->authorize()` do NOT go through this override** — they use Laravel's own `Gate` facade, which has no abilities registered for Just A Gate keys and will always deny. For route-level protection use Just A Gate's own `acl` middleware (see below), never `can:`.

## admin CRUD (Users / Roles / Permissions)

- Routes: `routes/web.php`, inside `Route::prefix('admin')->name('admin.')->middleware('acl')->group(...)`. Each resource has a `Route::get('{resource}/data', ...)` registered **before** `Route::resource(...)` so it isn't swallowed by the `{model}` wildcard.
- The `acl` middleware (`Fazzinipierluigi\JustAGate\Middleware\AclCheck`, aliased from `config('acl.middleware')`) derives the required permission key from the controller/action: `Admin\UserController@index` → `user.index` (strip namespace, strip `Controller`, lowercase, `@` → `.`). Every action — including the `data` endpoint — has its own key (`user.data`, `role.data`, etc). A role needs each key granted individually for granular access; `is_admin` roles bypass all of it.
- `admin.access` (checked in the base menu, see above) is a separate, coarser key not tied to any one controller action — it only gates the sidebar link's visibility, not an actual route. Not pre-created anywhere; only matters once a non-admin role needs the link visible (grant via `permission:create admin.access "..."` + `permission:assign admin.access {role}`).
- Controllers: `app/Http/Controllers/Admin/{User,Role,Permission}Controller.php`. Each has `index()` (renders the grid page), `data()` (JSON via `EloquentSource`), `create()/store()`, `edit()/update()`, `destroy()`.
- Business rules: `UserController::destroy()` refuses to let a user delete their own account (compares `$user->id` to `$request->user()->id`). `RoleController`: system roles (`is_system=true`, e.g. `admin`) can't be deleted, and their `slug` is silently ignored on update (name still editable). `PermissionController`: no special restrictions — deleting a permission cascades off `permission_role` via the vendor migration's FK `onDelete('cascade')`.
- `DatabaseSeeder` creates/fetches the `admin` role (`slug=admin, is_admin=true, is_system=true`) and assigns it to the seeded `test` user — without this, a fresh `db:seed` wouldn't grant the seeded user access to `/admin/*` or show the Amministrazione link.

### Just A Gate `syncPermissions()` expects **keys**, not IDs

`Fazzinipierluigi\JustAGate\Models\Role::resolvePermission()` accepts a `Permission` instance or a **string key** (`Permission::where('key', $permission)->firstOrFail()`) — there is no `int`-as-id branch (unlike `resolveRole()`, which does accept `Role|string|int`). Passing permission **IDs** to `syncPermissions()` throws `ModelNotFoundException` ("No query results for model [Permission]") because it tries to look up a permission whose `key` equals the numeric id string.

Consequence: the role edit/create form's permission checkboxes use `value="{{ $permission->key }}"` (not `->id`), `StoreRoleRequest`/`UpdateRoleRequest` validate `permissions.*` against `exists:permissions,key` (not `,id`), and the controller passes the raw request array straight through to `syncPermissions()` — no id-to-model resolution needed. Contrast with `User::syncRoles()` (`Authorizable` trait), which does accept role IDs directly.

## Raccoon Tables + Laraccoon Datasource integration

- `resources/js/app.js` imports `RaccoonGrid` from `raccoon-tables` and exposes it as `window.RaccoonGrid` — each admin index view instantiates its own grid via a plain inline `<script>` (no per-page Vite entrypoints), e.g. `new window.RaccoonGrid({ ... }).render('#users-grid')`.
- **`serverAdapter` defaults to `method: 'POST'`** (per Raccoon Tables' own docs), but our data routes are `Route::get(...)`. Left as default this 405s (`Method Not Allowed`). Fix: every grid config sets `serverAdapter: { url: ..., method: 'GET' }` explicitly. (Alternative considered and rejected: accepting POST too via `Route::match(['get','post'], ...)` — would need CSRF header wiring for no real benefit, since these endpoints are read-only.)
- **`EloquentSource::apply()` returns an empty result set if the request has no `limit` param** (`$limit = (int)($params['limit'] ?? 0); if ($limit <= 0) $this->filtered_dataset->take(0);`). Raccoon Tables' own client always sends `start`/`limit` in real usage, so this only bites manual/test requests hitting `*/data` directly — always pass `?start=0&limit=25` (or similar) when calling these endpoints outside the actual grid (see Pest tests in `tests/Feature/Admin/*ControllerTest.php`).
- **Global search requires `$search_fields` passed explicitly** to `EloquentSource::apply($query, $request, $field_map, $search_fields)` — the grid's `searchBar: true` sends a `globalSearch` param regardless, but the backend silently ignores it unless `$search_fields` lists which columns to search (e.g. `['name', 'username', 'email']` for users). Without this, typing in the search box returns unfiltered results.
- Row actions (Modifica/Elimina) are rendered by our own `render` callback on an `actions` column — plain HTML strings (an `<a>` and a `<form>` with a hidden `_method=DELETE` + `_token` from `window.CSRF_TOKEN`, exposed globally in `layouts/app.blade.php`). Not a Raccoon Tables feature — just template strings we own.
- Raccoon Tables DOM class names used by Dusk tests (stable, taken from the shipped source, not guessed): `.rt-search-bar-input` (the search box), `.rt-row` / `.rt-cell` (grid rows/cells).

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
- **Native `confirm()` dialogs (e.g. the delete buttons' `onsubmit="return confirm(...)"`) resolve immediately in this headless Chrome setup (`--headless=new`) — `waitForDialog()`/`acceptDialog()` never see an actual dialog and just time out.** Don't use Dusk's dialog-handling methods for these; just act as if the confirm always resolved to `true` and wait for the resulting navigation/text directly (see `tests/Browser/Admin/UsersTest.php`).
- When a page/grid shows a value the currently-logged-in user's own data also contains (e.g. the admin's name is always in the top navbar), scope `waitUntilMissingText()`/similar assertions to a specific container (`->within('#some-grid', fn ($el) => $el->waitUntilMissingText(...))`) — asserting against the whole page will never succeed if that same text is always present elsewhere on screen.

## known non-obvious gotchas (chronological)

1. SQLite `dropColumn` on a uniquely-indexed column requires `dropUnique` first (see migration above).
2. Pint can silently break `tests/Pest.php` if it reorders `use` past a `pest()->extend()` call — always keep imports at the top of that file, re-check after running Pint on it.
3. Dusk cannot use `:memory:` SQLite — must be a real file, shared between the test process and the served app process via a matching `.env`.
4. `php artisan serve` and the Dusk test-runner process are separate PHP processes started at different times — any env swap must happen *before* starting `serve`, not just before running `dusk`.
5. Laravel's `@can`/`can:` middleware and Just A Gate's `@can` Blade directive are NOT the same mechanism here — see "layout & menus" above. Using Laravel's `can:` middleware with a Just A Gate key always denies.
6. `Role::syncPermissions()` takes permission **keys**, not ids (unlike `User::syncRoles()`, which does accept ids) — see "admin CRUD" above.
7. Raccoon Tables' `serverAdapter` defaults to POST; our GET-only data routes need `method: 'GET'` set explicitly in every grid config, or requests 405.
8. `EloquentSource::apply()` silently returns zero rows if the request has no `limit` param — always pass `start`/`limit` when hitting a `*/data` endpoint directly (tests included).
9. Headless Chrome (`--headless=new`) resolves `confirm()` dialogs immediately — Dusk's `waitForDialog()`/`acceptDialog()` will time out waiting for something that already resolved; just wait for the resulting page change instead.
10. **Tailwind CSS and Bootstrap/Tabler both define a class literally named `.collapse`, with opposite meanings, and Tailwind's wins.** Tailwind ships a `visibility` utility `.collapse { visibility: collapse }` (table-row visibility helper); Bootstrap/Tabler use `.collapse` for collapsible sections (`.collapse:not(.show) { display: none }`, overridden to `display:flex!important` at the `lg` breakpoint for `.navbar-vertical`). With both stylesheets loaded, `display` ends up correct (Tabler's `!important` wins) but `visibility: collapse` from Tailwind is never contested — the element takes up layout space but renders with all descendants invisible. This is exactly what happened to the sidebar menu (`#sidebar-menu.navbar-collapse.collapse`): content existed in the DOM, `assertPresent()` passed, but nothing was visible to a real user. Root cause was `resources/css/app.css` importing both `@import 'tailwindcss'` and `@import '@tabler/core/dist/css/tabler.min.css'`. Fix: don't import Tailwind at all — this project uses Tabler/Bootstrap exclusively, never Tailwind utility classes. **Any Dusk assertion for on-screen navigation must use `assertVisible()`, not `assertPresent()`** — presence-only checks do not catch visibility bugs like this one (that's exactly how this bug shipped past the original test suite).
