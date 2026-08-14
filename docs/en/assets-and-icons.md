# Assets & icons

## Icons

Icons are always rendered as inline SVG markup — **never** an icon web font — via the `icon()` helper (`src/helpers.php`) and its JS equivalent (`resources/js/icon.js`). Both read directly from the `@tabler/icons` npm package's static SVG files.

Your host application installs `@tabler/icons` itself; the package doesn't ship its own copy for runtime use:

```bash
npm install @tabler/icons
```

`config('crm.icons.path')` defaults to `base_path('node_modules/@tabler/icons/icons')` — override it with the `CRM_ICONS_PATH` env var if your icons live elsewhere (a different install location, a monorepo, etc.).

## Compiled front-end assets

The package ships its own **pre-built** Vite output (`public/vendor/crm/`), published via the `crm-assets` tag — your application's own Vite config and build process are untouched, with no npm dependency merging required. Views load the package's assets via:

```blade
@vite([...], 'vendor/crm')
```

A Composer-installed package can't run the host's `npm run build`, which is why the compiled output is committed and shipped pre-built rather than built from source on install.

### Developing the package itself

If you're working on AsgardCRM's own front-end:

```bash
npm install
npm run build   # or `npm run dev` for HMR
```

Then re-publish the compiled output to any host consuming your local changes:

```bash
php artisan vendor:publish --tag=crm-assets --force
```

### Why `buildDirectory` matters

`vite.config.js`'s `buildDirectory: 'vendor/crm'` must match the `crm-assets` publish target (`public_path('vendor/crm')`) **exactly**. Paths inside the compiled manifest and the font CSS's `@font-face src` URLs are baked in at build time, not resolved at request time — a mismatch would silently serve broken font/asset paths on the consuming host.

There's one known, accepted exception: `Vite::renderFontPreloads()` ignores the `buildDirectory` argument passed to `@vite()` per-call and uses the framework's own default property instead — a confirmed Laravel core limitation, not a package bug. The actual `@font-face` CSS is correct either way; only the `<link rel="preload">` hint points at the wrong path, which is a cosmetic issue only (fonts still load correctly via the real `@font-face` CSS).

## Brand assets

The `crm-assets` tag also publishes AsgardCRM's own brand mark and favicon set straight to the host's **public root** (not `vendor/crm/`) — `logo.svg`, `favicon.ico`, `favicon-16x16.png`, `favicon-32x32.png`, `apple-touch-icon.png`, `site.webmanifest`, `android-chrome-192x192.png`, `android-chrome-512x512.png`. The login page and the install/update wizard views reference these by bare filename (`asset('logo.svg')`, `asset('favicon.ico')`, ...), so they need to land at the host's public root to resolve — publishing `crm-assets` is what makes them appear; a host that skips this tag (or runs an old copy of it from before this was added) sees a broken image icon on those pages instead.

## Third-party package view overrides

`crm-assets` also publishes `resources/views/vendor/` into the host's own `resource_path('views/vendor/...')` — currently just `fazzinipierluigi/laraccoon-layouts`' dropdown view (the per-grid saved-layout selector), themed to match Tabler (`form-select`, a real Bootstrap `dropdown-menu`, Tom-Select-aware option handling) instead of that package's own unstyled BEM-only markup, which it ships on purpose expecting a host to override. Laravel's view finder checks `resource_path('views/vendor/{namespace}/...')` before a package's own registered view path for **any** namespace — not just AsgardCRM's own `crm::` one — so publishing straight into that host tree is what makes the override take effect at all. If a future dependency needs the same treatment, add its override under `resources/views/vendor/{that-package's-namespace}/` in this repo and it publishes automatically through the same `crm-assets` entry.

