# Modules overview

A tour of what each part of AsgardCRM actually does. For installation and configuration, see [Installation](installation.md) and [Configuration](configuration.md).

## Dynamic entities

The core of AsgardCRM: build custom data models at runtime, without writing a migration per entity. For a hands-on, screen-by-screen walkthrough of the builder, see [Creating entities](creating-entities.md). An `Entity` owns:

- **`EntityField`s** — typed fields (see `EntityFieldType` enum) laid out on **`EntityTab`s** and **`EntityCard`s**, with per-field sequence, width, lock, and hidden flags.
- **`EntityRelation`s** and **`EntityRelationLink`s** — links between entity records, or to other configured "relatable models" (see [Configuration](configuration.md#entities)).
- **`EntityFieldCondition`s** and **`EntityFieldConditionTarget`s** — conditional field visibility/behavior driven by other fields' values.
- **`EntityRoleVisibility`** — per-role visibility control over an entity.
- **`EntityListWidget`s** — configurable list widgets for dashboards.
- **`EntityFieldChange`** and **`VersionHistory`** — field-level and record-level change tracking.

Entities are managed through the Admin entity builder (`Admin\EntityBuilderController`, `EntityController`, `EntityFieldController`, `EntityRelationController`, `EntityFieldConditionController`, `EntityVisibilityController`, `EntityListWidgetController`), and can be installed/uninstalled, exported, and imported. Records are served through `EntityRecordController` with rich-text field support (sanitized server-side — see the security note in [Versioning & changelog](versioning-and-changelog.md)).

## Workflow engine

A versioned workflow engine. For a hands-on walkthrough of the graph builder — node types, triggers, actions, draft/publish semantics — see [Building workflows](building-workflows.md). A `Workflow` has one or more `WorkflowVersion`s, each made of `WorkflowNode`s connected by `WorkflowEdge`s, with `WorkflowAction`s attached to nodes and `WorkflowVariable`s scoped to the workflow.

Running a workflow produces a `WorkflowInstance`, which advances via `WorkflowToken`s through the graph. Nodes can represent:

- **User tasks** (`WorkflowUserTask`) — work assigned to a person, tracked by status.
- **Timers** (`WorkflowTimer`) — delay or scheduled resumption, fired by the `FireDueWorkflowTimers` scheduled command.
- **Service tasks** — executed through a pluggable task-execution boundary, decoupling the engine's orchestration from how a given action actually runs.

`WorkflowApiEndpoint` and `WorkflowSqlConnection` let a workflow call out to external HTTP APIs or databases. `WorkflowActivityExecution` and `WorkflowNodeExecution` record what actually happened, node by node. The `RunDueWorkflows` scheduled command advances instances that are due.

Workflows are built through `Admin\WorkflowBuilderController` / `WorkflowController`, plus `WorkflowApiEndpointController` and `WorkflowSqlConnectionController` for the external connections they can use.

## Calendar

For a hands-on walkthrough — sharing levels, connector setup, sync settings — see [Calendar](calendar.md). Events (`CalendarEventExternalLink`) can be shared between users (`CalendarShare`, governed by the `CalendarSharePermission` enum) and kept in sync with external calendars through `Connector`s (`ConnectorType`, `ConnectorSyncDirection` enums), tracked per-user via `ConnectorUserMailbox` and `ConnectorSyncState`. The `SyncCalendarConnectors` scheduled command runs the sync. Connectors are configured through `Admin\ConnectorController` and `ConnectorMailboxController`; day-to-day calendar use goes through `CalendarController` and `CalendarSettingsController`.

## Documents

For a hands-on walkthrough — storage backend setup, upload rules — see [Documents](documents.md). A folder-based document manager (`DocumentFolder`) with pluggable storage backends (`DocumentStorageSetting`, `DocumentStorageType` enum) configured through `Admin\DocumentStorageController`. End users interact with it through `DocumentController` — upload, download, edit, organize into folders.

## Webmail

For a hands-on walkthrough — connectors, self-service accounts, OAuth, signatures — see [Webmail](webmail.md). IMAP/SMTP mail accounts (`MailAccount`) connect through `MailConnector`s (`MailConnectorType`, `MailAccountProtocol`, `MailEncryption`, `MailAuthMethod` enums), including OAuth-based providers (`MailOAuthProvider` enum) — OAuth here follows a per-user consent model on top of a shared admin app registration. Messages and folders are cached locally (`MailMessageCache`, `MailFolderCacheSync`) for performance, with `MailSetting` holding account-level configuration and `MailSignature` per-user signatures. End users work through `MailController` and `MailAccountController`; admins configure connectors and settings through `Admin\MailConnectorController`, `MailSettingController`, and `MailSignatureController`.

## Importer

For a hands-on walkthrough of the setup wizard, see [Setting up an importer](importer-setup.md). Scheduled or on-demand data import channels (`Importer`, `ImporterChannel` enum) with a run history (`ImporterRun`, `ImporterRunStatus` enum) and a schedule type (`ImporterScheduleType` enum). The `RunDueImporters` scheduled command triggers due imports; `Admin\ImporterController` configures channels, and `EntityController`'s import/export endpoints feed entity data through the same pipeline.

## Auth & Admin

See [The User model & authentication](user-model-and-auth.md) for the full picture of login providers (classic, SAML, social, LDAP) and the install/update wizard, and [Administration](administration.md) for a hands-on walkthrough of users, roles/permissions, login-provider configuration fields, languages/translations, and menu setup (`Admin\UserController`, `RoleController`, `LanguageController`, `TranslationController`, `MenuController`).

## Supporting pieces

- **Global search** — `GlobalSearchController` searches across entity records.
- **Trash** — `TrashController` recovers soft-deleted records.
- **Ticket timers** — `TicketTimerController`, time tracking for ticket-style entity records.
- **Manual maintenance commands** — `BackfillInstalledEntityUpgrades` (run by hand when needed, tied to the entity-upgrade pattern) and `ResetInstallCommand` (clears the install-wizard marker, for local development).
