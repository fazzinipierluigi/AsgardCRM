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
        Schema::create('mail_folder_cache_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_account_id')->constrained()->cascadeOnDelete();
            $table->string('folder');
            // The folder's total message count as of the last sync —
            // reported alongside the cached page instantly, rather than
            // making the first paint wait on a live COUNT from the mail
            // server too.
            $table->unsignedInteger('total')->default(0);
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['mail_account_id', 'folder']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_folder_cache_syncs');
    }
};
