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
        Schema::create('entity_field_changes', function (Blueprint $table) {
            $table->id();
            $table->string('entity_slug');
            $table->unsignedBigInteger('entity_id');
            $table->uuid('transaction_id');
            $table->string('column_name');
            $table->string('field_label');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_label')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['entity_slug', 'entity_id']);
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_field_changes');
    }
};
