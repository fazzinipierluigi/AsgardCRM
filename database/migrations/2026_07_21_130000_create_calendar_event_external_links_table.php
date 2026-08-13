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
        Schema::create('calendar_event_external_links', function (Blueprint $table) {
            $table->id();
            // No DB-level FK to entity_calendario: unlike every other table
            // here, that one isn't created by a migration — it's built at
            // runtime by EntitySchemaBuilder when CalendarEntitySeeder
            // installs the Calendar entity, which normally happens *after*
            // migrations finish. A `constrained()` FK would break a fresh
            // install's migrate-then-seed ordering. Same reasoning
            // EntitySchemaBuilder itself uses for a Relation field whose
            // target isn't installed yet — a plain indexed column, no
            // constraint.
            $table->unsignedBigInteger('entity_record_id');
            $table->foreignId('connector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('external_change_key')->nullable();
            $table->string('sync_hash')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['connector_id', 'external_id']);
            // Explicit short name: the auto-generated one
            // ("..._connector_id_entity_record_id_user_id_unique") is 74
            // chars, over MySQL/MariaDB's 64-char identifier limit — which
            // fails silently from artisan's point of view (CREATE TABLE's
            // own statement already committed by the time this second
            // index statement errors, so the table is left half-built and
            // the migration never gets recorded as run).
            $table->unique(['connector_id', 'entity_record_id', 'user_id'], 'cal_event_ext_links_connector_record_user_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_event_external_links');
    }
};
