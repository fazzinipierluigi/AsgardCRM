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
        Schema::create('workflow_node_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_token_id')->constrained()->cascadeOnDelete();
            // Which time the same (instance, node) pair got entered — a
            // node revisited by a cycle back to it gets iteration 2, 3...
            $table->unsignedInteger('iteration');
            $table->string('status');
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            // The edge the token arrived through, i.e. which branch led
            // here — null for the start node's very first execution.
            $table->foreignId('via_edge_id')->nullable()->constrained('workflow_edges')->nullOnDelete();
            $table->json('variables_snapshot');
            $table->timestamps();

            $table->index(['workflow_instance_id', 'workflow_node_id'], 'workflow_node_executions_instance_node_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_node_executions');
    }
};
