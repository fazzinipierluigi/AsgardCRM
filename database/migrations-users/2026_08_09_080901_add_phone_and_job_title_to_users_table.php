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
        Schema::table('users', function (Blueprint $table) {
            // Populated by the user themselves (or an admin) and used
            // as {{user.phone}}/{{user.job_title}} placeholders in
            // Fazzinipierluigi\AsgardCRM\Models\MailSignature — see MailSignature::render().
            $table->string('phone')->nullable()->after('email');
            $table->string('job_title')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'job_title']);
        });
    }
};
