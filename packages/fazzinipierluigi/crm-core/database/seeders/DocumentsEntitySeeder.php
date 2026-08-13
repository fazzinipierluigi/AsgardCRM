<?php

namespace Fazzinipierluigi\CrmCore\Database\Seeders;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "Documenti" Entity — a document library with
 * infinitely nestable folders (Fazzinipierluigi\CrmCore\Models\DocumentFolder). Two locked
 * fields (Nome/Descrizione) plus the fixed upload-bookkeeping columns
 * EntitySchemaBuilder adds for `is_documents` entities; users can
 * later append their own custom metadata fields via
 * EntityFieldController the same way they can for Calendario.
 *
 * Idempotent: does nothing if the "documenti" entity already exists.
 */
class DocumentsEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'documenti')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'Documenti',
            'slug' => 'documenti',
            'table_name' => 'entity_documenti',
            'icon' => 'folder',
            'is_system' => true,
            'is_documents' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Dati', 'position' => 0]);

        $fields = [
            ['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'required' => true],
            ['name' => 'Descrizione', 'column_name' => 'descrizione', 'type' => EntityFieldType::Textarea],
        ];

        foreach ($fields as $position => $field) {
            $card->fields()->create([
                'name' => $field['name'],
                'column_name' => $field['column_name'],
                'type' => $field['type'],
                'required' => $field['required'] ?? false,
                'position' => $position,
                'width' => 12,
                'is_locked' => true,
            ]);
        }

        $installer->install($entity);
    }
}
