<?php

// Package routes are added module by module during Fase 3 of the
// package conversion (see docs/package-conversion/03-migrazione-moduli.md).
// This file currently carries Modulo 1 (Core: Entity + Workflow + Importer)
// e Modulo 2 (Connettori calendario). Route names are unchanged
// dall'originale app/routes/web.php.

use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\ConnectorController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\ConnectorMailboxController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\DocumentStorageController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\LanguageController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\MenuController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\TranslationController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\EntityBuilderController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\MailConnectorController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\MailSettingController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\MailSignatureController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\EntityController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\EntityFieldConditionController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\EntityFieldController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\EntityListWidgetController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\EntityRelationController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\EntityVisibilityController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\ImporterController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\WorkflowApiEndpointController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\WorkflowBuilderController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\WorkflowController;
use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\WorkflowSqlConnectionController;
use Fazzinipierluigi\CrmCore\Http\Controllers\CalendarController;
use Fazzinipierluigi\CrmCore\Http\Controllers\CalendarSettingsController;
use Fazzinipierluigi\CrmCore\Http\Controllers\DocumentController;
use Fazzinipierluigi\CrmCore\Http\Controllers\GlobalSearchController;
use Fazzinipierluigi\CrmCore\Http\Controllers\IconController;
use Fazzinipierluigi\CrmCore\Http\Controllers\MailAccountController;
use Fazzinipierluigi\CrmCore\Http\Controllers\MailController;
use Fazzinipierluigi\CrmCore\Http\Controllers\MailOAuthController;
use Fazzinipierluigi\CrmCore\Http\Controllers\EntityFieldButtonController;
use Fazzinipierluigi\CrmCore\Http\Controllers\EntityListWidgetController as PublicEntityListWidgetController;
use Fazzinipierluigi\CrmCore\Http\Controllers\EntityRecordController;
use Fazzinipierluigi\CrmCore\Http\Controllers\EntityRelationLinkController;
use Fazzinipierluigi\CrmCore\Http\Controllers\SettingsController;
use Fazzinipierluigi\CrmCore\Http\Controllers\TrashController;
use Fazzinipierluigi\CrmCore\Http\Controllers\WorkflowUserTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('search', [GlobalSearchController::class, 'search'])->name('search');

    // NOT "/icons/..." — Apache's stock httpd-autoindex.conf can define a
    // server-wide Alias /icons/ that intercepts that path before it ever
    // reaches Laravel, regardless of vhost/.htaccess rewrite rules.
    Route::get('tabler-icons/{variant}/{name}', [IconController::class, 'show'])
        ->name('icons.show')
        ->where(['variant' => '[a-z]+', 'name' => '[a-z0-9-]+']);

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences.update');

    // The Calendar's own FullCalendar UI — not admin-only, and not the
    // generic entities.* CRUD: permission is checked by hand against
    // entity_calendario.*, same as EntityRecordController does for
    // every other entity.
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('calendar/events', [CalendarController::class, 'events'])->name('calendar.events');
    Route::post('calendar/events', [CalendarController::class, 'store'])->name('calendar.events.store');
    Route::put('calendar/events/{record}', [CalendarController::class, 'update'])->name('calendar.events.update');
    Route::delete('calendar/events/{record}', [CalendarController::class, 'destroy'])->name('calendar.events.destroy');
    Route::get('calendar/relatables', [CalendarController::class, 'relatables'])->name('calendar.relatables');
    Route::get('calendar/settings', [CalendarSettingsController::class, 'edit'])->name('calendar.settings.edit');
    Route::put('calendar/settings/shares', [CalendarSettingsController::class, 'updateShares'])->name('calendar.settings.shares.update');

    Route::prefix('admin')->name('admin.')->middleware('acl')->group(function () {
        Route::get('entities/data', [EntityController::class, 'data'])->name('entities.data');
        Route::get('entities/{entity}/builder', [EntityBuilderController::class, 'edit'])->name('entities.builder.edit');
        Route::put('entities/{entity}/builder', [EntityBuilderController::class, 'update'])->name('entities.builder.update');
        Route::get('entities/{entity}/fields/{field}/usage', [EntityFieldController::class, 'usage'])->name('entities.fields.usage');
        Route::get('entities/{entity}/widgets', [EntityListWidgetController::class, 'index'])->name('entities.widgets.index');
        Route::get('entities/{entity}/widgets/create', [EntityListWidgetController::class, 'create'])->name('entities.widgets.create');
        Route::post('entities/{entity}/widgets', [EntityListWidgetController::class, 'store'])->name('entities.widgets.store');
        Route::get('entities/{entity}/widgets/{widget}/edit', [EntityListWidgetController::class, 'edit'])->name('entities.widgets.edit');
        Route::put('entities/{entity}/widgets/{widget}', [EntityListWidgetController::class, 'update'])->name('entities.widgets.update');
        Route::delete('entities/{entity}/widgets/{widget}', [EntityListWidgetController::class, 'destroy'])->name('entities.widgets.destroy');
        Route::post('entities/{entity}/install', [EntityController::class, 'install'])->name('entities.install');
        Route::post('entities/{entity}/uninstall', [EntityController::class, 'uninstall'])->name('entities.uninstall');
        Route::get('entities/{entity}/visibility', [EntityVisibilityController::class, 'edit'])->name('entities.visibility.edit');
        Route::put('entities/{entity}/visibility', [EntityVisibilityController::class, 'update'])->name('entities.visibility.update');
        Route::get('entities/{entity}/relations', [EntityRelationController::class, 'index'])->name('entities.relations.index');
        Route::get('entities/{entity}/relations/create', [EntityRelationController::class, 'create'])->name('entities.relations.create');
        Route::post('entities/{entity}/relations', [EntityRelationController::class, 'store'])->name('entities.relations.store');
        Route::get('entities/{entity}/relations/{relation}/edit', [EntityRelationController::class, 'edit'])->name('entities.relations.edit');
        Route::put('entities/{entity}/relations/{relation}', [EntityRelationController::class, 'update'])->name('entities.relations.update');
        Route::delete('entities/{entity}/relations/{relation}', [EntityRelationController::class, 'destroy'])->name('entities.relations.destroy');
        Route::get('entities/{entity}/conditions', [EntityFieldConditionController::class, 'index'])->name('entities.conditions.index');
        Route::get('entities/{entity}/conditions/create', [EntityFieldConditionController::class, 'create'])->name('entities.conditions.create');
        Route::post('entities/{entity}/conditions', [EntityFieldConditionController::class, 'store'])->name('entities.conditions.store');
        Route::get('entities/{entity}/conditions/{condition}/edit', [EntityFieldConditionController::class, 'edit'])->name('entities.conditions.edit');
        Route::put('entities/{entity}/conditions/{condition}', [EntityFieldConditionController::class, 'update'])->name('entities.conditions.update');
        Route::delete('entities/{entity}/conditions/{condition}', [EntityFieldConditionController::class, 'destroy'])->name('entities.conditions.destroy');
        Route::get('entities/import', [EntityController::class, 'importForm'])->name('entities.import.form');
        Route::post('entities/import', [EntityController::class, 'import'])->name('entities.import');
        Route::get('entities/{entity}/export', [EntityController::class, 'export'])->name('entities.export');
        Route::resource('entities', EntityController::class)->except('show');

        Route::get('importers/data', [ImporterController::class, 'data'])->name('importers.data');
        Route::post('importers/preview', [ImporterController::class, 'preview'])->name('importers.preview');
        Route::get('importers/{importer}/runs/data', [ImporterController::class, 'runsData'])->name('importers.runs.data');
        Route::post('importers/{importer}/run', [ImporterController::class, 'run'])->name('importers.run');
        // ->except('show') registered before the catch-all {importer} show
        // route below, so the resource's literal 'importers/create' segment
        // isn't swallowed by the {importer} wildcard first.
        Route::resource('importers', ImporterController::class)->except('show');
        Route::get('importers/{importer}', [ImporterController::class, 'show'])->name('importers.show');

        Route::resource('sql-connections', WorkflowSqlConnectionController::class)
            ->except('show')
            ->parameters(['sql-connections' => 'sqlConnection']);
        Route::resource('api-endpoints', WorkflowApiEndpointController::class)
            ->except('show')
            ->parameters(['api-endpoints' => 'apiEndpoint']);

        Route::get('workflows/data', [WorkflowController::class, 'data'])->name('workflows.data');
        Route::get('workflows/import', [WorkflowController::class, 'importForm'])->name('workflows.import.form');
        Route::post('workflows/import', [WorkflowController::class, 'import'])->name('workflows.import');
        Route::get('workflows/{workflow}/export', [WorkflowController::class, 'export'])->name('workflows.export');
        Route::post('workflows/{workflow}/run', [WorkflowController::class, 'run'])->name('workflows.run');
        Route::get('workflows/{workflow}/instances/data', [WorkflowController::class, 'instancesData'])->name('workflows.instances.data');
        Route::get('workflows/{workflow}/builder', [WorkflowBuilderController::class, 'edit'])->name('workflows.builder.edit');
        Route::put('workflows/{workflow}/builder', [WorkflowBuilderController::class, 'update'])->name('workflows.builder.update');
        Route::post('workflows/{workflow}/builder/publish', [WorkflowBuilderController::class, 'publish'])->name('workflows.builder.publish');
        // ->except('show') registered before the catch-all {workflow} show
        // route below, so the resource's literal 'workflows/create' segment
        // isn't swallowed by the {workflow} wildcard first.
        Route::resource('workflows', WorkflowController::class)->except('show');
        Route::get('workflows/{workflow}', [WorkflowController::class, 'show'])->name('workflows.show');

        Route::get('translations/data', [TranslationController::class, 'data'])->name('translations.data');
        Route::resource('translations', TranslationController::class)->except('show');

        Route::resource('languages', LanguageController::class)->only(['index', 'store', 'destroy']);

        Route::get('connectors/data', [ConnectorController::class, 'data'])->name('connectors.data');
        Route::get('connectors/{connector}/mailboxes', [ConnectorMailboxController::class, 'edit'])->name('connectors.mailboxes.edit');
        Route::put('connectors/{connector}/mailboxes', [ConnectorMailboxController::class, 'update'])->name('connectors.mailboxes.update');
        Route::resource('connectors', ConnectorController::class)->except('show');

        Route::get('document-storage', [DocumentStorageController::class, 'edit'])->name('document-storage.edit');
        Route::put('document-storage', [DocumentStorageController::class, 'update'])->name('document-storage.update');

        Route::get('menu', [MenuController::class, 'edit'])->name('menu.edit');
        Route::put('menu', [MenuController::class, 'update'])->name('menu.update');

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

    // The "E-mail" system entity's own webmail UI — self-service, no
    // ACL permission: a user's mailbox accounts are personal, like
    // adding an account in a desktop mail client. 'mail/accounts*'
    // registered before 'mail/{mailAccount}/...' so it can never be
    // swallowed by that wildcard.
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

    // The "Documenti" system entity's own folder-browser UI — same
    // manual entity_documenti.* permission pattern as EntityRecordController.
    // 'documents/upload' and 'documents/folders' registered before the
    // wildcard 'documents/{folder?}' catch-all, or that wildcard would
    // swallow them first.
    Route::get('documents/upload', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('documents/folders', [DocumentController::class, 'storeFolder'])->name('documents.folders.store');
    Route::delete('documents/folders/{folder}', [DocumentController::class, 'destroyFolder'])->name('documents.folders.destroy');
    Route::get('documents/{record}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::put('documents/{record}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{record}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('documents/{record}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{folder?}', [DocumentController::class, 'index'])->name('documents.index');

    // Installed entities' own records — not admin-only. Permission and
    // visibility checks happen manually inside EntityRecordController,
    // since the `acl` middleware can't derive a per-entity key from a
    // single generic controller shared by every entity.
    Route::get('entities/{entity:slug}/data', [EntityRecordController::class, 'data'])->name('entities.data');
    Route::get('entities/{entity:slug}/create', [EntityRecordController::class, 'create'])->name('entities.create');
    Route::post('entities/{entity:slug}', [EntityRecordController::class, 'store'])->name('entities.store');
    Route::get('entities/{entity:slug}', [EntityRecordController::class, 'index'])->name('entities.index');
    Route::get('entities/{entity:slug}/{record}', [EntityRecordController::class, 'show'])->whereNumber('record')->name('entities.show');
    Route::get('entities/{entity:slug}/{record}/edit', [EntityRecordController::class, 'edit'])->name('entities.edit');
    Route::get('entities/{entity:slug}/{record}/workflow-instances/{workflowInstance}', [EntityRecordController::class, 'workflowInstanceGraph'])->name('entities.workflow-instance-graph');
    Route::put('entities/{entity:slug}/{record}', [EntityRecordController::class, 'update'])->name('entities.update');
    Route::delete('entities/{entity:slug}/{record}', [EntityRecordController::class, 'destroy'])->name('entities.destroy');
    Route::post('entities/{entity:slug}/{record}/fields/{field}/trigger', [EntityFieldButtonController::class, 'trigger'])->name('entities.fields.trigger');
    Route::post('entities/{entity:slug}/widgets/{widget}/trigger', [PublicEntityListWidgetController::class, 'trigger'])->name('entities.widgets.trigger');
    Route::get('entities/{entity:slug}/widgets/{widget}/data', [PublicEntityListWidgetController::class, 'data'])->name('entities.widgets.data');
    Route::get('entities/{entity:slug}/{record}/relations/{relation}/data', [EntityRelationLinkController::class, 'data'])->name('entities.relations.data');
    Route::get('entities/{entity:slug}/{record}/relations/{relation}/options', [EntityRelationLinkController::class, 'options'])->name('entities.relations.options');
    Route::post('entities/{entity:slug}/{record}/relations/{relation}/attach', [EntityRelationLinkController::class, 'attach'])->name('entities.relations.attach');
    Route::delete('entities/{entity:slug}/{record}/relations/{relation}/{link}', [EntityRelationLinkController::class, 'detach'])->name('entities.relations.detach');

    // A workflow user task can be assigned to anyone, not just admins —
    // access is checked by hand in WorkflowUserTaskController (the task
    // must be assigned to the current user, or to a role they hold).
    Route::get('workflow-tasks', [WorkflowUserTaskController::class, 'index'])->name('workflow-tasks.index');
    Route::get('workflow-tasks/data', [WorkflowUserTaskController::class, 'data'])->name('workflow-tasks.data');
    Route::get('workflow-tasks/{workflowUserTask}', [WorkflowUserTaskController::class, 'edit'])->name('workflow-tasks.edit');
    Route::put('workflow-tasks/{workflowUserTask}', [WorkflowUserTaskController::class, 'update'])->name('workflow-tasks.update');

    // Il Cestino: permessi globali (trash.show/restore/empty/delete),
    // incrociati per riga/entità con entity_{slug}.delete — vedi
    // TrashController.
    Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
    Route::get('trash/{entity:slug}/data', [TrashController::class, 'data'])->name('trash.data');
    Route::post('trash/{entity:slug}/{record}/restore', [TrashController::class, 'restore'])->name('trash.restore');
    Route::delete('trash/{entity:slug}/{record}', [TrashController::class, 'forceDelete'])->name('trash.force-delete');
    Route::delete('trash/{entity:slug}', [TrashController::class, 'emptyEntity'])->name('trash.empty-entity');
    Route::delete('trash', [TrashController::class, 'emptyAll'])->name('trash.empty-all');
});
