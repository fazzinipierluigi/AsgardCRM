<?php

use App\Models\Entity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entities installed before soft deletes existed (see
 * EntitySchemaBuilder::create()) never got a deleted_at column on
 * their dynamic table — this backfills it so EntityRecord's
 * SoftDeletes trait works for every entity, system or custom, old or
 * new.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (Entity::where('is_installed', true)->get() as $entity) {
            if (Schema::hasTable($entity->table_name) && ! Schema::hasColumn($entity->table_name, 'deleted_at')) {
                Schema::table($entity->table_name, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (Entity::where('is_installed', true)->get() as $entity) {
            if (Schema::hasTable($entity->table_name) && Schema::hasColumn($entity->table_name, 'deleted_at')) {
                Schema::table($entity->table_name, function (Blueprint $table) {
                    $table->dropColumn('deleted_at');
                });
            }
        }
    }
};
