<?php

use App\Models\Entity;
use Fazzinipierluigi\JustAGate\Facades\JustAGate;
use Illuminate\Database\Migrations\Migration;

/**
 * Entities installed before the entity_{slug}.workflows permission
 * existed (see EntityInstaller::permissionDefinitions()) never got it
 * created — this backfills it for every already-installed entity,
 * system or custom.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (Entity::where('is_installed', true)->get() as $entity) {
            JustAGate::createPermission("entity_{$entity->slug}.workflows", "Vedi flussi {$entity->name}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (Entity::where('is_installed', true)->get() as $entity) {
            $permission = JustAGate::findPermission("entity_{$entity->slug}.workflows");

            if ($permission !== null) {
                JustAGate::deletePermission($permission);
            }
        }
    }
};
