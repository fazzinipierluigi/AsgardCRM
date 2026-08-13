# Creazione delle entità

Un'**Entità** è un modello dati personalizzato che definisci a runtime, tramite l'interfaccia Admin — nessuna migration da scrivere a mano. Questa pagina illustra le schermate reali del builder e le regole che il package applica lungo il percorso.

## 1. Crea l'entità

Admin → Entità → *Nuova* crea il "guscio" dell'entità:

| Campo | Regola |
|---|---|
| Nome | obbligatorio, massimo 100 caratteri |
| Icona | opzionale, deve essere un nome icona Tabler valido |

A questo punto l'entità esiste come riga nella tabella `entities` ma **non ha ancora una tabella nel database** — non è `is_installed`.

## 2. Progetta la struttura: tab → card → campi

Apri il **builder** dell'entità (Admin → Entità → *Builder*). La struttura è un albero a tre livelli:

- **Tab** — sezioni di primo livello del form del record.
- **Card** — riquadri di campi raggruppati all'interno di un tab.
- **Campi** — le vere e proprie colonne di dati.

Prima che l'entità venga installata, modifichi l'intero albero in un colpo solo e lo salvi tutto insieme: ogni tab richiede almeno una card, ogni card richiede almeno un campo.

### Tipi di campo

| Tipo | Valore | Note |
|---|---|---|
| Stringa | `string` | Testo semplice. |
| Numero intero | `integer` | |
| Numero decimale | `decimal` | Utilizzabile anche come sorgente "prezzo unitario" / "totale" per un campo Blocco Prodotti, vedi sotto. |
| Textarea | `textarea` | |
| Rich text | `richtext` | Sanificato lato server al salvataggio — vedi [Versioning e changelog](versioning-and-changelog.md) per l'approccio di sanificazione. |
| Checkbox | `checkbox` | |
| Select | `select` | Richiede delle **Opzioni** — senza di esse il campo non può essere salvato. |
| Data / Ora / Data e ora | `date` / `time` / `datetime` | |
| Color picker | `color` | |
| Relazione | `relation` | Richiede un **target di relazione** (un'altra entità, oppure uno dei modelli in `config('crm.entities.relatable_models')`) — vedi [Configurazione](configuration.md#entità). |
| Codice | `code` | Generato dal sistema — mai inserito dall'utente, escluso dai form/validazione del record. Supporta un prefisso opzionale. |
| Bottone | `button` | Non contiene alcun valore — al click innesca un'azione (vedi sotto). Escluso da validazione, persistenza e dalle colonne reali dell'entità. |
| Tabella | `table` | Una sotto-tabella incorporata e liberamente definita (colonne proprie — vedi sotto), memorizzata come JSON. |
| Blocco Prodotti | `products_block` | Un blocco selettore-catalogo + righe articolo — vedi sotto. |

Ogni campo ha inoltre:

- **Nome colonna** — obbligatorio, minuscolo, deve iniziare con una lettera (`^[a-z][a-z0-9_]*$`), unico nell'entità. La colonna fisica reale di un campo Relazione è `{nome_colonna}_id`.
- **Obbligatorio** — booleano.
- **Valore di default** — testo libero, massimo 255 caratteri.
- **Larghezza** — da 1 a 12 (stile griglia Bootstrap).

I nomi di colonna riservati non possono essere riutilizzati — il builder rifiuta qualsiasi nome che collida con le colonne di sistema dell'entità.

### Campi Bottone

Un campo Bottone richiede un `button_action`, uno tra:

- `workflow` — esegue un workflow ad avvio manuale, indicato da `button_workflow_id`.
- `importer` — esegue uno o più canali importer, elencati in `button_importer_ids` (ognuno deve esistere).
- `javascript` — esegue JS personalizzato inline (`button_javascript`).

### Campi Tabella

La textarea `table_columns` usa una sintassi compatta, una colonna per riga:

```
nome_colonna:Etichetta:tipo:obbligatoria
```

- `tipo` — uno tra `string`, `integer`, `decimal`, `date`, `checkbox` (default `string` se omesso/non valido).
- `obbligatoria` — `si`, `1` o `true` la rende obbligatoria (qualsiasi altro valore la rende opzionale).

È richiesta almeno una riga colonna valida.

### Campi Blocco Prodotti

Un blocco di righe articolo basato su un'altra entità installata usata come catalogo prodotti:

- **Catalogo** (`products_catalog`) — lo slug di un'altra entità *installata*.
- **Colonna prezzo** (`products_price_column`) — uno dei campi `decimal` di quell'entità catalogo, letto come prezzo unitario.
- **Target totale** (`products_total_target`, opzionale) — un campo `decimal` **su questa entità** che riceve il totale calcolato dal blocco.
- **Colonne extra** (`products_extra_columns`, opzionale) — colonne aggiuntive del catalogo da mostrare per riga articolo.

## 3. Installa l'entità

L'installazione (Admin → Entità → *Installa*) è ciò che crea realmente la tabella del database dell'entità a partire dall'albero tab/card/campi progettato. Una volta installata:

- **`nome_colonna`, `tipo` e `relation_target` diventano immutabili** per i campi esistenti — la colonna fisica esiste già e non può retroattivamente cambiare forma.
- Puoi comunque: rinominare tab/card, modificare i metadati di un campo (nome, obbligatorietà, valore di default, larghezza, opzioni, colonne tabella, configurazione bottone) e **aggiungere campi nuovi** — la colonna di un campo nuovo viene aggiunta live alla tabella reale.
- Rimuovere un campo esistente dal builder elimina la sua colonna e la sua riga — **a meno che il campo non sia bloccato** (`is_locked`), nel qual caso l'intero salvataggio viene rifiutato.
- Un campo può essere marcato **nascosto** (`is_hidden`) — resta nello schema ma non viene mostrato nel form del record.

**Disinstallare** un'entità elimina completamente la sua tabella fisica. Entrambe le azioni sono distruttive in una direzione (installare crea colonne che non puoi più rimodellare liberamente; disinstallare elimina tutti i dati) — trattale di conseguenza.

## 4. Relazioni tra entità

Admin → Entità → *Relazioni* permette di definire una relazione con nome tra l'entità corrente e un'altra entità **installata** (`entity_b_id`, che non può mai essere l'entità stessa). I singoli collegamenti record-a-record (`EntityRelationLink`) vengono poi creati dalla vista del record entità, attraverso la relazione definita qui.

Questo è diverso da un *campo* `relation` (una singola chiave esterna sul record stesso) — un `EntityRelation` è un collegamento in stile molti-a-molti tra due tipi di entità, esplorabile da entrambi i lati.

## 5. Comportamento condizionale dei campi

Admin → Entità → *Condizioni* definisce regole — costruite con un editor JsonLogic — che attivano/disattivano, per ciascun campo interessato, se esso è:

- `managed` — se questa condizione controlla o meno il campo,
- `visible`,
- `readonly`,
- `required`.

Una regola vuota o non valida viene trattata come sempre vera, valutata sia lato client (mentre compili il form) sia — presumibilmente — ricontrollata lato server dove serve.

## 6. Visibilità per ruolo

Admin → Entità → *Visibilità* assegna a ciascun ruolo uno di quattro livelli progressivamente più permissivi:

| Livello | Significato |
|---|---|
| Solo le proprie | Solo i record posseduti dall'utente. |
| Le proprie, in sola lettura quelle altrui | I propri record completamente; quelli altrui in sola lettura. |
| Le proprie, in lettura e modifica quelle altrui | I propri record completamente; quelli altrui in lettura + modifica. |
| Controllo completo su tutte | Controllo completo su ogni record. |

## 7. Widget elenco

Admin → Entità → *Widget* aggancia widget dashboard a un'entità, di uno di tre tipi:

- **Bottone** — stessa configurazione `button_action` (`workflow`/`importer`/`javascript`) di un campo Bottone.
- **Contatore** — un numero, opzionalmente filtrato (`filter_column`/`filter_operator`/`filter_value` su una delle colonne reali e confrontabili dell'entità — i campi Bottone/Tabella/Blocco Prodotti non sono ammessi).
- **Grafico** — `bar`/`line`/`pie`/`doughnut`, raggruppato per colonna (`chart_group_by`) e aggregato (`count`/`sum`/`avg`; `sum`/`avg` richiedono una `chart_value_column` numerica), con lo stesso filtro opzionale del Contatore.

## 8. Importa / esporta

Lo schema di un'entità installata (tab/card/campi, non i suoi dati) può essere esportato in un formato portabile e reimportato in un'altra installazione tramite Admin → Entità → *Importa/Esporta* — utile per spostare la progettazione di un'entità tra ambienti senza doverla ricostruire a mano dall'interfaccia.

## 9. Flag speciali dell'entità

Oltre alla struttura tab/card/campi, un'entità stessa può essere contrassegnata con:

- `is_calendar` — i suoi record partecipano al modulo calendario (vedi [Panoramica dei moduli](modules-overview.md#calendario)).
- `is_documents` — i suoi record ottengono un'area cartelle documentali (vedi [Panoramica dei moduli](modules-overview.md#documenti)).
- `is_email` — i suoi record sono collegabili dal modulo webmail.
- `show_in_menu` / `menu_position` — se e dove compare nella navigazione principale.
- `show_in_quick_access` / `quick_access_position` — lo stesso, per l'elenco scorciatoie ad accesso rapido.
- `is_system` — contrassegna un'entità fornita dal package come di proprietà del sistema (il comportamento di protezione da eliminazioni accidentali deriva da questo flag).

## Dove si inserisce

Una volta che un'entità esiste, i suoi campi diventano disponibili come target in tutto il resto del package: come trigger/target di azione nel [builder dei workflow](building-workflows.md), come target di import/export per l'[Importer](modules-overview.md#importer), e come target `relatable_models` in [Configurazione](configuration.md#entità) per i campi relazione di altre entità.
