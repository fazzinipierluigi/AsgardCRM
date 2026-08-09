<?php

namespace Database\Seeders;

use App\Enums\EntityFieldType;
use App\Enums\EntityRelationTargetType;
use App\Models\Entity;
use App\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "Ordini di vendita" Entity — same Blocco Prodotti +
 * synced Totale pattern as PreventiviEntitySeeder, with an optional
 * Relation back to the originating Preventivo.
 *
 * Must run after ClientiEntitySeeder, PreventiviEntitySeeder and
 * ProdottiEntitySeeder.
 *
 * Idempotent: does nothing if the "ordini_vendita" entity already exists.
 */
class OrdiniVenditaEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'ordini_vendita')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'Ordini di vendita',
            'slug' => 'ordini_vendita',
            'table_name' => 'entity_ordini_vendita',
            'icon' => 'shopping-cart',
            'is_system' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Dati', 'position' => 0]);

        $fields = [
            ['name' => 'Titolo', 'column_name' => 'titolo', 'type' => EntityFieldType::String, 'required' => true, 'width' => 12],
            ['name' => 'Numero', 'column_name' => 'numero', 'type' => EntityFieldType::Code, 'options' => ['prefix' => 'OV']],
            [
                'name' => 'Cliente', 'column_name' => 'cliente', 'type' => EntityFieldType::Relation, 'required' => true,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'clienti',
            ],
            [
                'name' => 'Preventivo di origine', 'column_name' => 'preventivo_di_origine', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'preventivi',
            ],
            ['name' => 'Data ordine', 'column_name' => 'data_ordine', 'type' => EntityFieldType::Date, 'required' => true],
            ['name' => 'Data consegna prevista', 'column_name' => 'data_consegna_prevista', 'type' => EntityFieldType::Date],
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
                'options' => ['in_lavorazione' => 'In lavorazione', 'confermato' => 'Confermato', 'spedito' => 'Spedito', 'consegnato' => 'Consegnato', 'annullato' => 'Annullato'],
                'default_value' => 'in_lavorazione',
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
