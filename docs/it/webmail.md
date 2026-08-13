# Webmail

Il modulo webmail ha un lato amministrativo (connettori e impostazioni a livello di organizzazione) e un lato self-service (ogni utente gestisce i propri account casella postale e firme).

## Impostazioni mail globali

Admin → Impostazioni Mail (un solo record, `MailSetting::current()`):

| Campo | Regola |
|---|---|
| Timeout connessione | 1–120 secondi |
| Dimensione massima allegato | in KB, si applica agli allegati di ogni messaggio in uscita |
| Cache TTL | 0–3600 secondi — per quanto tempo i dati di cartelle/messaggi in cache sono considerati aggiornati prima di risincronizzare |
| Protocolli abilitati | almeno uno tra IMAP / POP3 / Exchange — protocolli che un utente può scegliere creando il proprio account mail |
| Client ID / secret OAuth Google | la registrazione app condivisa usata per la casella postale autenticata Google di ogni utente (vedi account OAuth sotto) |
| Client ID / secret OAuth Microsoft | lo stesso, per Microsoft 365 |

## Connettori mail a livello di organizzazione

Admin → Connettori Mail — la stessa struttura di connettore `exchange_graph` / `exchange_ews` del [modulo calendario](calendar.md#connettori-calendario-esterni) (Tenant ID/Client ID/Client secret per Graph; URL EWS/Username/Password/NTLM per EWS), ma con scope sulla mail invece che sul calendario. L'account mail di un utente può puntare a uno di questi connettori condivisi invece di conservare proprie credenziali IMAP/SMTP — utile per una casella Exchange gestita a livello di organizzazione.

## Account mail (self-service)

Ogni utente gestisce i propri account (Mail → Account → *Nuovo*). Un account ha:

- **Nome**, **Indirizzo email** — obbligatori.
- **Protocollo** — IMAP, POP3 o Exchange, **immutabile una volta creato**.
- **Metodo di autenticazione** — Password, Google (OAuth) o Microsoft 365 (OAuth). I metodi di autenticazione OAuth sono disponibili solo per account **IMAP**.
- **Connettore mail** (opzionale) — collegati a un connettore condiviso dell'organizzazione invece di inserire credenziali dirette (gli account Exchange senza un connettore devono fornire propri URL EWS/username/password).
- **Firma** (opzionale) — una delle firme proprie dell'utente, vedi sotto.

### Set di campi di connessione

Solo il set di campi corrispondente al protocollo/metodo di autenticazione scelto è obbligatorio:

| Set di campi | Quando obbligatorio |
|---|---|
| IMAP (`imap_host`/`port`/`encryption`/`username`/`password`) | Protocollo IMAP **e** metodo di autenticazione Password. Non necessario per un account IMAP OAuth — host/porta provengono invece dalle impostazioni note del provider OAuth. |
| POP3 (`pop3_*`) | Protocollo POP3. |
| Exchange (`exchange_ews_url`/`username`/`password`/`use_ntlm`) | Protocollo Exchange e nessun connettore selezionato. |
| SMTP (`smtp_*`, per l'invio) | Protocollo IMAP o POP3 **e** metodo di autenticazione Password — un account OAuth invia tramite lo stesso token con cui legge, nessuna credenziale SMTP separata. Gli account Exchange inviano sempre tramite EWS/Graph, mai via SMTP. |

In modifica, lasciare vuoto un campo password/secret mantiene il valore già memorizzato; cambiare il metodo di autenticazione ricostruisce da zero la configurazione memorizzata dell'account, quindi non resta nulla di obsoleto dal metodo precedente.

### Account OAuth (Google / Microsoft 365)

L'OAuth segue un modello di consenso per utente sopra la registrazione app amministrativa condivisa (il client ID/secret OAuth Google/Microsoft configurato una sola volta nelle Impostazioni Mail, sopra): ogni utente collega la propria casella postale (`mail/accounts/{mailAccount}/oauth/{provider}/connect`) e concede l'accesso individualmente — la registrazione app condivisa non è mai una credenziale di casella condivisa.

## Firme

Mail → Firme — ogni utente gestisce le proprie firme HTML con nome (`name` + `body_html` rich-text), selezionabili per singolo account mail.

## Invio della posta

Comporre un messaggio (Mail → Componi) richiede:

- L'**account mail** mittente (deve appartenere all'utente corrente).
- Almeno un destinatario **A**; **Cc**/**Ccn** opzionali. Tutti gli indirizzi validati come email reali.
- **Oggetto** e **corpo HTML**.
- **Allegati** opzionali, ciascuno limitato alla dimensione massima allegato configurata (vedi Impostazioni mail globali sopra).
- `in_reply_to`/`references` vengono impostati automaticamente quando si risponde a un thread di messaggi esistente.

## Dove si inserisce

Messaggi e cartelle vengono memorizzati in cache localmente per le prestazioni (`MailMessageCache`/`MailFolderCacheSync`, governati dall'impostazione Cache TTL sopra) — vedi [Panoramica dei moduli](modules-overview.md#webmail) per le relazioni tra i modelli sottostanti.
