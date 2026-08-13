# Testing

Non c'è alcun `artisan` in questo repository — è un package, non un'applicazione eseguibile. La suite di test gira in modo completamente autonomo tramite [Orchestra Testbench](https://packages.tools/testbench.html).

## Eseguire i test

```bash
composer install
vendor/bin/pest
vendor/bin/pest --filter=NomeDelTest
```

Non esiste `make:test` — crea il file Pest direttamente sotto `tests/Feature/` o `tests/Unit/`, seguendo la struttura dei file già presenti in quella directory.

`vendor/bin/testbench` è disponibile se ti serve un comando equivalente ad artisan contro l'applicazione sandbox del package stesso (ad esempio per ispezionare le rotte registrate da `AsgardCRMServiceProvider` in isolamento).

## CI

`.github/workflows/tests.yml` esegue PHP 8.3 e 8.4 su Laravel 13, più `composer audit`.

## Stile del codice

Se hai modificato file PHP:

```bash
vendor/bin/pint --dirty --format agent
```

Eseguilo in modalità predefinita (di correzione) — non in modalità `--test`.

## Regole per la suite del package

La suite di test del package deve essere **autosufficiente al 100%** — non deve mai fare riferimento a nulla che esista solo in un'applicazione host consumer (un seeder dell'host, un layout Blade dell'host, una rotta dell'host). Vedi [Il workflow a due repository](two-repo-workflow.md#isolamento-della-suite-del-package) per capire perché questo conta e cosa è andato storto l'ultima volta che è stato violato.

Ogni nuovo test costruisce le proprie fixture — `Entity::create()` + `EntityInstaller`, stub Blade locali al package sotto `tests/resources/views` — mai una classe o vista dell'host.

## Una nota sui fallimenti silenziosi dei test

Un'esecuzione di test che termina con `1` senza alcun output quasi mai significa quello che sembra. `laravel/pao` (output di test orientato agli agenti) può lanciare `stream_filter_remove(): Unable to flush filter, not removing` e inghiottire tutto l'output quando qualcosa a monte è già fallito in modo fatale. Se ti capita un fallimento di test silenzioso, senza output, controlla `storage/logs/laravel.log` nell'applicazione che sta effettivamente eseguendo i test prima di dare per scontato un misterioso bug di `pao` — un vero errore fatale (ad esempio, due migration con lo stesso nome/timestamp tra il `database/migrations/` di un host e quello del package) è una causa più probabile.
