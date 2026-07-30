<p align="center">
  <img src="public/logo.svg" alt="AsgardCRM" width="120">
</p>

# AsgardCRM

AsgardCRM is a self-hosted CRM platform built for teams that need to model their own data, not just fit into someone else's fixed schema.

## Features

- **Custom entities** — build your own record types (contacts, deals, tickets, or anything else) through a visual entity builder, no code required.
- **Workflows** — automate what happens to your records with a visual workflow editor, including timers and conditional branches.
- **Configurable menu** — decide what each user sees in the sidebar and quick-access bar, with drag-and-drop ordering.
- **Roles & permissions** — fine-grained access control down to the individual action, assignable per role.
- **Multiple sign-in options** — username/password, LDAP, SAML, and OAuth-based social login.
- **Multi-language interface** — every piece of UI text is translatable at runtime from the admin panel, no redeploy needed.
- **Calendar** — schedule and visualize events tied to your records.
- **Light & dark themes** — switchable per user.
- **Server-side data grids** — fast, filterable, paginated tables even on large datasets.
- **Automated testing** — the codebase ships with an extensive test suite covering both application logic and browser-driven flows.

## Installation

Requirements: PHP 8.3+, Composer, and Node.js with npm. For the database, we recommend **PostgreSQL, MySQL, or MariaDB** for anything beyond quick local testing — SQLite works too, but isn't recommended for production use.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

npm run build
composer dev
```

Then open the app in your browser. On first visit, an **installation wizard** takes over automatically — no further command-line steps needed:

1. **Requirements check** — confirms your PHP version and file permissions are ready.
2. **Database** — pick your driver (PostgreSQL/MySQL/MariaDB recommended, SQLite for local testing) and enter its connection details; a "Test connection" button verifies them before continuing.
3. **Administrator account** — create the first user, who gets full access to the app.
4. **Review & install** — confirms your choices, writes the configuration, runs the database migrations, and logs you in as the new administrator.

The wizard only ever runs once: after installation completes, visiting it again redirects you away.

### Updating

Deploy the new code (`git pull` or equivalent), then open the app in your browser. If the deployed version differs from the one recorded in the database, an **update wizard** takes over automatically: it refreshes dependencies (composer/npm), runs the database migrations (or rolls them back, if the deployed code is actually older than what the database was last updated to), and records the new version — no manual `composer update`/`npm update`/`migrate` needed.

A rollback only works for a version this same database has already recorded passing through; rolling back further than that is refused rather than guessed at, with a prompt to restore a backup instead.

## Credits

AsgardCRM is built on top of the following open-source libraries.

### Backend (PHP / Composer)

| Library | License |
|---|---|
| [Laravel](https://laravel.com) | MIT |
| [Laravel Socialite](https://laravel.com/docs/socialite) | MIT |
| [Laravel Tinker](https://github.com/laravel/tinker) | MIT |
| [Laravel Dusk](https://laravel.com/docs/dusk) | MIT |
| [Pest](https://pestphp.com) | MIT |
| [LdapRecord-Laravel](https://ldaprecord.com) | MIT |
| [OneLogin PHP SAML](https://github.com/SAML-Toolkits/php-saml) | MIT |
| [Symfony Expression Language](https://symfony.com/components/ExpressionLanguage) | MIT |
| [JSON Logic PHP](https://github.com/JsonLogic/json-logic-php) | MIT |
| [Just A Gate](https://github.com/fazzinipierluigi/just-a-gate) | MIT |
| [Laraccoon Datasource](https://github.com/fazzinipierluigi/laraccoon_datasource) | MIT |
| [Laraccoon Layouts](https://github.com/fazzinipierluigi/laraccoon-layouts) | MIT |

### Frontend (npm)

| Library | License |
|---|---|
| [Tabler](https://tabler.io) | MIT |
| [Tabler Icons](https://tabler.io/icons) | MIT |
| [Bootstrap](https://getbootstrap.com) | MIT |
| [Raccoon Tables](https://github.com/fazzinipierluigi/raccoon-tables) | MIT |
| [Chart.js](https://www.chartjs.org) | MIT |
| [FullCalendar](https://fullcalendar.io) | MIT |
| [maxGraph](https://maxgraph.github.io) | Apache-2.0 |
| [SortableJS](https://sortablejs.github.io/Sortable) | MIT |
| [SweetAlert2](https://sweetalert2.github.io) | MIT |
| [jsonlogic-editor-core](https://github.com/fazzinipierluigi/jsonlogic-editor-core) | MIT |
| [Vite](https://vitejs.dev) | MIT |
| [Tailwind CSS](https://tailwindcss.com) | MIT |
