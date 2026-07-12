<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datagrid_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('page_key', 40); // SHA1 hash
            $table->string('name');
            $table->json('layout_data');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'page_key']);
            $table->index(['page_key', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datagrid_layouts');
    }
};
