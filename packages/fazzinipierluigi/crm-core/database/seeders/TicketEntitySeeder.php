<?php

namespace Fazzinipierluigi\CrmCore\Database\Seeders;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Enums\EntityRelationTargetType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityRelation;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "Ticket" Entity, including its time-tracking timer:
 * a hidden "Timer avviato il" DateTime + "Tempo tracciato (minuti)"
 * Decimal — real columns on entity_ticket via EntitySchemaBuilder, but
 * is_hidden so they never render in the record form/view and are
 * skipped by EntityRecordController::prepareAttributes()/
 * StoreEntityRecordRequest — written only by
 * Fazzinipierluigi\CrmCore\Http\Controllers\TicketTimerController, whose start/stop actions
 * back the live timer rendered in the record page's header buttons
 * (see resources/views/entities/_ticket-timer-buttons.blade.php,
 * included from entities/edit.blade.php and entities/show.blade.php
 * when entity->slug === 'ticket' — not a generic entity feature).
 *
 * A single "Generale" tab: entities with only one tab don't show a
 * sub-tab nav at all (see resources/views/entities/_form.blade.php).
 *
 * Must run after ClientiEntitySeeder and ContattiEntitySeeder.
 *
 * Idempotent: does nothing if the "ticket" entity already exists.
 */
class TicketEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'ticket')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'Ticket',
            'slug' => 'ticket',
            'table_name' => 'entity_ticket',
            'icon' => 'headset',
            'is_system' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Dati', 'position' => 0]);

        $fields = [
            ['name' => 'Oggetto', 'column_name' => 'oggetto', 'type' => EntityFieldType::String, 'required' => true, 'width' => 12],
            ['name' => 'Numero', 'column_name' => 'numero', 'type' => EntityFieldType::Code, 'options' => ['prefix' => 'TK']],
            ['name' => 'Descrizione', 'column_name' => 'descrizione', 'type' => EntityFieldType::Textarea, 'width' => 12],
            [
                'name' => 'Cliente', 'column_name' => 'cliente', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'clienti',
            ],
            [
                'name' => 'Contatto', 'column_name' => 'contatto', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Entity, 'relation_target' => 'contatti',
            ],
            [
                'name' => 'Priorità', 'column_name' => 'priorita', 'type' => EntityFieldType::Select,
                'options' => ['bassa' => 'Bassa', 'media' => 'Media', 'alta' => 'Alta', 'urgente' => 'Urgente'],
                'default_value' => 'media',
            ],
            [
                'name' => 'Stato', 'column_name' => 'stato', 'type' => EntityFieldType::Select,
                'options' => ['aperto' => 'Aperto', 'in_lavorazione' => 'In lavorazione', 'in_attesa' => 'In attesa', 'risolto' => 'Risolto', 'chiuso' => 'Chiuso'],
                'default_value' => 'aperto',
            ],
            ['name' => 'Categoria', 'column_name' => 'categoria', 'type' => EntityFieldType::String],
            [
                'name' => 'Assegnato a', 'column_name' => 'assegnato_a', 'type' => EntityFieldType::Relation,
                'relation_target_type' => EntityRelationTargetType::Model, 'relation_target' => config('crm.user_model'),
            ],
            ['name' => 'Data apertura', 'column_name' => 'data_apertura', 'type' => EntityFieldType::Date],
            ['name' => 'Data chiusura', 'column_name' => 'data_chiusura', 'type' => EntityFieldType::Date],
            [
                'name' => 'Timer avviato il', 'column_name' => 'timer_avviato_il', 'type' => EntityFieldType::DateTime,
                'is_locked' => true, 'is_hidden' => true,
            ],
            [
                'name' => 'Tempo tracciato (minuti)', 'column_name' => 'tempo_tracciato_minuti', 'type' => EntityFieldType::DecimalNumber,
                'default_value' => '0', 'is_locked' => true, 'is_hidden' => true,
            ],
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
                'is_locked' => $field['is_locked'] ?? false,
                'is_hidden' => $field['is_hidden'] ?? false,
            ]);
        }

        $installer->install($entity);

        $calendario = Entity::where('slug', 'calendario')->first();

        if ($calendario !== null) {
            EntityRelation::create([
                'entity_a_id' => $entity->id,
                'entity_b_id' => $calendario->id,
                'name' => 'Attività',
            ]);
        }

        $documenti = Entity::where('slug', 'documenti')->first();

        if ($documenti !== null) {
            EntityRelation::create([
                'entity_a_id' => $entity->id,
                'entity_b_id' => $documenti->id,
                'name' => 'Documenti',
            ]);
        }
    }
}
