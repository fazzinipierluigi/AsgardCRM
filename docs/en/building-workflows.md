# Building workflows

The workflow engine is a versioned, node-and-edge graph builder (a drag-and-drop editor built on MaxGraph). This page walks through creating a workflow, the graph editor's building blocks, and how drafts/publishing/execution actually behave.

## 1. Create the workflow

Admin → Workflow → *New*:

| Field | Rule |
|---|---|
| Name | required, max 255 characters |
| Description | optional |
| Attivo (`is_active`) | boolean — an inactive workflow won't be picked up by triggers or the scheduled runner |

This creates the `Workflow` record. The actual logic — nodes, edges, variables — lives in its **versions**, edited separately in the graph builder.

## 2. The graph builder

Admin → Workflow → *Builder* opens the graph editor for one workflow. Everything you do here — adding a node, dragging it, wiring an edge, editing a node's config panel — edits the workflow's current **draft** version in memory; nothing is written to the database until you save.

### Draft vs. published versions

This is the most important mechanic to understand:

- Saving the graph writes to the workflow's **draft** `WorkflowVersion` — creating one, at the next version number, on the first save since the last publish (or ever). Repeated saves while you're still working reuse that same draft; they don't mint a new version each time.
- The draft has **no effect on running instances** and isn't picked up by new ones. Only **Publish** promotes the current draft to published, making it `workflow.current_version_id` — the version any *new* `WorkflowInstance` starts against.
- Every already-running instance stays pinned to the version it started with, even after you publish a new one. The version that was live before publishing is left untouched — still published, still what its own running instances are pinned to.

In short: edit and save freely — nothing goes live until you explicitly publish, and publishing never disturbs instances already in flight.

### Variables

Workflow-scoped variables, each with a type and optional default:

| Type | Value |
|---|---|
| Stringa | `string` |
| Intero | `integer` |
| Float | `float` |
| Booleano | `boolean` |
| Data | `date` |
| Data e ora | `datetime` |
| Oggetto | `object` |
| Array | `array` |

If the workflow's trigger is entity-bound (see below), a system variable holding the triggering entity record is available automatically.

## 3. Node types

Every node has a type, a name, an `x`/`y` position on the canvas, and a type-specific config panel. Exactly **one Start node** is required per graph — the builder rejects a save with zero or more than one.

| Type | Value | Behavior |
|---|---|---|
| Nodo di avvio | `start` | Unique per workflow. Carries the trigger configuration (see below). |
| Task utente | `user_task` | **Blocking** — the engine halts token advancement here until a person completes it (`WorkflowUserTask`, tracked as Pending/Completed/Expired). |
| Task processo/script | `service_task` | Executes through a pluggable task-execution boundary. Can run `sync` or `async` (`execution_mode` in its config) — only an **async** service task can host a Boundary Timer (see below). |
| Gate esclusivo | `exclusive_gateway` | A **gateway** — one of several outgoing edges is chosen based on each edge's condition. |
| Gate parallelo | `parallel_gateway` | A **gateway** — splits into (or joins) multiple parallel branches. |
| Timer | `timer` | **Blocking** — pauses the token until it fires (see Timers below). |
| Boundary Timer | `boundary_timer` | Attached to a **host node** via `attached_to_node_key` in its config — only valid on a **Task utente** or an **async Task processo/script**. Fires if the host doesn't complete in time. |
| Semaforo | `semaphore` | **Blocking** — waits on an external signal/join condition. |
| Nodo di fine | `end` | Terminates the instance (or that branch of it). |
| Subworkflow | `subworkflow` | Delegates to another workflow; the parent instance waits on the child. |

### The Start node's trigger

Configured on the Start node itself:

| Trigger | Value | Notes |
|---|---|---|
| Avvio manuale | `manual` | Started on demand — e.g. from a Button field or list widget. |
| Avvio via timer/cron | `cron` | Picked up by the `RunDueWorkflows` scheduled command. |
| Alla creazione di un'entità | `entity_created` | Entity-bound — see below. |
| Alla modifica di un'entità | `entity_updated` | Entity-bound. |
| Alla creazione o modifica di un'entità | `entity_created_or_updated` | Entity-bound. |

The three entity-bound triggers bind the resulting instance to the record that fired it, exposed to the workflow as a mandatory system variable.

Alongside the trigger, the **start occurrence** controls repeat behavior for entity-bound triggers:

| Occurrence | Value | Meaning |
|---|---|---|
| Avvia una sola volta | `once` | Fires the workflow for a given record only the first time the trigger condition matches. |
| Avvia ogni volta | `every_time` | Fires every time the trigger condition matches (e.g. every update). |

### Timers

Both `timer` and `boundary_timer` nodes are configured with a duration in a chosen unit:

| Unit | Value |
|---|---|
| Minuti | `minutes` |
| Ore | `hours` |
| Giorni | `days` |

Timers are fired by the `FireDueWorkflowTimers` scheduled command, tracked as Pending/Fired/Cancelled (`WorkflowTimerStatus`).

## 4. Edges and branching

An edge connects a source node to a target node, with:

- **Label** — display text on the graph.
- **Sequence** — ordering, also used to break ties.
- **Condition logic** — a JsonLogic rule, evaluated to decide whether an **Exclusive Gateway**'s outgoing edge is taken. An empty/invalid rule is treated as always-true.
- **Waypoints** — visual routing only, no execution effect.

An edge can also carry its own **before/after actions**, in addition to (or instead of) actions on nodes.

## 5. Actions

Both nodes and edges can carry a list of actions, each with a **phase**:

| Phase | Value | Meaning |
|---|---|---|
| In ingresso | `before` | Runs before the node/edge's own step. |
| In uscita | `after` | Runs after it. |

Action types:

| Action | Value |
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

`assign_variable_from_sql` and `assign_variable_from_api` read from **`WorkflowSqlConnection`** and **`WorkflowApiEndpoint`** records respectively — configure these separately (Admin → Workflow → *Connessioni SQL* / *Endpoint API*) and pick from them in the action's config. Each connection/endpoint is either global (`workflow_id` left empty, available to every workflow) or scoped to one specific workflow.

**SQL connection fields:** driver (`mysql`/`pgsql`/`sqlsrv`/`sqlite`), database (required), plus host/port/username/password as needed for that driver. On edit, leaving the password blank keeps the previously stored value.

**API endpoint fields:** base URL (required, must start with `http://` or `https://`), and an auth type — `none`, `bearer` (needs a token), `basic` (needs username/password), or `header` (needs a custom header name/value). On edit, leaving a secret field (token/password/header value) blank keeps the previously stored value.

## 6. Publishing and running

Once your graph passes validation (one Start node, every edge pointing at a real node, every Boundary Timer attached to an allowed host) and you save it, hit **Publish** to promote the draft. From then on:

- New instances (triggered manually, by cron, or by an entity event) start against the newly published version.
- `RunDueWorkflows` advances instances that are ready to move (a completed timer, a completed user task, etc.).
- `FireDueWorkflowTimers` fires due `timer`/`boundary_timer` nodes.

## 7. Watching what happened

Execution is fully recorded, useful when something doesn't behave as expected:

- **`WorkflowInstance`** status: Running, Waiting, Completed, Failed, Cancelled.
- **`WorkflowToken`** status (finer-grained, per branch): Active, In attesa del timer, In attesa dell'utente, In attesa delle altre strade (parallel join), In attesa del subworkflow, In attesa dell'attività in coda, Completed, Cancelled.
- **`WorkflowNodeExecution`** / **`WorkflowActivityExecution`** — what happened at each individual node/service-task run.

An instance view in the Admin area (or from the triggering entity record, via `entities/{entity:slug}/{record}/workflow-instances/{workflowInstance}`) surfaces this history per instance.

## Where this fits

Workflows commonly interact with [entities](creating-entities.md) — as triggers (entity created/updated), as action targets (create/update/fetch an entity record), and as Button-field or list-widget targets a user can start manually. See [Modules overview](modules-overview.md#workflow-engine) for the underlying model relationships.
