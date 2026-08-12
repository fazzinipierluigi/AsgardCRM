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
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            // Pinned at start time — later builder saves publish a new
            // version but never touch this one, so an in-flight instance
            // keeps executing the exact graph it started with.
            $table->foreignId('workflow_version_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            // The entity record that triggered this instance, when the
            // start node's trigger is entity created/updated. entity_slug
            // says which Entity's table entity_id is a row of — see
            // WorkflowInstance::resolveEntity().
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_slug')->nullable();
            $table->json('variables')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['workflow_id', 'entity_slug', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};
