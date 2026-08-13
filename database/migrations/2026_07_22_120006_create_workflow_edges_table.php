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
        Schema::create('workflow_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_node_id')->constrained('workflow_nodes')->cascadeOnDelete();
            $table->foreignId('target_node_id')->constrained('workflow_nodes')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            // JsonLogic rule (jwadhams/json-logic-php), evaluated against
            // the instance's variables + entity. Null on an exclusive
            // gate's edge = always matches (default/else branch).
            $table->json('condition_logic')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_edges');
    }
};
