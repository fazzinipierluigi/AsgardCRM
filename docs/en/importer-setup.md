# Setting up an importer

An **Importer** brings external data into one [installed entity](creating-entities.md), on a schedule or on demand. Admin → Importer walks through a wizard: pick a channel, configure the connection, preview, map fields, and schedule it.

## 1. Basics

| Field | Rule |
|---|---|
| Title | required |
| Description | optional |
| Target entity | required — must be an **installed** entity |
| Channel | required, see below |
| Attivo | boolean |

## 2. Channels

| Channel | Value | What it reads |
|---|---|---|
| Database | `database` | Runs a SQL query against an external database connection. |
| API REST | `rest_api` | Calls an external HTTP endpoint. |
| CSV | `csv` | Reads a CSV file (local path or URL). |
| JSON | `json` | Reads a JSON file/endpoint (local path or URL). |

Only the fieldset matching the chosen channel is required; the channel itself is **immutable once the importer is created**.

### Database fields

| Field | Notes |
|---|---|
| Driver, Host, Port, Database, Username, Password | connection details for the external database |
| Query | the SQL query that produces the rows to import |

On edit, leaving Password blank keeps the previously stored value.

### API REST fields

| Field | Notes |
|---|---|
| Method | HTTP method (max 10 characters — e.g. `GET`, `POST`) |
| Endpoint | required, a valid URL |
| Auth type | `none`, `basic`, `bearer`, or `api_key` |
| Auth username/password | for `basic` |
| Auth token | for `bearer` |
| Auth API key name/value | for `api_key` (a custom header or query parameter) |
| Params (JSON) | additional request parameters, as a JSON object |

### CSV / JSON fields

| Field | Notes |
|---|---|
| Path or URL | required — either an `http(s)://` URL or an absolute path (starting with `/`) |
| Delimiter | CSV only |
| Has header row | CSV only, boolean |

## 3. Preview

Before committing to a full configuration, the wizard's preview step (channel + connection fields only — entity, field mapping, and scheduling aren't needed yet) lets you sample what the channel actually returns, so you can check the connection works and see the real field names before mapping them.

## 4. Field mapping

`field_mapping_json` is a required JSON object mapping source fields to the target entity's **column names** — every destination must be a real column on the chosen entity, or the whole configuration is rejected.

An optional **unique key field** designates one of the *mapped* source fields as the natural key for de-duplication — matching records are updated instead of duplicated on repeated runs. It must be one of the fields actually present in the mapping.

## 5. Scheduling

| Schedule type | Value | Meaning |
|---|---|---|
| Manuale | `manual` | Run on demand only (from Admin, or from a Button field/list widget configured with `button_action: importer`). |
| Pianificata (cron) | `cron` | Runs automatically, picked up by the `RunDueImporters` scheduled command. |
| Manuale e pianificata | `both` | Both — can be run on demand and is also picked up automatically. |

A **cron expression** is required for `cron` and `both`, and must be a syntactically valid cron expression.

## Run history

Every run is recorded (`ImporterRun`) with a status:

| Status | Value | Meaning |
|---|---|---|
| In esecuzione | `running` | |
| Completato | `success` | Every row imported cleanly. |
| Completato con errori | `partial_failure` | Some rows failed; the rest were imported. |
| Fallito | `failed` | The run couldn't complete. |

## Where this fits

Importers are also reachable as a Button-field or list-widget action from any entity — see [Creating entities → Button fields](creating-entities.md#button-fields) and [→ List widgets](creating-entities.md#7-list-widgets).
