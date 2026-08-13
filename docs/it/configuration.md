# Configurazione

Tutto risiede in `config/crm.php`, pubblicato tramite il tag `crm-config` (vedi [Tag di pubblicazione](publishing-tags.md)). Questa pagina illustra ogni opzione.

## Modello User

```php
'user_model' => env('CRM_USER_MODEL', User::class),
```

Il nome completo della classe del modello Eloquent che implementa `Fazzinipierluigi\AsgardCRM\Contracts\CrmUser`. Il package non fa mai riferimento diretto a una classe modello concreta dell'host — risolve sempre dinamicamente questa classe configurata. Vedi [Modello User e autenticazione](user-model-and-auth.md).

## Prefisso rotte & middleware

```php
'route_prefix' => env('CRM_ROUTE_PREFIX', ''),
'route_middleware' => ['web'],
```

Applicati al gruppo di rotte caricato dal `routes/web.php` del package, montato da `AsgardCRMServiceProvider::boot()`. Usali se devi segmentare o isolare le rotte del package all'interno di un'applicazione più grande.

## Passi di aggiornamento versione

```php
'upgrades' => [
    'steps' => [
        //
    ],
],
```

Un elenco ordinato (per versione crescente) di implementazioni di `Fazzinipierluigi\AsgardCRM\Services\Upgrades\UpgradeStep`. `VersionUpgradeRunner` esegue l'`->upgrade()` di ogni passo tra la versione registrata nel database e la versione del codice distribuito (`config('app.version')`) — oppure il `->downgrade()` di ogni passo, in ordine inverso, durante un rollback. Vuoto di default; un host registra qui i propri passi man mano che gli servono.

## Entità

```php
'entities' => [
    'relatable_models' => [
        User::class => 'Utente',
    ],
],
```

Modelli verso cui un campo "Relazione" di un'entità dinamica può puntare, oltre ad altre entità. Nome completo della classe mappato all'etichetta mostrata nel selettore del builder entità.

## Icone Tabler

```php
'icons' => [
    'path' => env('CRM_ICONS_PATH', base_path('node_modules/@tabler/icons/icons')),
    'default_variant' => 'outline',
],
```

Le icone vengono sempre stampate come markup SVG inline — mai come web font — tramite l'helper `icon()` (`src/helpers.php`) e il suo equivalente JS (`resources/js/icon.js`). Entrambi leggono direttamente i file SVG statici del package npm `@tabler/icons`, quindi `path` presuppone che l'applicazione host abbia quel package installato nel proprio `node_modules`. Sovrascrivi con `CRM_ICONS_PATH` se le tue icone si trovano altrove. Vedi [Asset e icone](assets-and-icons.md).

## Preferenze utente

```php
'preferences' => [
    'date_format' => [...],
    'number_format' => [...],
    'theme' => [...],
    'theme_base' => [...],
    'theme_color' => [...],
],
```

Impostazioni per utente (persistite nella tabella `settings`) esposte nella pagina delle impostazioni personali. Ogni voce elenca i valori ammessi (`valore => etichetta`) e il default applicato quando né l'utente né il fallback globale hanno un valore impostato.

- `theme_base` corrisponde all'attributo Tabler `data-bs-theme-base` (la scala neutra/grigia usata per sfondi, bordi e testo).
- `theme_color` corrisponde all'attributo Tabler `data-bs-theme-primary` (il colore d'accento usato per link, pulsanti primari, stati attivi, ecc.).
- `language` non è definita qui volutamente — le sue opzioni provengono dinamicamente dalla tabella `languages` (`Fazzinipierluigi\AsgardCRM\Models\Language`) invece che da un elenco statico, così un amministratore può aggiungere una lingua a runtime.

Usa l'helper `preferences()` (`src/helpers.php`) invece di leggere direttamente `config('crm.preferences')` ogni volta che ti serve l'intero set di preferenze, lingua inclusa.

## Riferimento variabili d'ambiente

| Variabile | Default | Scopo |
|---|---|---|
| `CRM_USER_MODEL` | `App\Models\User` | Classe del modello `User` dell'host che implementa `CrmUser`. |
| `CRM_ROUTE_PREFIX` | `''` | Prefisso applicato a tutte le rotte del package. |
| `CRM_ICONS_PATH` | `node_modules/@tabler/icons/icons` (`base_path()` dell'host) | Percorso in cui il package cerca i file SVG delle icone Tabler. |
