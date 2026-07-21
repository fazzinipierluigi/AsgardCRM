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
        Schema::create('importer_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('status')->default('running');
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['importer_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('importer_runs');
    }
};
