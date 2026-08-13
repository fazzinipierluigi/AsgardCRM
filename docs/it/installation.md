# Installazione

Esistono due modi supportati per avere AsgardCRM funzionante: installare il **package** in un'applicazione Laravel esistente, oppure partire dall'host di riferimento **[AsgardCRM-Scaffolding](https://github.com/fazzinipierluigi/AsgardCRM-Scaffolding)** se stai iniziando da zero.

## Requisiti

- PHP `^8.3`
- Laravel 13 (`illuminate/support: ^13.12`)

Laravel 11 e 12 **non** sono supportati. Ogni modello usa gli attributi PHP Eloquent `#[Fillable]`/`#[Hidden]`, disponibili solo a partire da Laravel 13 — questo è stato effettivamente verificato installando il package su Laravel 12.61.1, dove ogni attributo `#[Fillable]` viene ignorato silenziosamente e la suite di test del package passa da verde a 335/375 fallimenti (`MassAssignmentException`). `^13.12` è anche la soglia minima che risolve tre advisory di sicurezza di `laravel/framework` aperti nelle versioni precedenti; una di esse, la CVE-2026-48019 (CRLF injection nella regola di validazione email di default), non è mai stata corretta sulla linea 11.x.

## Opzione A — Installazione in un'app Laravel esistente

```bash
composer require fazzinipierluigi/asgardcrm
php artisan vendor:publish --tag=crm-config --tag=crm-migrations --tag=crm-assets
```

Questo pubblica la configurazione del package (`config/crm.php`), le migration principali e gli asset front-end già compilati. Vedi [Tag di pubblicazione](publishing-tags.md) per l'elenco completo dei tag e cosa fa ciascuno.

### 1. La tabella `users`

`crm-migrations` **non** include volutamente le migration che alterano la tabella `users` della tua applicazione (`username`, `login_provider_id`, `phone`, `job_title`). Se il tuo modello `User` non ha già colonne equivalenti:

```bash
php artisan vendor:publish --tag=crm-migrations-users
```

Se le ha già — nomi di colonna diversi, oppure stai integrando AsgardCRM in un'app che ha già colonne equivalenti — salta questo tag e adatta il tuo schema. Non pubblicarlo alla cieca su una tabella `users` che non è tua da rimodellare.

### 2. Il tuo modello `User`

Il package fornisce login, wizard di installazione/aggiornamento e CRUD amministrativi propri — ma la tua applicazione host resta comunque proprietaria del modello `User` (il modello Eloquent concreto, la sua migration, la sua factory). Il package parla con quel modello solo attraverso l'interfaccia `Fazzinipierluigi\AsgardCRM\Contracts\CrmUser`. Vedi [Modello User e autenticazione](user-model-and-auth.md) per il contratto completo e un esempio funzionante.

Punta `config('crm.user_model')` (o la variabile d'ambiente `CRM_USER_MODEL`) verso il tuo modello se non è `App\Models\User`.

### 3. Collega i middleware nel tuo host

Il service provider registra `EnsureAppIsInstalled` / `EnsureAppIsUpToDate` come alias di rotta (`crm.installed`, `crm.up-to-date`) invece di forzarli globalmente su ogni host — decidi tu dove applicarli:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->appendToGroup('web', 'crm.installed');
    $middleware->appendToGroup('web', 'crm.up-to-date');

    // L'endpoint ACS di SAML riceve il suo POST direttamente dall'IdP,
    // che non ha mai avuto un token CSRF della tua app.
    $middleware->validateCsrfTokens(except: ['login/saml/*/acs']);
})
```

`ApplyUserPreferences` (locale derivata dalle impostazioni utente) viene aggiunto automaticamente al gruppo `web` dal service provider — non serve registrarlo manualmente.

### 4. Pubblica le traduzioni ed esegui le migration

```bash
php artisan vendor:publish --tag=crm-lang
php artisan migrate
```

`crm-lang` è separato ed esplicito per lo stesso motivo di `crm-migrations-users`: un host con un proprio `lang/en/auth.php` personalizzato non deve vederselo sovrascritto silenziosamente.

### 5. Contenuti dimostrativi (opzionale)

Quattordici classi seeder `Fazzinipierluigi\AsgardCRM\Database\Seeders\*EntitySeeder` (Clienti, Fatture, Preventivi, Ticket, e così via) sono incluse nel package. Richiamale dal tuo `DatabaseSeeder` se vuoi le entità dimostrative di AsgardCRM, oppure saltale del tutto per partire da una base pulita. Non esiste un seeder lato package che le esegua automaticamente.

### 6. Icone

Le icone vengono renderizzate come SVG inline (mai un web font) direttamente dal package npm `@tabler/icons`, che **la tua applicazione host** installa — il package non ne include una copia propria a runtime:

```bash
npm install @tabler/icons
```

Vedi [Asset e icone](assets-and-icons.md) per la configurazione del percorso.

## Opzione B — Partire da AsgardCRM-Scaffolding

[`AsgardCRM-Scaffolding`](https://github.com/fazzinipierluigi/AsgardCRM-Scaffolding) è un host Laravel 13 di riferimento, pronto all'uso, che richiede già questo package e non ha codice proprio di Auth/Admin/Install — tutto proviene da AsgardCRM stesso. È la via più rapida per un'installazione funzionante e funge anche da bersaglio di verifica end-to-end del package.

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

Scaffolding include già solo un modello `User` e il collegamento bootstrap (gruppi middleware, provider) — i passaggi 1-4 dell'Opzione A sono già fatti per te. Fai riferimento al suo `README.md` per le istruzioni di configurazione esatte e aggiornate, ed eventuali impostazioni specifiche dell'host (valori `.env`, configurazione database, ecc.), poiché possono evolvere indipendentemente da questo package.

Se stai sviluppando AsgardCRM stesso in locale, Scaffolding funge anche da consumer di test: richiede il package tramite un repository `path` puntato su un checkout gemello `../AsgardCRM`, quindi le modifiche locali al package vengono recepite con `composer update fazzinipierluigi/asgardcrm`. Vedi [Il workflow a due repository](two-repo-workflow.md).

## Prossimi passi

- [Configurazione](configuration.md) — tutte le opzioni di `config/crm.php`, una per una.
- [Modello User e autenticazione](user-model-and-auth.md) — il contratto `CrmUser` e i provider di login.
- [Panoramica dei moduli](modules-overview.md) — un tour di entità, workflow, calendario, documenti, webmail e altro.
