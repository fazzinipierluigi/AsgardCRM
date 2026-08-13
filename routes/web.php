<?php

use Illuminate\Support\Facades\Route;

// Every route previously here now lives in the fazzinipierluigi/crm-core
// package (Moduli 1-5, see CrmServiceProvider) — vedi
// docs/package-conversion/03-migrazione-moduli.md. `dashboard` stays
// host-owned: it's just a landing view, never tied to any one module.

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});
