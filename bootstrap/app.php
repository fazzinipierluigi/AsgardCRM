<?php

use App\Http\Middleware\ApplyUserPreferences;
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
        $middleware->appendToGroup('web', ApplyUserPreferences::class);

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
