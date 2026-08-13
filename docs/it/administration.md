# Amministrazione

Amministrazione quotidiana della piattaforma: utenti, ruoli e permessi, provider di login, lingue/traduzioni e menu principale. Tutto questo vive nell'area Admin.

## Utenti

Admin → Utenti → *Nuovo*:

| Campo | Regola |
|---|---|
| Nome | obbligatorio |
| Username | obbligatorio, univoco |
| Email | obbligatoria, valida, univoca |
| Telefono / Ruolo lavorativo | opzionali |
| Password | obbligatoria in creazione (confermata, segue la policy password dell'app), opzionale in modifica — lasciala vuota per mantenere quella attuale |
| Ruoli | zero o più ruoli esistenti |
| Provider di login | opzionale — lascialo vuoto per il login locale (password); impostalo per collegare l'utente a un provider LDAP/OAuth/OIDC/SAML (vedi sotto) |
| Identificativo provider | l'identificativo dell'utente lato provider esterno (es. il DN LDAP, il subject OAuth), usato per riconoscerlo al login |

## Ruoli e permessi

Admin → Ruoli:

- **Crea/rinomina un ruolo** — un nome; lo slug (usato internamente e nei controlli sui permessi) è modificabile separatamente (`alpha_dash`, univoco) una volta che il ruolo esiste.
- **Assegna permessi** — a ogni ruolo viene assegnato un insieme di chiavi permesso scelte dall'elenco completo che l'applicazione registra (tabella `permissions`). Non esiste un insieme predefinito documentato qui, poiché cresce con ogni modulo abilitato dall'host — controlla la schermata di modifica dei permessi del ruolo per l'elenco attuale e autorevole.

La **visibilità delle entità per ruolo** si configura per singola entità, non qui — vedi [Creazione delle entità → Visibilità per ruolo](creating-entities.md#6-visibilità-per-ruolo).

## Provider di login

Admin → Provider di login gestisce ogni fonte di autenticazione non locale. Un `LoginProvider` ha un `type` — **immutabile una volta creato** — uno tra:

| Tipo | Cosa è |
|---|---|
| `ldap` | Autenticazione basata su directory tramite `directorytree/ldaprecord-laravel`. |
| `oauth` | OAuth2 generico (copre il social login tramite provider basati su Socialite). |
| `oidc` | OpenID Connect. |
| `saml` | SAML 2.0, tramite `onelogin/php-saml`. |

Esiste sempre un provider **`local`** implicito e non eliminabile (username/password contro la tua tabella `users`) — non viene gestito da questa schermata.

### Campi LDAP

| Campo | Note |
|---|---|
| Host, Porta | obbligatori |
| Base DN | obbligatorio — la base di ricerca nella directory |
| Bind DN / Bind password | account di servizio usato per interrogare la directory (opzionale se è consentito il bind anonimo) |
| Usa TLS | booleano |
| Filtro utente | il filtro LDAP usato per localizzare un utente dal suo login |
| Mappature attributi (username / email / nome) | quali attributi della directory corrispondono a quali campi locali |

### Campi OAuth / OIDC

| Campo | Note |
|---|---|
| Client ID / Client secret | obbligatori |
| URL di autorizzazione / URL token / URL userinfo | obbligatori — gli endpoint OAuth2 propri del provider |
| Scope | separati da spazio o virgola, dipende dal provider |

In modifica, lasciare vuoto **Client secret** mantiene il valore già memorizzato — non viene mai mostrato di nuovo.

### Campi SAML

| Campo | Note |
|---|---|
| IdP Entity ID | obbligatorio |
| IdP SSO URL | obbligatorio |
| Certificato x509 IdP | obbligatorio — il certificato di firma dell'Identity Provider |
| SP Entity ID | override opzionale dell'Entity ID del Service Provider di questa applicazione |

Ricorda che l'endpoint ACS di SAML (`login/saml/{provider:slug}/acs`) necessita di una propria esenzione CSRF nell'host — vedi [Installazione](installation.md#3-collega-i-middleware-nel-tuo-host).

## Lingue e traduzioni

- **Lingue** (Admin → Lingue) — ciascuna ha un `code` (breve, `alpha_dash`, univoco — es. `it`, `en`) e un `name` visualizzato. È anche ciò che alimenta l'opzione dinamica di preferenza `language` descritta in [Configurazione](configuration.md#preferenze-utente).
- **Traduzioni** (Admin → Traduzioni) — ogni voce è una `key` univoca con un valore per ogni lingua configurata (`values`, indicizzato per lingua). Almeno una lingua deve avere un valore non vuoto per salvare una voce. Sia `t()` (l'helper proprio del package, preferito ovunque — vedi le indicazioni del progetto sul sistema di traduzione) sia la traduzione standard di Laravel si risolvono tramite questa tabella per le stringhe di package/applicazione che ci passano attraverso.

## Configurazione del menu

Admin → Menu controlla quali **entità installate** compaiono nella navigazione principale e nell'elenco scorciatoie ad accesso rapido, e in che ordine — sono gli stessi dati `show_in_menu`/`menu_position`/`show_in_quick_access`/`quick_access_position` descritti in [Creazione delle entità → Flag speciali dell'entità](creating-entities.md#9-flag-speciali-dellentità), modificati qui in blocco su tutte le entità invece che una alla volta nelle impostazioni di ciascuna entità.
