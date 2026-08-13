<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only stand-in for the host application's own base `users` table
 * migration — never published (the host owns its own User model/schema
 * per Fase 1 decision 3, see Contracts/CrmUser) — plus the extra
 * columns the 3 `crm-migrations-users` migrations add on a real host,
 * folded into one file here since Testbench has no separate "host" to
 * install them onto in sequence.
 *
 * Needed because ApplicationInstaller::install() runs a real
 * `Artisan::call('migrate')` against a fresh connection (see
 * InstallWizardTest) — a real migration file, not the inline
 * Schema::create() TestCase::setUp() uses for the rest of the suite's
 * shared :memory: connection, which that command never touches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('login_provider_id')->nullable();
            $table->string('provider_identifier')->nullable();
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
