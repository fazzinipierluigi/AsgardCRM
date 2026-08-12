<?php

namespace Fazzinipierluigi\CrmCore\Services;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityField;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Exports an entity's tab/card/field structure to a plain array (JSON
 * schema, no record data) and imports one back as a brand new custom
 * entity. Mirrors the shape UpdateEntityBuilderRequest/
 * EntityBuilderController already validate/persist, so an exported
 * file is guaranteed re-importable.
 */
class EntitySchemaTransfer
{
    /**
     * @return array<string, mixed>
     */
    public function export(Entity $entity): array
    {
        $entity->loadMissing('tabs.cards.fields');

        return [
            'name' => $entity->name,
            'icon' => $entity->icon,
            'tabs' => $entity->tabs->map(fn (EntityTab $tab) => [
                'name' => $tab->name,
                'cards' => $tab->cards->map(fn (EntityCard $card) => [
                    'name' => $card->name,
                    'fields' => $card->fields->map(fn (EntityField $field) => [
                        'name' => $field->name,
                        'column_name' => $field->column_name,
                        'type' => $field->type->value,
                        'options' => $field->options,
                        'relation_target_type' => $field->relation_target_type?->value,
                        'relation_target' => $field->relation_target,
                        'required' => $field->required,
                        'default_value' => $field->default_value,
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Create a brand new custom, not-yet-installed entity from a
     * previously exported schema array.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException if the structure is malformed
     */
    public function import(array $data): Entity
    {
        $this->assertValidStructure($data);

        return DB::transaction(function () use ($data) {
            $slug = Entity::uniqueSlug($data['name']);

            $entity = Entity::create([
                'name' => $data['name'],
                'slug' => $slug,
                'table_name' => 'entity_'.$slug,
                'icon' => $data['icon'] ?? null,
            ]);

            foreach (array_values($data['tabs']) as $tabPosition => $tabData) {
                $tab = $entity->tabs()->create(['name' => $tabData['name'], 'position' => $tabPosition]);

                foreach (array_values($tabData['cards']) as $cardPosition => $cardData) {
                    $card = $tab->cards()->create(['name' => $cardData['name'], 'position' => $cardPosition]);

                    foreach (array_values($cardData['fields']) as $fieldPosition => $fieldData) {
                        $card->fields()->create([
                            'name' => $fieldData['name'],
                            'column_name' => $fieldData['column_name'],
                            'type' => $fieldData['type'],
                            'options' => $fieldData['options'] ?? null,
                            'relation_target_type' => $fieldData['relation_target_type'] ?? null,
                            'relation_target' => $fieldData['relation_target'] ?? null,
                            'required' => $fieldData['required'] ?? false,
                            'default_value' => $fieldData['default_value'] ?? null,
                            'position' => $fieldPosition,
                        ]);
                    }
                }
            }

            // is_system/is_installed were left to their DB defaults (not
            // passed to create()), so the in-memory instance doesn't have
            // them hydrated yet — refresh so callers get real values.
            return $entity->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException
     */
    private function assertValidStructure(array $data): void
    {
        if (empty($data['name']) || ! is_string($data['name'])) {
            throw new RuntimeException('Il file non contiene un nome entità valido.');
        }

        if (empty($data['tabs']) || ! is_array($data['tabs'])) {
            throw new RuntimeException('Il file non contiene alcun tab.');
        }

        $seenColumnNames = [];

        foreach ($data['tabs'] as $tab) {
            if (empty($tab['name']) || empty($tab['cards']) || ! is_array($tab['cards'])) {
                throw new RuntimeException('Ogni tab deve avere un nome e almeno una card.');
            }

            foreach ($tab['cards'] as $card) {
                if (empty($card['name']) || empty($card['fields']) || ! is_array($card['fields'])) {
                    throw new RuntimeException('Ogni card deve avere un nome e almeno un campo.');
                }

                foreach ($card['fields'] as $field) {
                    if (empty($field['name']) || empty($field['column_name']) || empty($field['type'])) {
                        throw new RuntimeException('Ogni campo deve avere nome, nome colonna e tipo.');
                    }

                    if (EntityFieldType::tryFrom($field['type']) === null) {
                        throw new RuntimeException("Tipo di campo sconosciuto: {$field['type']}.");
                    }

                    $physicalColumn = $field['type'] === EntityFieldType::Relation->value
                        ? "{$field['column_name']}_id"
                        : $field['column_name'];

                    if (in_array($physicalColumn, EntitySchemaBuilder::RESERVED_COLUMN_NAMES, true)) {
                        throw new RuntimeException("Nome colonna riservato: {$physicalColumn}.");
                    }

                    if (isset($seenColumnNames[$physicalColumn])) {
                        throw new RuntimeException("Nome colonna duplicato: {$physicalColumn}.");
                    }

                    $seenColumnNames[$physicalColumn] = true;
                }
            }
        }
    }
}
