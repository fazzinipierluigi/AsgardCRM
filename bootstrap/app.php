<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // crm.installed/crm.up-to-date are aliases CrmServiceProvider
        // registers for EnsureAppIsInstalled/EnsureAppIsUpToDate — they
        // gate the whole app's lifecycle (not just the package's own
        // routes), so applying them is a host decision, same as before
        // Modulo 5. Not registered for Pest's `testing` env: most
        // feature tests hit routes without going through the wizard
        // first, and the app's real safeguard against being locked out
        // of an existing DB (the self-heal check in
        // EnsureAppIsInstalled) can't kick in on an empty per-test
        // RefreshDatabase schema. Dusk (env=local) does register it —
        // scripts/dusk.sh marks the app installed instead.
        if (! app()->runningUnitTests()) {
            $middleware->appendToGroup('web', 'crm.installed');
            $middleware->appendToGroup('web', 'crm.up-to-date');
        }

        // ApplyUserPreferences is pushed onto the 'web' group directly
        // by CrmServiceProvider — no host wiring needed.

        // The SAML Assertion Consumer Service receives its POST straight
        // from the IdP, which never had a CSRF token from this app.
        $middleware->validateCsrfTokens(except: [
            'login/saml/*/acs',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Widened beyond the original `api/*`-only check: the app has no
        // api/* routes, but does have fetch()-driven JSON endpoints on
        // regular web routes (e.g. CalendarController) that need a JSON
        // 422/403/404 instead of the default redirect-with-flashed-errors
        // behavior meant for traditional form posts.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
