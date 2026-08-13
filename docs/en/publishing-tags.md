# Publishing tags

All of these are registered by `AsgardCRMServiceProvider` and published with `php artisan vendor:publish --tag=<tag>`. Several tags are deliberately **not** included in a typical first install — see the notes below.

| Tag | Publishes | Auto-included in a typical install? |
|---|---|---|
| `crm-config` | `config/crm.php` | Yes |
| `crm-migrations` | Core package migrations (entities, workflows, calendar, documents, mail, importers, settings, translations, languages, login providers) | Yes |
| `crm-migrations-users` | The 3 migrations that alter your app's own `users` table (`username`, `login_provider_id`, `phone`, `job_title`) | **No** — explicit opt-in only |
| `crm-assets` | Pre-built Vite output into `public/vendor/crm/`, plus AsgardCRM's own brand mark and favicon set into the host's public root (see [Assets & icons](assets-and-icons.md#brand-assets)) | Yes |
| `crm-views` | Package Blade views (the `crm::` namespace) | No — only if you need to override a view |
| `crm-lang` | The 3 custom `auth.provider_*` translation keys | **No** — explicit opt-in only |

## Why some tags are opt-in

- **`crm-migrations-users`** alters a table your host application owns the schema of. A host with its own `users` column names, or one merging AsgardCRM into an app that already has equivalent columns, must not have this applied silently.
- **`crm-lang`** would otherwise silently overwrite a host's own customized `lang/en/auth.php`.

Both are published explicitly, on purpose, so you make the call for your own application.

## Re-publishing after an update

`vendor:publish` never overwrites files that already exist unless you pass `--force`. When you upgrade the package and a tag's underlying files changed (a new migration, an updated compiled asset), re-run the specific tag you need — for example, after a package upgrade that touched compiled front-end assets:

```bash
php artisan vendor:publish --tag=crm-assets --force
```

Be careful with `--force` on `crm-config`, `crm-views`, or `crm-lang` if you've customized the published copies — it will overwrite your changes.
