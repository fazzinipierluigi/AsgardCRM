<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflow_versions', function (Blueprint $table) {
            // Every version created before this migration already went
            // live at some point under the old one-step save model, so
            // none of them were ever a "draft" — they all become published.
            $table->string('status')->default('published')->after('version');
            $table->timestamp('published_at')->nullable()->after('status');
        });

        DB::table('workflow_versions')->update(['published_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_versions', function (Blueprint $table) {
            $table->dropColumn(['status', 'published_at']);
        });
    }
};
