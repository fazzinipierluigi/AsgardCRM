# Esecuzione dei workflow: confine tra orchestrazione e trasporto

Questo documento descrive il confine architetturale introdotto per separare
la logica di orchestrazione del motore workflow (`WorkflowEngine`) da COME
un'attività (un Task processo/script) viene effettivamente eseguita, senza
richiedere alcuna infrastruttura esterna per impostazione predefinita.

## Il problema che risolve

Prima di questo refactor, `WorkflowEngine::runActions()` chiamava
`WorkflowActionExecutor::execute()` direttamente e sincronamente per ogni
fase (before/after) di ogni nodo. Non esisteva alcun confine dietro cui
sostituire l'esecuzione con un backend diverso (una coda, un broker, un
motore di esecuzione esterno) senza modificare il motore stesso.

## Il confine: `TaskExecutor`

`App\Services\Workflows\Contracts\TaskExecutor` è l'unica interfaccia che
`WorkflowEngine` conosce per eseguire l'attività di un nodo `ServiceTask`
("Task processo/script"):

```php
interface TaskExecutor
{
    public function execute(WorkflowNode $node, WorkflowInstance $instance, WorkflowToken $token): void;
}
```

L'engine non esegue mai un'azione di un `ServiceTask` direttamente: sceglie
quale `TaskExecutor` usare in base alla configurazione del nodo
(`config.execution_mode`) e gli delega l'esecuzione.

Due implementazioni esistono oggi:

- **`SyncTaskExecutor`** (default, `execution_mode` assente o `'sync'`):
  esegue le action e attraversa l'arco in-process, nello stesso momento in
  cui l'engine lo raggiunge. Comportamento identico a prima del refactor —
  **zero infrastruttura richiesta**, questo è ciò che gira quando nessun
  nodo è configurato diversamente.
- **`QueuedTaskExecutor`** (`execution_mode: 'async'`): mette il token in
  stato `WaitingActivity` e accoda `ExecuteServiceTaskJob` tramite le codes
  di Laravel. Usa qualunque connessione sia configurata in
  `config('queue.default')` — per default `database` (nessun servizio
  esterno, solo la tabella `jobs` già presente). Per scalare a Redis, SQS o
  un broker, **basta cambiare `QUEUE_CONNECTION` nel `.env`**: né il
  motore, né `QueuedTaskExecutor`, né il job cambiano.

Il job, una volta eseguito dal worker, richiama esattamente la stessa logica
di `SyncTaskExecutor` (le action e l'attraversamento dell'arco sono un unico
pezzo di codice, non duplicato) e poi chiama `WorkflowEngine::advance()` per
far proseguire l'istanza — lo stesso pattern già usato da
`completeUserTask()` e `fireTimer()` per risvegliare un token in attesa.

## Perché solo il Task processo/script

Le action di Start/Gateway/Edge/End restano sempre sincrone. Un
`ExclusiveGateway` valuta le sue condizioni leggendo le variabili
dell'istanza subito dopo il nodo precedente: se quel nodo fosse asincrono
senza che l'engine aspettasse il suo completamento, il gateway
deciderebbe il ramo su uno stato non ancora finale. Il pattern
"token in attesa, ripreso da un evento esterno" — già usato da
`UserTask`/`Timer`/`Subworkflow` — è ciò che rende l'esecuzione async
corretta anche per il `ServiceTask`: l'engine riprende a valutare i nodi
successivi solo dopo che il job ha effettivamente finito.

## Idempotenza

`workflow_activity_executions` è un registro minimale (una riga per token,
chiave unica su `workflow_token_id`) che impedisce a una consegna duplicata
del job — un retry dopo un crash, una ridelivery di SQS — di rieseguire le
action (es. un'altra email, un'altra creazione di record) o di
riattraversare l'arco una seconda volta. Il percorso sincrono non tocca
questa tabella: non ha rischio di doppia consegna.

## Stato esplicito

Lo stato di un'istanza (`WorkflowInstance.status`, `.variables`) e di un
token (`WorkflowToken.status`) era già esplicito e serializzabile (colonne
enum/json, nessuno stato nascosto in memoria). Il nuovo stato
`WorkflowTokenStatus::WaitingActivity` e la tabella
`workflow_activity_executions` estendono la stessa ispezionabilità
all'esecuzione asincrona: in ogni momento è una query, non uno stato
implicito nel processo di un worker.

## Cosa NON è stato fatto (di proposito)

- **Nessun broker richiesto.** RabbitMQ, ActiveMQ, Temporal non sono stati
  integrati. Un'implementazione futura di `TaskExecutor` per uno di questi
  backend si aggiunge senza toccare `WorkflowEngine`, `WorkflowActionExecutor`
  o il resto del grafo — è esattamente lo scopo dell'interfaccia.
- **Nessun toggle nell'editor visuale.** `execution_mode` si imposta oggi
  scrivendo `config.execution_mode: "async"` sul nodo (via API/seed/import);
  un controllo nell'editor MaxGraph è un'estensione naturale ma separata,
  non necessaria per la fondazione architetturale.
- **Nessuna riscrittura del motore.** `advance()`, `processToken()`, e tutti
  gli altri handler dei tipi di nodo sono invariati; l'unico punto toccato
  è la scelta di come un `ServiceTask` viene eseguito.

## File coinvolti

- `app/Services/Workflows/Contracts/TaskExecutor.php` — l'interfaccia
- `app/Services/Workflows/TaskExecutors/SyncTaskExecutor.php` — default
- `app/Services/Workflows/TaskExecutors/QueuedTaskExecutor.php` — opzionale
- `app/Services/Workflows/WorkflowTokenTransitioner.php` — meccanica di
  esecuzione delle action e attraversamento dell'arco, condivisa da engine
  e task executor
- `app/Jobs/Workflows/ExecuteServiceTaskJob.php` — il job accodato
- `app/Models/WorkflowActivityExecution.php` +
  `database/migrations/2026_07_23_100000_create_workflow_activity_executions_table.php`
  — il registro di idempotenza
- `app/Services/Workflows/WorkflowEngine.php` — `handleServiceTask()`
  sceglie il `TaskExecutor` in base a `config.execution_mode`
