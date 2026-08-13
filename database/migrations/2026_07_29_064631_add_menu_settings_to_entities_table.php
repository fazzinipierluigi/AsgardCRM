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
        Schema::table('entities', function (Blueprint $table) {
            $table->boolean('show_in_menu')->default(true)->after('is_installed');
            $table->integer('menu_position')->default(0)->after('show_in_menu');
            $table->boolean('show_in_quick_access')->default(false)->after('menu_position');
            $table->integer('quick_access_position')->default(0)->after('show_in_quick_access');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn(['show_in_menu', 'menu_position', 'show_in_quick_access', 'quick_access_position']);
        });
    }
};
