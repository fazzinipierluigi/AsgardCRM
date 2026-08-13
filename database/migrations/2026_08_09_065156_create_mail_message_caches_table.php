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
        Schema::create('mail_message_caches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_account_id')->constrained()->cascadeOnDelete();
            $table->string('folder');
            $table->string('uid');
            $table->string('subject')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->timestamp('message_date')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            // One row per message header on the folder's first page —
            // see App\Services\Mail\MailMessageHeaderCache. Unique so a
            // resync (delete-then-insert) can never leave duplicates,
            // the composite index is what makes reading a folder's
            // cached page a single indexed lookup.
            $table->unique(['mail_account_id', 'folder', 'uid']);
            $table->index(['mail_account_id', 'folder', 'message_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_message_caches');
    }
};
