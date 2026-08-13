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
        Schema::create('workflow_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_token_id')->constrained()->cascadeOnDelete();
            $table->timestamp('run_at');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['status', 'run_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_timers');
    }
};
