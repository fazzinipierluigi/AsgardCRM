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
        Schema::table('importers', function (Blueprint $table) {
            // Every dynamic entity table requires a non-null user_id owner
            // (see EntitySchemaBuilder::create()) — imported records are
            // attributed to whoever created the importer, since a
            // cron-triggered run has no authenticated user of its own.
            $table->foreignId('created_by')->nullable()->after('entity_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('importers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
