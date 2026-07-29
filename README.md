<p align="center">
  <img src="public/logo.svg" alt="AsgardCRM" width="120">
</p>

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

## Settings (key/value)

The `settings` table stores arbitrary `key => value` pairs, each either **global** (`user_id` null) or scoped to a specific user. Resolution order when reading: user value → global value → the default you pass in.

```php
$user->getSetting('theme', 'light');   // read (user, then global, then default)
$user->setSetting('theme', 'dark');    // write, scoped to this user
Setting::setValue(null, 'theme', 'dark'); // write a global default for everyone
```

Per-user preferences exposed today on the personal settings page (`/settings`): **formato data**, **lingua**, **formato numeri**, **tema** (chiaro/scuro). Allowed values/labels/defaults for each live in `config/preferences.php` — add a new preference by adding a key there plus a `<select>` in `resources/views/settings/edit.blade.php` (the form loops over `config('preferences')` automatically).

The `theme` preference drives `data-bs-theme` on `<html>` (`resources/views/layouts/app.blade.php`); `language` is applied every request by `App\Http\Middleware\ApplyUserPreferences` (registered globally on the `web` middleware group). The same `theme` preference also sets `dark: true/false` on every Raccoon Tables grid, so admin datatables match the user's chosen theme.

## Translations (database-backed)

The `translations` table stores `(key, language) => value` triples (unique per key+language pair) — a simple alternative to Laravel's file-based translations, editable at runtime from the admin panel.

```php
t('dashboard.welcome');                       // resolves using the current user's language, falling back to APP_LOCALE
t('dashboard.welcome', [], 'en');             // force a specific language
t('settings.greeting', ['name' => $user->name]); // :name-style placeholder replacement, like trans()
```

Resolution order: the authenticated user's `language` preference (see Settings above) → `config('app.locale')` (`APP_LOCALE` in `.env`) → the key itself, unchanged, if no translation exists in either language (same convention as Laravel's own missing-translation behavior). Guests always use `APP_LOCALE`.

Manage translations from `/admin/translations` — one row per **key**, one column per available language (pivoted view, not a flat key+language+value list). Click **Modifica** on a key to edit every language's value together in one form (leave a language blank to remove that language's row for the key); click **Nuova traduzione** to add a brand-new key with as many language values as you want to fill in up front. **Elimina** removes the key entirely, across every language.

**Languages are managed at runtime, not hardcoded in config** — `/admin/languages` (Utenti/Ruoli/Traduzioni/Lingue in the admin menu) lets you add a new language (code + display name), which immediately becomes available both as a translation column and as an option on the personal "language" preference. A language can't be deleted while any translation still uses it. `it`/`en` ship as the two default languages (`LanguageSeeder`).

Every Blade view in this app uses `t('String')` instead of Laravel's `__('String')`/`@lang` for UI text — the string itself is the lookup key, same convention Laravel's own `__()` uses when no translation file matches. Reserve `__()`/`trans()` for anything that must stay tied to Laravel's own file-based translations (validation messages, framework/package strings); use `t()` for everything you write in this app's own views.

**Installation seeds a full set of translations automatically** — `php artisan db:seed` runs `LanguageSeeder` (it/en) then `TranslationSeeder`, which bulk-imports the `it`/`en` value for every UI string currently used in the app. Both are safe to re-run: existing rows are updated in place, not duplicated. **Whenever you add a new `t('...')` call in a view, add its `it`/`en` pair to `database/seeders/TranslationSeeder.php`'s `$strings` array AND run `php artisan db:seed --class=TranslationSeeder` against the live database** — this is a standing project rule, not just an install-time step, so nobody ends up seeing a raw untranslated key.

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

Other useful commands: `php artisan permission:import` (regenerates permissions from `config/acl.php` — custom keys, route-based permissions, role-based permissions — and applies the assignments/cleanup declared there), `php artisan permission:init` (first-time setup, creates the `admin` role — running it again is a no-op if the role already exists, e.g. via `db:seed`). The built-in `admin` role has full access.

From the UI: on `/admin/roles`, each row has a dedicated **Permessi** action (separate from Modifica) that opens a checklist of every permission grouped by resource — this is the only place permissions are assigned to a role; the role create/edit form only handles name/slug. `roles.slug` has a database-level unique constraint (in addition to form validation), so a role can never be silently duplicated.

To gate a Livewire component or method behind a permission, use the `#[RequiresPermission('key')]` attribute (class-level = checked every lifecycle, method-level = checked only on that action).

## Interfaccia

Layout applicativo unico (`resources/views/layouts/app.blade.php`): sidebar verticale fissa (dark) + navbar superiore fissa, contenuto in un `container-fluid`. Due varianti di menu, entrambe estendono lo shell:

- **`layouts.base`** — menu utente standard: voce "Dashboard", le entità installate mostrate in menu (ordine e visibilità configurabili, vedi sotto) e, ancorata in basso, la voce "Amministrazione" — visibile solo con il permesso `admin.access` (bypassato automaticamente da qualunque ruolo con `is_admin`).
- **`layouts.admin`** — menu dell'area di amministrazione: Utenti, Ruoli, Traduzioni, Lingue, Entità, Menù, più il link per tornare alla dashboard.

Il dropdown utente (in alto a destra) mostra nome e ruolo/i, e contiene i link a "Impostazioni" (`/settings`, modifica nome/email/password) e "Logout". A sinistra del dropdown utente compaiono le icone di **accesso rapido** (vedi sotto), poi il dropdown notifiche (placeholder, altre icone verranno aggiunte in seguito).

### Gestione del menù

`/admin/menu` (voce "Menù" nell'area di amministrazione) permette di configurare, per ogni entità installata (esclusa quella calendario, che ha la propria voce fissa):

- se compare direttamente nel menu principale o viene raggruppata sotto la voce collassabile **"Altre entità"**, ancorata in basso sopra ad "Amministrazione" (si espande automaticamente quando si sta navigando una delle entità che contiene);
- se compare come icona di **accesso rapido** nella topbar (solo icona, con tooltip al passaggio del mouse) — al click apre l'entità in uno sheet che scorre da destra e copre l'area di contenuto (lasciando visibile la sidebar), senza cambiare pagina: chiudendolo si ritrova la pagina sottostante esattamente come prima;
- l'ordine delle voci nel menu principale e nell'area di accesso rapido, tramite drag and drop.

Le modifiche si salvano con un unico form (`admin.menu.update`); l'ordine tra "Menu principale" e "Altre entità" si scambia con il pulsante di spostamento su ciascuna voce (non via drag, seguendo la stessa convenzione dell'Entity Builder — il drag riordina solo all'interno della stessa lista), mentre l'accesso rapido si attiva/disattiva con l'icona a stella.

### Area di amministrazione

`/admin/users`, `/admin/roles`, `/admin/translations`, `/admin/languages` — CRUD per utenti, ruoli, traduzioni e lingue, con liste server-side (Raccoon Tables + Laraccoon Datasource) e form Tabler. Non esiste una pagina/CRUD dedicata ai permessi: le chiavi permesso si creano via CLI (`permission:create`/`permission:import`, vedi sopra) e si assegnano a un ruolo dalla schermata Ruoli. Regole particolari:

- un utente non può eliminare se stesso;
- un ruolo di sistema (`is_system`, es. `admin`) non può essere eliminato;
- il ruolo **admin** (`is_admin`) non può essere modificato né eliminato, e non gli si possono assegnare permessi (ha già accesso completo) — le voci Modifica/Permessi non compaiono nemmeno in tabella per quel ruolo, e le rotte sono bloccate anche accedendovi direttamente;
- lo slug di un nuovo ruolo si genera automaticamente dal nome (con suffisso numerico se già esistente) — non è richiesto in fase di creazione, resta modificabile in seguito dalla modifica ruolo (tranne per l'admin, vedi sopra);
- i permessi da assegnare a un ruolo si scelgono per **chiave** (`key`), non per ID — vedi [DOCUMENTATION.md](DOCUMENTATION.md) per il perché.

Ogni rotta admin è protetta dal middleware `acl` di Just A Gate, che deriva automaticamente la permission key da controller/metodo (es. `Admin\UserController@index` → `user.index`). Per dare accesso a un ruolo non-admin a una singola risorsa, assegna le chiavi `{risorsa}.index`, `.data`, `.create`, `.store`, `.edit`, `.update`, `.destroy` (es. `user.index`, `user.data`, ...) via `php artisan permission:assign`.

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
