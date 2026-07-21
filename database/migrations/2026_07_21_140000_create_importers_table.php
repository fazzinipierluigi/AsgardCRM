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
        Schema::create('importers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('channel');
            // text, not json: the "encrypted:array" cast stores ciphertext
            // here, which would fail a json column's json_valid() CHECK
            // constraint on MariaDB/MySQL — same reasoning as connectors.config.
            $table->text('config')->nullable();
            $table->json('field_mapping')->nullable();
            $table->string('unique_key_field')->nullable();
            $table->string('schedule_type')->default('manual');
            $table->string('cron_expression')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable();
            $table->text('last_run_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('importers');
    }
};
