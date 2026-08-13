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
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('protocol');
            $table->string('name');
            $table->string('email_address');
            $table->boolean('is_active')->default(true);
            $table->foreignId('mail_connector_id')->nullable()->constrained('mail_connectors')->nullOnDelete();
            // text, not json: the "encrypted:array" cast stores ciphertext
            // here, which would fail a json column's json_valid() CHECK
            // constraint on MariaDB/MySQL — same reasoning as connectors.
            // Holds everything protocol-specific: host/port/encryption/
            // username/password for IMAP/POP3/EWS-direct, plus
            // smtp_* keys for sending — never present at all when
            // mail_connector_id is set, since the shared connector's own
            // app-only credentials cover both reading and sending.
            $table->text('config')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
