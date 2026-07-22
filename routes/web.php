<?php

use App\Http\Controllers\Admin\ConnectorController;
use App\Http\Controllers\Admin\ConnectorMailboxController;
use App\Http\Controllers\Admin\EntityBuilderController;
use App\Http\Controllers\Admin\EntityController;
use App\Http\Controllers\Admin\EntityFieldController;
use App\Http\Controllers\Admin\EntityVisibilityController;
use App\Http\Controllers\Admin\ImporterController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\LoginProviderController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkflowBuilderController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\SamlLoginController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarSettingsController;
use App\Http\Controllers\EntityRecordController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\IconController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WorkflowUserTaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
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

        Route::get('entities/data', [EntityController::class, 'data'])->name('entities.data');
        Route::get('entities/{entity}/builder', [EntityBuilderController::class, 'edit'])->name('entities.builder.edit');
        Route::put('entities/{entity}/builder', [EntityBuilderController::class, 'update'])->name('entities.builder.update');
        Route::get('entities/{entity}/fields/create', [EntityFieldController::class, 'create'])->name('entities.fields.create');
        Route::post('entities/{entity}/fields', [EntityFieldController::class, 'store'])->name('entities.fields.store');
        Route::post('entities/{entity}/install', [EntityController::class, 'install'])->name('entities.install');
        Route::post('entities/{entity}/uninstall', [EntityController::class, 'uninstall'])->name('entities.uninstall');
        Route::get('entities/{entity}/visibility', [EntityVisibilityController::class, 'edit'])->name('entities.visibility.edit');
        Route::put('entities/{entity}/visibility', [EntityVisibilityController::class, 'update'])->name('entities.visibility.update');
        Route::get('entities/import', [EntityController::class, 'importForm'])->name('entities.import.form');
        Route::post('entities/import', [EntityController::class, 'import'])->name('entities.import');
        Route::get('entities/{entity}/export', [EntityController::class, 'export'])->name('entities.export');
        Route::resource('entities', EntityController::class)->except('show');

        Route::get('connectors/data', [ConnectorController::class, 'data'])->name('connectors.data');
        Route::get('connectors/{connector}/mailboxes', [ConnectorMailboxController::class, 'edit'])->name('connectors.mailboxes.edit');
        Route::put('connectors/{connector}/mailboxes', [ConnectorMailboxController::class, 'update'])->name('connectors.mailboxes.update');
        Route::resource('connectors', ConnectorController::class)->except('show');

        Route::get('importers/data', [ImporterController::class, 'data'])->name('importers.data');
        Route::post('importers/preview', [ImporterController::class, 'preview'])->name('importers.preview');
        Route::get('importers/{importer}/runs/data', [ImporterController::class, 'runsData'])->name('importers.runs.data');
        Route::post('importers/{importer}/run', [ImporterController::class, 'run'])->name('importers.run');
        // ->except('show') registered before the catch-all {importer} show
        // route below, so the resource's literal 'importers/create' segment
        // isn't swallowed by the {importer} wildcard first.
        Route::resource('importers', ImporterController::class)->except('show');
        Route::get('importers/{importer}', [ImporterController::class, 'show'])->name('importers.show');

        Route::get('workflows/data', [WorkflowController::class, 'data'])->name('workflows.data');
        Route::get('workflows/import', [WorkflowController::class, 'importForm'])->name('workflows.import.form');
        Route::post('workflows/import', [WorkflowController::class, 'import'])->name('workflows.import');
        Route::get('workflows/{workflow}/export', [WorkflowController::class, 'export'])->name('workflows.export');
        Route::post('workflows/{workflow}/run', [WorkflowController::class, 'run'])->name('workflows.run');
        Route::get('workflows/{workflow}/instances/data', [WorkflowController::class, 'instancesData'])->name('workflows.instances.data');
        Route::get('workflows/{workflow}/builder', [WorkflowBuilderController::class, 'edit'])->name('workflows.builder.edit');
        Route::put('workflows/{workflow}/builder', [WorkflowBuilderController::class, 'update'])->name('workflows.builder.update');
        // ->except('show') registered before the catch-all {workflow} show
        // route below, so the resource's literal 'workflows/create' segment
        // isn't swallowed by the {workflow} wildcard first.
        Route::resource('workflows', WorkflowController::class)->except('show');
        Route::get('workflows/{workflow}', [WorkflowController::class, 'show'])->name('workflows.show');
    });

    // Installed entities' own records — not admin-only. Permission and
    // visibility checks happen manually inside EntityRecordController,
    // since the `acl` middleware can't derive a per-entity key from a
    // single generic controller shared by every entity.
    Route::get('entities/{entity:slug}/data', [EntityRecordController::class, 'data'])->name('entities.data');
    Route::get('entities/{entity:slug}/create', [EntityRecordController::class, 'create'])->name('entities.create');
    Route::post('entities/{entity:slug}', [EntityRecordController::class, 'store'])->name('entities.store');
    Route::get('entities/{entity:slug}', [EntityRecordController::class, 'index'])->name('entities.index');
    Route::get('entities/{entity:slug}/{record}/edit', [EntityRecordController::class, 'edit'])->name('entities.edit');
    Route::put('entities/{entity:slug}/{record}', [EntityRecordController::class, 'update'])->name('entities.update');
    Route::delete('entities/{entity:slug}/{record}', [EntityRecordController::class, 'destroy'])->name('entities.destroy');

    // A workflow user task can be assigned to anyone, not just admins —
    // access is checked by hand in WorkflowUserTaskController (the task
    // must be assigned to the current user, or to a role they hold).
    Route::get('workflow-tasks', [WorkflowUserTaskController::class, 'index'])->name('workflow-tasks.index');
    Route::get('workflow-tasks/data', [WorkflowUserTaskController::class, 'data'])->name('workflow-tasks.data');
    Route::get('workflow-tasks/{workflowUserTask}', [WorkflowUserTaskController::class, 'edit'])->name('workflow-tasks.edit');
    Route::put('workflow-tasks/{workflowUserTask}', [WorkflowUserTaskController::class, 'update'])->name('workflow-tasks.update');
});
