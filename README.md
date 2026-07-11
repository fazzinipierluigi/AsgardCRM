# AsgardCRM

CRM application built on Laravel 13.

## Stack

- **[Laravel](https://laravel.com)** 13 — application framework.
- **[Tabler](https://tabler.io)** — UI template (Bootstrap-based), installed via `@tabler/core` (npm).
- **[Raccoon Tables](https://github.com/fazzinipierluigi/raccoon-tables)** — frontend datatable/grid component, installed via `raccoon-tables` (npm).
- **[Laraccoon Datasource](https://github.com/fazzinipierluigi/laraccoon_datasource)** — server-side handler that turns Raccoon Tables requests into filtered/paginated Eloquent responses, installed via `fazzinipierluigi/laraccoon_datasource` (composer).
- **[Just A Gate](https://github.com/fazzinipierluigi/just-a-gate)** — ACL (roles & permissions), installed via `fazzinipierluigi/just-a-gate` (composer).
- **[Pest](https://pestphp.com)** — feature/unit testing.
- **[Laravel Dusk](https://laravel.com/docs/dusk)** — browser testing.

For deep low-level/architectural documentation meant for AI agents working on this codebase, see [DOCUMENTATION.md](DOCUMENTATION.md). For API/webservice/SDK reference, see [SDK.md](SDK.md).

## Installation

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate

php artisan vendor:publish --provider="Fazzinipierluigi\JustAGate\JustAGateServiceProvider"
php artisan permission:init

npm run build
```

`permission:init` runs Just A Gate's migrations (`roles`, `permissions`, `permission_role`, `role_user`) and creates the built-in Administrator role (`slug: admin`).

Seed a test user (username `test`, password `password`):

```bash
php artisan db:seed
```

Run the app:

```bash
composer dev
```

## Updating

```bash
composer update
npm update

php artisan migrate

npm run build
```

Re-run `php artisan vendor:publish --provider="Fazzinipierluigi\JustAGate\JustAGateServiceProvider" --force` after a Just A Gate update if `config/acl.php` gained new keys.

## Authentication

Login is **username + password** (not email). Email is still stored on the `users` table but is not used to authenticate.

- Login: `GET/POST /login`
- Logout: `POST /logout`
- Dashboard (auth-protected): `GET /dashboard`

Login attempts are rate-limited (5 attempts per username+IP, then a cooldown) — see `app/Http/Requests/Auth/LoginRequest.php`.

## Permissions (Just A Gate)

Roles and permissions are managed by Just A Gate. The `User` model uses the `Authorizable` trait (`app/Models/User.php`).

Creating a new permission:

```bash
php artisan permission:create {key} {name}
# e.g. php artisan permission:create contacts.manage "Manage Contacts"
```

Assigning a permission to a role:

```bash
php artisan permission:assign {key} {role}
# e.g. php artisan permission:assign contacts.manage admin
```

Other useful commands: `php artisan permission:import` (regenerates permissions from `config/acl.php` — custom keys, route-based permissions, role-based permissions — and applies the assignments/cleanup declared there), `php artisan permission:init` (first-time setup, creates the `admin` role). The built-in `admin` role has full access.

To gate a Livewire component or method behind a permission, use the `#[RequiresPermission('key')]` attribute (class-level = checked every lifecycle, method-level = checked only on that action).

## Testing

**Every feature and technical procedure must ship with exhaustive Pest and/or Dusk tests before it's considered done.**

- Pest (feature/unit, fast, no browser):

  ```bash
  composer test
  ```

- Dusk (real browser, for UI/JS-driven flows — clicks, forms, datatable interactions, ACL-gated UI):

  ```bash
  composer test:dusk
  ```

  Requires Chromium/Chrome installed on the system. The script (`scripts/dusk.sh`) swaps in `.env.dusk.local` (a dedicated SQLite file at `database/testing.sqlite`, since SQLite `:memory:` can't be shared with the browser's separate process), migrates it fresh, starts `php artisan serve` on `127.0.0.1:8000`, runs `php artisan dusk`, then restores your `.env` and stops the server automatically — even on failure.

- Both:

  ```bash
  composer test:all
  ```

Dusk browser tests live in `tests/Browser`; Pest feature/unit tests live in `tests/Feature` and `tests/Unit`.
