# Calendar

The calendar is itself an [entity](creating-entities.md) — a special one, seeded with six fixed fields (`is_calendar` flagged) — so events accept custom fields the same way any entity record does, on top of those six. This page covers what's specific to the calendar module: sharing and external connectors.

## Events

Events are created/edited like any entity record, plus:

- **`relatable_type` / `relatable_id`** — an optional polymorphic link to any other record (for example, tying a calendar event to a Ticket or a Preventivo), hardcoded onto every calendar entity's table rather than a configurable relation field.

## Sharing

A user can share their calendar with other users at one of three levels, set per target user (Calendar → Impostazioni → Condivisioni):

| Level | Meaning |
|---|---|
| `none` | No access (removes an existing share). |
| Visualizza (`view`) | The target user can see the events. |
| Modifica (`edit`) | The target user can see and edit the events. |

## External calendar connectors

Admin → Connettori Calendario links the calendar to an external Exchange calendar, keeping both sides in sync on a schedule. A `Connector` has a `type` — **immutable once created**:

| Type | What it is |
|---|---|
| Exchange (Microsoft Graph) | `exchange_graph` — modern Microsoft Graph API. |
| Exchange (EWS on-premise) | `exchange_ews` — legacy Exchange Web Services. |

### Microsoft Graph fields

| Field | Notes |
|---|---|
| Tenant ID, Client ID, Client secret | required — an Azure AD app registration with calendar permissions |

### EWS fields

| Field | Notes |
|---|---|
| EWS URL | required |
| Username / Password | required |
| Use NTLM | boolean, for on-premise servers that require it |

On edit, leaving **Client secret** / **Password** blank keeps the previously stored value.

### Sync settings

Every connector, regardless of type, also has:

| Field | Notes |
|---|---|
| Sync direction | Bidirezionale / Solo importazione / Solo esportazione — see [Modules overview](modules-overview.md#calendar) for the underlying enum |
| Sync interval | 1 to 1440 minutes |
| Mailboxes | which mailboxes (email addresses) this connector actually syncs, configured separately per connector |

The `SyncCalendarConnectors` scheduled command performs the actual sync run, respecting each connector's own interval.

## Where this fits

Since the calendar is entity-backed, everything in [Creating entities](creating-entities.md) applies to it too — custom fields, conditions, role visibility, list widgets. The `is_calendar` flag is what makes an entity behave as a calendar in the first place; see [Creating entities → Special entity flags](creating-entities.md#9-special-entity-flags).
