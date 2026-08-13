<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('connection_timeout_seconds')->default(10);
            $table->unsignedInteger('max_attachment_size_kb')->default(25600);
            // Plain json (no "encrypted:array" here — no secrets live in
            // this singleton, only global non-sensitive policy).
            $table->json('enabled_protocols')->nullable();
            $table->unsignedInteger('cache_ttl_seconds')->default(60);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
