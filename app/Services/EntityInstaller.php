<?php

namespace App\Services;

use App\Models\Entity;
use Fazzinipierluigi\JustAGate\Facades\JustAGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Installs/uninstalls an Entity: creates or drops its dedicated SQL
 * table (via EntitySchemaBuilder) and its four CRUD permissions.
 *
 * These permissions are deliberately NOT derivable by Just A Gate's
 * `acl` middleware (which keys off Controller@action, and every entity
 * shares the same generic EntityRecordController) — they're created
 * here and checked manually wherever entity records are read/written,
 * the same pattern already used for the app's own `admin.access` key.
 */
class EntityInstaller
{
    public function __construct(private readonly EntitySchemaBuilder $schemaBuilder) {}

    /**
     * @return array<string, string> permission key => display name
     */
    public function permissionDefinitions(Entity $entity): array
    {
        return [
            "entity_{$entity->slug}.index" => "Vedi {$entity->name}",
            "entity_{$entity->slug}.create" => "Crea {$entity->name}",
            "entity_{$entity->slug}.edit" => "Modifica {$entity->name}",
            "entity_{$entity->slug}.delete" => "Elimina {$entity->name}",
        ];
    }

    /**
     * @throws RuntimeException if the tab/card/field tree isn't complete
     */
    public function install(Entity $entity): void
    {
        if ($entity->is_installed) {
            return;
        }

        $entity->loadMissing('tabs.cards.fields');
        $this->assertStructureIsComplete($entity);

        // CREATE TABLE implicitly commits on MySQL/MariaDB, ending any
        // surrounding transaction early — DB::transaction()'s own closing
        // commit() then fails with "There is no active transaction". So the
        // DDL runs on its own (Schema::hasTable guards it being skipped on
        // a retry after a previous partial failure), and only the plain
        // inserts/updates are wrapped in a real transaction.
        if (! Schema::hasTable($entity->table_name)) {
            $this->schemaBuilder->create($entity);
        }

        DB::transaction(function () use ($entity) {
            foreach ($this->permissionDefinitions($entity) as $key => $name) {
                JustAGate::createPermission($key, $name);
            }

            $entity->update(['is_installed' => true]);
        });
    }

    /**
     * @throws RuntimeException if the entity is a system entity
     */
    public function uninstall(Entity $entity): void
    {
        if (! $entity->is_installed) {
            return;
        }

        if ($entity->is_system) {
            throw new RuntimeException('Le entità di sistema non possono essere disinstallate.');
        }

        // DROP TABLE is DDL too (see install()) — do it last, after the
        // transactional bookkeeping below has committed, so a failure here
        // still leaves the entity correctly marked uninstalled and its
        // permissions gone (a stray physical table is harmless: install()
        // skips CREATE TABLE when one already exists).
        DB::transaction(function () use ($entity) {
            foreach (array_keys($this->permissionDefinitions($entity)) as $key) {
                $permission = JustAGate::findPermission($key);

                if ($permission !== null) {
                    JustAGate::deletePermission($permission);
                }
            }

            $entity->update(['is_installed' => false]);
        });

        $this->schemaBuilder->dropTable($entity);
    }

    private function assertStructureIsComplete(Entity $entity): void
    {
        if ($entity->tabs->isEmpty()) {
            throw new RuntimeException('L\'entità deve avere almeno un tab prima di poter essere installata.');
        }

        foreach ($entity->tabs as $tab) {
            if ($tab->cards->isEmpty()) {
                throw new RuntimeException('Ogni tab deve avere almeno una card prima dell\'installazione.');
            }

            foreach ($tab->cards as $card) {
                if ($card->fields->isEmpty()) {
                    throw new RuntimeException('Ogni card deve avere almeno un campo prima dell\'installazione.');
                }
            }
        }
    }
}
