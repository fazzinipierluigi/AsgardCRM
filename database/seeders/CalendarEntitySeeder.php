<?php

namespace Database\Seeders;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "Calendario" Entity: a fixed set of locked fields
 * (title/description/show_as/status/start/end datetime) installed the
 * same way any other Entity is, so it gets a real dynamic table plus
 * the standard entity_calendario.* CRUD permissions for free. Users can
 * later append their own custom fields via EntityFieldController — the
 * six seeded here just can't be removed or renamed (is_locked).
 *
 * Idempotent: does nothing if the "calendario" entity already exists.
 */
class CalendarEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'calendario')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'Calendario',
            'slug' => 'calendario',
            'table_name' => 'entity_calendario',
            'icon' => 'calendar',
            'is_system' => true,
            'is_calendar' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Dettagli', 'position' => 0]);

        $fields = [
            ['name' => 'Titolo', 'column_name' => 'title', 'type' => EntityFieldType::String, 'required' => true],
            ['name' => 'Descrizione', 'column_name' => 'description', 'type' => EntityFieldType::Textarea],
            [
                'name' => 'Mostra come', 'column_name' => 'show_as', 'type' => EntityFieldType::Select, 'required' => true,
                'options' => ['available' => 'Disponibile', 'busy' => 'Occupato', 'out_of_office' => 'Fuori sede'],
                'default_value' => 'busy',
            ],
            [
                'name' => 'Stato', 'column_name' => 'status', 'type' => EntityFieldType::Select, 'required' => true,
                'options' => ['tentative' => 'Provvisorio', 'confirmed' => 'Confermato', 'cancelled' => 'Annullato'],
                'default_value' => 'confirmed',
            ],
            ['name' => 'Data/ora inizio', 'column_name' => 'start_datetime', 'type' => EntityFieldType::DateTime, 'required' => true],
            ['name' => 'Data/ora fine', 'column_name' => 'end_datetime', 'type' => EntityFieldType::DateTime, 'required' => true],
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
                'width' => 12,
                'is_locked' => true,
            ]);
        }

        $installer->install($entity);
    }
}
