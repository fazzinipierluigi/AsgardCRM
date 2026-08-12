<?php

namespace Database\Seeders;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Illuminate\Database\Seeder;

/**
 * Seeds the system "E-mail" Entity: a fixed set of locked fields
 * (oggetto/mittente/destinatari/data_messaggio/ha_allegati) installed
 * the same way any other Entity is, so it gets a real dynamic table
 * plus the standard entity_email.* CRUD permissions for free. This
 * entity never stores a message body or attachments — App\Http\
 * Controllers\MailController fetches those live from the mailbox on
 * demand and only writes a row here (a bookmark: mail_account_id +
 * folder + message_uid, see EntitySchemaBuilder::create()'s is_email
 * branch) when a user explicitly attaches a message to another
 * entity's record via the existing EntityRelation N:M system.
 *
 * "Oggetto" is deliberately the first String-typed field so
 * EntityRelationLinkResolver::labelFieldFor() picks it as this
 * entity's label everywhere a relation picker needs one.
 *
 * Idempotent: does nothing if the "email" entity already exists.
 */
class EmailEntitySeeder extends Seeder
{
    public function run(EntityInstaller $installer): void
    {
        if (Entity::where('slug', 'email')->exists()) {
            return;
        }

        $entity = Entity::create([
            'name' => 'E-mail',
            'slug' => 'email',
            'table_name' => 'entity_email',
            'icon' => 'mail',
            'is_system' => true,
            'is_email' => true,
        ]);

        $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
        $card = $tab->cards()->create(['name' => 'Dettagli', 'position' => 0]);

        $fields = [
            ['name' => 'Oggetto', 'column_name' => 'oggetto', 'type' => EntityFieldType::String, 'required' => true],
            ['name' => 'Mittente', 'column_name' => 'mittente', 'type' => EntityFieldType::String],
            ['name' => 'Destinatari', 'column_name' => 'destinatari', 'type' => EntityFieldType::Textarea],
            ['name' => 'Data messaggio', 'column_name' => 'data_messaggio', 'type' => EntityFieldType::DateTime],
            ['name' => 'Ha allegati', 'column_name' => 'ha_allegati', 'type' => EntityFieldType::Checkbox],
        ];

        foreach ($fields as $position => $field) {
            $card->fields()->create([
                'name' => $field['name'],
                'column_name' => $field['column_name'],
                'type' => $field['type'],
                'required' => $field['required'] ?? false,
                'position' => $position,
                'width' => 12,
                'is_locked' => true,
            ]);
        }

        $installer->install($entity);
    }
}
