# crm-core

Core package (dynamic entities, workflows, calendar, mail, documenti, importer) behind [AsgardCRM](https://github.com/fazzinipierluigi/AsgardCRM), extracted so it can be installed into any Laravel 13 application.

## Requirements

- PHP ^8.3
- Laravel 13 (`illuminate/support: ^13.12`)

Laravel 11/12 aren't supported — every model uses the `#[Fillable]`/`#[Hidden]` Eloquent PHP attributes, which only exist in Laravel 13. `^13.12` is also the floor that clears 3 `laravel/framework` security advisories open below that version (see `composer audit`; one, CVE-2026-48019, was never patched on the 11.x line at all).

## Installation

```bash
composer require fazzinipierluigi/crm-core
php artisan vendor:publish --tag=crm-config --tag=crm-migrations --tag=crm-assets
```

### The `users` table

`crm-migrations` deliberately does **not** include the 3 migrations that alter your app's own `users` table (`username`, `login_provider_id`, `phone`, `job_title`). If your `User` model doesn't already have equivalent columns:

```bash
php artisan vendor:publish --tag=crm-migrations-users
```

If it does (different names, or you're merging into an existing app), skip this tag and adapt your own schema instead — don't publish it blindly onto a `users` table that isn't yours to reshape.

### Your `User` model

```php
use Fazzinipierluigi\CrmCore\Contracts\CrmUser;
use Fazzinipierluigi\CrmCore\Models\Setting;
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
}
```

Point `config('crm.user_model')` (`CRM_USER_MODEL` env) at it if it isn't `App\Models\User`.

### Run the migrations

```bash
php artisan migrate
```

See `starter-kit/` in this repo for a complete, verified from-scratch install (minimal auth, seeded admin, adapted layouts) — `docs/package-conversion/04-starter-kit.md` has the full walkthrough.

## Icons

Icons render as inline SVG (never a webfont) from the `@tabler/icons` npm package, read straight off disk — **your host app** installs it, the package doesn't ship its own copy for runtime use:

```bash
npm install @tabler/icons
```

`config('crm.icons.path')` defaults to `base_path('node_modules/@tabler/icons/icons')`; override via `CRM_ICONS_PATH` if your icons live elsewhere.

## Assets

The package ships its own **pre-built** Vite output (`public/vendor/crm/`, published by `crm-assets`) — your app's own Vite config/build is untouched, no npm dependency merging. Views load them via `@vite([...], 'vendor/crm')`. If you're developing the package itself: `npm install && npm run build` inside `packages/fazzinipierluigi/crm-core/` (or `npm run dev` for HMR), then republish `crm-assets`.

## Routes

Mounted from `CrmServiceProvider::boot()` under:

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

Plus `BackfillInstalledEntityUpgrades`, run by hand during an upgrade (not scheduled) — see `docs/package-conversion/03-migrazione-moduli.md` for the entity-upgrade pattern it belongs to.

## Testing

The package's own suite runs fully standalone (no dependency on a consuming app) via Orchestra Testbench:

```bash
cd packages/fazzinipierluigi/crm-core
composer install
vendor/bin/pest
```

CI (`.github/workflows/crm-core-tests.yml`) runs it against PHP 8.3/8.4, plus `composer audit`.

## Versioning

SemVer, currently `0.x` — `1.0.0` is reserved for the point where an external app (not this monorepo) has installed the package from scratch via Packagist and verified it end-to-end. See `CHANGELOG.md`.
