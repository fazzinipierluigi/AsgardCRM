# Configuration

Everything lives in `config/crm.php`, published via the `crm-config` tag (see [Publishing tags](publishing-tags.md)). This page walks through every option.

## User model

```php
'user_model' => env('CRM_USER_MODEL', User::class),
```

The fully-qualified class name of the Eloquent model implementing `Fazzinipierluigi\AsgardCRM\Contracts\CrmUser`. The package never references a concrete host model class directly — it always resolves this configured class dynamically. See [The User model & authentication](user-model-and-auth.md).

## Route prefix & middleware

```php
'route_prefix' => env('CRM_ROUTE_PREFIX', ''),
'route_middleware' => ['web'],
```

Applied to the route group loaded from the package's `routes/web.php`, mounted from `AsgardCRMServiceProvider::boot()`. Use these if you need to namespace or segregate the package's routes inside a larger application.

## Version upgrade steps

```php
'upgrades' => [
    'steps' => [
        //
    ],
],
```

An ordered (ascending by version) list of `Fazzinipierluigi\AsgardCRM\Services\Upgrades\UpgradeStep` implementations. `VersionUpgradeRunner` runs each step's `->upgrade()` between the database's recorded version and the deployed code's version (`config('app.version')`) — or each step's `->downgrade()` in reverse when rolling back. Empty by default; a host registers its own steps here as it needs them.

## Entities

```php
'entities' => [
    'relatable_models' => [
        User::class => 'Utente',
    ],
],
```

Models that a "Relazione" (relation) field on a dynamic entity can point to, besides other entities. Fully-qualified class name mapped to the display label shown in the entity builder's target picker.

## Tabler Icons

```php
'icons' => [
    'path' => env('CRM_ICONS_PATH', base_path('node_modules/@tabler/icons/icons')),
    'default_variant' => 'outline',
],
```

Icons are always printed as inline SVG markup — never an icon web font — via the `icon()` helper (`src/helpers.php`) and its JS equivalent (`resources/js/icon.js`). Both read directly from the `@tabler/icons` npm package's static SVG files, so `path` assumes the host application has that package installed under its own `node_modules`. Override with `CRM_ICONS_PATH` if your icons live elsewhere. See [Assets & icons](assets-and-icons.md).

## User preferences

```php
'preferences' => [
    'date_format' => [...],
    'number_format' => [...],
    'theme' => [...],
    'theme_base' => [...],
    'theme_color' => [...],
],
```

Per-user settings (persisted in the `settings` table) exposed on the personal settings page. Each entry lists the allowed values (`value => label`) and the default applied when neither the user nor the global fallback has a value set.

- `theme_base` maps to Tabler's `data-bs-theme-base` attribute (the neutral/gray scale used for backgrounds, borders, and body text).
- `theme_color` maps to Tabler's `data-bs-theme-primary` attribute (the accent color used for links, primary buttons, active states, etc.).
- `language` is intentionally **not** defined here — its options are sourced dynamically from the `languages` table (`Fazzinipierluigi\AsgardCRM\Models\Language`) instead of a static list, so admins can add a language at runtime.

Use the `preferences()` helper (`src/helpers.php`) rather than reading `config('crm.preferences')` directly whenever you need the full preference set including language.

## Environment variables reference

| Variable | Default | Purpose |
|---|---|---|
| `CRM_USER_MODEL` | `App\Models\User` | Host's `User` model class implementing `CrmUser`. |
| `CRM_ROUTE_PREFIX` | `''` | Prefix applied to all package routes. |
| `CRM_ICONS_PATH` | `node_modules/@tabler/icons/icons` (host `base_path()`) | Where the package looks for Tabler SVG icon files. |
