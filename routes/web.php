<?php

use App\Http\Controllers\Admin\EntityBuilderController;
use App\Http\Controllers\Admin\EntityController;
use App\Http\Controllers\Admin\EntityVisibilityController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\EntityRecordController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences.update');

    Route::prefix('admin')->name('admin.')->middleware('acl')->group(function () {
        Route::get('users/data', [UserController::class, 'data'])->name('users.data');
        Route::resource('users', UserController::class)->except('show');

        Route::get('roles/data', [RoleController::class, 'data'])->name('roles.data');
        Route::get('roles/{role}/permissions', [RoleController::class, 'editPermissions'])->name('roles.permissions.edit');
        Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');
        Route::resource('roles', RoleController::class)->except('show');

        Route::get('translations/data', [TranslationController::class, 'data'])->name('translations.data');
        Route::resource('translations', TranslationController::class)->except('show');

        Route::resource('languages', LanguageController::class)->only(['index', 'store', 'destroy']);

        Route::get('entities/data', [EntityController::class, 'data'])->name('entities.data');
        Route::get('entities/{entity}/builder', [EntityBuilderController::class, 'edit'])->name('entities.builder.edit');
        Route::put('entities/{entity}/builder', [EntityBuilderController::class, 'update'])->name('entities.builder.update');
        Route::post('entities/{entity}/install', [EntityController::class, 'install'])->name('entities.install');
        Route::post('entities/{entity}/uninstall', [EntityController::class, 'uninstall'])->name('entities.uninstall');
        Route::get('entities/{entity}/visibility', [EntityVisibilityController::class, 'edit'])->name('entities.visibility.edit');
        Route::put('entities/{entity}/visibility', [EntityVisibilityController::class, 'update'])->name('entities.visibility.update');
        Route::get('entities/import', [EntityController::class, 'importForm'])->name('entities.import.form');
        Route::post('entities/import', [EntityController::class, 'import'])->name('entities.import');
        Route::get('entities/{entity}/export', [EntityController::class, 'export'])->name('entities.export');
        Route::resource('entities', EntityController::class)->except('show');
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
});
