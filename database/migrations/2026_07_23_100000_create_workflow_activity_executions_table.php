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
        Schema::create('workflow_activity_executions', function (Blueprint $table) {
            $table->id();
            // One row per token that ever reached an async Task processo/script:
            // the unique constraint is what makes a redelivered queue job a
            // no-op instead of re-running the activity's actions and
            // re-traversing the edge a second time.
            $table->foreignId('workflow_token_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_activity_executions');
    }
};
