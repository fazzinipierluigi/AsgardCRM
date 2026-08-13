# Installation

There are two supported ways to get AsgardCRM running: install the **package** into an existing Laravel application, or start from the **[AsgardCRM-Scaffolding](https://github.com/fazzinipierluigi/AsgardCRM-Scaffolding)** reference host if you're starting from scratch.

## Requirements

- PHP `^8.3`
- Laravel 13 (`illuminate/support: ^13.12`)

Laravel 11 and 12 are **not** supported. Every model uses the `#[Fillable]`/`#[Hidden]` Eloquent PHP attributes, which only exist starting in Laravel 13 — this was actually verified by installing against Laravel 12.61.1, where every `#[Fillable]` attribute is silently ignored and the package's own test suite drops from green to 335/375 failures (`MassAssignmentException`). `^13.12` is also the floor that clears three `laravel/framework` security advisories open below that version; one of them, CVE-2026-48019 (CRLF injection in the default email validation rule), was never patched on the 11.x line at all.

## Option A — Install into an existing Laravel app

```bash
composer require fazzinipierluigi/asgardcrm
php artisan vendor:publish --tag=crm-config --tag=crm-migrations --tag=crm-assets
```

This publishes the package configuration (`config/crm.php`), the core migrations, and the pre-built front-end assets. See [Publishing tags](publishing-tags.md) for the full list of tags and what each one does.

### 1. The `users` table

`crm-migrations` deliberately does **not** include the migrations that alter your application's own `users` table (`username`, `login_provider_id`, `phone`, `job_title`). If your `User` model doesn't already have equivalent columns:

```bash
php artisan vendor:publish --tag=crm-migrations-users
```

If it does — different column names, or you're merging into an existing app — skip this tag and adapt your own schema instead. Don't publish it blindly onto a `users` table that isn't yours to reshape.

### 2. Your `User` model

The package ships its own login, install/update wizard, and admin CRUD — but your host application still owns the `User` model itself (the concrete Eloquent model, its migration, its factory). It only ever talks to that model through the `Fazzinipierluigi\AsgardCRM\Contracts\CrmUser` interface. See [The User model & authentication](user-model-and-auth.md) for the full contract and a working example.

Point `config('crm.user_model')` (or the `CRM_USER_MODEL` env var) at your model if it isn't `App\Models\User`.

### 3. Wire the middleware your host applies

The service provider registers `EnsureAppIsInstalled` / `EnsureAppIsUpToDate` as router aliases (`crm.installed`, `crm.up-to-date`) rather than forcing them onto every host globally — you decide where they apply:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->appendToGroup('web', 'crm.installed');
    $middleware->appendToGroup('web', 'crm.up-to-date');

    // The SAML ACS endpoint receives its POST straight from the IdP,
    // which never had a CSRF token from your app.
    $middleware->validateCsrfTokens(except: ['login/saml/*/acs']);
})
```

`ApplyUserPreferences` (locale-from-user-setting) is pushed onto the `web` group automatically by the service provider — you don't need to register it yourself.

### 4. Publish translations and run migrations

```bash
php artisan vendor:publish --tag=crm-lang
php artisan migrate
```

`crm-lang` is separate and explicit for the same reason `crm-migrations-users` is: a host with its own customized `lang/en/auth.php` shouldn't have it silently overwritten.

### 5. Built-in system entities

Nothing to do here — once you complete the install wizard (see below), it seeds every built-in system entity for you: Calendario, Documenti, E-mail, and the standard CRM set (Clienti, Fornitori, Prodotti, Lead, Contatti, Opportunità, Preventivi, Ordini di acquisto/vendita, Fatture, Ticket). All fourteen `Fazzinipierluigi\AsgardCRM\Database\Seeders\*EntitySeeder` classes are `is_system => true` and called by `ApplicationInstaller::install()` in an order that respects their Relation-field dependencies. See [Modules overview](modules-overview.md) for what each one covers.

If you're bootstrapping the package **without** the install wizard, call the same seeder classes yourself in the same order — check `ApplicationInstaller::install()`'s call sequence in the package source.

### 6. Icons

Icons render as inline SVG (never a web font) straight from the `@tabler/icons` npm package, which **your host application** installs — the package doesn't ship its own runtime copy:

```bash
npm install @tabler/icons
```

See [Assets & icons](assets-and-icons.md) for path configuration.

## Option B — Start from AsgardCRM-Scaffolding

[`AsgardCRM-Scaffolding`](https://github.com/fazzinipierluigi/AsgardCRM-Scaffolding) is a ready-to-run reference Laravel 13 host that already requires this package and has no Auth/Admin/Install code of its own — everything comes from AsgardCRM itself. It's the fastest path to a working install and doubles as the package's own end-to-end verification target.

```bash
git clone https://github.com/fazzinipierluigi/AsgardCRM-Scaffolding.git
cd AsgardCRM-Scaffolding
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Scaffolding ships with only a `User` model and the bootstrap wiring (middleware groups, providers) already in place — steps 1–4 from Option A are done for you. Refer to its own `README.md` for exact, up-to-date setup instructions and any host-specific configuration (`.env` values, database setup, etc.), since those can evolve independently of this package.

If you're developing AsgardCRM itself locally, Scaffolding also doubles as your test consumer: it requires the package via a `path` repository pointed at a sibling `../AsgardCRM` checkout, so local package changes are picked up with `composer update fazzinipierluigi/asgardcrm`. See [The two-repo workflow](two-repo-workflow.md).

## Next steps

- [Configuration](configuration.md) — walk through every `config/crm.php` option.
- [The User model & authentication](user-model-and-auth.md) — the `CrmUser` contract and login providers.
- [Modules overview](modules-overview.md) — a tour of entities, workflows, calendar, documents, webmail, and more.
