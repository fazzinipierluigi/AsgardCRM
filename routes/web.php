<?php

use App\Http\Controllers\Admin\LoginProviderController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\SamlLoginController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\TicketTimerController;
use App\Http\Controllers\Update\UpdateController;
use Illuminate\Support\Facades\Route;

// Le rotte di Entity/Workflow/Importer (Modulo 1) sono ora registrate
// dal package fazzinipierluigi/crm-core (vedi CrmServiceProvider) —
// vedi docs/package-conversion/03-migrazione-moduli.md.

Route::get('/', function () {
    return redirect()->route('login');
});

Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('database', [InstallController::class, 'database'])->name('database');
    Route::post('database', [InstallController::class, 'storeDatabase'])->name('database.store');
    Route::post('database/test-connection', [InstallController::class, 'testConnection'])->name('database.test');
    Route::get('admin', [InstallController::class, 'admin'])->name('admin');
    Route::post('admin', [InstallController::class, 'storeAdmin'])->name('admin.store');
    Route::get('finish', [InstallController::class, 'finish'])->name('finish');
    Route::post('finish', [InstallController::class, 'run'])->name('run');
});

Route::prefix('update')->name('update.')->group(function () {
    Route::get('/', [UpdateController::class, 'welcome'])->name('welcome');
    Route::post('/', [UpdateController::class, 'run'])->name('run');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('login/{provider:slug}/redirect', [SocialLoginController::class, 'redirect'])->name('login.social.redirect');
    Route::get('login/{provider:slug}/callback', [SocialLoginController::class, 'callback'])->name('login.social.callback');

    Route::get('login/saml/{provider:slug}/redirect', [SamlLoginController::class, 'redirect'])->name('login.saml.redirect');
});

// Reachable by an unauthenticated IdP-initiated POST, so outside the
// `guest` group (which only guards routes meant to bounce logged-in users
// away, not ones an external party needs to reach regardless of session
// state) — and the ACS route is exempted from CSRF in bootstrap/app.php
// since the POST body comes from the IdP, not a form this app rendered.
Route::get('login/saml/{provider:slug}/metadata', [SamlLoginController::class, 'metadata'])->name('login.saml.metadata');
Route::post('login/saml/{provider:slug}/acs', [SamlLoginController::class, 'acs'])->name('login.saml.acs');

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // The Ticket entity's own timer, backing its "Avvia timer"/"Ferma
    // timer" Button fields (see TicketEntitySeeder) — not the generic
    // entities.fields.trigger route, since neither of that controller's
    // two server actions can read now() to stamp a start/stop timestamp.
    // Permission checked by hand against entity_ticket.edit, same
    // pattern as the Calendar/Documenti routes above.
    Route::post('tickets/{record}/timer/start', [TicketTimerController::class, 'start'])->name('tickets.timer.start');
    Route::post('tickets/{record}/timer/stop', [TicketTimerController::class, 'stop'])->name('tickets.timer.stop');

    Route::prefix('admin')->name('admin.')->middleware('acl')->group(function () {
        Route::get('users/data', [UserController::class, 'data'])->name('users.data');
        Route::resource('users', UserController::class)->except('show');

        Route::get('roles/data', [RoleController::class, 'data'])->name('roles.data');
        Route::get('roles/{role}/permissions', [RoleController::class, 'editPermissions'])->name('roles.permissions.edit');
        Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');
        Route::resource('roles', RoleController::class)->except('show');

        Route::get('login-providers/data', [LoginProviderController::class, 'data'])->name('login-providers.data');
        Route::resource('login-providers', LoginProviderController::class)
            ->except('show')
            ->parameters(['login-providers' => 'loginProvider']);

    });
});
