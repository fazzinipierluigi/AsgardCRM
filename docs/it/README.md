# AsgardCRM

**AsgardCRM** è un CRM estensibile basato su entità dinamiche e workflow — entità dinamiche, un motore di workflow, un calendario con connettori esterni, un client webmail, un gestore documentale e un importer dati — distribuito come **package Laravel 13** riutilizzabile. Include autenticazione completa (classica, SAML, social login, LDAP), una procedura guidata di installazione/aggiornamento e CRUD amministrativi per Utenti, Ruoli e Provider di login.

- Package Composer: `fazzinipierluigi/asgardcrm`
- Namespace PHP: `Fazzinipierluigi\AsgardCRM\`
- Service provider: `Fazzinipierluigi\AsgardCRM\AsgardCRMServiceProvider` (auto-discovered)
- Supporta **solo Laravel 13**

Puoi installarlo in un'applicazione Laravel esistente oppure partire da [`AsgardCRM-Scaffolding`](https://github.com/fazzinipierluigi/AsgardCRM-Scaffolding), un host di riferimento già pronto all'uso. Vedi [Installazione](installation.md) per entrambi i percorsi.

## Cosa contiene

| Modulo | Cosa fa |
|---|---|
| **Entità dinamiche** | Costruisci modelli dati personalizzati (campi, tab, card, relazioni, visibilità per ruolo, widget elenco) a runtime, senza scrivere una migration per ogni entità. |
| **Motore di workflow** | Workflow versionati con nodi, archi, timer, task utente, variabili ed esecuzione pianificata o innescata da eventi. |
| **Calendario** | Eventi, condivisioni per utente e connettori verso calendari esterni tenuti sincronizzati periodicamente. |
| **Documenti** | Gestore documentale basato su cartelle con backend di storage collegabili. |
| **Webmail** | Account di posta IMAP/SMTP, provider basati su OAuth, cache di cartelle/messaggi, firme. |
| **Importer** | Canali di importazione dati pianificati o on-demand, con storico delle esecuzioni. |
| **Autenticazione** | Login classico, SAML, social login (Socialite), LDAP — tutti dietro un'unica astrazione `LoginProvider`. |
| **Amministrazione** | CRUD per utenti, ruoli, provider di login, builder entità/workflow, connettori, impostazioni mail, lingue/traduzioni. |
| **Wizard installazione/aggiornamento** | Guida un host nuovo attraverso la configurazione iniziale e gli aggiornamenti di versione. |

## L'identità: il nome breve `crm`

Il package si chiama `asgardcrm`, ma il nome breve interno `crm` è mantenuto volutamente ovunque fosse già presente: `config/crm.php` (e la chiave `config('crm.*')`), il namespace Blade `crm::`, ogni tag di pubblicazione `crm-*`, ogni variabile d'ambiente `CRM_*` e il `buildDirectory: 'vendor/crm'` di Vite. È una decisione di scope deliberata, non una svista — vedi [Versioning e changelog](versioning-and-changelog.md).

## Dove andare adesso

- Sei nuovo al package? Inizia da [Installazione](installation.md).
- Devi collegare il tuo modello `User`? Vedi [Modello User e autenticazione](user-model-and-auth.md).
- Vuoi un tour di cosa fa realmente ogni modulo? Vedi [Panoramica dei moduli](modules-overview.md).
- Devi costruire il tuo modello dati? Vedi [Creazione delle entità](creating-entities.md).
- Devi automatizzare un processo? Vedi [Costruzione dei workflow](building-workflows.md).
- Devi configurare sincronizzazione calendario, documenti, webmail o un importer? Vedi [Calendario](calendar.md), [Documenti](documents.md), [Webmail](webmail.md), [Configurare un importer](importer-setup.md).
- Devi gestire utenti, ruoli, provider di login o traduzioni? Vedi [Amministrazione](administration.md).
- Contribuisci al package stesso? Vedi [Il workflow a due repository](two-repo-workflow.md) e [Testing](testing.md).
