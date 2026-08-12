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
        Schema::create('connectors', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            // text, not json: the "encrypted:array" cast stores ciphertext
            // here, which would fail a json column's json_valid() CHECK
            // constraint on MariaDB/MySQL — same reasoning as login_providers.
            $table->text('config')->nullable();
            $table->string('sync_direction')->default('bidirectional');
            $table->unsignedInteger('sync_interval_minutes')->default(15);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status')->nullable();
            $table->text('last_sync_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connectors');
    }
};
