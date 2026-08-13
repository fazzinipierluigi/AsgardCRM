# Testing

There's no `artisan` in this repository — it's a package, not a runnable application. The test suite runs fully standalone via [Orchestra Testbench](https://packages.tools/testbench.html).

## Running tests

```bash
composer install
vendor/bin/pest
vendor/bin/pest --filter=SomeTestName
```

There's no `make:test` — create the Pest file directly under `tests/Feature/` or `tests/Unit/`, following the structure of sibling files in that directory.

`vendor/bin/testbench` is available if you need an artisan-equivalent command against the package's own sandboxed application (for example, inspecting routes registered by `AsgardCRMServiceProvider` in isolation).

## CI

`.github/workflows/tests.yml` runs PHP 8.3 and 8.4 against Laravel 13, plus `composer audit`.

## Code style

If you've modified any PHP files:

```bash
vendor/bin/pint --dirty --format agent
```

Run it in its default (fixing) mode — not `--test` mode.

## Rules for the package's own suite

The package's test suite must be **100% self-contained** — it must never reference anything that only exists in a consuming host application (a host seeder, a host Blade layout, a host route). See [The two-repo workflow](two-repo-workflow.md#package-suite-isolation) for why this matters and what went wrong the last time it was violated.

Any new test builds its own fixtures — `Entity::create()` + `EntityInstaller`, package-local Blade stubs under `tests/resources/views` — never a host class or view.

## A note on silent test failures

A test run that exits `1` with zero output almost never means what it looks like. `laravel/pao` (agent-friendly test output) can throw `stream_filter_remove(): Unable to flush filter, not removing` and swallow all output when something upstream already fataled. If you hit a silent, zero-output test failure, check `storage/logs/laravel.log` in whichever application is actually running the tests before assuming a mysterious `pao` bug — a real fatal (for example, two migrations sharing the same filename/timestamp between a host's own `database/migrations/` and this package's) is a more likely cause.
