<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin;

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowNodeType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowTriggerType;
use Fazzinipierluigi\AsgardCRM\Http\Controllers\Controller;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\StoreEntityFieldRequest;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\UpdateEntityBuilderRequest;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityField;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Models\Workflow;
use Fazzinipierluigi\AsgardCRM\Services\EntityRelationResolver;
use Fazzinipierluigi\AsgardCRM\Services\EntitySchemaBuilder;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\WorkflowFieldReferenceCleaner;
use Fazzinipierluigi\AsgardCRM\Support\ButtonConfigValidator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EntityBuilderController extends Controller
{
    public function __construct(private readonly EntityRelationResolver $relationResolver) {}

    /**
     * Show the tab/card/field builder for an entity.
     */
    public function edit(Entity $entity): View
    {
        $entity->load('tabs.cards.fields');

        return view('crm::admin.entities.builder', [
            'entity' => $entity,
            'fieldTypes' => EntityFieldType::options(),
            'relationTargets' => $this->relationResolver->targetOptions($entity),
            'manualWorkflows' => $this->manualWorkflows(),
            'catalogEntityOptions' => $this->catalogEntityOptions($entity),
            'decimalFieldsByEntity' => $this->decimalFieldsByEntity(),
        ]);
    }

    /**
     * Every OTHER installed entity, offered as a "Blocco Prodotti" field's
     * catalog target — same "self excluded" shape as
     * EntityRelationResolver::targetOptions(), just entity-only (no
     * model targets, a catalog is always another entity's own records).
     *
     * @return array<string, string>
     */
    private function catalogEntityOptions(Entity $entity): array
    {
        return Entity::where('is_installed', true)
            ->where('id', '!=', $entity->id)
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();
    }

    /**
     * Every installed entity's Decimal fields, keyed by entity slug — lets
     * the field modal's "unit price column" <select> repopulate client-side
     * as soon as a catalog entity is picked (resources/js/entity-builder.js),
     * without a round trip per selection.
     *
     * @return array<string, array<string, string>>
     */
    private function decimalFieldsByEntity(): array
    {
        return Entity::where('is_installed', true)->get()
            ->mapWithKeys(fn (Entity $e) => [
                $e->slug => $e->allFields()
                    ->filter(fn (EntityField $f) => $f->type === EntityFieldType::DecimalNumber)
                    ->pluck('name', 'column_name')
                    ->all(),
            ])
            ->all();
    }

    /**
     * Replace an entity's whole tab/card/field tree with the submitted
     * one (not-yet-installed entities), or apply the narrower diff
     * updateInstalled() allows once the entity is live — see
     * UpdateEntityBuilderRequest for why the two payloads look
     * different.
     */
    public function update(UpdateEntityBuilderRequest $request, Entity $entity, EntitySchemaBuilder $schemaBuilder, WorkflowFieldReferenceCleaner $referenceCleaner): RedirectResponse
    {
        if ($entity->is_installed) {
            return $this->updateInstalled($request, $entity, $schemaBuilder, $referenceCleaner);
        }

        DB::transaction(function () use ($request, $entity) {
            $entity->tabs()->delete();

            foreach (array_values($request->input('tabs', [])) as $tabPosition => $tabInput) {
                $tab = $entity->tabs()->create([
                    'name' => $tabInput['name'],
                    'position' => $tabPosition,
                ]);

                foreach (array_values($tabInput['cards'] ?? []) as $cardPosition => $cardInput) {
                    $card = $tab->cards()->create([
                        'name' => $cardInput['name'],
                        'position' => $cardPosition,
                    ]);

                    foreach (array_values($cardInput['fields'] ?? []) as $fieldPosition => $fieldInput) {
                        $card->fields()->create([
                            'name' => $fieldInput['name'],
                            'column_name' => $fieldInput['column_name'],
                            'type' => $fieldInput['type'],
                            'options' => $this->parseOptions($fieldInput),
                            'relation_target_type' => $this->relationTargetType($fieldInput),
                            'relation_target' => $this->relationTarget($fieldInput),
                            'required' => (bool) ($fieldInput['required'] ?? false),
                            'default_value' => $fieldInput['default_value'] ?? null,
                            'position' => $fieldPosition,
                            'width' => min(12, max(1, (int) ($fieldInput['width'] ?? 12))),
                        ]);
                    }
                }
            }
        });

        return redirect()->route('admin.entities.builder.edit', $entity)->with('status', 'entity-structure-saved');
    }

    /**
     * Diffs the submitted tree against the entity's current one instead
     * of replacing it: new tabs/cards/fields are created exactly like the
     * not-yet-installed flow does (same builder UI, same per-type field
     * rules — see UpdateEntityBuilderRequest — the only difference is a
     * new field's column is appended live via
     * EntitySchemaBuilder::addColumn() instead of the table being built
     * from scratch), existing tabs and cards can be renamed, existing
     * fields have only their metadata updated (name/required/
     * default_value/width/options — never column_name/type/
     * relation_target, immutable once the physical column exists), and
     * any existing field missing from the payload (the admin removed it,
     * after confirming the data-loss alert client-side) gets its column
     * dropped and its row deleted — unless it's is_locked, in which case
     * the whole save is rejected and nothing is persisted.
     *
     * Deliberately NOT wrapped in a DB::transaction(): on MySQL/MariaDB
     * every ALTER TABLE (addColumn/dropColumn) causes an implicit commit,
     * silently ending any open transaction out from under Laravel and
     * making the eventual commit/rollback fail with "There is no active
     * transaction" — DDL was never really rollback-able here to begin
     * with. The is_locked check therefore runs as a fail-fast guard up
     * front, before anything is touched, rather than as a mid-flight
     * rollback trigger.
     */
    private function updateInstalled(UpdateEntityBuilderRequest $request, Entity $entity, EntitySchemaBuilder $schemaBuilder, WorkflowFieldReferenceCleaner $referenceCleaner): RedirectResponse
    {
        $existingFieldIds = $entity->allFields()->pluck('id');
        $submittedFieldIds = $this->submittedFieldIds($request);

        $lockedFieldBeingDeleted = EntityField::whereIn('id', $existingFieldIds->diff($submittedFieldIds))
            ->where('is_locked', true)
            ->first();

        if ($lockedFieldBeingDeleted) {
            return redirect()->route('admin.entities.builder.edit', $entity)
                ->withErrors(['tabs' => "Il campo «{$lockedFieldBeingDeleted->name}» è protetto e non può essere eliminato."]);
        }

        foreach ($request->input('tabs', []) as $tabToken => $tabInput) {
            if (ctype_digit((string) $tabToken)) {
                $tab = EntityTab::where('entity_id', $entity->id)->findOrFail((int) $tabToken);
                $tab->update(['name' => $tabInput['name']]);
            } else {
                $tab = $entity->tabs()->create(['name' => $tabInput['name'], 'position' => $entity->tabs()->max('position') + 1]);
            }

            foreach (($tabInput['cards'] ?? []) as $cardToken => $cardInput) {
                if (ctype_digit((string) $cardToken)) {
                    $card = EntityCard::where('entity_tab_id', $tab->id)->findOrFail((int) $cardToken);
                    $card->update(['name' => $cardInput['name']]);
                } else {
                    $card = $tab->cards()->create(['name' => $cardInput['name'], 'position' => $tab->cards()->max('position') + 1]);
                }

                $position = 0;

                foreach (($cardInput['fields'] ?? []) as $fieldToken => $fieldInput) {
                    if (! ctype_digit((string) $fieldToken)) {
                        $field = $card->fields()->create([
                            'name' => $fieldInput['name'],
                            'column_name' => $fieldInput['column_name'],
                            'type' => $fieldInput['type'],
                            'options' => $this->parseOptions($fieldInput),
                            'relation_target_type' => $this->relationTargetType($fieldInput),
                            'relation_target' => $this->relationTarget($fieldInput),
                            'required' => (bool) ($fieldInput['required'] ?? false),
                            'default_value' => $fieldInput['default_value'] ?? null,
                            'position' => $position,
                            'width' => min(12, max(1, (int) ($fieldInput['width'] ?? 12))),
                            'is_locked' => false,
                        ]);

                        $schemaBuilder->addColumn($entity, $field);
                        $position++;

                        continue;
                    }

                    $field = EntityField::where('id', (int) $fieldToken)
                        ->whereHas('card.tab', fn ($query) => $query->where('entity_id', $entity->id))
                        ->firstOrFail();

                    $fieldInput['type'] = $field->type->value;

                    $field->update([
                        'name' => $fieldInput['name'],
                        'entity_card_id' => $card->id,
                        'position' => $position,
                        'width' => min(12, max(1, (int) ($fieldInput['width'] ?? 12))),
                        'required' => (bool) ($fieldInput['required'] ?? false),
                        'default_value' => $fieldInput['default_value'] ?? null,
                        'options' => $this->parseOptions($fieldInput),
                    ]);
                    $position++;
                }
            }
        }

        foreach ($existingFieldIds->diff($submittedFieldIds) as $deletedId) {
            $field = EntityField::find($deletedId);

            if (! $field) {
                continue;
            }

            $schemaBuilder->dropColumn($entity, $field);
            $referenceCleaner->removeReferencesToField($entity, $field);
            $field->delete();
        }

        return redirect()->route('admin.entities.builder.edit', $entity)->with('status', 'entity-structure-saved');
    }

    /**
     * The set of existing (numeric-token) field ids present anywhere in
     * the submitted payload — used both to fail fast on a locked-field
     * deletion before touching anything, and to know which existing
     * fields are missing from the payload (and therefore being removed).
     *
     * @return Collection<int, int>
     */
    private function submittedFieldIds(UpdateEntityBuilderRequest $request): Collection
    {
        $ids = collect();

        foreach ($request->input('tabs', []) as $tabInput) {
            foreach (($tabInput['cards'] ?? []) as $cardInput) {
                foreach (($cardInput['fields'] ?? []) as $fieldToken => $fieldInput) {
                    if (ctype_digit((string) $fieldToken)) {
                        $ids->push((int) $fieldToken);
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * Parse the type-specific "options" blob: the "key:Label" per-line
     * textarea for Select fields, or the prefix input for Code fields.
     *
     * @param  array<string, mixed>  $fieldInput
     * @return array<string, string>|null
     */
    private function parseOptions(array $fieldInput): ?array
    {
        $type = $fieldInput['type'] ?? null;

        if ($type === 'code') {
            $prefix = trim((string) ($fieldInput['code_prefix'] ?? ''));

            return $prefix !== '' ? ['prefix' => $prefix] : null;
        }

        if ($type === 'table') {
            return ['columns' => StoreEntityFieldRequest::parseTableColumns((string) ($fieldInput['table_columns'] ?? ''))];
        }

        if ($type === 'button') {
            return ButtonConfigValidator::parse($fieldInput);
        }

        if ($type === 'products_block') {
            return [
                'catalog_entity_slug' => trim((string) ($fieldInput['products_catalog'] ?? '')) ?: null,
                'price_column' => trim((string) ($fieldInput['products_price_column'] ?? '')) ?: null,
                'extra_columns' => StoreEntityFieldRequest::parseTableColumns((string) ($fieldInput['products_extra_columns'] ?? '')),
                'total_target_column' => trim((string) ($fieldInput['products_total_target'] ?? '')) ?: null,
            ];
        }

        if ($type !== 'select') {
            return null;
        }

        $options = [];

        foreach (preg_split('/\R/', (string) ($fieldInput['options'] ?? '')) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$key, $label] = array_pad(explode(':', $line, 2), 2, null);
            $key = trim((string) $key);

            if ($key !== '') {
                $options[$key] = trim((string) ($label ?? $key));
            }
        }

        return $options ?: null;
    }

    /**
     * @param  array<string, mixed>  $fieldInput
     */
    private function relationTargetType(array $fieldInput): ?string
    {
        if (($fieldInput['type'] ?? null) !== 'relation') {
            return null;
        }

        return explode(':', (string) ($fieldInput['relation_target'] ?? ''), 2)[0] ?: null;
    }

    /**
     * @param  array<string, mixed>  $fieldInput
     */
    private function relationTarget(array $fieldInput): ?string
    {
        if (($fieldInput['type'] ?? null) !== 'relation') {
            return null;
        }

        return explode(':', (string) ($fieldInput['relation_target'] ?? ''), 2)[1] ?? null;
    }

    /**
     * Active workflows whose start node is configured for manual
     * launch — the only ones a Button field can offer to run.
     */
    private function manualWorkflows(): EloquentCollection
    {
        return Workflow::where('is_active', true)
            ->whereHas('currentVersion.nodes', function ($query) {
                $query->where('type', WorkflowNodeType::Start->value)
                    ->where('config->trigger_type', WorkflowTriggerType::Manual->value);
            })
            ->get();
    }
}
