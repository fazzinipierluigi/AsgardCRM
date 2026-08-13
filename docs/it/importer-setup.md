# Configurare un importer

Un **Importer** porta dati esterni dentro un'[entità installata](creating-entities.md), su base pianificata o on-demand. Admin → Importer guida attraverso un wizard: scegli un canale, configura la connessione, anteprima, mappa i campi, pianifica.

## 1. Informazioni di base

| Campo | Regola |
|---|---|
| Titolo | obbligatorio |
| Descrizione | opzionale |
| Entità di destinazione | obbligatoria — deve essere un'entità **installata** |
| Canale | obbligatorio, vedi sotto |
| Attivo | booleano |

## 2. Canali

| Canale | Valore | Cosa legge |
|---|---|---|
| Database | `database` | Esegue una query SQL su una connessione a un database esterno. |
| API REST | `rest_api` | Chiama un endpoint HTTP esterno. |
| CSV | `csv` | Legge un file CSV (percorso locale o URL). |
| JSON | `json` | Legge un file/endpoint JSON (percorso locale o URL). |

Solo il set di campi corrispondente al canale scelto è obbligatorio; il canale stesso è **immutabile una volta creato l'importer**.

### Campi Database

| Campo | Note |
|---|---|
| Driver, Host, Porta, Database, Username, Password | dettagli di connessione per il database esterno |
| Query | la query SQL che produce le righe da importare |

In modifica, lasciare vuota la Password mantiene il valore già memorizzato.

### Campi API REST

| Campo | Note |
|---|---|
| Metodo | metodo HTTP (massimo 10 caratteri — es. `GET`, `POST`) |
| Endpoint | obbligatorio, un URL valido |
| Tipo autenticazione | `none`, `basic`, `bearer` o `api_key` |
| Username/password autenticazione | per `basic` |
| Token autenticazione | per `bearer` |
| Nome/valore chiave API autenticazione | per `api_key` (un header personalizzato o parametro query) |
| Parametri (JSON) | parametri aggiuntivi della richiesta, come oggetto JSON |

### Campi CSV / JSON

| Campo | Note |
|---|---|
| Percorso o URL | obbligatorio — un URL `http(s)://` oppure un percorso assoluto (che inizia con `/`) |
| Delimitatore | solo CSV |
| Ha riga di intestazione | solo CSV, booleano |

## 3. Anteprima

Prima di impegnarti in una configurazione completa, il passo di anteprima del wizard (solo canale + campi di connessione — entità, mappatura campi e pianificazione non servono ancora) ti permette di campionare cosa restituisce realmente il canale, così puoi verificare che la connessione funzioni e vedere i nomi reali dei campi prima di mapparli.

## 4. Mappatura dei campi

`field_mapping_json` è un oggetto JSON obbligatorio che mappa i campi sorgente ai **nomi colonna** dell'entità di destinazione — ogni destinazione deve essere una colonna reale dell'entità scelta, altrimenti l'intera configurazione viene rifiutata.

Una **chiave univoca** opzionale designa uno dei campi sorgente *mappati* come chiave naturale per la deduplicazione — i record corrispondenti vengono aggiornati invece che duplicati alle esecuzioni successive. Deve essere uno dei campi effettivamente presenti nella mappatura.

## 5. Pianificazione

| Tipo pianificazione | Valore | Significato |
|---|---|---|
| Manuale | `manual` | Eseguito solo su richiesta (da Admin, oppure da un campo Bottone/widget elenco configurato con `button_action: importer`). |
| Pianificata (cron) | `cron` | Eseguito automaticamente, intercettato dal comando pianificato `RunDueImporters`. |
| Manuale e pianificata | `both` | Entrambi — può essere eseguito su richiesta ed è anche intercettato automaticamente. |

Un'**espressione cron** è obbligatoria per `cron` e `both`, e deve essere sintatticamente valida.

## Storico esecuzioni

Ogni esecuzione viene registrata (`ImporterRun`) con uno stato:

| Stato | Valore | Significato |
|---|---|---|
| In esecuzione | `running` | |
| Completato | `success` | Ogni riga importata correttamente. |
| Completato con errori | `partial_failure` | Alcune righe fallite; il resto importato. |
| Fallito | `failed` | L'esecuzione non è riuscita a completarsi. |

## Dove si inserisce

Gli importer sono raggiungibili anche come azione di un campo Bottone o widget elenco da qualsiasi entità — vedi [Creazione delle entità → Campi Bottone](creating-entities.md#campi-bottone) e [→ Widget elenco](creating-entities.md#7-widget-elenco).
