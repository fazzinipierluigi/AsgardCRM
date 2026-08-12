<?php

namespace Fazzinipierluigi\CrmCore\Services;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityField;
use Fazzinipierluigi\CrmCore\Models\EntityFieldChange;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Contracts\CrmUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Writes to entity_field_changes — the only place in the app that
 * does. Used by EntityRecordController (a user editing a record from
 * the UI) and WorkflowActionExecutor (an UpdateEntity/CreateEntity
 * action, where there's no user, only a workflow name).
 *
 * Every call to logCreated()/logUpdated() shares one transaction_id
 * across all the field rows it writes — that's what "raggruppare per
 * transazione" means: one save produces one group in the log.
 */
class EntityChangeLogger
{
    public function __construct(private readonly EntityRelationResolver $relationResolver) {}

    /**
     * @param  array<string, mixed>  $attributes  The record's just-created column values.
     */
    public function logCreated(Entity $entity, EntityRecord $record, array $attributes, ?CrmUser $user, ?string $sourceLabel = null): void
    {
        $transactionId = (string) Str::uuid();

        foreach ($this->loggableFields($entity) as $field) {
            $column = $this->columnFor($field);

            if (! array_key_exists($column, $attributes) || $attributes[$column] === null) {
                continue;
            }

            $this->insert(
                $entity,
                $record,
                $transactionId,
                $field,
                null,
                $this->formatValue($field, $attributes[$column]),
                $user,
                $sourceLabel,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $original  Column values before the save.
     * @param  array<string, mixed>  $updated  Column values being saved.
     */
    public function logUpdated(Entity $entity, EntityRecord $record, array $original, array $updated, ?CrmUser $user, ?string $sourceLabel = null): void
    {
        $transactionId = (string) Str::uuid();
        $fields = $this->loggableFields($entity)->keyBy(fn (EntityField $f) => $this->columnFor($f));

        foreach ($updated as $column => $newValue) {
            $field = $fields->get($column);

            if (! $field) {
                continue;
            }

            $oldValue = $original[$column] ?? null;

            if ($this->normalize($oldValue) === $this->normalize($newValue)) {
                continue;
            }

            $this->insert(
                $entity,
                $record,
                $transactionId,
                $field,
                $this->formatValue($field, $oldValue),
                $this->formatValue($field, $newValue),
                $user,
                $sourceLabel,
            );
        }
    }

    /**
     * @return Collection<int, EntityField>
     */
    private function loggableFields(Entity $entity): Collection
    {
        return $entity->allFields()->reject(fn (EntityField $f) => $f->type->isAction());
    }

    /**
     * Loose equality for "did this field actually change" — a
     * checkbox's true/false vs 1/0 vs "1"/"0", or a decimal column's
     * padded DB string ("10.0000") vs the freshly-submitted "10",
     * shouldn't count as a change.
     */
    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) (float) $value;
        }

        return (string) $value;
    }

    private function insert(
        Entity $entity,
        EntityRecord $record,
        string $transactionId,
        EntityField $field,
        ?string $oldValue,
        ?string $newValue,
        ?CrmUser $user,
        ?string $sourceLabel,
    ): void {
        EntityFieldChange::create([
            'entity_slug' => $entity->slug,
            'entity_id' => $record->getKey(),
            'transaction_id' => $transactionId,
            'column_name' => $field->column_name,
            'field_label' => $field->name,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_by_user_id' => $user?->id,
            'changed_by_label' => $sourceLabel,
        ]);
    }

    /**
     * The same per-type display formatting as
     * EntityRecordController::displayValue(), applied here to a raw
     * value that isn't necessarily the one currently on the record
     * (the "old" value no longer exists anywhere once overwritten).
     */
    private function formatValue(EntityField $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field->type) {
            EntityFieldType::Checkbox => ((bool) $value) ? 'Sì' : 'No',
            EntityFieldType::Select => $field->options[$value] ?? (string) $value,
            EntityFieldType::Relation => $this->relationResolver->labelsForField($field)[$value] ?? "#{$value}",
            EntityFieldType::RichText => strip_tags((string) $value),
            EntityFieldType::Table => count(json_decode((string) $value, true) ?: []).' righe',
            EntityFieldType::ProductsBlock => count(json_decode((string) $value, true) ?: []).' prodotti',
            default => (string) $value,
        };
    }

    private function columnFor(EntityField $field): string
    {
        return $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
    }
}
