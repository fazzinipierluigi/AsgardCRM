# Tag di pubblicazione

Tutti questi tag sono registrati da `AsgardCRMServiceProvider` e pubblicati con `php artisan vendor:publish --tag=<tag>`. Alcuni tag sono volutamente **esclusi** da un'installazione tipica — vedi le note sotto.

| Tag | Cosa pubblica | Incluso in un'installazione tipica? |
|---|---|---|
| `crm-config` | `config/crm.php` | Sì |
| `crm-migrations` | Migration principali del package (entità, workflow, calendario, documenti, mail, importer, impostazioni, traduzioni, lingue, provider di login) | Sì |
| `crm-migrations-users` | Le 3 migration che alterano la tabella `users` della tua app (`username`, `login_provider_id`, `phone`, `job_title`) | **No** — solo opt-in esplicito |
| `crm-assets` | Output Vite pre-compilato in `public/vendor/crm/`, più il marchio e il set favicon di AsgardCRM nella root pubblica dell'host (vedi [Asset e icone](assets-and-icons.md#asset-di-brand)) | Sì |
| `crm-views` | Viste Blade del package (namespace `crm::`) | No — solo se devi sovrascrivere una vista |
| `crm-lang` | Le 3 chiavi di traduzione personalizzate `auth.provider_*` | **No** — solo opt-in esplicito |

## Perché alcuni tag sono opt-in

- **`crm-migrations-users`** altera una tabella di cui lo schema appartiene alla tua applicazione host. Un host con propri nomi di colonna in `users`, o che integra AsgardCRM in un'app che ha già colonne equivalenti, non deve vederselo applicare silenziosamente.
- **`crm-lang`** altrimenti sovrascriverebbe silenziosamente un `lang/en/auth.php` personalizzato dell'host.

Entrambi sono pubblicati in modo esplicito, di proposito, così la scelta spetta a te per la tua applicazione.

## Ripubblicare dopo un aggiornamento

`vendor:publish` non sovrascrive mai i file già esistenti a meno che tu non passi `--force`. Quando aggiorni il package e i file sottostanti di un tag sono cambiati (una nuova migration, un asset compilato aggiornato), riesegui il tag specifico che ti serve — ad esempio, dopo un aggiornamento del package che ha toccato gli asset front-end compilati:

```bash
php artisan vendor:publish --tag=crm-assets --force
```

Fai attenzione con `--force` su `crm-config`, `crm-views` o `crm-lang` se hai personalizzato le copie pubblicate — sovrascriverà le tue modifiche.
