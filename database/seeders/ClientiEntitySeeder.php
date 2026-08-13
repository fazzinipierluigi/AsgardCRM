<?php

namespace Fazzinipierluigi\AsgardCRM\Database\Seeders;

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "Clienti" Entity — the customer registry that
 * Contatti/Opportunità/Preventivi/Ordini di vendita/Fatture/Ticket all
 * point back to via a Relation field. See ClientiEntitySeeder's twin
 * FornitoriEntitySeeder for the supplier side (kept a separate entity
 * on purpose: distinct business domain, permissions, workflows).
 *
 * Idempotent: does nothing if the "clienti" entity already exists.
 */
class ClientiEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'clienti')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'Clienti',
            'slug' => 'clienti',
            'table_name' => 'entity_clienti',
            'icon' => 'building',
            'is_system' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Anagrafica', 'position' => 0]);

        $fields = [
            ['name' => 'Ragione sociale', 'column_name' => 'ragione_sociale', 'type' => EntityFieldType::String, 'required' => true, 'width' => 12],
            ['name' => 'P.IVA', 'column_name' => 'piva', 'type' => EntityFieldType::String],
            ['name' => 'Codice Fiscale', 'column_name' => 'codice_fiscale', 'type' => EntityFieldType::String],
            ['name' => 'Email', 'column_name' => 'email', 'type' => EntityFieldType::String],
            ['name' => 'Telefono', 'column_name' => 'telefono', 'type' => EntityFieldType::String],
            ['name' => 'Indirizzo', 'column_name' => 'indirizzo', 'type' => EntityFieldType::String, 'width' => 12],
            ['name' => 'Città', 'column_name' => 'citta', 'type' => EntityFieldType::String],
            ['name' => 'CAP', 'column_name' => 'cap', 'type' => EntityFieldType::String],
            ['name' => 'Provincia', 'column_name' => 'provincia', 'type' => EntityFieldType::String],
            ['name' => 'Nazione', 'column_name' => 'nazione', 'type' => EntityFieldType::String, 'default_value' => 'Italia'],
            [
                'name' => 'Stato', 'column_name' => 'stato', 'type' => EntityFieldType::Select,
                'options' => ['attivo' => 'Attivo', 'prospect' => 'Prospect', 'inattivo' => 'Inattivo'],
                'default_value' => 'attivo',
            ],
            ['name' => 'Note', 'column_name' => 'note', 'type' => EntityFieldType::Textarea, 'width' => 12],
        ];

        foreach ($fields as $position => $field) {
            $card->fields()->create([
                'name' => $field['name'],
                'column_name' => $field['column_name'],
                'type' => $field['type'],
                'options' => $field['options'] ?? null,
                'required' => $field['required'] ?? false,
                'default_value' => $field['default_value'] ?? null,
                'position' => $position,
                'width' => $field['width'] ?? 6,
            ]);
        }

        $installer->install($entity);
    }
}
