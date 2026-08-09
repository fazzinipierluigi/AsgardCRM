<?php

namespace Database\Seeders;

use App\Enums\EntityFieldType;
use App\Enums\EntityRelationTargetType;
use App\Models\Entity;
use App\Models\EntityFieldCondition;
use App\Models\EntityRelation;
use App\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "Opportunità" Entity plus its N:M link to Contatti
 * and its one conditional-field rule.
 *
 * The Contatti link is a genuine many-to-many (EntityRelation, not a
 * Relation field): several people can be involved in one deal, and the
 * same contact can appear on several open deals — unlike Opportunità's
 * own pointer to Clienti/Lead, which is a single-owner 1:N Relation
 * field, this can't be reduced to a single id column. See
 * DOCUMENTATION.md's "relazioni tra entità" section.
 *
 * "Motivo perdita" demonstrates EntityFieldCondition: visible+required
 * only while Fase == persa (JsonLogic evaluated client-side by
 * resources/js/entity-field-conditions.js; server-side enforcement is
 * limited to downgrading its required rule to nullable while hidden,
 * see EntityFieldConditionEvaluator).
 *
 * Must run after ClientiEntitySeeder, LeadEntitySeeder and
 * ContattiEntitySeeder.
 *
 * Idempotent: does nothing if the "opportunita" entity already exists.
 */
class OpportunitaEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'opportunita')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'Opportunità',
            'slug' => 'opportunita',
            'table_name' => 'entity_opportunita',
            'icon' => 'chart-arrows',
            'is_system' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Dati', 'position' => 0]);

        $fields = [
            ['name' => 'Nome opportunità', 'column_name' => 'nome_opportunita', 'type' => EntityFieldType::String, 'required' => true, 'width' => 12],
            [
                'name' => 'Cliente', 'column_name' => 'cliente', 'type' => EntityFieldType::Relation, 'required' => true,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'clienti',
            ],
            [
                'name' => 'Lead di origine', 'column_name' => 'lead_di_origine', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'lead',
            ],
            ['name' => 'Valore stimato', 'column_name' => 'valore_stimato', 'type' => EntityFieldType::DecimalNumber],
            [
                'name' => 'Fase', 'column_name' => 'fase', 'type' => EntityFieldType::Select,
                'options' => ['qualifica' => 'Qualifica', 'proposta' => 'Proposta', 'negoziazione' => 'Negoziazione', 'vinta' => 'Vinta', 'persa' => 'Persa'],
                'default_value' => 'qualifica',
            ],
            ['name' => 'Probabilità %', 'column_name' => 'probabilita', 'type' => EntityFieldType::IntegerNumber],
            ['name' => 'Data chiusura prevista', 'column_name' => 'data_chiusura_prevista', 'type' => EntityFieldType::Date],
            ['name' => 'Motivo perdita', 'column_name' => 'motivo_perdita', 'type' => EntityFieldType::Textarea, 'width' => 12],
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

        $contatti = Entity::where('slug', 'contatti')->first();

        if ($contatti !== null) {
            EntityRelation::create([
                'entity_a_id' => $entity->id,
                'entity_b_id' => $contatti->id,
                'name' => 'Contatti coinvolti',
            ]);
        }

        $condition = EntityFieldCondition::create([
            'entity_id' => $entity->id,
            'name' => 'Motivo perdita se persa',
            'rule' => ['==' => [['var' => 'fase'], 'persa']],
            'position' => 0,
        ]);

        $condition->targets()->create([
            'entity_field_id' => $createdFields['motivo_perdita']->id,
            'visible' => true,
            'readonly' => false,
            'required' => true,
        ]);
    }
}
