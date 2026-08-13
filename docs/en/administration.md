# Administration

Day-to-day platform administration: users, roles and permissions, login providers, languages/translations, and the main menu. All of this lives under the Admin area.

## Users

Admin → Utenti → *Nuovo*:

| Field | Rule |
|---|---|
| Name | required |
| Username | required, unique |
| Email | required, valid, unique |
| Phone / Job title | optional |
| Password | required on creation (confirmed, follows the app's password policy), optional on edit — leave blank to keep the current one |
| Roles | zero or more existing roles |
| Login provider | optional — leave unset for local (password) login; set to bind this user to an LDAP/OAuth/OIDC/SAML provider (see below) |
| Provider identifier | the user's identifier on the external provider side (e.g. the LDAP DN, the OAuth subject), used to match them on login |

## Roles & permissions

Admin → Ruoli:

- **Create/rename a role** — a name; the slug (used internally and in permission checks) is editable separately (`alpha_dash`, unique) once the role exists.
- **Assign permissions** — each role gets a set of permission keys picked from the full list the application registers (`permissions` table). There's no bundled default set documented here since it grows with every module a host enables — check the role's permission-editing screen for the current, authoritative list.

Role-based **entity visibility** is configured per-entity, not here — see [Creating entities → Visibility per role](creating-entities.md#6-visibility-per-role).

## Login providers

Admin → Provider di login manages every non-local authentication source. A `LoginProvider` has a `type` — **immutable once created** — one of:

| Type | What it is |
|---|---|
| `ldap` | Directory-based authentication via `directorytree/ldaprecord-laravel`. |
| `oauth` | Generic OAuth2 (covers social login via Socialite-backed providers). |
| `oidc` | OpenID Connect. |
| `saml` | SAML 2.0, via `onelogin/php-saml`. |

There's always an implicit, non-deletable **`local`** provider (username/password against your own `users` table) — it isn't managed from this screen.

### LDAP fields

| Field | Notes |
|---|---|
| Host, Port | required |
| Base DN | required — the directory search base |
| Bind DN / Bind password | service account used to search the directory (optional if anonymous bind is allowed) |
| Use TLS | boolean |
| User filter | the LDAP filter used to locate a user by their login |
| Attribute mappings (username / email / name) | which directory attributes map to which local fields |

### OAuth / OIDC fields

| Field | Notes |
|---|---|
| Client ID / Client secret | required |
| Authorize URL / Token URL / Userinfo URL | required — the provider's own OAuth2 endpoints |
| Scopes | space- or comma-separated, provider-dependent |

On edit, leaving **Client secret** blank keeps the previously stored value — it's never displayed back.

### SAML fields

| Field | Notes |
|---|---|
| IdP Entity ID | required |
| IdP SSO URL | required |
| IdP x509 certificate | required — the Identity Provider's signing certificate |
| SP Entity ID | optional override of this application's own Service Provider entity ID |

Remember the SAML ACS endpoint (`login/saml/{provider:slug}/acs`) needs its own CSRF exemption on the host — see [Installation](installation.md#3-wire-the-middleware-your-host-applies).

## Languages & translations

- **Languages** (Admin → Lingue) — each has a `code` (short, `alpha_dash`, unique — e.g. `it`, `en`) and a display `name`. This is also what powers the dynamic `language` preference option described in [Configuration](configuration.md#user-preferences).
- **Translations** (Admin → Traduzioni) — each entry is a unique `key` with one value per configured language (`values`, keyed by language). At least one language must have a non-empty value to save an entry. Both `t()` (the package's own helper, preferred throughout — see the project's translation-system guidance) and standard Laravel translation resolve through this table for package/application strings that go through it.

## Menu configuration

Admin → Menu controls which **installed entities** show up in the main navigation and the quick-access shortcut list, and in what order — this is the same `show_in_menu`/`menu_position`/`show_in_quick_access`/`quick_access_position` data described in [Creating entities → Special entity flags](creating-entities.md#9-special-entity-flags), edited here in bulk across every entity rather than one at a time on each entity's own settings.
