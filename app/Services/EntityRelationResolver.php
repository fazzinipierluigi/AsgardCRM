<?php

namespace App\Services;

use App\Enums\EntityFieldType;
use App\Enums\EntityRelationTargetType;
use App\Models\Entity;
use App\Models\EntityField;
use App\Models\EntityRecord;
use App\Models\User;

/**
 * Resolves a Relation field's (or the Calendar's hardcoded
 * relatable_type/relatable_id pair — see EntitySchemaBuilder) target:
 * the grouped list of entities/models a relation can point to, and the
 * id => label options for a specific one of those targets.
 *
 * Extracted from EntityRecordController/EntityBuilderController/
 * EntityFieldController, which all needed the same "entity slug or
 * model FQCN -> option list" resolution independently.
 */
class EntityRelationResolver
{
    /**
     * Every entity (except $excludeEntity, typically "self") plus the
     * configured system models, grouped for a target picker <select>.
     *
     * @return array<string, array<string, string>>
     */
    public function targetOptions(?Entity $excludeEntity = null): array
    {
        $entityTargets = Entity::query()
            ->when($excludeEntity, fn ($query) => $query->where('id', '!=', $excludeEntity->id))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Entity $target) => ["entity:{$target->slug}" => $target->name])
            ->all();

        $modelTargets = collect(config('entities.relatable_models', []))
            ->mapWithKeys(fn (string $label, string $class) => ["model:{$class}" => $label])
            ->all();

        return [
            'Entità' => $entityTargets,
            'Modelli' => $modelTargets,
        ];
    }

    /**
     * @return array<int|string, string>
     */
    public function labelsForField(EntityField $field): array
    {
        if ($field->relation_target_type === null || $field->relation_target === null) {
            return [];
        }

        return $this->labelsFor($field->relation_target_type, $field->relation_target);
    }

    /**
     * @return array<int|string, string>
     */
    public function labelsFor(EntityRelationTargetType $type, string $target): array
    {
        if ($type === EntityRelationTargetType::Model) {
            if ($target === User::class) {
                return User::query()->orderBy('name')->pluck('name', 'id')->all();
            }

            return [];
        }

        $targetEntity = Entity::where('slug', $target)->where('is_installed', true)->first();

        if ($targetEntity === null) {
            return [];
        }

        $labelField = $targetEntity->allFields()->first(fn (EntityField $f) => $f->type === EntityFieldType::String);
        $columns = $labelField !== null ? ['id', $labelField->column_name] : ['id'];

        return EntityRecord::forEntity($targetEntity)->newQuery()->get($columns)
            ->mapWithKeys(fn (EntityRecord $r) => [$r->id => ($labelField !== null ? $r->{$labelField->column_name} : null) ?: "#{$r->id}"])
            ->all();
    }
}
