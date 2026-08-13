# Costruzione dei workflow

Il motore di workflow è un builder a grafo versionato, con nodi e archi (un editor drag-and-drop costruito su MaxGraph). Questa pagina illustra la creazione di un workflow, i mattoncini dell'editor a grafo, e come si comportano davvero bozze/pubblicazione/esecuzione.

## 1. Crea il workflow

Admin → Workflow → *Nuovo*:

| Campo | Regola |
|---|---|
| Nome | obbligatorio, massimo 255 caratteri |
| Descrizione | opzionale |
| Attivo (`is_active`) | booleano — un workflow non attivo non viene intercettato né dai trigger né dall'esecutore pianificato |

Questo crea il record `Workflow`. La logica vera e propria — nodi, archi, variabili — vive nelle sue **versioni**, modificate separatamente nel builder a grafo.

## 2. Il builder a grafo

Admin → Workflow → *Builder* apre l'editor a grafo per un workflow. Tutto ciò che fai qui — aggiungere un nodo, trascinarlo, collegare un arco, modificare il pannello di configurazione di un nodo — modifica la versione **bozza** corrente del workflow in memoria; nulla viene scritto nel database finché non salvi.

### Bozza vs versione pubblicata

Questo è il meccanismo più importante da capire:

- Salvare il grafo scrive nella `WorkflowVersion` **bozza** del workflow — creandone una, al numero di versione successivo, al primo salvataggio dall'ultima pubblicazione (o mai pubblicato). Salvataggi ripetuti mentre stai ancora lavorando riusano la stessa bozza; non ne creano una nuova ogni volta.
- La bozza **non ha alcun effetto sulle istanze in esecuzione** e non viene intercettata dalle nuove. Solo **Pubblica** promuove la bozza corrente a pubblicata, rendendola `workflow.current_version_id` — la versione con cui parte ogni *nuova* `WorkflowInstance`.
- Ogni istanza già in esecuzione resta ancorata alla versione con cui è partita, anche dopo che pubblichi una versione nuova. La versione che era attiva prima della pubblicazione resta intatta — ancora pubblicata, ancora quella a cui restano ancorate le sue istanze in esecuzione.

In sintesi: modifica e salva liberamente — nulla va in produzione finché non pubblichi esplicitamente, e la pubblicazione non disturba mai le istanze già in corso.

### Variabili

Variabili con scope sul workflow, ciascuna con un tipo e un default opzionale:

| Tipo | Valore |
|---|---|
| Stringa | `string` |
| Intero | `integer` |
| Float | `float` |
| Booleano | `boolean` |
| Data | `date` |
| Data e ora | `datetime` |
| Oggetto | `object` |
| Array | `array` |

Se il trigger del workflow è legato a un'entità (vedi sotto), una variabile di sistema contenente il record entità che ha innescato l'esecuzione è disponibile automaticamente.

## 3. Tipi di nodo

Ogni nodo ha un tipo, un nome, una posizione `x`/`y` sul canvas e un pannello di configurazione specifico per tipo. È richiesto esattamente **un Nodo di avvio** per grafo — il builder rifiuta il salvataggio con zero o più di uno.

| Tipo | Valore | Comportamento |
|---|---|---|
| Nodo di avvio | `start` | Unico per workflow. Contiene la configurazione del trigger (vedi sotto). |
| Task utente | `user_task` | **Bloccante** — il motore ferma l'avanzamento del token qui finché una persona non lo completa (`WorkflowUserTask`, tracciato come In attesa/Completato/Scaduto). |
| Task processo/script | `service_task` | Eseguito tramite un confine di esecuzione collegabile. Può girare in modo `sync` o `async` (`execution_mode` nella sua configurazione) — solo un task processo/script **async** può ospitare un Boundary Timer (vedi sotto). |
| Gate esclusivo | `exclusive_gateway` | Un **gate** — viene scelto uno tra più archi in uscita in base alla condizione di ciascun arco. |
| Gate parallelo | `parallel_gateway` | Un **gate** — si divide in (o riunisce) più rami paralleli. |
| Timer | `timer` | **Bloccante** — mette in pausa il token finché non scatta (vedi Timer sotto). |
| Boundary Timer | `boundary_timer` | Agganciato a un **nodo ospite** tramite `attached_to_node_key` nella sua configurazione — valido solo su un **Task utente** o un **Task processo/script async**. Scatta se l'ospite non si completa in tempo. |
| Semaforo | `semaphore` | **Bloccante** — attende un segnale esterno/condizione di unione. |
| Nodo di fine | `end` | Termina l'istanza (o quel ramo). |
| Subworkflow | `subworkflow` | Delega a un altro workflow; l'istanza padre attende quella figlia. |

### Il trigger del Nodo di avvio

Configurato sul Nodo di avvio stesso:

| Trigger | Valore | Note |
|---|---|---|
| Avvio manuale | `manual` | Avviato su richiesta — ad esempio da un campo Bottone o un widget elenco. |
| Avvio via timer/cron | `cron` | Intercettato dal comando pianificato `RunDueWorkflows`. |
| Alla creazione di un'entità | `entity_created` | Legato a un'entità — vedi sotto. |
| Alla modifica di un'entità | `entity_updated` | Legato a un'entità. |
| Alla creazione o modifica di un'entità | `entity_created_or_updated` | Legato a un'entità. |

I tre trigger legati a un'entità collegano l'istanza risultante al record che ha innescato l'esecuzione, esposto al workflow come variabile di sistema obbligatoria.

Insieme al trigger, l'**occorrenza di avvio** controlla il comportamento di ripetizione per i trigger legati a un'entità:

| Occorrenza | Valore | Significato |
|---|---|---|
| Avvia una sola volta | `once` | Avvia il workflow per un dato record solo la prima volta che la condizione del trigger corrisponde. |
| Avvia ogni volta | `every_time` | Si avvia ogni volta che la condizione del trigger corrisponde (ad esempio ogni modifica). |

### Timer

Sia i nodi `timer` che `boundary_timer` sono configurati con una durata in un'unità scelta:

| Unità | Valore |
|---|---|
| Minuti | `minutes` |
| Ore | `hours` |
| Giorni | `days` |

I timer vengono innescati dal comando pianificato `FireDueWorkflowTimers`, tracciati come In attesa/Eseguito/Annullato (`WorkflowTimerStatus`).

## 4. Archi e diramazioni

Un arco collega un nodo sorgente a un nodo di destinazione, con:

- **Etichetta** — testo mostrato nel grafo.
- **Sequenza** — ordinamento, usato anche per risolvere parità.
- **Condition logic** — una regola JsonLogic, valutata per decidere se l'arco in uscita di un **Gate esclusivo** viene percorso. Una regola vuota o non valida viene trattata come sempre vera.
- **Waypoints** — solo instradamento visivo, nessun effetto sull'esecuzione.

Un arco può inoltre avere proprie **azioni in ingresso/uscita**, in aggiunta a (o al posto di) azioni sui nodi.

## 5. Azioni

Sia i nodi che gli archi possono avere un elenco di azioni, ciascuna con una **fase**:

| Fase | Valore | Significato |
|---|---|---|
| In ingresso | `before` | Eseguita prima del passo proprio del nodo/arco. |
| In uscita | `after` | Eseguita dopo. |

Tipi di azione:

| Azione | Valore |
|---|---|
| Assegna valore a una variabile | `set_variable` |
| Svuota variabile | `clear_variable` |
| Assegna entità a una variabile | `assign_entity_to_variable` |
| Invia email | `send_email` |
| Aggiorna entità | `update_entity` |
| Crea entità | `create_entity` |
| Assegna valore variabile da SQL | `assign_variable_from_sql` |
| Assegna valore variabile da API | `assign_variable_from_api` |
| Preleva entità | `fetch_entity` |
| Reindirizza a un'entità | `redirect` |

`assign_variable_from_sql` e `assign_variable_from_api` leggono rispettivamente da record **`WorkflowSqlConnection`** e **`WorkflowApiEndpoint`** — configurali separatamente (Admin → Workflow → *Connessioni SQL* / *Endpoint API*) e scegli tra questi nella configurazione dell'azione. Ogni connessione/endpoint è globale (`workflow_id` lasciato vuoto, disponibile a ogni workflow) oppure con scope su un workflow specifico.

**Campi connessione SQL:** driver (`mysql`/`pgsql`/`sqlsrv`/`sqlite`), database (obbligatorio), più host/porta/username/password secondo necessità per quel driver. In modifica, lasciare vuota la password mantiene il valore già memorizzato.

**Campi endpoint API:** URL base (obbligatorio, deve iniziare con `http://` o `https://`), e un tipo di autenticazione — `none`, `bearer` (richiede un token), `basic` (richiede username/password), oppure `header` (richiede nome/valore di un header personalizzato). In modifica, lasciare vuoto un campo secret (token/password/valore header) mantiene il valore già memorizzato.

## 6. Pubblicazione ed esecuzione

Una volta che il tuo grafo supera la validazione (un solo Nodo di avvio, ogni arco che punta a un nodo reale, ogni Boundary Timer agganciato a un ospite ammesso) e lo salvi, premi **Pubblica** per promuovere la bozza. Da quel momento:

- Le nuove istanze (avviate manualmente, via cron, o da un evento su un'entità) partono con la versione appena pubblicata.
- `RunDueWorkflows` fa avanzare le istanze pronte a muoversi (un timer completato, un task utente completato, ecc.).
- `FireDueWorkflowTimers` innesca i nodi `timer`/`boundary_timer` in scadenza.

## 7. Osservare cosa è successo

L'esecuzione viene registrata integralmente, utile quando qualcosa non si comporta come previsto:

- Stato **`WorkflowInstance`**: In esecuzione, In attesa, Completato, Fallito, Annullato.
- Stato **`WorkflowToken`** (più granulare, per ramo): Attivo, In attesa del timer, In attesa dell'utente, In attesa delle altre strade (unione paralleli), In attesa del subworkflow, In attesa dell'attività in coda, Completato, Annullato.
- **`WorkflowNodeExecution`** / **`WorkflowActivityExecution`** — cosa è successo in ogni singola esecuzione di nodo/task processo.

Una vista dell'istanza nell'area Admin (o dal record entità che l'ha innescata, tramite `entities/{entity:slug}/{record}/workflow-instances/{workflowInstance}`) mostra questa cronologia per istanza.

## Dove si inserisce

I workflow interagiscono comunemente con le [entità](creating-entities.md) — come trigger (creazione/modifica entità), come target di azione (crea/aggiorna/preleva un record entità), e come target di campi Bottone o widget elenco che un utente può avviare manualmente. Vedi [Panoramica dei moduli](modules-overview.md#motore-di-workflow) per le relazioni tra i modelli sottostanti.
