<?php

namespace Database\Seeders;

use App\Enums\EntityFieldType;
use App\Enums\EntityRelationTargetType;
use App\Models\Entity;
use App\Models\EntityFieldCondition;
use App\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "Fatture" Entity — a single entity for both active
 * and passive invoices (per product decision), with two conditional
 * field rules driven by "Tipo": Cliente/Ordine di vendita collegato show
 * (Cliente required) only for Tipo=attiva, Fornitore/Ordine di acquisto
 * collegato show (Fornitore required) only for Tipo=passiva. Same
 * EntityFieldCondition mechanism as OpportunitaEntitySeeder's "Motivo
 * perdita", just two rules instead of one.
 *
 * Must run after ClientiEntitySeeder, FornitoriEntitySeeder,
 * OrdiniVenditaEntitySeeder, OrdiniAcquistoEntitySeeder and
 * ProdottiEntitySeeder.
 *
 * Idempotent: does nothing if the "fatture" entity already exists.
 */
class FattureEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'fatture')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'Fatture',
            'slug' => 'fatture',
            'table_name' => 'entity_fatture',
            'icon' => 'file-invoice',
            'is_system' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Dati', 'position' => 0]);

        $fields = [
            ['name' => 'Titolo', 'column_name' => 'titolo', 'type' => EntityFieldType::String, 'required' => true, 'width' => 12],
            ['name' => 'Numero', 'column_name' => 'numero', 'type' => EntityFieldType::Code, 'options' => ['prefix' => 'FT']],
            [
                'name' => 'Tipo', 'column_name' => 'tipo', 'type' => EntityFieldType::Select, 'required' => true,
                'options' => ['attiva' => 'Attiva', 'passiva' => 'Passiva'],
            ],
            [
                'name' => 'Cliente', 'column_name' => 'cliente', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'clienti',
            ],
            [
                'name' => 'Fornitore', 'column_name' => 'fornitore', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'fornitori',
            ],
            [
                'name' => 'Ordine di vendita collegato', 'column_name' => 'ordine_vendita_collegato', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'ordini_vendita',
            ],
            [
                'name' => 'Ordine di acquisto collegato', 'column_name' => 'ordine_acquisto_collegato', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'ordini_acquisto',
            ],
            ['name' => 'Data fattura', 'column_name' => 'data_fattura', 'type' => EntityFieldType::Date, 'required' => true],
            ['name' => 'Scadenza pagamento', 'column_name' => 'scadenza_pagamento', 'type' => EntityFieldType::Date],
            [
                'name' => 'Righe prodotto', 'column_name' => 'righe_prodotto', 'type' => EntityFieldType::ProductsBlock, 'required' => true, 'width' => 12,
                'options' => [
                    'catalog_entity_slug' => 'prodotti',
                    'price_column' => 'prezzo_di_listino',
                    'extra_columns' => [],
                    'total_target_column' => 'totale',
                ],
            ],
            ['name' => 'Totale', 'column_name' => 'totale', 'type' => EntityFieldType::DecimalNumber],
            [
                'name' => 'Stato pagamento', 'column_name' => 'stato_pagamento', 'type' => EntityFieldType::Select,
                'options' => ['da_pagare' => 'Da pagare', 'pagata_parzialmente' => 'Pagata parzialmente', 'pagata' => 'Pagata', 'scaduta' => 'Scaduta', 'insoluta' => 'Insoluta'],
                'default_value' => 'da_pagare',
            ],
            ['name' => 'Note', 'column_name' => 'note', 'type' => EntityFieldType::Textarea, 'width' => 12],
        ];

        $createdFields = [];

        foreach ($fields as $position => $field) {
            $createdFields[$field['column_name']] = $card->fields()->create([
                'name' => $field['name'],
                'column_name' => $field['column_name'],
                'type' => $field['type'],
                'options' => $field['options'] ?? null,
                'relation_target_type' => $field['relation_target_type'] ?? null,
                'relation_target' => $field['relation_target'] ?? null,
                'required' => $field['required'] ?? false,
                'default_value' => $field['default_value'] ?? null,
                'position' => $position,
                'width' => $field['width'] ?? 6,
            ]);
        }

        $installer->install($entity);

        $active = EntityFieldCondition::create([
            'entity_id' => $entity->id,
            'name' => 'Campi fattura attiva',
            'rule' => ['==' => [['var' => 'tipo'], 'attiva']],
            'position' => 0,
        ]);
        $active->targets()->create(['entity_field_id' => $createdFields['cliente']->id, 'visible' => true, 'readonly' => false, 'required' => true]);
        $active->targets()->create(['entity_field_id' => $createdFields['ordine_vendita_collegato']->id, 'visible' => true, 'readonly' => false, 'required' => false]);

        $passive = EntityFieldCondition::create([
            'entity_id' => $entity->id,
            'name' => 'Campi fattura passiva',
            'rule' => ['==' => [['var' => 'tipo'], 'passiva']],
            'position' => 1,
        ]);
        $passive->targets()->create(['entity_field_id' => $createdFields['fornitore']->id, 'visible' => true, 'readonly' => false, 'required' => true]);
        $passive->targets()->create(['entity_field_id' => $createdFields['ordine_acquisto_collegato']->id, 'visible' => true, 'readonly' => false, 'required' => false]);
    }
}
