# Webmail

The webmail module has an admin side (organization-wide connectors and settings) and a self-service side (each user manages their own mailbox accounts and signatures).

## Global mail settings

Admin → Impostazioni Mail (one record, `MailSetting::current()`):

| Field | Rule |
|---|---|
| Connection timeout | 1–120 seconds |
| Max attachment size | in KB, applies to every outgoing message's attachments |
| Cache TTL | 0–3600 seconds — how long cached folder/message data is considered fresh before re-syncing |
| Enabled protocols | at least one of IMAP / POP3 / Exchange — protocols a user is allowed to pick when creating their own mail account |
| Google OAuth client ID / secret | the shared app registration used for every user's Google-authenticated mailbox (see OAuth accounts below) |
| Microsoft OAuth client ID / secret | same, for Microsoft 365 |

## Organization-wide mail connectors

Admin → Connettori Mail — the same `exchange_graph` / `exchange_ews` connector shape as the [calendar module](calendar.md#external-calendar-connectors) (Tenant ID/Client ID/Client secret for Graph; EWS URL/Username/Password/NTLM for EWS), but scoped to mail rather than calendar. A user's mail account can point at one of these shared connectors instead of holding its own IMAP/SMTP credentials — useful for an organization-managed Exchange mailbox.

## Mail accounts (self-service)

Each user manages their own accounts (Mail → Account → *Nuovo*). An account has:

- **Name**, **Email address** — required.
- **Protocol** — IMAP, POP3, or Exchange, **immutable once created**.
- **Auth method** — Password, Google (OAuth), or Microsoft 365 (OAuth). OAuth auth methods are only available for **IMAP** accounts.
- **Mail connector** (optional) — bind to a shared organization connector instead of entering credentials directly (Exchange accounts without a connector must supply their own EWS URL/username/password).
- **Signature** (optional) — one of the user's own signatures, see below.

### Connection fieldsets

Only the fieldset matching the chosen protocol/auth method is required:

| Fieldset | When required |
|---|---|
| IMAP (`imap_host`/`port`/`encryption`/`username`/`password`) | Protocol is IMAP **and** auth method is Password. Not needed for an OAuth IMAP account — host/port come from the OAuth provider's own known settings instead. |
| POP3 (`pop3_*`) | Protocol is POP3. |
| Exchange (`exchange_ews_url`/`username`/`password`/`use_ntlm`) | Protocol is Exchange and no connector is selected. |
| SMTP (`smtp_*`, for sending) | Protocol is IMAP or POP3 **and** auth method is Password — an OAuth account sends through the same token it reads with, no separate SMTP credentials. Exchange accounts always send through EWS/Graph, never SMTP. |

On edit, leaving a password/secret field blank keeps the previously stored value; switching auth method rebuilds the account's stored config from scratch, so nothing stale carries over from the previous method.

### OAuth accounts (Google / Microsoft 365)

OAuth follows a per-user consent model on top of the shared admin app registration (the Google/Microsoft OAuth client ID/secret configured once in Mail Settings, above): each user connects their own mailbox (`mail/accounts/{mailAccount}/oauth/{provider}/connect`) and grants access individually — the shared app registration is never a shared mailbox credential.

## Signatures

Mail → Firme — each user manages their own named HTML signatures (`name` + rich-text `body_html`), selectable per mail account.

## Sending mail

Composing a message (Mail → Componi) requires:

- The sending **mail account** (must belong to the current user).
- At least one **To** recipient; **Cc**/**Bcc** optional. All addresses validated as real email addresses.
- **Subject** and **HTML body**.
- Optional **attachments**, each capped at the configured max attachment size (see Global mail settings above).
- `in_reply_to`/`references` are set automatically when replying to an existing message thread.

## Where this fits

Messages and folders are cached locally for performance (`MailMessageCache`/`MailFolderCacheSync`, governed by the Cache TTL setting above) — see [Modules overview](modules-overview.md#webmail) for the underlying model relationships.
