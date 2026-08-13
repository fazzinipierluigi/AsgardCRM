# Calendario

Il calendario è esso stesso un'[entità](creating-entities.md) — speciale, seminata con sei campi fissi (contrassegnata `is_calendar`) — quindi gli eventi accettano campi personalizzati esattamente come qualsiasi record di entità, in aggiunta a quei sei. Questa pagina copre ciò che è specifico del modulo calendario: condivisione e connettori esterni.

## Eventi

Gli eventi si creano/modificano come qualsiasi record di entità, più:

- **`relatable_type` / `relatable_id`** — un collegamento polimorfico opzionale a qualsiasi altro record (ad esempio, per legare un evento calendario a un Ticket o un Preventivo), fissato "a codice" sulla tabella di ogni entità calendario invece di essere un campo relazione configurabile.

## Condivisione

Un utente può condividere il proprio calendario con altri utenti a uno di tre livelli, impostato per singolo utente destinatario (Calendario → Impostazioni → Condivisioni):

| Livello | Significato |
|---|---|
| `none` | Nessun accesso (rimuove una condivisione esistente). |
| Visualizza (`view`) | L'utente destinatario può vedere gli eventi. |
| Modifica (`edit`) | L'utente destinatario può vedere e modificare gli eventi. |

## Connettori calendario esterni

Admin → Connettori Calendario collega il calendario a un calendario Exchange esterno, mantenendo entrambi i lati sincronizzati periodicamente. Un `Connector` ha un `type` — **immutabile una volta creato**:

| Tipo | Cosa è |
|---|---|
| Exchange (Microsoft Graph) | `exchange_graph` — API Microsoft Graph moderna. |
| Exchange (EWS on-premise) | `exchange_ews` — Exchange Web Services legacy. |

### Campi Microsoft Graph

| Campo | Note |
|---|---|
| Tenant ID, Client ID, Client secret | obbligatori — una registrazione app Azure AD con permessi calendario |

### Campi EWS

| Campo | Note |
|---|---|
| URL EWS | obbligatorio |
| Username / Password | obbligatori |
| Usa NTLM | booleano, per server on-premise che lo richiedono |

In modifica, lasciare vuoto **Client secret** / **Password** mantiene il valore già memorizzato.

### Impostazioni di sincronizzazione

Ogni connettore, a prescindere dal tipo, ha inoltre:

| Campo | Note |
|---|---|
| Direzione sincronizzazione | Bidirezionale / Solo importazione / Solo esportazione — vedi [Panoramica dei moduli](modules-overview.md#calendario) per l'enum sottostante |
| Intervallo sincronizzazione | da 1 a 1440 minuti |
| Mailbox | quali mailbox (indirizzi email) questo connettore sincronizza realmente, configurate separatamente per connettore |

Il comando pianificato `SyncCalendarConnectors` esegue la sincronizzazione vera e propria, rispettando l'intervallo proprio di ciascun connettore.

## Dove si inserisce

Poiché il calendario è basato su entità, tutto ciò che vale in [Creazione delle entità](creating-entities.md) si applica anche a esso — campi personalizzati, condizioni, visibilità per ruolo, widget elenco. Il flag `is_calendar` è ciò che fa comportare un'entità come calendario fin dall'inizio; vedi [Creazione delle entità → Flag speciali dell'entità](creating-entities.md#9-flag-speciali-dellentità).
