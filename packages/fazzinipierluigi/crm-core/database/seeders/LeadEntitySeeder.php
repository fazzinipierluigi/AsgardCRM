<?php

namespace Fazzinipierluigi\CrmCore\Database\Seeders;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Enums\EntityRelationTargetType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "Lead" Entity — prospective contacts not yet tied to
 * a Cliente record. OpportunitaEntitySeeder's "Lead di origine" Relation
 * field points back here when a lead converts into a deal.
 *
 * Idempotent: does nothing if the "lead" entity already exists.
 */
class LeadEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'lead')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'Lead',
            'slug' => 'lead',
            'table_name' => 'entity_lead',
            'icon' => 'target-arrow',
            'is_system' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Dati', 'position' => 0]);

        $fields = [
            ['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'required' => true],
            ['name' => 'Azienda', 'column_name' => 'azienda', 'type' => EntityFieldType::String],
            ['name' => 'Email', 'column_name' => 'email', 'type' => EntityFieldType::String],
            ['name' => 'Telefono', 'column_name' => 'telefono', 'type' => EntityFieldType::String],
            [
                'name' => 'Fonte', 'column_name' => 'fonte', 'type' => EntityFieldType::Select,
                'options' => ['sito_web' => 'Sito web', 'referral' => 'Referral', 'fiera' => 'Fiera', 'cold_call' => 'Cold call', 'altro' => 'Altro'],
            ],
            [
                'name' => 'Stato', 'column_name' => 'stato', 'type' => EntityFieldType::Select,
                'options' => ['nuovo' => 'Nuovo', 'contattato' => 'Contattato', 'qualificato' => 'Qualificato', 'perso' => 'Perso', 'convertito' => 'Convertito'],
                'default_value' => 'nuovo',
            ],
            [
                'name' => 'Assegnato a', 'column_name' => 'assegnato_a', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Model, 'relation_target' => config('crm.user_model'),
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
