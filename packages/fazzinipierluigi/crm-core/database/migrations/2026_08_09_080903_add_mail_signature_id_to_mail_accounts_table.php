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
        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->foreignId('mail_signature_id')->nullable()->after('mail_connector_id')->constrained('mail_signatures')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mail_signature_id');
        });
    }
};
