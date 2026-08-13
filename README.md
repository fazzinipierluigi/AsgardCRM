# asgardcrm

Extensible entity/workflow CRM (dynamic entities, workflow engine, calendar, webmail, documenti, importer) as a reusable Laravel 13 package — full auth (classic + SAML + social), install/update wizard, and admin CRUD for Users/Roles/Login-providers included. Install it into an existing Laravel app, or start from [`AsgardCRM-Scaffolding`](https://github.com/fazzinipierluigi/AsgardCRM-Scaffolding) for a ready-to-run host.

This repository *is* the package — `composer.json` at its root, no monorepo wrapper. It used to be published under `fazzinipierluigi/crm-core`, nested inside the standalone AsgardCRM app; both were folded into this single package as of the `asgardcrm` rename (see `CHANGELOG.md`).

## Requirements

- PHP ^8.3
- Laravel 13 (`illuminate/support: ^13.12`)

Laravel 11/12 aren't supported — every model uses the `#[Fillable]`/`#[Hidden]` Eloquent PHP attributes, which only exist in Laravel 13. `^13.12` is also the floor that clears 3 `laravel/framework` security advisories open below that version (see `composer audit`; one, CVE-2026-48019, was never patched on the 11.x line at all).

## Installation

```bash
composer require fazzinipierluigi/asgardcrm
php artisan vendor:publish --tag=crm-config --tag=crm-migrations --tag=crm-assets
```

### The `users` table

`crm-migrations` deliberately does **not** include the 3 migrations that alter your app's own `users` table (`username`, `login_provider_id`, `phone`, `job_title`). If your `User` model doesn't already have equivalent columns:

```bash
php artisan vendor:publish --tag=crm-migrations-users
```

If it does (different names, or you're merging into an existing app), skip this tag and adapt your own schema instead — don't publish it blindly onto a `users` table that isn't yours to reshape.

### Your `User` model

The package ships its own login, install/update wizard, and admin CRUD — your host still owns the `User` model itself (concrete Eloquent model, migrations, factory), implementing this contract:

```php
use Fazzinipierluigi\AsgardCRM\Contracts\CrmUser;
use Fazzinipierluigi\AsgardCRM\Models\LoginProvider;
use Fazzinipierluigi\AsgardCRM\Models\Setting;
use Fazzinipierluigi\JustAGate\Traits\Authorizable;

class User extends Authenticatable implements CrmUser
{
    use Authorizable;

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return Setting::valueFor($this->id, $key, $default);
    }

    public function setSetting(string $key, mixed $value): void
    {
        Setting::setValue($this->id, $key, $value);
    }

    public function loginProvider(): BelongsTo
    {
        return $this->belongsTo(LoginProvider::class);
    }

    public function effectiveLoginProvider(): LoginProvider
    {
        return $this->loginProvider ?? LoginProvider::local();
    }
}
```

Point `config('crm.user_model')` (`CRM_USER_MODEL` env) at it if it isn't `App\Models\User`.

### Wire the middleware your host applies

The service provider registers `EnsureAppIsInstalled`/`EnsureAppIsUpToDate` as router aliases (`crm.installed`, `crm.up-to-date`) instead of forcing them onto every host globally — a host decides where they apply (e.g. skipped entirely in a test environment with no install wizard route to redirect to):

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->appendToGroup('web', 'crm.installed');
    $middleware->appendToGroup('web', 'crm.up-to-date');

    // The SAML ACS endpoint receives its POST straight from the IdP,
    // which never had a CSRF token from your app.
    $middleware->validateCsrfTokens(except: ['login/saml/*/acs']);
})
```

`ApplyUserPreferences` (locale-from-user-setting) is pushed onto the `web` group automatically by the provider — nothing to wire for that one.

### Publish the auth translation keys and run the migrations

```bash
php artisan vendor:publish --tag=crm-lang
php artisan migrate
```

`crm-lang` is separate/explicit for the same reason `crm-migrations-users` is: a host with its own customized `lang/en/auth.php` shouldn't have it silently overwritten.

### Demo content (optional)

14 `Fazzinipierluigi\AsgardCRM\Database\Seeders\*EntitySeeder` classes (Clienti, Fatture, Preventivi, Ticket, and so on) ship as package-owned seeders — call them from your own `DatabaseSeeder` if you want AsgardCRM's own demo entities, or skip them entirely for a blank slate.

See [`AsgardCRM-Scaffolding`](https://github.com/fazzinipierluigi/AsgardCRM-Scaffolding) for a complete, verified from-scratch install doing all of the above.

## Icons

Icons render as inline SVG (never a webfont) from the `@tabler/icons` npm package, read straight off disk — **your host app** installs it, the package doesn't ship its own copy for runtime use:

```bash
npm install @tabler/icons
```

`config('crm.icons.path')` defaults to `base_path('node_modules/@tabler/icons/icons')`; override via `CRM_ICONS_PATH` if your icons live elsewhere.

## Assets

The package ships its own **pre-built** Vite output (`public/vendor/crm/`, published by `crm-assets`) — your app's own Vite config/build is untouched, no npm dependency merging. Views load them via `@vite([...], 'vendor/crm')`. If you're developing the package itself: `npm install && npm run build` in this repo's root (or `npm run dev` for HMR), then republish `crm-assets`.

## Routes

Mounted from `AsgardCRMServiceProvider::boot()` under:

```php
Route::group([
    'prefix' => config('crm.route_prefix', ''),
    'middleware' => config('crm.route_middleware', ['web']),
], ...);
```

Both configurable via `crm.php` (or `CRM_ROUTE_PREFIX`) if you need to namespace/segregate the package's routes inside a larger app.

## Scheduled commands

Registered automatically once the app is booted (`Schedule::command(...)->everyMinute()`, so your own `schedule:run` cron picks them up — nothing extra to configure):

- `RunDueImporters`
- `RunDueWorkflows`
- `FireDueWorkflowTimers`
- `SyncCalendarConnectors`

Plus, run by hand (not scheduled):
- `BackfillInstalledEntityUpgrades` — see `docs/package-conversion/03-migrazione-moduli.md` for the entity-upgrade pattern it belongs to.
- `ResetInstallCommand` — clears the install-wizard marker, for local development.

## Testing

The package's own suite runs fully standalone (no dependency on a consuming app) via Orchestra Testbench:

```bash
composer install
vendor/bin/pest
```

CI (`.github/workflows/tests.yml`) runs it against PHP 8.3/8.4 on Laravel 13, plus `composer audit`.

## Versioning

SemVer, currently `0.x` — `1.0.0` is reserved for the point where an external app (not a sibling repo on the same disk) has installed the package from scratch via Packagist and verified it end-to-end. See `CHANGELOG.md`.
