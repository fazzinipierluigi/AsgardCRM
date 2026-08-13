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
        Schema::create('mail_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // HTML written by an admin in HugeRTE, with literal
            // {{user.name}}/{{user.email}}/{{user.phone}}/
            // {{user.job_title}} placeholders — see
            // App\Models\MailSignature::render().
            $table->text('body_html');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_signatures');
    }
};
