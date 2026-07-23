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
        Schema::table('workflow_edges', function (Blueprint $table) {
            // Manually-dragged bend points on the edge (MaxGraph geometry
            // control points), so the builder can restore the exact routing
            // the user arranged instead of falling back to auto-routing.
            $table->json('waypoints')->nullable()->after('condition_logic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_edges', function (Blueprint $table) {
            $table->dropColumn('waypoints');
        });
    }
};
