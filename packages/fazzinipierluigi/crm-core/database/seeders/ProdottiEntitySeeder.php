<?php

namespace Fazzinipierluigi\AsgardCRM\Database\Seeders;

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "Prodotti/Servizi" Entity — the catalog every
 * "Blocco Prodotti" field on Preventivi/Ordini/Fatture picks its lines
 * from (see EntityFieldType::ProductsBlock). Not one of the 10 entities
 * explicitly requested, but required by them: a ProductsBlock field's
 * `catalog_entity_slug` option needs some installed entity to point at.
 *
 * Idempotent: does nothing if the "prodotti" entity already exists.
 */
class ProdottiEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'prodotti')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'Prodotti/Servizi',
            'slug' => 'prodotti',
            'table_name' => 'entity_prodotti',
            'icon' => 'box',
            'is_system' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Dati', 'position' => 0]);

        $fields = [
            ['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'required' => true],
            ['name' => 'Codice', 'column_name' => 'codice', 'type' => EntityFieldType::Code, 'options' => ['prefix' => 'PRD']],
            ['name' => 'Descrizione', 'column_name' => 'descrizione', 'type' => EntityFieldType::RichText],
            ['name' => 'Categoria', 'column_name' => 'categoria', 'type' => EntityFieldType::String],
            ['name' => 'Prezzo di listino', 'column_name' => 'prezzo_di_listino', 'type' => EntityFieldType::DecimalNumber, 'required' => true],
            [
                'name' => 'Unità di misura', 'column_name' => 'unita_di_misura', 'type' => EntityFieldType::Select,
                'options' => ['pz' => 'Pezzo', 'ora' => 'Ora', 'giorno' => 'Giorno', 'kg' => 'Kg', 'altro' => 'Altro'],
                'default_value' => 'pz',
            ],
            ['name' => 'Aliquota IVA %', 'column_name' => 'aliquota_iva', 'type' => EntityFieldType::DecimalNumber, 'default_value' => '22'],
            [
                'name' => 'Stato', 'column_name' => 'stato', 'type' => EntityFieldType::Select,
                'options' => ['attivo' => 'Attivo', 'disattivo' => 'Disattivo'],
                'default_value' => 'attivo',
            ],
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
                'width' => 6,
            ]);
        }

        $installer->install($entity);
    }
}
