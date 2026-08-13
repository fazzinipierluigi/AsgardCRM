# Panoramica dei moduli

Un tour di cosa fa realmente ogni parte di AsgardCRM. Per installazione e configurazione vedi [Installazione](installation.md) e [Configurazione](configuration.md).

## Entità dinamiche

Il cuore di AsgardCRM: costruisci modelli dati personalizzati a runtime, senza scrivere una migration per ogni entità. Per una guida pratica, schermata per schermata, del builder, vedi [Creazione delle entità](creating-entities.md). Un'`Entity` possiede:

- **`EntityField`** — campi tipizzati (vedi l'enum `EntityFieldType`) disposti su **`EntityTab`** e **`EntityCard`**, con sequenza, larghezza, blocco e visibilità nascosta per campo.
- **`EntityRelation`** e **`EntityRelationLink`** — collegamenti tra record di entità, o verso altri "modelli relazionabili" configurati (vedi [Configurazione](configuration.md#entità)).
- **`EntityFieldCondition`** e **`EntityFieldConditionTarget`** — visibilità/comportamento condizionale dei campi in base al valore di altri campi.
- **`EntityRoleVisibility`** — controllo della visibilità di un'entità per ruolo.
- **`EntityListWidget`** — widget elenco configurabili per le dashboard.
- **`EntityFieldChange`** e **`VersionHistory`** — tracciamento delle modifiche a livello di campo e di record.

Le entità si gestiscono tramite il builder Admin (`Admin\EntityBuilderController`, `EntityController`, `EntityFieldController`, `EntityRelationController`, `EntityFieldConditionController`, `EntityVisibilityController`, `EntityListWidgetController`), e possono essere installate/disinstallate, esportate e importate. I record vengono serviti tramite `EntityRecordController`, con supporto per campi rich-text (sanificati lato server — vedi la nota di sicurezza in [Versioning e changelog](versioning-and-changelog.md)).

## Motore di workflow

Un motore di workflow versionato. Per una guida pratica al builder a grafo — tipi di nodo, trigger, azioni, semantica bozza/pubblicazione — vedi [Costruzione dei workflow](building-workflows.md). Un `Workflow` ha una o più `WorkflowVersion`, ciascuna composta da `WorkflowNode` collegati da `WorkflowEdge`, con `WorkflowAction` associate ai nodi e `WorkflowVariable` con scope sul workflow.

Eseguire un workflow produce una `WorkflowInstance`, che avanza tramite `WorkflowToken` lungo il grafo. I nodi possono rappresentare:

- **Task utente** (`WorkflowUserTask`) — lavoro assegnato a una persona, tracciato per stato.
- **Timer** (`WorkflowTimer`) — ritardo o ripresa pianificata, innescati dal comando pianificato `FireDueWorkflowTimers`.
- **Task di servizio** — eseguiti tramite un confine di esecuzione collegabile, che disaccoppia l'orchestrazione del motore da come una data azione viene effettivamente eseguita.

`WorkflowApiEndpoint` e `WorkflowSqlConnection` permettono a un workflow di chiamare API HTTP esterne o database. `WorkflowActivityExecution` e `WorkflowNodeExecution` registrano cosa è realmente accaduto, nodo per nodo. Il comando pianificato `RunDueWorkflows` fa avanzare le istanze in scadenza.

I workflow si costruiscono tramite `Admin\WorkflowBuilderController` / `WorkflowController`, più `WorkflowApiEndpointController` e `WorkflowSqlConnectionController` per le connessioni esterne che possono usare.

## Calendario

Per una guida pratica — livelli di condivisione, configurazione connettori, impostazioni di sincronizzazione — vedi [Calendario](calendar.md). Gli eventi (`CalendarEventExternalLink`) possono essere condivisi tra utenti (`CalendarShare`, governato dall'enum `CalendarSharePermission`) e tenuti sincronizzati con calendari esterni tramite `Connector` (enum `ConnectorType`, `ConnectorSyncDirection`), tracciati per utente tramite `ConnectorUserMailbox` e `ConnectorSyncState`. Il comando pianificato `SyncCalendarConnectors` esegue la sincronizzazione. I connettori si configurano tramite `Admin\ConnectorController` e `ConnectorMailboxController`; l'uso quotidiano del calendario passa da `CalendarController` e `CalendarSettingsController`.

## Documenti

Per una guida pratica — configurazione del backend di storage, regole di caricamento — vedi [Documenti](documents.md). Un gestore documentale basato su cartelle (`DocumentFolder`) con backend di storage collegabili (`DocumentStorageSetting`, enum `DocumentStorageType`) configurati tramite `Admin\DocumentStorageController`. Gli utenti finali interagiscono tramite `DocumentController` — caricamento, download, modifica, organizzazione in cartelle.

## Webmail

Per una guida pratica — connettori, account self-service, OAuth, firme — vedi [Webmail](webmail.md). Gli account di posta IMAP/SMTP (`MailAccount`) si collegano tramite `MailConnector` (enum `MailConnectorType`, `MailAccountProtocol`, `MailEncryption`, `MailAuthMethod`), inclusi provider basati su OAuth (enum `MailOAuthProvider`) — l'OAuth qui segue un modello di consenso per utente sopra una registrazione app amministrativa condivisa. Messaggi e cartelle vengono memorizzati in cache localmente (`MailMessageCache`, `MailFolderCacheSync`) per le prestazioni, con `MailSetting` che contiene la configurazione a livello di account e `MailSignature` le firme per utente. Gli utenti finali lavorano tramite `MailController` e `MailAccountController`; gli amministratori configurano connettori e impostazioni tramite `Admin\MailConnectorController`, `MailSettingController` e `MailSignatureController`.

## Importer

Per una guida pratica al wizard di configurazione, vedi [Configurare un importer](importer-setup.md). Canali di importazione dati pianificati o on-demand (`Importer`, enum `ImporterChannel`) con uno storico delle esecuzioni (`ImporterRun`, enum `ImporterRunStatus`) e un tipo di pianificazione (enum `ImporterScheduleType`). Il comando pianificato `RunDueImporters` avvia le importazioni in scadenza; `Admin\ImporterController` configura i canali, e gli endpoint di import/export di `EntityController` alimentano i dati delle entità attraverso la stessa pipeline.

## Autenticazione & Amministrazione

Vedi [Modello User e autenticazione](user-model-and-auth.md) per il quadro completo dei provider di login (classico, SAML, social, LDAP) e del wizard di installazione/aggiornamento, e [Amministrazione](administration.md) per una guida pratica a utenti, ruoli/permessi, campi di configurazione dei provider di login, lingue/traduzioni e configurazione del menu (`Admin\UserController`, `RoleController`, `LanguageController`, `TranslationController`, `MenuController`).

## Componenti di supporto

- **Ricerca globale** — `GlobalSearchController` cerca tra i record di tutte le entità.
- **Cestino** — `TrashController` recupera i record eliminati logicamente (soft-delete).
- **Timer ticket** — `TicketTimerController`, tracciamento del tempo per record di entità di tipo ticket.
- **Comandi di manutenzione manuale** — `BackfillInstalledEntityUpgrades` (eseguito manualmente quando serve, legato al pattern di aggiornamento entità) e `ResetInstallCommand` (azzera il marcatore del wizard di installazione, per lo sviluppo locale).
