<?php

namespace App\Services;

use App\Models\Entity;
use Fazzinipierluigi\JustAGate\Facades\JustAGate;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($entity) {
            $this->schemaBuilder->create($entity);

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

        DB::transaction(function () use ($entity) {
            $this->schemaBuilder->dropTable($entity);

            foreach (array_keys($this->permissionDefinitions($entity)) as $key) {
                $permission = JustAGate::findPermission($key);

                if ($permission !== null) {
                    JustAGate::deletePermission($permission);
                }
            }

            $entity->update(['is_installed' => false]);
        });
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
