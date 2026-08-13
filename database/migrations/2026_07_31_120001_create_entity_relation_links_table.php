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
        Schema::create('entity_relation_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_relation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('entity_a_record_id');
            $table->unsignedBigInteger('entity_b_record_id');
            $table->timestamps();

            $table->unique(['entity_relation_id', 'entity_a_record_id', 'entity_b_record_id'], 'entity_relation_links_unique');
            $table->index(['entity_relation_id', 'entity_a_record_id'], 'entity_relation_links_relation_a_index');
            $table->index(['entity_relation_id', 'entity_b_record_id'], 'entity_relation_links_relation_b_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_relation_links');
    }
};
