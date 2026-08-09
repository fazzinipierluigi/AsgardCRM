<?php

namespace Database\Seeders;

use App\Enums\EntityFieldType;
use App\Enums\EntityRelationTargetType;
use App\Models\Entity;
use App\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "Contatti" Entity — people, each optionally linked to
 * one Cliente and/or one Fornitore via a Relation field (1:N: a contact
 * belongs to a single company; the reverse "contacts of this client"
 * view is just the Contatti list filtered by that Relation column, no
 * N:M needed here — unlike Opportunità's own link to Contatti, see
 * OpportunitaEntitySeeder, where both sides genuinely admit more than
 * one).
 *
 * Must run after ClientiEntitySeeder/FornitoriEntitySeeder so the two
 * Relation fields get a real FK (EntitySchemaBuilder only adds one when
 * the target entity is already installed at creation time).
 *
 * Idempotent: does nothing if the "contatti" entity already exists.
 */
class ContattiEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'contatti')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'Contatti',
            'slug' => 'contatti',
            'table_name' => 'entity_contatti',
            'icon' => 'address-book',
            'is_system' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Anagrafica', 'position' => 0]);

        $fields = [
            ['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'required' => true],
            ['name' => 'Cognome', 'column_name' => 'cognome', 'type' => EntityFieldType::String, 'required' => true],
            ['name' => 'Email', 'column_name' => 'email', 'type' => EntityFieldType::String],
            ['name' => 'Telefono', 'column_name' => 'telefono', 'type' => EntityFieldType::String],
            ['name' => 'Cellulare', 'column_name' => 'cellulare', 'type' => EntityFieldType::String],
            ['name' => 'Ruolo/Posizione', 'column_name' => 'ruolo', 'type' => EntityFieldType::String],
            [
                'name' => 'Cliente collegato', 'column_name' => 'cliente_collegato', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'clienti',
            ],
            [
                'name' => 'Fornitore collegato', 'column_name' => 'fornitore_collegato', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'fornitori',
            ],
            ['name' => 'Note', 'column_name' => 'note', 'type' => EntityFieldType::Textarea, 'width' => 12],
        ];

        foreach ($fields as $position => $field) {
            $card->fields()->create([
                'name' => $field['name'],
                'column_name' => $field['column_name'],
                'type' => $field['type'],
                'relation_target_type' => $field['relation_target_type'] ?? null,
                'relation_target' => $field['relation_target'] ?? null,
                'required' => $field['required'] ?? false,
                'position' => $position,
                'width' => $field['width'] ?? 6,
            ]);
        }

        $installer->install($entity);
    }
}
