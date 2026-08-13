# The User model & authentication

## The `CrmUser` contract

`App\Models\User` (or whatever a host names it) always stays in the consuming application — it is **never** package-owned. AsgardCRM only ever talks to it through `config('crm.user_model')` and the `Fazzinipierluigi\AsgardCRM\Contracts\CrmUser` interface (`src/Contracts/CrmUser.php`).

A working example:

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

Point `config('crm.user_model')` (`CRM_USER_MODEL` env) at your class if it isn't `App\Models\User`.

The contract is extended only when a real, concrete call site needs a new method — `effectiveLoginProvider()` was added when the Auth/Admin/Install controllers needed to resolve a user's login provider without ever referencing a concrete `App\Models\User` class.

## Login providers

Authentication is unified behind a single `LoginProvider` model and abstraction, supporting:

- **Classic login** — username/password, handled by `AuthenticatedSessionController`.
- **SAML** — via `onelogin/php-saml`, handled by `SamlLoginController`. The SAML ACS endpoint (`login/saml/{provider:slug}/acs`) receives its POST straight from the Identity Provider, which never had a CSRF token from your app — exempt it explicitly:

  ```php
  $middleware->validateCsrfTokens(except: ['login/saml/*/acs']);
  ```

- **Social login** — via `laravel/socialite`, handled by `SocialLoginController` (`login/{provider:slug}/redirect` and `.../callback`).
- **LDAP** — via `directorytree/ldaprecord-laravel`.

Each configured provider is managed through the Admin CRUD (`Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin\LoginProviderController`) — no provider configuration lives in `config/crm.php` itself.

## The `EnsureAppIsInstalled` / `EnsureAppIsUpToDate` middleware

These are registered as router aliases (`crm.installed`, `crm.up-to-date`), **not** auto-applied. A consuming host must explicitly add them to its own `bootstrap/app.php`'s `web` middleware group — they gate the host's entire application, including host-defined routes outside this package, so forcing them on globally from the service provider would make it impossible for a Testbench-style test app (or any host without an install wizard route) to opt out.

`ApplyUserPreferences` (locale-from-user-setting), by contrast, **is** auto-pushed onto the `web` group by `AsgardCRMServiceProvider::boot()` — don't double-register it in a host's `bootstrap/app.php`.

## Install / Update wizard

`InstallController` and `UpdateController` guide a fresh host through first-run setup and, on later deploys, through any registered `UpgradeStep`s (see [Configuration](configuration.md#version-upgrade-steps)). The wizard is what `crm.installed`/`crm.up-to-date` redirect an unconfigured or out-of-date host into.
