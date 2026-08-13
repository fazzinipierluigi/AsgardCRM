# Il workflow a due repository

Questa pagina è per chi sviluppa AsgardCRM **stesso**, non solo per chi lo utilizza.

AsgardCRM viene sviluppato insieme a un'app consumer di riferimento, **[AsgardCRM-Scaffolding](https://github.com/fazzinipierluigi/AsgardCRM-Scaffolding)** — attesa come directory gemella, `../AsgardCRM-Scaffolding` rispetto a questo repository. Richiede questo package tramite un repository Composer di tipo `path` (che punta a `../AsgardCRM`) e non ha codice proprio di Auth/Admin/Install; tutto proviene da questo package.

## Perché un secondo repository

La suite di test del package stesso (Orchestra Testbench) verifica il package in isolamento, ma solo un *host di riferimento* può individuare i bug che compaiono soltanto quando il package viene realmente consumato da un'applicazione Laravel reale — conflitti di routing, percorsi degli asset pubblicati, merge della configurazione, un gruppo middleware collegato diversamente rispetto a un'app di test sintetica. Questo progetto ha una storia documentata proprio di questo tipo di bug sfuggiti a una suite di package isolata.

## Il flusso di lavoro

Quando modifichi codice del package che influisce sul comportamento visibile dall'host (rotte, viste, valori di default della configurazione, migration, il contratto `CrmUser`, asset pubblicati):

1. **Verifica prima in questo repository.** `vendor/bin/pest` deve restare verde — vedi [Testing](testing.md).
2. **Verifica contro un consumer reale in Scaffolding.**

   ```bash
   cd ../AsgardCRM-Scaffolding
   composer update fazzinipierluigi/asgardcrm --no-interaction
   ```

   Un repository `path` non si aggiorna automaticamente — Composer lo tratta come qualsiasi altro vincolo di versione e richiede un aggiornamento esplicito per recepire le modifiche locali. Poi riesegui il `php artisan test` di Scaffolding e, per tutto ciò che tocca un flusso HTTP reale (autenticazione, wizard di installazione, una nuova rotta), fai una verifica end-to-end reale con `php artisan serve` + `curl` — non limitarti alla suite di test.

3. **Ripubblica se necessario.** Se la modifica riguarda migration, asset o configurazione pubblicati, riesegui il `vendor:publish --tag=...` pertinente in Scaffolding e committa lì anche l'output ripubblicato. Scaffolding distribuisce file già pubblicati (in stile Breeze/Jetstream), non un'istruzione "eseguilo tu stesso" per i suoi utenti.

Se `AsgardCRM-Scaffolding` non è disponibile in `../AsgardCRM-Scaffolding` sulla tua macchina, fai il checkout lì oppure adatta l'URL del repository `path` nel suo `composer.json` — non dare per scontato che il percorso gemello sia altro.

## Cosa vive dove

| | Questo repository (AsgardCRM) | AsgardCRM-Scaffolding |
|---|---|---|
| Scopo | Il package Composer vero e proprio | Host di riferimento/consumer, verifica installazioni reali |
| Auth, Admin, Wizard Install/Update | Di proprietà qui | Nessuno proprio — tutto da questo package |
| Modello `User` | Non qui — vedi [Modello User e autenticazione](user-model-and-auth.md) | Di proprietà qui |
| Suite di test | Autosufficiente (Testbench) | Esercita il package come dipendenza reale |

## Isolamento della suite del package

La suite di test del package (`tests/`) deve essere autosufficiente al 100% — nessun riferimento a nulla che esista solo nell'host. Questo è stato violato ripetutamente quando app e package convivevano in un unico monorepo (i test dipendevano silenziosamente da seeder e viste Blade esistenti solo nell'host), e la cosa restava invisibile perché un classpath condiviso mascherava il problema. È emerso solo come veri `BindingResolutionException` e falsi successi una volta che la suite ha iniziato a girare realmente in autonomia. Ogni nuovo test costruisce le proprie fixture — non fa mai riferimento a una classe o vista dell'host.
