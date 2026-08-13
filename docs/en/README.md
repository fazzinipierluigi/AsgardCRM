# AsgardCRM

**AsgardCRM** is an extensible entity/workflow CRM — dynamic entities, a workflow engine, a calendar with external connectors, a webmail client, a document manager, and a data importer — distributed as a reusable **Laravel 13 package**. It ships full authentication (classic, SAML, social login, LDAP), an install/update wizard, and admin CRUD for Users, Roles and Login Providers.

- Composer package: `fazzinipierluigi/asgardcrm`
- PHP namespace: `Fazzinipierluigi\AsgardCRM\`
- Service provider: `Fazzinipierluigi\AsgardCRM\AsgardCRMServiceProvider` (auto-discovered)
- Supports **Laravel 13 only**

Install it into an existing Laravel application, or start from [`AsgardCRM-Scaffolding`](https://github.com/fazzinipierluigi/AsgardCRM-Scaffolding), a ready-to-run reference host. See [Installation](installation.md) for both paths.

## What's inside

| Module | What it does |
|---|---|
| **Dynamic entities** | Build custom data models (fields, tabs, cards, relations, visibility per role, list widgets) at runtime, no migrations required per entity. |
| **Workflow engine** | Versioned workflows with nodes, edges, timers, user tasks, variables, and scheduled/triggered execution. |
| **Calendar** | Events, per-user shares, and external calendar connectors kept in sync on a schedule. |
| **Documents** | Folder-based document manager with pluggable storage backends. |
| **Webmail** | IMAP/SMTP mail accounts, OAuth-based providers, folder/message caching, signatures. |
| **Importer** | Scheduled or on-demand data import channels with run history. |
| **Auth** | Classic login, SAML, social login (Socialite), LDAP — all behind one `LoginProvider` abstraction. |
| **Admin** | CRUD for users, roles, login providers, entity/workflow builders, connectors, mail settings, languages/translations. |
| **Install / Update wizard** | Guides a fresh host through first-run setup and version upgrades. |

## Identity: the `crm` short name

The package is called `asgardcrm`, but the internal short name `crm` is intentionally kept everywhere it already existed: `config/crm.php` (and the `config('crm.*')` key), the `crm::` Blade view namespace, every `crm-*` publish tag, every `CRM_*` env var, and the Vite `buildDirectory: 'vendor/crm'`. This is a deliberate scope decision, not an oversight — see [Versioning & changelog](versioning-and-changelog.md).

## Where to go next

- New to the package? Start with [Installation](installation.md).
- Wiring your own `User` model? See [The User model & authentication](user-model-and-auth.md).
- Want a tour of what each module actually does? See [Modules overview](modules-overview.md).
- Building your data model? See [Creating entities](creating-entities.md).
- Automating a process? See [Building workflows](building-workflows.md).
- Setting up calendar sync, documents, webmail, or an importer? See [Calendar](calendar.md), [Documents](documents.md), [Webmail](webmail.md), [Setting up an importer](importer-setup.md).
- Managing users, roles, login providers, or translations? See [Administration](administration.md).
- Contributing to the package itself? See [The two-repo workflow](two-repo-workflow.md) and [Testing](testing.md).
