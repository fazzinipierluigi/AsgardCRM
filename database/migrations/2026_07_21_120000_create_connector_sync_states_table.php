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
        Schema::create('connector_sync_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connector_user_mailbox_id')->constrained()->cascadeOnDelete();
            $table->text('delta_link')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Explicit short name: the auto-generated one is 67 chars,
            // over MySQL/MariaDB's 64-char identifier limit — see
            // calendar_event_external_links's migration for the full
            // failure mode this causes if left to the default name.
            $table->unique(['connector_id', 'connector_user_mailbox_id'], 'connector_sync_states_connector_mailbox_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connector_sync_states');
    }
};
