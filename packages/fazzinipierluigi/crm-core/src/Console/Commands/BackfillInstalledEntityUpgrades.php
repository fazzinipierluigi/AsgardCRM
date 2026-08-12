<?php

namespace Fazzinipierluigi\CrmCore\Console\Commands;

use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\JustAGate\Facades\JustAGate;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills two changes onto every already-installed entity, for hosts
 * upgrading from before these existed:
 *
 * - a deleted_at column on the entity's dynamic table (see
 *   EntitySchemaBuilder::create()), so EntityRecord's SoftDeletes trait
 *   works for every entity, system or custom, old or new;
 * - the entity_{slug}.workflows permission (see
 *   EntityInstaller::permissionDefinitions()).
 *
 * Reclassified from two one-shot data migrations
 * (add_deleted_at_to_installed_entity_tables,
 * add_workflows_permission_to_installed_entities) into a command: they
 * read/write application state (Entity::where('is_installed', true)),
 * not just schema, so they don't fit the "publish and `migrate`" model
 * a package's own migrations are meant for. The host's own upgrade
 * orchestration is expected to call this command once when upgrading
 * across the version boundary that introduced these.
 */
class BackfillInstalledEntityUpgrades extends Command
{
    protected $signature = 'crm:backfill-installed-entities {--rollback : Reverse both backfills instead of applying them}';

    protected $description = 'Backfill deleted_at column and workflows permission onto already-installed entities';

    public function handle(): int
    {
        if ($this->option('rollback')) {
            $this->rollbackWorkflowsPermission();
            $this->rollbackDeletedAt();

            return self::SUCCESS;
        }

        $this->backfillDeletedAt();
        $this->backfillWorkflowsPermission();

        return self::SUCCESS;
    }

    private function backfillDeletedAt(): void
    {
        foreach (Entity::where('is_installed', true)->get() as $entity) {
            if (Schema::hasTable($entity->table_name) && ! Schema::hasColumn($entity->table_name, 'deleted_at')) {
                Schema::table($entity->table_name, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    private function rollbackDeletedAt(): void
    {
        foreach (Entity::where('is_installed', true)->get() as $entity) {
            if (Schema::hasTable($entity->table_name) && Schema::hasColumn($entity->table_name, 'deleted_at')) {
                Schema::table($entity->table_name, function (Blueprint $table) {
                    $table->dropColumn('deleted_at');
                });
            }
        }
    }

    private function backfillWorkflowsPermission(): void
    {
        foreach (Entity::where('is_installed', true)->get() as $entity) {
            JustAGate::createPermission("entity_{$entity->slug}.workflows", "Vedi flussi {$entity->name}");
        }
    }

    private function rollbackWorkflowsPermission(): void
    {
        foreach (Entity::where('is_installed', true)->get() as $entity) {
            $permission = JustAGate::findPermission("entity_{$entity->slug}.workflows");

            if ($permission !== null) {
                JustAGate::deletePermission($permission);
            }
        }
    }
}
