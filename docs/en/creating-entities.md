# Creating entities

An **Entity** is a custom data model you define at runtime, through the Admin UI — no migration to write by hand. This page walks through the actual builder screens and the rules the package enforces along the way.

## 1. Create the entity

Admin → Entities → *New* creates the entity shell:

| Field | Rule |
|---|---|
| Name | required, max 100 characters |
| Icon | optional, must be a valid Tabler icon name |

At this point the entity exists as a row in `entities` but has **no database table yet** — it isn't `is_installed`.

## 2. Design the structure: tabs → cards → fields

Open the entity's **builder** (Admin → Entities → *Builder*). The structure is a three-level tree:

- **Tabs** — top-level sections of the record form.
- **Cards** — grouped boxes of fields inside a tab.
- **Fields** — the actual data columns.

Before the entity is installed, you edit this whole tree at once and save it in one shot: every tab needs at least one card, every card needs at least one field.

### Field types

| Type | Value | Notes |
|---|---|---|
| Stringa | `string` | Plain text. |
| Numero intero | `integer` | |
| Numero decimale | `decimal` | Also usable as a "unit price" / "total" source for a Blocco Prodotti field, see below. |
| Textarea | `textarea` | |
| Rich text | `richtext` | Sanitized server-side on save — see [Versioning & changelog](versioning-and-changelog.md) for the sanitization approach. |
| Checkbox | `checkbox` | |
| Select | `select` | Requires **Options** — without them the field can't be saved. |
| Data / Ora / Data e ora | `date` / `time` / `datetime` | |
| Color picker | `color` | |
| Relazione | `relation` | Requires a **relation target** (another entity, or one of `config('crm.entities.relatable_models')`) — see [Configuration](configuration.md#entities). |
| Codice | `code` | System-generated — never submitted by the user, excluded from record forms/validation. Supports an optional prefix. |
| Bottone | `button` | Holds no value at all — triggers an action on click instead (see below). Excluded from validation, persistence, and the entity's own database columns. |
| Tabella | `table` | An embedded, freeform sub-table (its own column definitions — see below), stored as JSON. |
| Blocco Prodotti | `products_block` | A catalog-picker + line-item block — see below. |

Every field also has:

- **Column name** — required, lowercase, must start with a letter (`^[a-z][a-z0-9_]*$`), unique within the entity. A Relation field's real physical column is `{column_name}_id`.
- **Required** — boolean.
- **Default value** — free text, max 255 characters.
- **Width** — 1 to 12 (Bootstrap-grid style row width).

Reserved column names can't be reused — the builder rejects any that collide with the entity's own system columns.

### Button fields

A Button field needs a `button_action`, one of:

- `workflow` — runs a manually-triggered workflow, pointed at by `button_workflow_id`.
- `importer` — runs one or more importer channels, listed by `button_importer_ids` (each must exist).
- `javascript` — runs custom inline JS (`button_javascript`).

### Table fields

The `table_columns` textarea uses a compact one-column-per-line syntax:

```
nome_colonna:Etichetta:tipo:obbligatoria
```

- `tipo` — one of `string`, `integer`, `decimal`, `date`, `checkbox` (defaults to `string` if omitted/invalid).
- `obbligatoria` — `si`, `1`, or `true` marks it required (anything else is optional).

At least one valid column line is required.

### Blocco Prodotti (product block) fields

A line-item block backed by another installed entity acting as a product catalog:

- **Catalog** (`products_catalog`) — the slug of another *installed* entity.
- **Price column** (`products_price_column`) — one of that catalog entity's own `decimal` fields, read as the unit price.
- **Total target** (`products_total_target`, optional) — a `decimal` field **on this entity** that receives the block's computed total.
- **Extra columns** (`products_extra_columns`, optional) — additional catalog columns to surface per line item.

## 3. Install the entity

Installing (Admin → Entities → *Install*) is what actually creates the entity's real database table from the tab/card/field tree you designed. Once installed:

- **`column_name`, `type`, and `relation_target` become immutable** for existing fields — the physical column already exists and can't retroactively change shape.
- You can still: rename tabs/cards, edit a field's metadata (name, required, default value, width, options, table columns, button config), and **add brand-new fields** — a new field's column is appended live to the real table.
- Removing an existing field from the builder drops its column and deletes its row — **unless the field is locked** (`is_locked`), in which case the whole save is rejected.
- A field can be marked **hidden** (`is_hidden`) — kept in the schema but not shown on the record form.

**Uninstalling** an entity drops its physical table entirely. Both actions are destructive in one direction (install creates columns you can no longer freely reshape; uninstall drops all data) — treat them accordingly.

## 4. Relations between entities

Admin → Entities → *Relazioni* lets you define a named relation between the current entity and another **installed** entity (`entity_b_id`, which can never be the entity itself). Individual record-to-record links (`EntityRelationLink`) are then created from the entity record view, through the relation you defined here.

This is separate from a `relation` *field* (a single foreign key on the record itself) — an `EntityRelation` is a many-to-many-style link between two entity types, browsable from either side.

## 5. Conditional field behavior

Admin → Entities → *Condizioni* defines rules — built with a JsonLogic editor — that toggle, per matched field, whether it is:

- `managed` — whether this condition controls the field at all,
- `visible`,
- `readonly`,
- `required`.

An empty or invalid rule is treated as always-true, evaluated both client-side (as you fill the form) and — presumably — re-checked wherever it matters server-side.

## 6. Visibility per role

Admin → Entities → *Visibilità* assigns each role one of four increasingly permissive levels:

| Level | Meaning |
|---|---|
| Solo le proprie | Only records the user owns. |
| Le proprie, in sola lettura quelle altrui | Own records fully; others' read-only. |
| Le proprie, in lettura e modifica quelle altrui | Own records fully; others' read + edit. |
| Controllo completo su tutte | Full control over every record. |

## 7. List widgets

Admin → Entities → *Widget* attaches dashboard widgets to an entity, one of three types:

- **Button** — same `button_action` config (`workflow`/`importer`/`javascript`) as a Button field.
- **Counter** — a number, optionally filtered (`filter_column`/`filter_operator`/`filter_value` against one of the entity's real, comparable columns — Button/Table/Blocco Prodotti fields don't qualify).
- **Chart** — `bar`/`line`/`pie`/`doughnut`, grouped by a column (`chart_group_by`) and aggregated (`count`/`sum`/`avg`; `sum`/`avg` require a numeric `chart_value_column`), with the same optional filter as Counter.

## 8. Import / export

An installed entity's schema (tabs/cards/fields, not its data) can be exported to a portable format and re-imported into another installation via Admin → Entities → *Importa/Esporta* — useful for moving an entity design between environments without rebuilding it by hand through the UI.

## 9. Special entity flags

Beyond the tab/card/field structure, an entity itself can be flagged:

- `is_calendar` — its records participate in the calendar module (see [Modules overview](modules-overview.md#calendar)).
- `is_documents` — its records get a document-folder area (see [Modules overview](modules-overview.md#documents)).
- `is_email` — its records are linkable from the webmail module.
- `show_in_menu` / `menu_position` — whether and where it appears in the main navigation.
- `show_in_quick_access` / `quick_access_position` — same, for the quick-access shortcut list.
- `is_system` — marks a package-provided entity as system-owned (behavior around protecting these from casual deletion follows from this flag).

## Where this fits

Once an entity exists, its fields become available as targets throughout the rest of the package: as trigger/action targets in the [workflow builder](building-workflows.md), as import/export targets for the [Importer](modules-overview.md#importer), and as `relatable_models` targets in [Configuration](configuration.md#entities) for relation fields on other entities.
