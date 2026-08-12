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
        Schema::create('workflow_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->integer('pos_x')->default(0);
            $table->integer('pos_y')->default(0);
            // Node-specific settings, e.g. a start node's trigger_type,
            // entity_slug, occurrence ("once"/"every_time"), and its
            // start_condition (a JsonLogic rule, null = always starts).
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_nodes');
    }
};
