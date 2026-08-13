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
        Schema::table('workflow_instances', function (Blueprint $table) {
            // Set when this instance was started by a Subworkflow node
            // configured to wait — lets the engine resume that parent
            // token once this child instance completes.
            $table->foreignId('parent_token_id')->nullable()->after('workflow_id')->constrained('workflow_tokens')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_token_id');
        });
    }
};
