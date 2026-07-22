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
        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained()->cascadeOnDelete();
            // Polymorphic: attached to a WorkflowNode or a WorkflowEdge.
            $table->string('actionable_type');
            $table->unsignedBigInteger('actionable_id');
            $table->string('phase');
            $table->unsignedInteger('sequence')->default(0);
            $table->string('type');
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['actionable_type', 'actionable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_actions');
    }
};
