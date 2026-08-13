# The two-repo workflow

This page is for anyone developing AsgardCRM **itself**, not just consuming it.

AsgardCRM is developed alongside a reference consumer app, **[AsgardCRM-Scaffolding](https://github.com/fazzinipierluigi/AsgardCRM-Scaffolding)** — expected checked out as a sibling directory, `../AsgardCRM-Scaffolding` relative to this repository. It requires this package through a Composer `path` repository (pointing at `../AsgardCRM`) and has no Auth/Admin/Install code of its own; all of that comes from this package.

## Why a second repo

This package's own test suite (Orchestra Testbench) verifies the package in isolation, but a *reference host* is the only thing that can catch bugs that only appear when the package is actually consumed by a real Laravel application — routing conflicts, published-asset paths, config merging, a middleware group wired differently than a synthetic test app. This project has a documented history of exactly that kind of bug slipping past an isolated package suite.

## The workflow

When changing package code that affects host-visible behavior (routes, views, config defaults, migrations, the `CrmUser` contract, published assets):

1. **Verify in this repo first.** `vendor/bin/pest` must stay green — see [Testing](testing.md).
2. **Verify against a real consumer in Scaffolding.**

   ```bash
   cd ../AsgardCRM-Scaffolding
   composer update fazzinipierluigi/asgardcrm --no-interaction
   ```

   A path repository doesn't auto-refresh — Composer treats it like any other version constraint and needs an explicit update to pick up local changes. Then re-run Scaffolding's own `php artisan test` and, for anything touching a real HTTP flow (auth, the install wizard, a new route), do a real end-to-end check with `php artisan serve` + `curl` — not just the test suite.

3. **Re-publish if needed.** If the change affects published migrations, assets, or config, re-run the relevant `vendor:publish --tag=...` in Scaffolding and commit the republished output there too. Scaffolding ships pre-published files (Breeze/Jetstream-style), not a "run this yourself" instruction for its own users.

If `AsgardCRM-Scaffolding` isn't checked out at `../AsgardCRM-Scaffolding` on your machine, either check it out there or adjust the `path` repository URL in its `composer.json` — don't assume the sibling path is something else.

## What lives where

| | This repo (AsgardCRM) | AsgardCRM-Scaffolding |
|---|---|---|
| Purpose | The Composer package itself | Reference/consumer host, verifies real installs |
| Auth, Admin, Install/Update wizard | Owned here | None of its own — all from this package |
| `User` model | Not here — see [The User model & authentication](user-model-and-auth.md) | Owned here |
| Test suite | Self-contained (Testbench) | Exercises the package as a real dependency |

## Package-suite isolation

The package's own test suite (`tests/`) must be 100% self-contained — no reference to anything host-only. This was violated repeatedly while the app and package used to coexist in one monorepo (tests quietly depended on host-only seeders and host-only Blade views), and it stayed invisible because a shared classpath papered over it. It only surfaced as real `BindingResolutionException`s and false passes once the suite ran genuinely standalone. Any new test builds its own fixtures — never reaches for a host class or view.
