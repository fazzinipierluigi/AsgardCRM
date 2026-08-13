<?php

namespace Fazzinipierluigi\CrmCore\Database\Seeders;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Enums\EntityRelationTargetType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "Ordini di acquisto" Entity — same Blocco Prodotti +
 * synced Totale pattern as OrdiniVenditaEntitySeeder, but against
 * Fornitori and deliberately with no link back to Ordini di vendita
 * (kept independent, per product decision).
 *
 * Must run after FornitoriEntitySeeder and ProdottiEntitySeeder.
 *
 * Idempotent: does nothing if the "ordini_acquisto" entity already exists.
 */
class OrdiniAcquistoEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'ordini_acquisto')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'Ordini di acquisto',
            'slug' => 'ordini_acquisto',
            'table_name' => 'entity_ordini_acquisto',
            'icon' => 'shopping-cart-plus',
            'is_system' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Dati', 'position' => 0]);

        $fields = [
            ['name' => 'Titolo', 'column_name' => 'titolo', 'type' => EntityFieldType::String, 'required' => true, 'width' => 12],
            ['name' => 'Numero', 'column_name' => 'numero', 'type' => EntityFieldType::Code, 'options' => ['prefix' => 'OA']],
            [
                'name' => 'Fornitore', 'column_name' => 'fornitore', 'type' => EntityFieldType::Relation, 'required' => true,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'fornitori',
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
                'options' => ['bozza' => 'Bozza', 'inviato' => 'Inviato', 'confermato' => 'Confermato', 'ricevuto' => 'Ricevuto', 'annullato' => 'Annullato'],
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
