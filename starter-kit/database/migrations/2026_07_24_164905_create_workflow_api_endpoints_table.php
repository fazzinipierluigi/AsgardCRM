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
        Schema::create('workflow_api_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('base_url');
            $table->text('config')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_api_endpoints');
    }
};
