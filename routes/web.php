<?php

use App\Http\Controllers\Admin\ConnectorController;
use App\Http\Controllers\Admin\ConnectorMailboxController;
use App\Http\Controllers\Admin\DocumentStorageController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\LoginProviderController;
use App\Http\Controllers\Admin\MailConnectorController;
use App\Http\Controllers\Admin\MailSettingController;
use App\Http\Controllers\Admin\MailSignatureController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\SamlLoginController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarSettingsController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\IconController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\MailAccountController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\MailOAuthController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TicketTimerController;
use App\Http\Controllers\TrashController;
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

    Route::get('search', [GlobalSearchController::class, 'search'])->name('search');

    // NOT "/icons/..." — Apache's stock httpd-autoindex.conf defines a
    // server-wide `Alias /icons/ "/usr/share/httpd/icons/"` (FancyIndexing
    // icons) that intercepts that path before it ever reaches Laravel,
    // regardless of vhost/.htaccess rewrite rules. Confirmed via the vhost
    // access/error log: Apache 404s straight from
    // /usr/share/httpd/icons/..., never invoking index.php.
    Route::get('tabler-icons/{variant}/{name}', [IconController::class, 'show'])
        ->name('icons.show')
        ->where(['variant' => '[a-z]+', 'name' => '[a-z0-9-]+']);

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences.update');

    // The Calendar's own FullCalendar UI — not admin-only, and not the
    // generic entities.* CRUD (see CalendarController): permission is
    // checked by hand against entity_calendario.*, same as
    // EntityRecordController does for every other entity.
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('calendar/events', [CalendarController::class, 'events'])->name('calendar.events');
    Route::post('calendar/events', [CalendarController::class, 'store'])->name('calendar.events.store');
    Route::put('calendar/events/{record}', [CalendarController::class, 'update'])->name('calendar.events.update');
    Route::delete('calendar/events/{record}', [CalendarController::class, 'destroy'])->name('calendar.events.destroy');
    Route::get('calendar/relatables', [CalendarController::class, 'relatables'])->name('calendar.relatables');
    Route::get('calendar/settings', [CalendarSettingsController::class, 'edit'])->name('calendar.settings.edit');
    Route::put('calendar/settings/shares', [CalendarSettingsController::class, 'updateShares'])->name('calendar.settings.shares.update');

    // The "Documenti" system entity's own folder-browser UI — same
    // manual entity_documenti.* permission pattern as the Calendar
    // above. 'documents/upload' and 'documents/folders' are literal
    // segments registered before the wildcard 'documents/{folder?}'
    // catch-all, or that wildcard would swallow them first (same
    // ordering gotcha as the importers/workflows routes below).
    Route::get('documents/upload', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('documents/folders', [DocumentController::class, 'storeFolder'])->name('documents.folders.store');
    Route::delete('documents/folders/{folder}', [DocumentController::class, 'destroyFolder'])->name('documents.folders.destroy');
    Route::get('documents/{record}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::put('documents/{record}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{record}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('documents/{record}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{folder?}', [DocumentController::class, 'index'])->name('documents.index');

    // The "E-mail" system entity's own webmail UI — self-service, no
    // ACL permission (see MailAccountController's docblock): a user's
    // mailbox accounts are personal, like adding an account in a
    // desktop mail client. 'mail/accounts*' registered before
    // 'mail/{mailAccount}/...' so it can never be swallowed by that
    // wildcard (same ordering gotcha as documents/importers/workflows).
    Route::get('mail/accounts', [MailAccountController::class, 'index'])->name('mail.accounts.index');
    Route::get('mail/accounts/create', [MailAccountController::class, 'create'])->name('mail.accounts.create');
    Route::post('mail/accounts', [MailAccountController::class, 'store'])->name('mail.accounts.store');
    Route::get('mail/accounts/{mailAccount}/edit', [MailAccountController::class, 'edit'])->name('mail.accounts.edit');
    Route::put('mail/accounts/{mailAccount}', [MailAccountController::class, 'update'])->name('mail.accounts.update');
    Route::delete('mail/accounts/{mailAccount}', [MailAccountController::class, 'destroy'])->name('mail.accounts.destroy');

    // "Connetti con Google/Microsoft" (see MailAuthMethod/MailOAuthService)
    // — connect() is scoped under mail/accounts/{mailAccount}/... like the
    // rest of that CRUD; callback() has no account id in its URL at all
    // (Google/Microsoft only ever redirect back to one fixed, provider-
    // registered URI), the account is instead resolved from the signed
    // `state` param MailOAuthService::authorizeUrl() generates.
    Route::get('mail/accounts/{mailAccount}/oauth/{provider}/connect', [MailOAuthController::class, 'connect'])->name('mail.oauth.connect');
    Route::get('mail/oauth/{provider}/callback', [MailOAuthController::class, 'callback'])->name('mail.oauth.callback');

    Route::get('mail/compose', [MailController::class, 'compose'])->name('mail.compose');
    Route::post('mail/send', [MailController::class, 'send'])->name('mail.send');
    Route::get('mail', [MailController::class, 'index'])->name('mail.index');
    Route::get('mail/{mailAccount}/folders', [MailController::class, 'folders'])->name('mail.folders');
    Route::get('mail/{mailAccount}/messages', [MailController::class, 'messages'])->name('mail.messages');
    Route::get('mail/{mailAccount}/messages/show', [MailController::class, 'show'])->name('mail.messages.show');
    Route::get('mail/{mailAccount}/messages/attachment', [MailController::class, 'attachmentDownload'])->name('mail.messages.attachment');
    Route::get('mail/{mailAccount}/messages/reply', [MailController::class, 'reply'])->name('mail.messages.reply');
    Route::get('mail/{mailAccount}/messages/forward', [MailController::class, 'forward'])->name('mail.messages.forward');
    Route::post('mail/{mailAccount}/attach', [MailController::class, 'attach'])->name('mail.attach');

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

        Route::get('translations/data', [TranslationController::class, 'data'])->name('translations.data');
        Route::resource('translations', TranslationController::class)->except('show');

        Route::resource('languages', LanguageController::class)->only(['index', 'store', 'destroy']);

        Route::get('menu', [MenuController::class, 'edit'])->name('menu.edit');
        Route::put('menu', [MenuController::class, 'update'])->name('menu.update');

        Route::get('document-storage', [DocumentStorageController::class, 'edit'])->name('document-storage.edit');
        Route::put('document-storage', [DocumentStorageController::class, 'update'])->name('document-storage.update');

        Route::get('connectors/data', [ConnectorController::class, 'data'])->name('connectors.data');
        Route::get('connectors/{connector}/mailboxes', [ConnectorMailboxController::class, 'edit'])->name('connectors.mailboxes.edit');
        Route::put('connectors/{connector}/mailboxes', [ConnectorMailboxController::class, 'update'])->name('connectors.mailboxes.update');
        Route::resource('connectors', ConnectorController::class)->except('show');

        Route::get('mail-settings', [MailSettingController::class, 'edit'])->name('mail-settings.edit');
        Route::put('mail-settings', [MailSettingController::class, 'update'])->name('mail-settings.update');

        Route::get('mail-connectors/data', [MailConnectorController::class, 'data'])->name('mail-connectors.data');
        Route::resource('mail-connectors', MailConnectorController::class)
            ->except('show')
            ->parameters(['mail-connectors' => 'mailConnector']);

        Route::get('mail-signatures/data', [MailSignatureController::class, 'data'])->name('mail-signatures.data');
        Route::resource('mail-signatures', MailSignatureController::class)
            ->except('show')
            ->parameters(['mail-signatures' => 'mailSignature']);

    });

    // Il Cestino: permessi globali (trash.show/restore/empty/delete),
    // incrociati per riga/entità con entity_{slug}.delete — vedi
    // TrashController. Stesso motivo dei controller sopra: nessun
    // middleware `acl` possibile su un controller condiviso da tutte le
    // entità.
    Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
    Route::get('trash/{entity:slug}/data', [TrashController::class, 'data'])->name('trash.data');
    Route::post('trash/{entity:slug}/{record}/restore', [TrashController::class, 'restore'])->name('trash.restore');
    Route::delete('trash/{entity:slug}/{record}', [TrashController::class, 'forceDelete'])->name('trash.force-delete');
    Route::delete('trash/{entity:slug}', [TrashController::class, 'emptyEntity'])->name('trash.empty-entity');
    Route::delete('trash', [TrashController::class, 'emptyAll'])->name('trash.empty-all');
});
