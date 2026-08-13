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
        Schema::create('entity_field_condition_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_field_condition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_field_id')->constrained()->cascadeOnDelete();
            $table->boolean('visible')->default(true);
            $table->boolean('readonly')->default(false);
            $table->boolean('required')->default(false);
            $table->timestamps();

            $table->unique(['entity_field_condition_id', 'entity_field_id'], 'entity_field_condition_targets_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_field_condition_targets');
    }
};
