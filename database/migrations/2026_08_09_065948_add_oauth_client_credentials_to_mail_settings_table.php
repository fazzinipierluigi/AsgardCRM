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
        Schema::table('mail_settings', function (Blueprint $table) {
            // One app registration per provider, shared by every user's
            // "Connetti con Google/Microsoft" button (see
            // App\Services\Mail\OAuth\MailOAuthService) — client_secret
            // is text, not string: the "encrypted" cast stores
            // ciphertext here, which can run well past 255 chars.
            $table->string('google_oauth_client_id')->nullable();
            $table->text('google_oauth_client_secret')->nullable();
            $table->string('microsoft_oauth_client_id')->nullable();
            $table->text('microsoft_oauth_client_secret')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            $table->dropColumn(['google_oauth_client_id', 'google_oauth_client_secret', 'microsoft_oauth_client_id', 'microsoft_oauth_client_secret']);
        });
    }
};
