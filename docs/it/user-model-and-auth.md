# Modello User e autenticazione

## Il contratto `CrmUser`

`App\Models\User` (o come lo chiama un host) resta sempre nell'applicazione consumer — **non** è mai di proprietà del package. AsgardCRM parla con quel modello solo tramite `config('crm.user_model')` e l'interfaccia `Fazzinipierluigi\AsgardCRM\Contracts\CrmUser` (`src/Contracts/CrmUser.php`).

Un esempio funzionante:

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

Punta `config('crm.user_model')` (env `CRM_USER_MODEL`) verso la tua classe se non è `App\Models\User`.

Il contratto viene esteso solo quando un caso d'uso reale e concreto richiede un nuovo metodo — `effectiveLoginProvider()` è stato aggiunto quando i controller di Auth/Admin/Install hanno avuto bisogno di risolvere il provider di login di un utente senza mai fare riferimento a una classe concreta `App\Models\User`.

## Provider di login

L'autenticazione è unificata dietro un unico modello e un'astrazione `LoginProvider`, con supporto per:

- **Login classico** — username/password, gestito da `AuthenticatedSessionController`.
- **SAML** — tramite `onelogin/php-saml`, gestito da `SamlLoginController`. L'endpoint ACS di SAML (`login/saml/{provider:slug}/acs`) riceve il suo POST direttamente dall'Identity Provider, che non ha mai avuto un token CSRF della tua app — va escluso esplicitamente:

  ```php
  $middleware->validateCsrfTokens(except: ['login/saml/*/acs']);
  ```

- **Social login** — tramite `laravel/socialite`, gestito da `SocialLoginController` (`login/{provider:slug}/redirect` e `.../callback`).
- **LDAP** — tramite `directorytree/ldaprecord-laravel`.

Ogni provider configurato viene gestito tramite il CRUD Admin (`Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin\LoginProviderController`) — nessuna configurazione di provider risiede in `config/crm.php` stesso.

## I middleware `EnsureAppIsInstalled` / `EnsureAppIsUpToDate`

Sono registrati come alias di rotta (`crm.installed`, `crm.up-to-date`), **non** applicati automaticamente. Un host consumer deve aggiungerli esplicitamente al proprio gruppo middleware `web` in `bootstrap/app.php` — governano l'intera applicazione host, comprese le rotte definite dall'host al di fuori del package, quindi forzarli globalmente dal service provider renderebbe impossibile per un'app di test in stile Testbench (o qualsiasi host senza una rotta per il wizard di installazione) fare opt-out.

`ApplyUserPreferences` (locale derivata dalle impostazioni utente), al contrario, **viene** aggiunto automaticamente al gruppo `web` da `AsgardCRMServiceProvider::boot()` — non registrarlo una seconda volta nel `bootstrap/app.php` dell'host.

## Wizard di installazione/aggiornamento

`InstallController` e `UpdateController` guidano un host nuovo attraverso la configurazione al primo avvio e, nei deploy successivi, attraverso ogni `UpgradeStep` registrato (vedi [Configurazione](configuration.md#passi-di-aggiornamento-versione)). Il wizard è la destinazione verso cui `crm.installed`/`crm.up-to-date` reindirizzano un host non configurato o non aggiornato.
