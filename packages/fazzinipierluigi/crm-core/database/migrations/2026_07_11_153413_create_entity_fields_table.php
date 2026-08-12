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
        Schema::create('entity_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_card_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('column_name');
            $table->string('type');
            $table->json('options')->nullable();
            $table->string('relation_target_type')->nullable();
            $table->string('relation_target')->nullable();
            $table->boolean('required')->default(false);
            $table->string('default_value')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['entity_card_id', 'column_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_fields');
    }
};
