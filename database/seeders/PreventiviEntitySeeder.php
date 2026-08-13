<?php

namespace Fazzinipierluigi\AsgardCRM\Database\Seeders;

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Enums\EntityRelationTargetType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "Preventivi" Entity. Its line items use the new
 * "Blocco Prodotti" field type (EntityFieldType::ProductsBlock) against
 * the Prodotti catalog, with its computed total synced live into the
 * "Totale" Decimal field below (see resources/js/products-block-field.js
 * and the field's `total_target_column` option).
 *
 * "Titolo" exists purely so relation pickers on other entities (which
 * label a record after its first String field, see
 * EntityRelationResolver::labelsFor()) show something readable — "Numero"
 * is a Code field, not a String, so it can't serve as that label.
 *
 * Must run after ClientiEntitySeeder, OpportunitaEntitySeeder and
 * ProdottiEntitySeeder.
 *
 * Idempotent: does nothing if the "preventivi" entity already exists.
 */
class PreventiviEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'preventivi')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'Preventivi',
            'slug' => 'preventivi',
            'table_name' => 'entity_preventivi',
            'icon' => 'file-description',
            'is_system' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Dati', 'position' => 0]);

        $fields = [
            ['name' => 'Titolo', 'column_name' => 'titolo', 'type' => EntityFieldType::String, 'required' => true, 'width' => 12],
            ['name' => 'Numero', 'column_name' => 'numero', 'type' => EntityFieldType::Code, 'options' => ['prefix' => 'PRV']],
            [
                'name' => 'Cliente', 'column_name' => 'cliente', 'type' => EntityFieldType::Relation, 'required' => true,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'clienti',
            ],
            [
                'name' => 'Opportunità collegata', 'column_name' => 'opportunita_collegata', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'opportunita',
            ],
            ['name' => 'Data preventivo', 'column_name' => 'data_preventivo', 'type' => EntityFieldType::Date, 'required' => true],
            ['name' => 'Validità fino al', 'column_name' => 'validita_fino_al', 'type' => EntityFieldType::Date],
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
                'name' => 'Stato', 'column_name' => 'stato', 'type' => EntityFieldType::Select,
                'options' => ['bozza' => 'Bozza', 'inviato' => 'Inviato', 'accettato' => 'Accettato', 'rifiutato' => 'Rifiutato', 'scaduto' => 'Scaduto'],
                'default_value' => 'bozza',
            ],
            ['name' => 'Note', 'column_name' => 'note', 'type' => EntityFieldType::Textarea, 'width' => 12],
        ];

        foreach ($fields as $position => $field) {
            $card->fields()->create([
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
    }
}
