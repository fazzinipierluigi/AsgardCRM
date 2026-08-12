<?php

// Package routes are added module by module during Fase 3 of the
// package conversion (see docs/package-conversion/03-migrazione-moduli.md).
// This file currently carries Modulo 1 (Core: Entity + Workflow + Importer).
// Route names are unchanged from the original app/routes/web.php so
// existing route() calls/links keep working once the app is wired to
// the package (Fase 3, step 6).

use Fazzinipierluigi\CrmCore\Http\Controllers\Admin\EntityBuilderController;
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
use Fazzinipierluigi\CrmCore\Http\Controllers\EntityFieldButtonController;
use Fazzinipierluigi\CrmCore\Http\Controllers\EntityListWidgetController as PublicEntityListWidgetController;
use Fazzinipierluigi\CrmCore\Http\Controllers\EntityRecordController;
use Fazzinipierluigi\CrmCore\Http\Controllers\EntityRelationLinkController;
use Fazzinipierluigi\CrmCore\Http\Controllers\WorkflowUserTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
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
    });

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
});
